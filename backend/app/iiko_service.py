"""
Сервис-коннектор для iiko Cloud API
"""
import json
import logging
import time
from typing import Optional
import httpx
from sqlalchemy.orm import Session
from sqlalchemy.sql import func as sa_func
from database.models import ApiLog, IikoSettings
from config.settings import settings
from app.iiko_auth import get_access_token

logger = logging.getLogger(__name__)

MAX_LOG_BODY_LENGTH = 2000
MIN_API_KEY_LENGTH = 16  # iiko API keys are typically 32 characters, but allow shorter for flexibility


class IikoService:
    """Коннектор для работы с iiko Cloud API"""

    def __init__(self, db: Session, iiko_settings: Optional[IikoSettings] = None):
        self.db = db
        self.iiko_settings = iiko_settings
        self.base_url = (iiko_settings.api_url if iiko_settings else settings.IIKO_API_URL).rstrip("/")
        self._token: Optional[str] = None

    def _log_request(
        self,
        method: str,
        url: str,
        request_body: Optional[str],
        response_status: Optional[int],
        response_body: Optional[str],
        duration_ms: int,
    ):
        log = ApiLog(
            method=method,
            url=url,
            request_body=request_body,
            response_status=response_status,
            response_body=response_body[:MAX_LOG_BODY_LENGTH] if response_body else None,
            duration_ms=duration_ms,
        )
        self.db.add(log)
        self.db.commit()

    async def _request(
        self,
        method: str,
        path: str,
        json_data: Optional[dict] = None,
        headers: Optional[dict] = None,
        _retried: bool = False,
        _is_auth: bool = False,
    ) -> dict | str:
        url = f"{self.base_url}/{path.lstrip('/')}"
        req_body = json.dumps(json_data) if json_data else None
        hdrs = {
            "Content-Type": "application/json",
            "Timeout": "45",
        }
        if headers:
            hdrs.update(headers)
        if self._token and not _is_auth:
            hdrs["Authorization"] = f"Bearer {self._token}"

        start = time.time()
        async with httpx.AsyncClient(timeout=30.0) as client:
            response = await client.request(method, url, json=json_data, headers=hdrs)
        duration = int((time.time() - start) * 1000)

        resp_text = response.text
        self._log_request(method, url, req_body, response.status_code, resp_text, duration)

        # Auto-retry once on 401 (expired token), but NOT for auth requests
        if response.status_code == 401 and not _retried and not _is_auth:
            await self.authenticate()
            return await self._request(method, path, json_data, headers, _retried=True)

        if response.status_code >= 400:
            raise Exception(f"iiko API error {response.status_code}: {resp_text}")

        if not resp_text:
            return {}
        # iiko API may return plain text (e.g. access_token endpoint) or JSON
        try:
            return response.json()
        except Exception:
            return resp_text

    async def authenticate(self, api_key: Optional[str] = None) -> str:
        """
        Получить токен доступа iiko (токен живет ~15 минут).
        Теперь использует централизованное управление токенами из iiko_auth.
        """
        try:
            # Используем централизованную функцию получения токена
            self._token = await get_access_token()
            
            # Обновить время последнего обновления токена в БД
            if self.iiko_settings:
                self.iiko_settings.last_token_refresh = sa_func.now()
                self.db.commit()
            
            return self._token
        except Exception as e:
            # FALLBACK: Если централизованное получение не удалось, пробуем локальный метод
            # Это необходимо для обратной совместимости и для случаев, когда используются
            # разные API ключи для разных организаций (из БД, а не из переменных окружения)
            logger.warning(f"Централизованное получение токена не удалось, используем локальный метод: {e}")
            key = api_key or (self.iiko_settings.api_key if self.iiko_settings else settings.IIKO_API_KEY)
            key = key.strip()
            if not key:
                raise Exception(
                    "API ключ (apiLogin) не задан. Укажите его в настройках iiko "
                    "(переменная IIKO_API_KEY или через панель администратора)."
                )
            
            # Validate API key format (iiko API keys are typically 32 hex characters)
            if len(key) < MIN_API_KEY_LENGTH:
                raise Exception(
                    f"API ключ слишком короткий ({len(key)} символов). "
                    f"Минимальная длина: {MIN_API_KEY_LENGTH}, стандартная длина: 32 символа. "
                    f"Проверьте, что ключ скопирован полностью."
                )
            
            try:
                result = await self._request("POST", "/access_token", json_data={"apiLogin": key}, _is_auth=True)
            except httpx.TimeoutException:
                raise Exception(
                    f"Тайм-аут подключения к iiko API ({self.base_url}). "
                    f"Проверьте доступность сервера iiko и сетевое подключение."
                )
            except httpx.ConnectError as e:
                raise Exception(
                    f"Ошибка подключения к iiko API ({self.base_url}): {e}. "
                    f"Проверьте доступность сервера, URL и DNS-настройки."
                )
            except Exception as e:
                error_msg = str(e)
                if "401" in error_msg or "400" in error_msg:
                    raise Exception(
                        f"Неверный API ключ (apiLogin). Проверьте: "
                        f"1) Ключ скопирован полностью, без лишних пробелов; "
                        f"2) API-ключ активен в личном кабинете iiko Cloud (https://api-ru.iiko.services); "
                        f"3) Ключ не был отозван или заменён; "
                        f"4) Используйте новый формат ключа (из раздела 'API' в iiko Cloud). "
                        f"Первые символы ключа: '{key[:min(8, len(key))]}...'"
                    )
                raise
            # iiko API may return token as plain text string or as JSON
            # Documented response: {"correlationId": "...", "token": "..."}
            if isinstance(result, str):
                # Plain text response; strip whitespace and surrounding quotes
                # (iiko may return a bare JSON string like "token-value")
                token_str = result.strip()
                if len(token_str) >= 2 and token_str[0] == '"' and token_str[-1] == '"':
                    token_str = token_str[1:-1]
                self._token = token_str
            elif isinstance(result, dict):
                # JSON response: try "token" (documented), "correlationId" response format,
                # or "access_token" (compatibility)
                self._token = result.get("token") or result.get("access_token") or ""
            else:
                self._token = str(result).strip()
            
            # Validate that we actually got a token
            if not self._token:
                raise Exception(
                    "iiko API вернул пустой токен. Это может быть связано с: "
                    "1) Неверным форматом ответа API; "
                    "2) Проблемами на стороне iiko Cloud; "
                    "3) Неправильной настройкой API ключа. "
                    "Попробуйте создать новый API ключ в личном кабинете iiko Cloud."
                )
            
            # Обновить время последнего обновления токена в БД
            if self.iiko_settings:
                self.iiko_settings.last_token_refresh = sa_func.now()
                self.db.commit()
            return self._token

    async def get_organizations(self) -> dict:
        """Получить список организаций"""
        if not self._token:
            await self.authenticate()
        return await self._request("POST", "/organizations", json_data={})

    async def get_menu(self, organization_id: str) -> dict:
        """Получить меню организации"""
        if not self._token:
            await self.authenticate()
        return await self._request(
            "POST",
            "/nomenclature",
            json_data={"organizationId": organization_id},
        )

    async def create_order(self, organization_id: str, order_data: dict) -> dict:
        """Создать заказ в iiko"""
        if not self._token:
            await self.authenticate()
        payload = {"organizationId": organization_id, "order": order_data}
        return await self._request("POST", "/deliveries/create", json_data=payload)

    async def get_order_status(self, organization_id: str, order_ids: list) -> dict:
        """Получить статус заказов"""
        if not self._token:
            await self.authenticate()
        return await self._request(
            "POST",
            "/deliveries/by_id",
            json_data={"organizationId": organization_id, "orderIds": order_ids},
        )

    async def get_stop_lists(self, organization_id: str) -> dict:
        """Получить стоп-листы"""
        if not self._token:
            await self.authenticate()
        return await self._request(
            "POST",
            "/stop_lists",
            json_data={"organizationIds": [organization_id]},
        )

    async def get_terminal_groups(self, organization_ids: list) -> dict:
        """Получить терминальные группы (точки/заведения)"""
        if not self._token:
            await self.authenticate()
        return await self._request(
            "POST",
            "/terminal_groups",
            json_data={"organizationIds": organization_ids},
        )

    async def get_payment_types(self, organization_ids: list) -> dict:
        """Получить доступные типы оплат"""
        if not self._token:
            await self.authenticate()
        return await self._request(
            "POST",
            "/payment_types",
            json_data={"organizationIds": organization_ids},
        )

    async def get_couriers(self, organization_id: str) -> dict:
        """Получить список курьеров"""
        if not self._token:
            await self.authenticate()
        return await self._request(
            "POST",
            "/employees/couriers",
            json_data={"organizationIds": [organization_id]},
        )

    async def get_order_types(self, organization_ids: list) -> dict:
        """Получить типы заказов"""
        if not self._token:
            await self.authenticate()
        return await self._request(
            "POST",
            "/deliveries/order_types",
            json_data={"organizationIds": organization_ids},
        )

    async def get_discount_types(self, organization_ids: list) -> dict:
        """Получить типы скидок"""
        if not self._token:
            await self.authenticate()
        return await self._request(
            "POST",
            "/discounts",
            json_data={"organizationIds": organization_ids},
        )

    async def register_webhook(self, organization_id: str, webhook_url: str, auth_token: str) -> dict:
        """Зарегистрировать вебхук в iiko"""
        if not self._token:
            await self.authenticate()
        return await self._request(
            "POST",
            "/webhooks/update_settings",
            json_data={
                "organizationId": organization_id,
                "webHooksUri": webhook_url,
                "authToken": auth_token,
                "webHooksFilter": {
                    "deliveryOrderFilter": {
                        "orderStatuses": [
                            "Unconfirmed", "WaitCooking", "ReadyForCooking",
                            "CookingStarted", "CookingCompleted", "Waiting",
                            "OnWay", "Delivered", "Closed", "Cancelled"
                        ],
                        "itemStatuses": [
                            "Added", "PrintedNotCooking", "CookingStarted",
                            "CookingCompleted", "Served"
                        ],
                        "errors": True,
                    },
                    "tableOrderFilter": {
                        "orderStatuses": ["New"],
                        "itemStatuses": ["Added"],
                        "errors": True,
                    },
                    "reserveFilter": {
                        "updates": True,
                        "errors": True,
                    },
                    "stopListUpdateFilter": {
                        "updates": True,
                    },
                    "personalShiftFilter": {
                        "updates": True,
                    },
                },
            },
        )

    async def get_webhook_settings(self, organization_id: str) -> dict:
        """Получить текущие настройки вебхука"""
        if not self._token:
            await self.authenticate()
        return await self._request(
            "POST",
            "/webhooks/settings",
            json_data={"organizationId": organization_id},
        )

    # ─── Loyalty / iikoCard ─────────────────────────────────────────────────
    async def get_loyalty_programs(self, organization_id: str) -> dict:
        """Получить список программ лояльности (бонусных программ)"""
        if not self._token:
            await self.authenticate()
        return await self._request(
            "POST",
            "/loyalty/iiko/program",
            json_data={"organizationId": organization_id},
        )

    async def get_customer_info(self, organization_id: str, customer_id: str = None,
                                phone: str = None, card_track: str = None,
                                card_number: str = None, email: str = None) -> dict:
        """Получить информацию о госте (клиенте) программы лояльности"""
        if not self._token:
            await self.authenticate()
        payload = {"organizationId": organization_id, "type": "phone"}
        if customer_id:
            payload["id"] = customer_id
            payload["type"] = "id"
        elif phone:
            payload["phone"] = phone
            payload["type"] = "phone"
        elif card_track:
            payload["cardTrack"] = card_track
            payload["type"] = "cardTrack"
        elif card_number:
            payload["cardNumber"] = card_number
            payload["type"] = "cardNumber"
        elif email:
            payload["email"] = email
            payload["type"] = "email"
        return await self._request(
            "POST",
            "/loyalty/iiko/customer/info",
            json_data=payload,
        )

    async def create_or_update_customer(self, organization_id: str,
                                        name: str = None, phone: str = None,
                                        email: str = None, card_track: str = None,
                                        card_number: str = None,
                                        birthday: str = None) -> dict:
        """Создать или обновить гостя в программе лояльности"""
        if not self._token:
            await self.authenticate()
        customer = {}
        if name:
            customer["name"] = name
        if phone:
            customer["phone"] = phone
        if email:
            customer["email"] = email
        if card_track:
            customer["cardTrack"] = card_track
        if card_number:
            customer["cardNumber"] = card_number
        if birthday:
            customer["birthday"] = birthday
        return await self._request(
            "POST",
            "/loyalty/iiko/customer/create_or_update",
            json_data={"organizationId": organization_id, **customer},
        )

    async def get_customer_balance(self, organization_id: str, customer_id: str) -> dict:
        """Получить баланс бонусов гостя"""
        if not self._token:
            await self.authenticate()
        return await self._request(
            "POST",
            "/loyalty/iiko/customer/wallet/balances",
            json_data={"organizationId": organization_id, "customerId": customer_id},
        )

    async def hold_loyalty_balance(self, organization_id: str, customer_id: str,
                                   wallet_id: str, amount: float,
                                   transaction_comment: str = "") -> dict:
        """Холдировать (заморозить) бонусы гостя"""
        if not self._token:
            await self.authenticate()
        return await self._request(
            "POST",
            "/loyalty/iiko/customer/wallet/hold",
            json_data={
                "organizationId": organization_id,
                "customerId": customer_id,
                "walletId": wallet_id,
                "amount": amount,
                "comment": transaction_comment,
            },
        )

    async def topup_loyalty_balance(self, organization_id: str, customer_id: str,
                                    wallet_id: str, amount: float,
                                    transaction_comment: str = "") -> dict:
        """Пополнить бонусный баланс гостя"""
        if not self._token:
            await self.authenticate()
        return await self._request(
            "POST",
            "/loyalty/iiko/customer/wallet/topup",
            json_data={
                "organizationId": organization_id,
                "customerId": customer_id,
                "walletId": wallet_id,
                "amount": amount,
                "comment": transaction_comment,
            },
        )

    async def withdraw_loyalty_balance(self, organization_id: str, customer_id: str,
                                       wallet_id: str, amount: float,
                                       transaction_comment: str = "") -> dict:
        """Списать бонусы с баланса гостя"""
        if not self._token:
            await self.authenticate()
        return await self._request(
            "POST",
            "/loyalty/iiko/customer/wallet/withdraw",
            json_data={
                "organizationId": organization_id,
                "customerId": customer_id,
                "walletId": wallet_id,
                "amount": amount,
                "comment": transaction_comment,
            },
        )

    async def get_cancel_causes(self, organization_ids: list) -> dict:
        """Получить причины отмены заказов"""
        if not self._token:
            await self.authenticate()
        return await self._request(
            "POST",
            "/deliveries/cancel_causes",
            json_data={"organizationIds": organization_ids},
        )

    async def get_removal_types(self, organization_ids: list) -> dict:
        """Получить типы удалений позиций из заказа"""
        if not self._token:
            await self.authenticate()
        return await self._request(
            "POST",
            "/removal_types",
            json_data={"organizationIds": organization_ids},
        )

    async def get_tips_types(self, organization_ids: list) -> dict:
        """Получить типы чаевых"""
        if not self._token:
            await self.authenticate()
        return await self._request(
            "POST",
            "/tips_types",
            json_data={"organizationIds": organization_ids},
        )

    async def get_delivery_restrictions(self, organization_ids: list) -> dict:
        """Получить ограничения доставки (зоны, минимальные суммы)"""
        if not self._token:
            await self.authenticate()
        return await self._request(
            "POST",
            "/delivery_restrictions",
            json_data={"organizationIds": organization_ids},
        )

    async def get_streets_by_city(self, organization_id: str, city_id: str) -> dict:
        """Получить список улиц по городу"""
        if not self._token:
            await self.authenticate()
        return await self._request(
            "POST",
            "/streets/by_city",
            json_data={"organizationId": organization_id, "cityId": city_id},
        )

    async def get_order_by_id(self, organization_ids: list, order_ids: list) -> dict:
        """Получить заказ по ID (из iiko Cloud)"""
        if not self._token:
            await self.authenticate()
        return await self._request(
            "POST",
            "/deliveries/by_id",
            json_data={"organizationIds": organization_ids, "orderIds": order_ids},
        )

    async def get_deliveries_by_statuses(self, organization_id: str, statuses: list) -> dict:
        """Получить заказы по статусам"""
        if not self._token:
            await self.authenticate()
        from datetime import datetime, timedelta
        now = datetime.utcnow()
        date_from = (now - timedelta(days=7)).strftime("%Y-%m-%d %H:%M:%S.000")
        date_to = now.strftime("%Y-%m-%d %H:%M:%S.000")
        return await self._request(
            "POST",
            "/deliveries/by_delivery_date_and_status",
            json_data={
                "organizationIds": [organization_id],
                "deliveryDateFrom": date_from,
                "deliveryDateTo": date_to,
                "statuses": statuses,
            },
        )

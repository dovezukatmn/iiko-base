"""
Сервис-коннектор для iiko Cloud API
"""
import json
import time
from typing import Optional
import httpx
from sqlalchemy.orm import Session
from database.models import ApiLog, IikoSettings
from config.settings import settings

MAX_LOG_BODY_LENGTH = 2000


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
    ) -> dict:
        url = f"{self.base_url}/{path.lstrip('/')}"
        req_body = json.dumps(json_data) if json_data else None
        hdrs = headers or {}
        if self._token:
            hdrs["Authorization"] = f"Bearer {self._token}"

        start = time.time()
        async with httpx.AsyncClient(timeout=30.0) as client:
            response = await client.request(method, url, json=json_data, headers=hdrs)
        duration = int((time.time() - start) * 1000)

        resp_text = response.text
        self._log_request(method, url, req_body, response.status_code, resp_text, duration)

        if response.status_code >= 400:
            raise Exception(f"iiko API error {response.status_code}: {resp_text}")

        return response.json() if resp_text else {}

    async def authenticate(self, api_key: Optional[str] = None) -> str:
        """Получить токен доступа iiko"""
        key = api_key or (self.iiko_settings.api_key if self.iiko_settings else settings.IIKO_API_KEY)
        result = await self._request("POST", "/access_token", json_data={"apiLogin": key})
        self._token = result.get("token", "")
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
            json_data={"organizationId": organization_id},
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
            },
        )

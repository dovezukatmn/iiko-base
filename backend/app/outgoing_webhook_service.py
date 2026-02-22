"""
Сервис для отправки исходящих вебхуков на внешние сервисы
"""
import json
import logging
import time
from typing import Dict, Any, Optional, List
from datetime import datetime
import httpx
from sqlalchemy.orm import Session
from database.models import OutgoingWebhook, OutgoingWebhookLog, Order

logger = logging.getLogger(__name__)


class OutgoingWebhookService:
    """Сервис для отправки вебхуков на внешние сервисы"""

    def __init__(self, db: Session):
        self.db = db

    async def send_order_webhook(
        self,
        order: Order,
        event_type: str,
        old_status: Optional[str] = None
    ) -> None:
        """
        Отправить вебхуки для заказа на все активные endpoints
        
        Args:
            order: Объект заказа
            event_type: Тип события (order.created, order.updated, order.status_changed)
            old_status: Предыдущий статус (для события status_changed)
        """
        # Получить все активные вебхуки
        webhooks = self.db.query(OutgoingWebhook).filter(
            OutgoingWebhook.is_active == True
        ).all()

        for webhook in webhooks:
            # Проверить, нужно ли отправлять для этого события
            if not self._should_send_webhook(webhook, event_type, order):
                continue

            # Подготовить payload
            payload = self._prepare_payload(webhook, order, event_type, old_status)
            
            # Отправить вебхук асинхронно
            await self._send_webhook_with_retry(webhook, order, event_type, payload)

    def _should_send_webhook(
        self,
        webhook: OutgoingWebhook,
        event_type: str,
        order: Order
    ) -> bool:
        """Проверить, нужно ли отправлять вебхук для данного события"""
        
        # Проверить тип события
        if event_type == "order.created" and not webhook.send_on_order_created:
            return False
        if event_type == "order.updated" and not webhook.send_on_order_updated:
            return False
        if event_type == "order.status_changed" and not webhook.send_on_order_status_changed:
            return False
        if event_type == "order.cancelled" and not webhook.send_on_order_cancelled:
            return False

        # Проверить фильтр по organization_id
        if webhook.filter_organization_ids:
            try:
                allowed_orgs = json.loads(webhook.filter_organization_ids)
                if order.organization_id and order.organization_id not in allowed_orgs:
                    return False
            except:
                pass

        # Проверить фильтр по типу заказа
        if webhook.filter_order_types:
            try:
                allowed_types = json.loads(webhook.filter_order_types)
                if order.order_type and order.order_type not in allowed_types:
                    return False
            except:
                pass

        # Проверить фильтр по статусу
        if webhook.filter_statuses:
            try:
                allowed_statuses = json.loads(webhook.filter_statuses)
                if order.status and order.status not in allowed_statuses:
                    return False
            except:
                pass

        return True

    def _prepare_payload(
        self,
        webhook: OutgoingWebhook,
        order: Order,
        event_type: str,
        old_status: Optional[str] = None
    ) -> Dict[str, Any]:
        """Подготовить payload для отправки"""
        
        if webhook.payload_format == "iiko_soi":
            # Формат iiko SOI API
            return self._prepare_iiko_soi_payload(order, event_type, old_status)
        elif webhook.payload_format == "iiko_cloud":
            # Формат iiko Cloud API
            return self._prepare_iiko_cloud_payload(order, event_type, old_status)
        elif webhook.payload_format == "custom" and webhook.custom_payload_template:
            # Пользовательский формат
            return self._prepare_custom_payload(webhook, order, event_type, old_status)
        else:
            # По умолчанию - простой формат
            return self._prepare_simple_payload(order, event_type, old_status)

    def _prepare_iiko_soi_payload(
        self,
        order: Order,
        event_type: str,
        old_status: Optional[str] = None
    ) -> Dict[str, Any]:
        """Подготовить payload в формате iiko SOI API"""
        
        # Определить тип события для iiko формата
        iiko_event_type = "UPDATE" if event_type != "order.created" else "CREATE"
        
        payload = {
            "type": iiko_event_type,
            "orderExternalId": order.external_order_id or f"order-{order.id}",
            "readableNumber": order.readable_number or str(order.id),
            "creationStatus": order.creation_status or "OK",
            "errorInfo": order.error_info,
            "transactionDetails": {
                "correlationId": f"webhook-{int(time.time())}",
                "organizationId": order.organization_id
            },
            "iikoOrderDetails": {
                "iikoOrderId": order.iiko_order_id,
                "iikoOrderNumber": order.readable_number or str(order.id),
                "restaurantName": order.restaurant_name,
                "orderType": order.order_type or "DELIVERY",
                "orderStatus": order.status,
                "receivedAt": order.created_at.isoformat() if order.created_at else None,
                "promisedTime": order.promised_time.isoformat() if order.promised_time else None,
                "problem": order.problem,
                "orderAmount": (order.total_amount / 100.0) if order.total_amount else 0
            }
        }

        # Добавить информацию о курьере если есть
        if order.courier_id or order.courier_name:
            payload["iikoOrderDetails"]["courier"] = {
                "id": order.courier_id,
                "name": order.courier_name
            }

        # Добавить информацию о клиенте
        if order.customer_name or order.customer_phone:
            payload["iikoOrderDetails"]["customer"] = {
                "name": order.customer_name,
                "phone": order.customer_phone
            }

        # Если есть старый статус, добавить его
        if old_status:
            payload["previousStatus"] = old_status

        return payload

    def _prepare_iiko_cloud_payload(
        self,
        order: Order,
        event_type: str,
        old_status: Optional[str] = None
    ) -> Dict[str, Any]:
        """Подготовить payload в формате iiko Cloud API"""
        
        payload = {
            "eventType": "DeliveryOrderUpdate" if event_type != "order.created" else "DeliveryOrderCreate",
            "eventInfo": {
                "id": order.iiko_order_id or f"order-{order.id}",
                "externalId": order.external_order_id,
                "organizationId": order.organization_id,
                "status": order.status,
                "sum": (order.total_amount / 100.0) if order.total_amount else 0,
                "number": order.readable_number,
                "orderType": order.order_type,
                "createdAt": order.created_at.isoformat() if order.created_at else None,
                "promisedTime": order.promised_time.isoformat() if order.promised_time else None
            }
        }

        # Добавить курьера
        if order.courier_id or order.courier_name:
            payload["eventInfo"]["courier"] = {
                "id": order.courier_id,
                "name": order.courier_name
            }

        # Добавить клиента
        if order.customer_name or order.customer_phone:
            payload["eventInfo"]["customer"] = {
                "name": order.customer_name,
                "phone": order.customer_phone,
                "address": order.delivery_address
            }

        return payload

    def _prepare_simple_payload(
        self,
        order: Order,
        event_type: str,
        old_status: Optional[str] = None
    ) -> Dict[str, Any]:
        """Простой формат payload"""
        
        return {
            "event": event_type,
            "order": {
                "id": order.id,
                "external_id": order.external_order_id,
                "iiko_order_id": order.iiko_order_id,
                "status": order.status,
                "previous_status": old_status,
                "customer_name": order.customer_name,
                "customer_phone": order.customer_phone,
                "delivery_address": order.delivery_address,
                "total_amount": order.total_amount,
                "courier_id": order.courier_id,
                "courier_name": order.courier_name,
                "order_type": order.order_type,
                "restaurant_name": order.restaurant_name,
                "created_at": order.created_at.isoformat() if order.created_at else None,
                "updated_at": order.updated_at.isoformat() if order.updated_at else None,
                "promised_time": order.promised_time.isoformat() if order.promised_time else None
            }
        }

    def _prepare_custom_payload(
        self,
        webhook: OutgoingWebhook,
        order: Order,
        event_type: str,
        old_status: Optional[str] = None
    ) -> Dict[str, Any]:
        """Подготовить пользовательский payload по шаблону"""
        
        try:
            template = webhook.custom_payload_template
            
            # Заменить переменные в шаблоне
            variables = {
                "{{order_id}}": str(order.id),
                "{{external_order_id}}": order.external_order_id or "",
                "{{iiko_order_id}}": order.iiko_order_id or "",
                "{{status}}": order.status or "",
                "{{previous_status}}": old_status or "",
                "{{customer_name}}": order.customer_name or "",
                "{{customer_phone}}": order.customer_phone or "",
                "{{total_amount}}": str(order.total_amount) if order.total_amount else "0",
                "{{event_type}}": event_type,
                "{{courier_name}}": order.courier_name or "",
                "{{order_type}}": order.order_type or ""
            }
            
            for var, value in variables.items():
                template = template.replace(var, value)
            
            return json.loads(template)
        except Exception as e:
            logger.error(f"Ошибка подготовки custom payload: {e}")
            return self._prepare_simple_payload(order, event_type, old_status)

    async def _send_webhook_with_retry(
        self,
        webhook: OutgoingWebhook,
        order: Order,
        event_type: str,
        payload: Dict[str, Any]
    ) -> None:
        """Отправить вебхук с повторными попытками"""
        
        for attempt in range(1, webhook.retry_count + 1):
            success, log_data = await self._send_single_webhook(
                webhook, order, event_type, payload, attempt
            )
            
            # Сохранить лог
            self._save_webhook_log(webhook, order, event_type, log_data)
            
            # Обновить статистику вебхука
            self._update_webhook_stats(webhook, success)
            
            if success:
                break
            
            # Подождать перед следующей попыткой
            if attempt < webhook.retry_count:
                await self._delay(webhook.retry_delay_seconds)

    async def _send_single_webhook(
        self,
        webhook: OutgoingWebhook,
        order: Order,
        event_type: str,
        payload: Dict[str, Any],
        attempt_number: int
    ) -> tuple[bool, Dict[str, Any]]:
        """Отправить один вебхук запрос"""
        
        start_time = time.time()
        log_data = {
            "webhook_id": webhook.id,
            "webhook_name": webhook.name,
            "order_id": order.id,
            "order_external_id": order.external_order_id,
            "event_type": event_type,
            "request_url": webhook.webhook_url,
            "request_method": "POST",
            "attempt_number": attempt_number,
            "success": False
        }

        try:
            # Подготовить заголовки
            headers = {"Content-Type": "application/json"}
            
            # Добавить авторизацию
            if webhook.auth_type == "bearer" and webhook.auth_token:
                headers["Authorization"] = f"Bearer {webhook.auth_token}"
            elif webhook.auth_type == "basic" and webhook.auth_username and webhook.auth_password:
                import base64
                credentials = f"{webhook.auth_username}:{webhook.auth_password}"
                encoded = base64.b64encode(credentials.encode()).decode()
                headers["Authorization"] = f"Basic {encoded}"
            
            # Добавить пользовательские заголовки
            if webhook.custom_headers:
                try:
                    custom = json.loads(webhook.custom_headers)
                    headers.update(custom)
                except:
                    pass

            # Сохранить заголовки и тело запроса
            log_data["request_headers"] = json.dumps(headers)
            log_data["request_body"] = json.dumps(payload, ensure_ascii=False)

            # Отправить запрос
            async with httpx.AsyncClient(timeout=webhook.timeout_seconds) as client:
                response = await client.post(
                    webhook.webhook_url,
                    json=payload,
                    headers=headers
                )

                # Сохранить ответ
                duration_ms = int((time.time() - start_time) * 1000)
                log_data["duration_ms"] = duration_ms
                log_data["response_status"] = response.status_code
                log_data["response_headers"] = json.dumps(dict(response.headers))
                log_data["response_body"] = response.text[:5000]  # Ограничить размер

                # Проверить успешность
                success = 200 <= response.status_code < 300
                log_data["success"] = success

                if not success:
                    log_data["error_message"] = f"HTTP {response.status_code}: {response.text[:200]}"

                return success, log_data

        except httpx.TimeoutException as e:
            log_data["error_message"] = f"Timeout after {webhook.timeout_seconds}s: {str(e)}"
            log_data["duration_ms"] = webhook.timeout_seconds * 1000
            logger.error(f"Timeout sending webhook to {webhook.webhook_url}: {e}")
            return False, log_data

        except Exception as e:
            duration_ms = int((time.time() - start_time) * 1000)
            log_data["duration_ms"] = duration_ms
            log_data["error_message"] = str(e)[:500]
            logger.error(f"Error sending webhook to {webhook.webhook_url}: {e}")
            return False, log_data

    async def _delay(self, seconds: int):
        """Асинхронная задержка"""
        import asyncio
        await asyncio.sleep(seconds)

    def _save_webhook_log(
        self,
        webhook: OutgoingWebhook,
        order: Order,
        event_type: str,
        log_data: Dict[str, Any]
    ) -> None:
        """Сохранить лог отправки вебхука"""
        
        try:
            log = OutgoingWebhookLog(**log_data)
            self.db.add(log)
            self.db.commit()
        except Exception as e:
            logger.error(f"Error saving webhook log: {e}")
            self.db.rollback()

    def _update_webhook_stats(self, webhook: OutgoingWebhook, success: bool) -> None:
        """Обновить статистику вебхука"""
        
        try:
            webhook.total_sent += 1
            if success:
                webhook.total_success += 1
                webhook.last_success_at = datetime.utcnow()
            else:
                webhook.total_failed += 1
            
            webhook.last_sent_at = datetime.utcnow()
            self.db.commit()
        except Exception as e:
            logger.error(f"Error updating webhook stats: {e}")
            self.db.rollback()

    async def test_webhook(self, webhook_id: int) -> Dict[str, Any]:
        """Тестовая отправка вебхука"""
        
        webhook = self.db.query(OutgoingWebhook).filter(
            OutgoingWebhook.id == webhook_id
        ).first()
        
        if not webhook:
            return {"success": False, "error": "Webhook not found"}

        # Создать тестовый payload
        test_payload = {
            "event": "test",
            "message": "This is a test webhook from iiko-base",
            "webhook_name": webhook.name,
            "timestamp": datetime.utcnow().isoformat()
        }

        # Отправить
        success, log_data = await self._send_single_webhook(
            webhook,
            order=None,  # Для теста заказ не нужен
            event_type="test",
            payload=test_payload,
            attempt_number=1
        )

        return {
            "success": success,
            "status_code": log_data.get("response_status"),
            "duration_ms": log_data.get("duration_ms"),
            "error": log_data.get("error_message"),
            "response": log_data.get("response_body")
        }

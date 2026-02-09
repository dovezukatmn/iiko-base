"""
Модуль для управления аутентификацией iiko Cloud API
"""
import asyncio
import logging
from datetime import datetime, timedelta, timezone
from typing import Optional
import httpx
from config.settings import settings

logger = logging.getLogger(__name__)

# Глобальная переменная для хранения токена и времени его истечения
_access_token: Optional[str] = None
_token_expires_at: Optional[datetime] = None
_token_lock = asyncio.Lock()


async def get_access_token(force_refresh: bool = False) -> str:
    """
    Получить актуальный токен доступа к iiko Cloud API.
    
    Токен кешируется и автоматически обновляется при истечении.
    Токены iiko живут примерно 15 минут.
    
    Args:
        force_refresh: Принудительно обновить токен, игнорируя кеш
        
    Returns:
        str: Актуальный токен доступа
        
    Raises:
        Exception: Если не удалось получить токен
    """
    global _access_token, _token_expires_at
    
    async with _token_lock:
        # Проверяем, нужно ли обновлять токен
        now = datetime.now(timezone.utc)
        if not force_refresh and _access_token and _token_expires_at:
            if now < _token_expires_at:
                logger.debug(f"Используем кешированный токен (истекает через {(_token_expires_at - now).seconds} секунд)")
                return _access_token
        
        # Получаем API ключ из переменных окружения
        api_login = settings.IIKO_API_KEY.strip()
        if not api_login:
            raise Exception(
                "IIKO_API_KEY не задан в переменных окружения. "
                "Установите его в .env файле или переменных окружения."
            )
        
        # Формируем запрос к iiko API
        url = f"{settings.IIKO_API_URL.rstrip('/')}/access_token"
        payload = {"apiLogin": api_login}
        
        logger.info(f"Запрос токена доступа к iiko API: {url}")
        
        try:
            async with httpx.AsyncClient(timeout=30.0) as client:
                response = await client.post(
                    url,
                    json=payload,
                    headers={"Content-Type": "application/json"}
                )
                
                if response.status_code != 200:
                    error_text = response.text
                    logger.error(f"Ошибка получения токена: {response.status_code} - {error_text}")
                    raise Exception(
                        f"Не удалось получить токен доступа iiko. "
                        f"Статус: {response.status_code}. "
                        f"Проверьте правильность IIKO_API_KEY."
                    )
                
                # Парсим ответ
                response_text = response.text.strip()
                
                # iiko API может вернуть токен как plain text или JSON
                if response_text.startswith('"') and response_text.endswith('"'):
                    # Plain text строка в кавычках
                    _access_token = response_text.strip('"')
                elif response_text.startswith('{'):
                    # JSON ответ
                    data = response.json()
                    _access_token = data.get("token") or data.get("access_token")
                else:
                    # Просто текст
                    _access_token = response_text
                
                if not _access_token:
                    raise Exception("iiko API вернул пустой токен")
                
                # Токены iiko живут ~15 минут, обновляем каждые 14 минут для безопасности
                _token_expires_at = now + timedelta(minutes=14)
                
                logger.info(f"Токен успешно получен, истекает: {_token_expires_at}")
                return _access_token
                
        except httpx.TimeoutException:
            logger.error(f"Тайм-аут при подключении к iiko API: {url}")
            raise Exception("Тайм-аут подключения к iiko API")
        except httpx.ConnectError as e:
            logger.error(f"Ошибка подключения к iiko API: {e}")
            raise Exception(f"Не удалось подключиться к iiko API: {e}")
        except Exception as e:
            logger.error(f"Неожиданная ошибка при получении токена: {e}")
            raise


async def refresh_token_periodically():
    """
    Фоновая задача для периодического обновления токена.
    Обновляет токен каждые 14 минут.
    """
    logger.info("Запущена фоновая задача обновления токена iiko")
    
    while True:
        try:
            # Обновляем токен каждые 14 минут (токены живут ~15 минут)
            await asyncio.sleep(14 * 60)  # 14 минут в секундах
            logger.info("Обновление токена iiko по расписанию...")
            await get_access_token(force_refresh=True)
            logger.info("Токен успешно обновлен")
        except Exception as e:
            logger.error(f"Ошибка при обновлении токена: {e}")
            # Продолжаем выполнение, чтобы попробовать снова через 14 минут

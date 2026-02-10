"""
Тесты для модуля аутентификации iiko
"""
import pytest
from unittest.mock import MagicMock, AsyncMock, patch
from datetime import datetime, timedelta, timezone
import app.iiko_auth as iiko_auth


@pytest.fixture(autouse=True)
def reset_token_cache():
    """Сбрасываем кеш токена перед каждым тестом"""
    iiko_auth._access_token = None
    iiko_auth._token_expires_at = None
    yield
    iiko_auth._access_token = None
    iiko_auth._token_expires_at = None


@pytest.mark.asyncio
async def test_get_access_token_success():
    """Проверяем успешное получение токена"""
    mock_response = MagicMock()
    mock_response.status_code = 200
    mock_response.text = '"test-token-value"'
    
    with patch("app.iiko_auth.settings") as mock_settings:
        mock_settings.IIKO_API_KEY = "test-api-key"
        mock_settings.IIKO_API_URL = "https://api-ru.iiko.services/api/1"
        
        with patch("httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client.__aexit__ = AsyncMock(return_value=False)
            mock_client.post = AsyncMock(return_value=mock_response)
            mock_client_cls.return_value = mock_client
            
            token = await iiko_auth.get_access_token()
            assert token == "test-token-value"
            assert iiko_auth._access_token == "test-token-value"
            assert iiko_auth._token_expires_at is not None


@pytest.mark.asyncio
async def test_get_access_token_json_response():
    """Проверяем получение токена из JSON ответа"""
    mock_response = MagicMock()
    mock_response.status_code = 200
    mock_response.text = '{"token": "json-token-value"}'
    mock_response.json.return_value = {"token": "json-token-value"}
    
    with patch("app.iiko_auth.settings") as mock_settings:
        mock_settings.IIKO_API_KEY = "test-api-key"
        mock_settings.IIKO_API_URL = "https://api-ru.iiko.services/api/1"
        
        with patch("httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client.__aexit__ = AsyncMock(return_value=False)
            mock_client.post = AsyncMock(return_value=mock_response)
            mock_client_cls.return_value = mock_client
            
            token = await iiko_auth.get_access_token()
            # Will be json-token-value since we're parsing from the JSON
            assert token == "json-token-value"


@pytest.mark.asyncio
async def test_get_access_token_uses_cache():
    """Проверяем, что токен берется из кеша если не истек"""
    # Устанавливаем кешированный токен
    iiko_auth._access_token = "cached-token"
    iiko_auth._token_expires_at = datetime.now(timezone.utc) + timedelta(minutes=10)
    
    # Не делаем никаких mock запросов - если метод попробует сделать запрос, тест упадет
    token = await iiko_auth.get_access_token()
    assert token == "cached-token"


@pytest.mark.asyncio
async def test_get_access_token_refreshes_expired():
    """Проверяем, что токен обновляется если истек"""
    # Устанавливаем истекший токен
    iiko_auth._access_token = "expired-token"
    iiko_auth._token_expires_at = datetime.now(timezone.utc) - timedelta(minutes=1)
    
    mock_response = MagicMock()
    mock_response.status_code = 200
    mock_response.text = '"new-token"'
    
    with patch("app.iiko_auth.settings") as mock_settings:
        mock_settings.IIKO_API_KEY = "test-api-key"
        mock_settings.IIKO_API_URL = "https://api-ru.iiko.services/api/1"
        
        with patch("httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client.__aexit__ = AsyncMock(return_value=False)
            mock_client.post = AsyncMock(return_value=mock_response)
            mock_client_cls.return_value = mock_client
            
            token = await iiko_auth.get_access_token()
            assert token == "new-token"
            assert iiko_auth._access_token == "new-token"


@pytest.mark.asyncio
async def test_get_access_token_force_refresh():
    """Проверяем принудительное обновление токена"""
    # Устанавливаем кешированный токен
    iiko_auth._access_token = "cached-token"
    iiko_auth._token_expires_at = datetime.now(timezone.utc) + timedelta(minutes=10)
    
    mock_response = MagicMock()
    mock_response.status_code = 200
    mock_response.text = '"refreshed-token"'
    
    with patch("app.iiko_auth.settings") as mock_settings:
        mock_settings.IIKO_API_KEY = "test-api-key"
        mock_settings.IIKO_API_URL = "https://api-ru.iiko.services/api/1"
        
        with patch("httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client.__aexit__ = AsyncMock(return_value=False)
            mock_client.post = AsyncMock(return_value=mock_response)
            mock_client_cls.return_value = mock_client
            
            token = await iiko_auth.get_access_token(force_refresh=True)
            assert token == "refreshed-token"
            mock_client.post.assert_called_once()


@pytest.mark.asyncio
async def test_get_access_token_empty_api_key():
    """Проверяем ошибку при пустом API ключе"""
    with patch("app.iiko_auth.settings") as mock_settings:
        mock_settings.IIKO_API_KEY = ""
        
        with pytest.raises(Exception, match="не задан"):
            await iiko_auth.get_access_token()


@pytest.mark.asyncio
async def test_get_access_token_401_error():
    """Проверяем обработку ошибки 401"""
    mock_response = MagicMock()
    mock_response.status_code = 401
    mock_response.text = "Unauthorized"
    
    with patch("app.iiko_auth.settings") as mock_settings:
        mock_settings.IIKO_API_KEY = "wrong-api-key"
        mock_settings.IIKO_API_URL = "https://api-ru.iiko.services/api/1"
        
        with patch("httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client.__aexit__ = AsyncMock(return_value=False)
            mock_client.post = AsyncMock(return_value=mock_response)
            mock_client_cls.return_value = mock_client
            
            with pytest.raises(Exception, match="Не удалось получить токен"):
                await iiko_auth.get_access_token()


@pytest.mark.asyncio
async def test_get_access_token_empty_token_response():
    """Проверяем ошибку при пустом токене в ответе"""
    mock_response = MagicMock()
    mock_response.status_code = 200
    mock_response.text = '""'
    
    with patch("app.iiko_auth.settings") as mock_settings:
        mock_settings.IIKO_API_KEY = "test-api-key"
        mock_settings.IIKO_API_URL = "https://api-ru.iiko.services/api/1"
        
        with patch("httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client.__aexit__ = AsyncMock(return_value=False)
            mock_client.post = AsyncMock(return_value=mock_response)
            mock_client_cls.return_value = mock_client
            
            with pytest.raises(Exception, match="пустой токен"):
                await iiko_auth.get_access_token()

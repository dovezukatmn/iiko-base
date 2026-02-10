"""
Тесты для сервиса iiko
"""
import pytest
from unittest.mock import MagicMock, AsyncMock, patch
from app.iiko_service import IikoService


@pytest.fixture
def mock_db():
    db = MagicMock()
    db.add = MagicMock()
    db.commit = MagicMock()
    return db


@pytest.fixture
def mock_settings():
    s = MagicMock()
    s.api_key = "test-key-with-minimum-16-chars"
    s.api_url = "https://api-ru.iiko.services/api/1"
    return s


def test_iiko_service_init(mock_db, mock_settings):
    svc = IikoService(mock_db, mock_settings)
    assert svc.base_url == "https://api-ru.iiko.services/api/1"
    assert svc._token is None


def test_iiko_service_init_without_settings(mock_db):
    svc = IikoService(mock_db)
    assert svc._token is None


@pytest.mark.asyncio
async def test_iiko_authenticate(mock_db, mock_settings):
    svc = IikoService(mock_db, mock_settings)
    mock_response = MagicMock()
    mock_response.status_code = 200
    mock_response.text = '{"token": "abc123"}'
    mock_response.json.return_value = {"token": "abc123"}

    with patch("httpx.AsyncClient") as mock_client_cls:
        mock_client = AsyncMock()
        mock_client.__aenter__ = AsyncMock(return_value=mock_client)
        mock_client.__aexit__ = AsyncMock(return_value=False)
        mock_client.request = AsyncMock(return_value=mock_response)
        mock_client_cls.return_value = mock_client

        token = await svc.authenticate()
        assert token == "abc123"
        assert svc._token == "abc123"


@pytest.mark.asyncio
async def test_iiko_authenticate_plain_text_token(mock_db, mock_settings):
    """iiko API may return token as plain text, not JSON"""
    svc = IikoService(mock_db, mock_settings)
    mock_response = MagicMock()
    mock_response.status_code = 200
    mock_response.text = "plain-text-token-value"
    mock_response.json.side_effect = Exception("Not JSON")

    with patch("httpx.AsyncClient") as mock_client_cls:
        mock_client = AsyncMock()
        mock_client.__aenter__ = AsyncMock(return_value=mock_client)
        mock_client.__aexit__ = AsyncMock(return_value=False)
        mock_client.request = AsyncMock(return_value=mock_response)
        mock_client_cls.return_value = mock_client

        token = await svc.authenticate()
        assert token == "plain-text-token-value"
        assert svc._token == "plain-text-token-value"


@pytest.mark.asyncio
async def test_iiko_authenticate_quoted_text_token(mock_db, mock_settings):
    """iiko API may return token wrapped in quotes as plain text"""
    svc = IikoService(mock_db, mock_settings)
    mock_response = MagicMock()
    mock_response.status_code = 200
    mock_response.text = '"quoted-token-value"'
    mock_response.json.return_value = "quoted-token-value"

    with patch("httpx.AsyncClient") as mock_client_cls:
        mock_client = AsyncMock()
        mock_client.__aenter__ = AsyncMock(return_value=mock_client)
        mock_client.__aexit__ = AsyncMock(return_value=False)
        mock_client.request = AsyncMock(return_value=mock_response)
        mock_client_cls.return_value = mock_client

        token = await svc.authenticate()
        assert token == "quoted-token-value"
        assert svc._token == "quoted-token-value"


@pytest.mark.asyncio
async def test_iiko_authenticate_strips_api_key(mock_db, mock_settings):
    """API key should be stripped of whitespace before sending"""
    mock_settings.api_key = "  test-key-with-spaces  "
    svc = IikoService(mock_db, mock_settings)
    mock_response = MagicMock()
    mock_response.status_code = 200
    mock_response.text = '{"token": "abc123"}'
    mock_response.json.return_value = {"token": "abc123"}

    with patch("httpx.AsyncClient") as mock_client_cls:
        mock_client = AsyncMock()
        mock_client.__aenter__ = AsyncMock(return_value=mock_client)
        mock_client.__aexit__ = AsyncMock(return_value=False)
        mock_client.request = AsyncMock(return_value=mock_response)
        mock_client_cls.return_value = mock_client

        token = await svc.authenticate()
        assert token == "abc123"
        # Verify the API key was stripped when sent
        call_args = mock_client.request.call_args
        json_payload = call_args.kwargs.get("json") or call_args[1].get("json")
        assert json_payload["apiLogin"] == "test-key-with-spaces"


@pytest.mark.asyncio
async def test_register_webhook_includes_organization_id(mock_db, mock_settings):
    """Проверяем, что register_webhook отправляет organizationId в теле запроса"""
    svc = IikoService(mock_db, mock_settings)
    svc._token = "pre-set-token"

    mock_response = MagicMock()
    mock_response.status_code = 200
    mock_response.text = '{"status": "ok"}'
    mock_response.json.return_value = {"status": "ok"}

    with patch("httpx.AsyncClient") as mock_client_cls:
        mock_client = AsyncMock()
        mock_client.__aenter__ = AsyncMock(return_value=mock_client)
        mock_client.__aexit__ = AsyncMock(return_value=False)
        mock_client.request = AsyncMock(return_value=mock_response)
        mock_client_cls.return_value = mock_client

        result = await svc.register_webhook(
            organization_id="org-123",
            webhook_url="https://example.com/api/v1/webhooks/iiko",
            auth_token="secret-token",
        )

        # Verify organizationId is present in the request payload
        call_args = mock_client.request.call_args
        json_payload = call_args.kwargs.get("json") or call_args[1].get("json")
        assert json_payload["organizationId"] == "org-123"
        assert json_payload["webHooksUri"] == "https://example.com/api/v1/webhooks/iiko"
        assert json_payload["authToken"] == "secret-token"
        assert result == {"status": "ok"}


@pytest.mark.asyncio
async def test_request_includes_timeout_header(mock_db, mock_settings):
    """Проверяем, что все запросы к iiko API содержат заголовок Timeout"""
    svc = IikoService(mock_db, mock_settings)
    svc._token = "pre-set-token"

    mock_response = MagicMock()
    mock_response.status_code = 200
    mock_response.text = '{"result": "ok"}'
    mock_response.json.return_value = {"result": "ok"}

    with patch("httpx.AsyncClient") as mock_client_cls:
        mock_client = AsyncMock()
        mock_client.__aenter__ = AsyncMock(return_value=mock_client)
        mock_client.__aexit__ = AsyncMock(return_value=False)
        mock_client.request = AsyncMock(return_value=mock_response)
        mock_client_cls.return_value = mock_client

        await svc.get_couriers("org-123")

        call_args = mock_client.request.call_args
        sent_headers = call_args.kwargs.get("headers") or call_args[1].get("headers")
        assert sent_headers["Timeout"] == "45"
        assert sent_headers["Content-Type"] == "application/json"
        assert sent_headers["Authorization"] == "Bearer pre-set-token"


@pytest.mark.asyncio
async def test_request_retries_on_401(mock_db, mock_settings):
    """Проверяем, что при 401 запрос повторяется после обновления токена"""
    svc = IikoService(mock_db, mock_settings)
    svc._token = "expired-token"

    # First call returns 401, second call (authenticate) returns token,
    # third call (retry) returns success
    response_401 = MagicMock()
    response_401.status_code = 401
    response_401.text = '{"error": "unauthorized"}'

    response_token = MagicMock()
    response_token.status_code = 200
    response_token.text = '{"token": "new-token"}'
    response_token.json.return_value = {"token": "new-token"}

    response_ok = MagicMock()
    response_ok.status_code = 200
    response_ok.text = '{"couriers": []}'
    response_ok.json.return_value = {"couriers": []}

    with patch("httpx.AsyncClient") as mock_client_cls:
        mock_client = AsyncMock()
        mock_client.__aenter__ = AsyncMock(return_value=mock_client)
        mock_client.__aexit__ = AsyncMock(return_value=False)
        mock_client.request = AsyncMock(
            side_effect=[response_401, response_token, response_ok]
        )
        mock_client_cls.return_value = mock_client

        result = await svc.get_couriers("org-123")
        assert result == {"couriers": []}
        assert svc._token == "new-token"
        assert mock_client.request.call_count == 3


@pytest.mark.asyncio
async def test_authenticate_empty_api_key_raises(mock_db, mock_settings):
    """authenticate() должен выбрасывать ошибку если API ключ пуст"""
    mock_settings.api_key = "   "
    svc = IikoService(mock_db, mock_settings)
    with pytest.raises(Exception, match="API ключ.*не задан"):
        await svc.authenticate()


@pytest.mark.asyncio
async def test_authenticate_401_gives_helpful_message(mock_db, mock_settings):
    """authenticate() должен давать понятное сообщение при 401 от iiko"""
    svc = IikoService(mock_db, mock_settings)
    mock_response = MagicMock()
    mock_response.status_code = 401
    mock_response.text = "Unauthorized"

    with patch("httpx.AsyncClient") as mock_client_cls:
        mock_client = AsyncMock()
        mock_client.__aenter__ = AsyncMock(return_value=mock_client)
        mock_client.__aexit__ = AsyncMock(return_value=False)
        mock_client.request = AsyncMock(return_value=mock_response)
        mock_client_cls.return_value = mock_client

        with pytest.raises(Exception, match="Неверный API ключ"):
            await svc.authenticate()


@pytest.mark.asyncio
async def test_get_organizations_with_temporary_settings(mock_db):
    """IikoService can fetch organizations using a temporary IikoSettings object"""
    from database.models import IikoSettings

    temp_settings = IikoSettings(
        api_key="test-api-key-long-enough-for-validation",
        api_url="https://api-ru.iiko.services/api/1",
    )
    svc = IikoService(mock_db, temp_settings)

    # Mock both authenticate and get_organizations calls
    auth_response = MagicMock()
    auth_response.status_code = 200
    auth_response.text = '{"token": "test-token"}'
    auth_response.json.return_value = {"token": "test-token"}

    orgs_response = MagicMock()
    orgs_response.status_code = 200
    orgs_response.text = '{"organizations": [{"id": "org-uuid-1", "name": "Test Org"}]}'
    orgs_response.json.return_value = {"organizations": [{"id": "org-uuid-1", "name": "Test Org"}]}

    with patch("httpx.AsyncClient") as mock_client_cls:
        mock_client = AsyncMock()
        mock_client.__aenter__ = AsyncMock(return_value=mock_client)
        mock_client.__aexit__ = AsyncMock(return_value=False)
        mock_client.request = AsyncMock(side_effect=[auth_response, orgs_response])
        mock_client_cls.return_value = mock_client

        await svc.authenticate(api_key="test-api-key-long-enough-for-validation")
        result = await svc.get_organizations()
        assert "organizations" in result
        assert len(result["organizations"]) == 1
        assert result["organizations"][0]["id"] == "org-uuid-1"
        assert result["organizations"][0]["name"] == "Test Org"

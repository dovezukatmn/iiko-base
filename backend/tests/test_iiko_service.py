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
    s.api_key = "test-key"
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

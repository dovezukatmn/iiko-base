"""
Тесты для методов лояльности в IikoService
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
    s.api_key = "test-key-0123456789abcdef"
    s.api_url = "https://api-ru.iiko.services/api/1"
    return s


def _make_mock_response(status_code=200, json_data=None, text=None):
    resp = MagicMock()
    resp.status_code = status_code
    resp.text = text or (str(json_data) if json_data else "")
    resp.json.return_value = json_data or {}
    return resp


def _patch_httpx(mock_response):
    mock_client = AsyncMock()
    mock_client.__aenter__ = AsyncMock(return_value=mock_client)
    mock_client.__aexit__ = AsyncMock(return_value=False)
    mock_client.request = AsyncMock(return_value=mock_response)
    return mock_client


@pytest.mark.asyncio
async def test_get_loyalty_programs(mock_db, mock_settings):
    svc = IikoService(mock_db, mock_settings)
    svc._token = "test-token"

    response = _make_mock_response(json_data={"programs": [{"id": "prog1", "name": "Bonus"}]})

    with patch("httpx.AsyncClient") as mock_client_cls:
        mock_client_cls.return_value = _patch_httpx(response)

        result = await svc.get_loyalty_programs("org-123")
        assert result == {"programs": [{"id": "prog1", "name": "Bonus"}]}

        call_args = mock_client_cls.return_value.request.call_args
        json_payload = call_args.kwargs.get("json") or call_args[1].get("json")
        assert json_payload["organizationId"] == "org-123"


@pytest.mark.asyncio
async def test_get_customer_info_by_phone(mock_db, mock_settings):
    svc = IikoService(mock_db, mock_settings)
    svc._token = "test-token"

    customer_data = {"id": "cust-1", "name": "Test", "phone": "+7900"}
    response = _make_mock_response(json_data=customer_data)

    with patch("httpx.AsyncClient") as mock_client_cls:
        mock_client_cls.return_value = _patch_httpx(response)

        result = await svc.get_customer_info("org-123", phone="+7900")
        assert result["id"] == "cust-1"

        call_args = mock_client_cls.return_value.request.call_args
        json_payload = call_args.kwargs.get("json") or call_args[1].get("json")
        assert json_payload["phone"] == "+7900"
        assert json_payload["type"] == "phone"
        assert json_payload["organizationId"] == "org-123"


@pytest.mark.asyncio
async def test_get_customer_info_by_email(mock_db, mock_settings):
    svc = IikoService(mock_db, mock_settings)
    svc._token = "test-token"

    response = _make_mock_response(json_data={"id": "cust-2", "email": "test@test.com"})

    with patch("httpx.AsyncClient") as mock_client_cls:
        mock_client_cls.return_value = _patch_httpx(response)

        result = await svc.get_customer_info("org-123", email="test@test.com")
        assert result["id"] == "cust-2"

        call_args = mock_client_cls.return_value.request.call_args
        json_payload = call_args.kwargs.get("json") or call_args[1].get("json")
        assert json_payload["email"] == "test@test.com"
        assert json_payload["type"] == "email"


@pytest.mark.asyncio
async def test_create_or_update_customer(mock_db, mock_settings):
    svc = IikoService(mock_db, mock_settings)
    svc._token = "test-token"

    response = _make_mock_response(json_data={"id": "new-cust"})

    with patch("httpx.AsyncClient") as mock_client_cls:
        mock_client_cls.return_value = _patch_httpx(response)

        result = await svc.create_or_update_customer(
            "org-123", name="Ivan", phone="+7900", email="ivan@test.com"
        )
        assert result["id"] == "new-cust"

        call_args = mock_client_cls.return_value.request.call_args
        json_payload = call_args.kwargs.get("json") or call_args[1].get("json")
        assert json_payload["organizationId"] == "org-123"
        assert json_payload["name"] == "Ivan"
        assert json_payload["phone"] == "+7900"


@pytest.mark.asyncio
async def test_get_customer_balance(mock_db, mock_settings):
    svc = IikoService(mock_db, mock_settings)
    svc._token = "test-token"

    response = _make_mock_response(json_data={"wallets": [{"walletId": "w1", "balance": 100}]})

    with patch("httpx.AsyncClient") as mock_client_cls:
        mock_client_cls.return_value = _patch_httpx(response)

        result = await svc.get_customer_balance("org-123", "cust-1")
        assert len(result["wallets"]) == 1

        call_args = mock_client_cls.return_value.request.call_args
        json_payload = call_args.kwargs.get("json") or call_args[1].get("json")
        assert json_payload["customerId"] == "cust-1"


@pytest.mark.asyncio
async def test_topup_loyalty_balance(mock_db, mock_settings):
    svc = IikoService(mock_db, mock_settings)
    svc._token = "test-token"

    response = _make_mock_response(json_data={"status": "ok"})

    with patch("httpx.AsyncClient") as mock_client_cls:
        mock_client_cls.return_value = _patch_httpx(response)

        result = await svc.topup_loyalty_balance("org-123", "cust-1", "w1", 50.0, "bonus")
        assert result["status"] == "ok"

        call_args = mock_client_cls.return_value.request.call_args
        json_payload = call_args.kwargs.get("json") or call_args[1].get("json")
        assert json_payload["amount"] == 50.0
        assert json_payload["walletId"] == "w1"


@pytest.mark.asyncio
async def test_withdraw_loyalty_balance(mock_db, mock_settings):
    svc = IikoService(mock_db, mock_settings)
    svc._token = "test-token"

    response = _make_mock_response(json_data={"status": "ok"})

    with patch("httpx.AsyncClient") as mock_client_cls:
        mock_client_cls.return_value = _patch_httpx(response)

        result = await svc.withdraw_loyalty_balance("org-123", "cust-1", "w1", 25.0)
        assert result["status"] == "ok"


@pytest.mark.asyncio
async def test_hold_loyalty_balance(mock_db, mock_settings):
    svc = IikoService(mock_db, mock_settings)
    svc._token = "test-token"

    response = _make_mock_response(json_data={"status": "ok"})

    with patch("httpx.AsyncClient") as mock_client_cls:
        mock_client_cls.return_value = _patch_httpx(response)

        result = await svc.hold_loyalty_balance("org-123", "cust-1", "w1", 10.0, "hold for order")
        assert result["status"] == "ok"

        call_args = mock_client_cls.return_value.request.call_args
        json_payload = call_args.kwargs.get("json") or call_args[1].get("json")
        assert json_payload["comment"] == "hold for order"


@pytest.mark.asyncio
async def test_authenticate_with_correlation_id_response(mock_db, mock_settings):
    """iiko API returns correlationId alongside token"""
    svc = IikoService(mock_db, mock_settings)
    response = _make_mock_response(
        json_data={"correlationId": "abc-123", "token": "my-token-value"}
    )

    with patch("httpx.AsyncClient") as mock_client_cls:
        mock_client_cls.return_value = _patch_httpx(response)

        token = await svc.authenticate()
        assert token == "my-token-value"
        assert svc._token == "my-token-value"

"""
API роуты
"""
import json
import logging
import secrets
import time
from fastapi import APIRouter, Depends, HTTPException, Request, status

logger = logging.getLogger(__name__)
from sqlalchemy.orm import Session
from sqlalchemy import func as sa_func
from typing import List as TypingList
from database.connection import get_db
from database.models import (
    MenuItem, User, IikoSettings, Order, WebhookEvent, ApiLog, BonusTransaction,
)
from app.schemas import (
    UserCreate, UserLogin, UserResponse, Token, RoleUpdate,
    IikoSettingsCreate, IikoSettingsResponse, IikoSettingsUpdate,
    OrderResponse, OrderCreate, PasswordChange,
    WebhookEventResponse, ApiLogResponse,
    CustomerSearch, CustomerCreate, LoyaltyBalanceOperation,
    AdminUserCreate, BonusTransactionResponse,
)
from app.auth import (
    get_password_hash, verify_password, create_access_token,
    get_current_user, require_role,
)
from app.iiko_service import IikoService
from config.settings import settings

_app_start_time = time.time()

api_router = APIRouter()

# Константы для типов событий вебхуков iiko
WEBHOOK_EVENT_ORDER_CHANGED = "OrderChanged"
WEBHOOK_EVENT_DELIVERY_ORDER_CHANGED = "DeliveryOrderChanged"
WEBHOOK_EVENT_ORDER = "order"


# ─── Auth ────────────────────────────────────────────────────────────────
@api_router.post("/auth/register", tags=["auth"], response_model=UserResponse)
async def register(user_in: UserCreate, db: Session = Depends(get_db)):
    """Регистрация нового пользователя"""
    if db.query(User).filter(User.email == user_in.email).first():
        raise HTTPException(status_code=400, detail="Email уже зарегистрирован")
    if db.query(User).filter(User.username == user_in.username).first():
        raise HTTPException(status_code=400, detail="Имя пользователя занято")
    user = User(
        email=user_in.email,
        username=user_in.username,
        hashed_password=get_password_hash(user_in.password),
        role="viewer",
    )
    db.add(user)
    db.commit()
    db.refresh(user)
    return user


@api_router.post("/auth/login", tags=["auth"], response_model=Token)
async def login(form: UserLogin, db: Session = Depends(get_db)):
    """Авторизация пользователя (получение JWT)"""
    user = db.query(User).filter(User.username == form.username).first()
    
    # Bootstrap: Create default admin user only on first-time setup
    # (no admin-role users exist yet and credentials match defaults)
    if not user and form.username == settings.DEFAULT_ADMIN_USERNAME:
        has_any_admin = db.query(User).filter(User.role == "admin").first() is not None
        if not has_any_admin:
            default_password = str(settings.DEFAULT_ADMIN_PASSWORD)
            if secrets.compare_digest(form.password, default_password):
                try:
                    user = User(
                        email=settings.DEFAULT_ADMIN_EMAIL,
                        username=settings.DEFAULT_ADMIN_USERNAME,
                        hashed_password=get_password_hash(default_password),
                        role="admin",
                        is_active=True,
                        is_superuser=True,
                    )
                    db.add(user)
                    db.commit()
                    db.refresh(user)
                except Exception:
                    db.rollback()
                    user = None

    # If the default admin user exists but has a corrupted/invalid hash,
    # repair it using the known default password.
    if user is not None and form.username == settings.DEFAULT_ADMIN_USERNAME:
        hash_corrupted = False
        try:
            verify_password(form.password, user.hashed_password)
        except Exception:
            hash_corrupted = True
        if hash_corrupted:
            default_password = str(settings.DEFAULT_ADMIN_PASSWORD)
            if secrets.compare_digest(form.password, default_password):
                user.hashed_password = get_password_hash(default_password)
                try:
                    db.commit()
                    db.refresh(user)
                except Exception:
                    db.rollback()

    try:
        password_valid = verify_password(form.password, user.hashed_password) if user else False
    except Exception:
        password_valid = False
    if not user or not password_valid:
        raise HTTPException(status_code=401, detail="Неверные учетные данные")
    if not user.is_active:
        raise HTTPException(status_code=403, detail="Пользователь деактивирован")
    token = create_access_token(data={"sub": user.username, "role": user.role})
    return {"access_token": token}


@api_router.get("/auth/me", tags=["auth"], response_model=UserResponse)
async def me(current_user: User = Depends(get_current_user)):
    """Текущий пользователь"""
    return current_user


@api_router.put("/auth/password", tags=["auth"])
async def change_password(
    data: PasswordChange,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db),
):
    """Смена пароля"""
    if not verify_password(data.old_password, current_user.hashed_password):
        raise HTTPException(status_code=400, detail="Неверный текущий пароль")
    current_user.hashed_password = get_password_hash(data.new_password)
    db.commit()
    return {"detail": "Пароль изменен"}


# ─── Users (admin) ──────────────────────────────────────────────────────
@api_router.get("/users", tags=["users"], response_model=TypingList[UserResponse])
async def get_users(
    skip: int = 0,
    limit: int = 100,
    db: Session = Depends(get_db),
    _current_user: User = Depends(require_role("admin")),
):
    """Получить список пользователей (только admin)"""
    return db.query(User).offset(skip).limit(limit).all()


@api_router.put("/users/{user_id}/role", tags=["users"], response_model=UserResponse)
async def update_user_role(
    user_id: int,
    role_update: RoleUpdate,
    db: Session = Depends(get_db),
    _current_user: User = Depends(require_role("admin")),
):
    """Изменить роль пользователя (только admin)"""
    user = db.query(User).filter(User.id == user_id).first()
    if not user:
        raise HTTPException(status_code=404, detail="Пользователь не найден")
    user.role = role_update.role
    db.commit()
    db.refresh(user)
    return user


# ─── Menu ────────────────────────────────────────────────────────────────
@api_router.get("/menu", tags=["menu"])
async def get_menu_items(
    skip: int = 0,
    limit: int = 100,
    db: Session = Depends(get_db),
):
    """Получить список элементов меню"""
    items = db.query(MenuItem).filter(MenuItem.is_available == True).offset(skip).limit(limit).all()
    return {"items": items, "total": len(items)}


@api_router.post("/menu", tags=["menu"], status_code=status.HTTP_201_CREATED)
async def create_menu_item(
    name: str,
    description: str = None,
    price: int = 0,
    category: str = None,
    db: Session = Depends(get_db),
    _current_user: User = Depends(require_role("manager")),
):
    """Создать новый элемент меню (manager+)"""
    item = MenuItem(name=name, description=description, price=price, category=category)
    db.add(item)
    db.commit()
    db.refresh(item)
    return item


@api_router.get("/menu/{item_id}", tags=["menu"])
async def get_menu_item(item_id: int, db: Session = Depends(get_db)):
    """Получить элемент меню по ID"""
    item = db.query(MenuItem).filter(MenuItem.id == item_id).first()
    if not item:
        raise HTTPException(status_code=404, detail="Элемент не найден")
    return item


# ─── iiko Settings ───────────────────────────────────────────────────────
@api_router.get("/iiko/settings", tags=["iiko"], response_model=TypingList[IikoSettingsResponse])
async def get_iiko_settings(
    db: Session = Depends(get_db),
    _current_user: User = Depends(require_role("admin")),
):
    """Получить настройки интеграции iiko"""
    return db.query(IikoSettings).all()


@api_router.post("/iiko/settings", tags=["iiko"], response_model=IikoSettingsResponse, status_code=201)
async def create_iiko_settings(
    data: IikoSettingsCreate,
    db: Session = Depends(get_db),
    _current_user: User = Depends(require_role("admin")),
):
    """Создать настройку интеграции iiko"""
    webhook_secret = secrets.token_urlsafe(32)
    rec = IikoSettings(
        api_key=data.api_key,
        api_url=data.api_url,
        organization_id=data.organization_id,
        organization_name=data.organization_name,
        webhook_secret=webhook_secret,
    )
    if settings.WEBHOOK_BASE_URL:
        rec.webhook_url = f"{settings.WEBHOOK_BASE_URL.rstrip('/')}{settings.API_V1_PREFIX}/webhooks/iiko"
    db.add(rec)
    db.commit()
    db.refresh(rec)
    return rec


@api_router.put("/iiko/settings/{setting_id}", tags=["iiko"], response_model=IikoSettingsResponse)
async def update_iiko_settings(
    setting_id: int,
    data: IikoSettingsUpdate,
    db: Session = Depends(get_db),
    _current_user: User = Depends(require_role("admin")),
):
    """Обновить настройку интеграции iiko"""
    rec = db.query(IikoSettings).filter(IikoSettings.id == setting_id).first()
    if not rec:
        raise HTTPException(status_code=404, detail="Настройка не найдена")
    if data.api_key is not None:
        rec.api_key = data.api_key
    if data.api_url is not None:
        rec.api_url = data.api_url
    if data.organization_id is not None:
        rec.organization_id = data.organization_id
    if data.organization_name is not None:
        rec.organization_name = data.organization_name
    db.commit()
    db.refresh(rec)
    return rec


@api_router.delete("/iiko/settings/{setting_id}", tags=["iiko"])
async def delete_iiko_settings(
    setting_id: int,
    db: Session = Depends(get_db),
    _current_user: User = Depends(require_role("admin")),
):
    """Удалить настройку интеграции iiko"""
    rec = db.query(IikoSettings).filter(IikoSettings.id == setting_id).first()
    if not rec:
        raise HTTPException(status_code=404, detail="Настройка не найдена")
    db.delete(rec)
    db.commit()
    return {"status": "ok", "message": "Настройка удалена"}


@api_router.post("/iiko/test-connection", tags=["iiko"])
async def test_iiko_connection(
    setting_id: int,
    db: Session = Depends(get_db),
    _current_user: User = Depends(require_role("admin")),
):
    """Тестовое подключение к iiko API"""
    rec = db.query(IikoSettings).filter(IikoSettings.id == setting_id).first()
    if not rec:
        raise HTTPException(status_code=404, detail="Настройка не найдена")
    try:
        svc = IikoService(db, rec)
        token = await svc.authenticate()
        return {"status": "ok", "message": "Подключение успешно! Токен получен."}
    except HTTPException:
        raise
    except Exception as e:
        error_msg = str(e)
        raise HTTPException(status_code=502, detail=f"{error_msg}")


@api_router.post("/iiko/diagnose", tags=["iiko"])
async def diagnose_iiko_connection(
    setting_id: int,
    db: Session = Depends(get_db),
    _current_user: User = Depends(require_role("admin")),
):
    """Диагностика подключения к iiko API — пошаговая проверка настроек и соединения"""
    checks = []

    # 1. Check that the settings record exists
    rec = db.query(IikoSettings).filter(IikoSettings.id == setting_id).first()
    if not rec:
        return {"status": "error", "checks": [{"step": "settings", "ok": False, "detail": "Настройка с данным ID не найдена"}]}
    checks.append({"step": "settings_found", "ok": True, "detail": f"Настройка id={setting_id} найдена"})

    # 2. Validate API URL
    api_url = (rec.api_url or "").strip()
    if not api_url:
        checks.append({"step": "api_url", "ok": False, "detail": "api_url не задан"})
        return {"status": "error", "checks": checks}
    if not api_url.startswith("https://"):
        checks.append({"step": "api_url", "ok": False, "detail": f"api_url должен начинаться с https:// (текущий: {api_url})"})
        return {"status": "error", "checks": checks}
    checks.append({"step": "api_url", "ok": True, "detail": f"api_url = {api_url}"})

    # 3. Validate API key format
    api_key = (rec.api_key or "").strip()
    if not api_key:
        checks.append({"step": "api_key_format", "ok": False, "detail": "api_key (apiLogin) пуст. Укажите ключ API."})
        return {"status": "error", "checks": checks}
    if len(api_key) < 10:
        checks.append({"step": "api_key_format", "ok": False, "detail": f"api_key слишком короткий ({len(api_key)} символов). Обычно ключ iiko содержит 30+ символов."})
        return {"status": "error", "checks": checks}
    if api_key != rec.api_key:
        checks.append({"step": "api_key_format", "ok": True, "detail": f"api_key содержит лишние пробелы (удалены автоматически). Ключ: {len(api_key)} символов, начинается с '{api_key[:4]}...'"})
    else:
        checks.append({"step": "api_key_format", "ok": True, "detail": f"api_key задан ({len(api_key)} символов, начинается с '{api_key[:4]}...')"})

    # 4. Network connectivity check
    import httpx
    try:
        async with httpx.AsyncClient(timeout=10.0) as client:
            probe = await client.get(api_url.rstrip("/"))
        checks.append({"step": "network", "ok": True, "detail": f"Сервер {api_url} доступен (HTTP {probe.status_code})"})
    except httpx.TimeoutException:
        checks.append({"step": "network", "ok": False, "detail": f"Тайм-аут подключения к {api_url}. Проверьте сеть и DNS."})
        return {"status": "error", "checks": checks}
    except Exception as e:
        checks.append({"step": "network", "ok": False, "detail": f"Не удалось подключиться к {api_url}: {e}"})
        return {"status": "error", "checks": checks}

    # 5. Authentication attempt
    try:
        svc = IikoService(db, rec)
        token = await svc.authenticate()
        if token:
            checks.append({"step": "auth", "ok": True, "detail": "Аутентификация успешна! Токен получен."})
        else:
            checks.append({"step": "auth", "ok": False, "detail": "iiko API вернул пустой токен. Проверьте ключ в личном кабинете iiko."})
            return {"status": "error", "checks": checks}
    except Exception as e:
        checks.append({"step": "auth", "ok": False, "detail": f"Ошибка аутентификации: {e}"})
        return {"status": "error", "checks": checks}

    # 6. Try fetching organizations (quick API sanity check)
    try:
        orgs = await svc.get_organizations()
        org_list = orgs.get("organizations", [])
        checks.append({"step": "organizations", "ok": True, "detail": f"Получено организаций: {len(org_list)}"})
    except Exception as e:
        checks.append({"step": "organizations", "ok": False, "detail": f"Ошибка получения организаций: {e}"})

    all_ok = all(c["ok"] for c in checks)
    return {"status": "ok" if all_ok else "warning", "checks": checks}


# ─── iiko Data ───────────────────────────────────────────────────────────
@api_router.post("/iiko/organizations", tags=["iiko"])
async def get_iiko_organizations(
    setting_id: int,
    db: Session = Depends(get_db),
    _current_user: User = Depends(require_role("operator")),
):
    """Получить список организаций из iiko"""
    rec = db.query(IikoSettings).filter(IikoSettings.id == setting_id).first()
    if not rec:
        raise HTTPException(status_code=404, detail="Настройка не найдена")
    svc = IikoService(db, rec)
    return await svc.get_organizations()


@api_router.post("/iiko/organizations-by-key", tags=["iiko"])
async def get_iiko_organizations_by_key(
    request: Request,
    db: Session = Depends(get_db),
    _current_user: User = Depends(require_role("operator")),
):
    """Получить список организаций из iiko по API ключу (без сохранённой настройки)"""
    body = await request.json()
    api_key = (body.get("api_key") or "").strip()
    api_url = (body.get("api_url") or "https://api-ru.iiko.services/api/1").strip()

    if not api_key:
        raise HTTPException(status_code=400, detail="API ключ (apiLogin) обязателен")

    if not api_url.startswith("https://"):
        raise HTTPException(status_code=400, detail="API URL должен начинаться с https://")

    # Create a temporary settings-like object for IikoService
    temp_settings = IikoSettings(
        api_key=api_key,
        api_url=api_url,
    )
    svc = IikoService(db, temp_settings)
    await svc.authenticate(api_key=api_key)
    return await svc.get_organizations()


@api_router.post("/iiko/menu", tags=["iiko"])
async def get_iiko_menu(
    setting_id: int,
    organization_id: str,
    db: Session = Depends(get_db),
    _current_user: User = Depends(require_role("operator")),
):
    """Получить меню из iiko"""
    rec = db.query(IikoSettings).filter(IikoSettings.id == setting_id).first()
    if not rec:
        raise HTTPException(status_code=404, detail="Настройка не найдена")
    svc = IikoService(db, rec)
    return await svc.get_menu(organization_id)


# ─── Sync ────────────────────────────────────────────────────────────────
@api_router.post("/iiko/sync-menu", tags=["iiko"])
async def sync_menu_from_iiko(
    setting_id: int,
    organization_id: str,
    db: Session = Depends(get_db),
    _current_user: User = Depends(require_role("manager")),
):
    """Синхронизировать меню из iiko в локальную БД"""
    rec = db.query(IikoSettings).filter(IikoSettings.id == setting_id).first()
    if not rec:
        raise HTTPException(status_code=404, detail="Настройка не найдена")
    svc = IikoService(db, rec)
    menu_data = await svc.get_menu(organization_id)

    synced = 0
    products = menu_data.get("products", [])
    for product in products:
        existing = db.query(MenuItem).filter(MenuItem.name == product.get("name")).first()
        price = 0
        sizes = product.get("sizePrices", [])
        if sizes:
            price_val = sizes[0].get("price", {})
            if isinstance(price_val, dict):
                price = int(float(price_val.get("currentPrice", 0)) * 100)
            else:
                price = int(float(price_val) * 100)
        if existing:
            existing.price = price
            existing.description = product.get("description", "")
            existing.is_available = True
        else:
            db.add(MenuItem(
                name=product.get("name", ""),
                description=product.get("description", ""),
                price=price,
                category=product.get("groupId", ""),
                is_available=True,
            ))
        synced += 1
    db.commit()
    return {"detail": f"Синхронизировано {synced} позиций"}


# ─── Orders ──────────────────────────────────────────────────────────────
@api_router.get("/orders", tags=["orders"], response_model=TypingList[OrderResponse])
async def get_orders(
    status_filter: str = None,
    skip: int = 0,
    limit: int = 50,
    db: Session = Depends(get_db),
    _current_user: User = Depends(require_role("operator")),
):
    """Получить список заказов (мониторинг)"""
    q = db.query(Order)
    if status_filter:
        q = q.filter(Order.status == status_filter)
    return q.order_by(Order.created_at.desc()).offset(skip).limit(limit).all()


@api_router.post("/orders", tags=["orders"], response_model=OrderResponse, status_code=201)
async def create_order(
    order_in: OrderCreate,
    setting_id: int,
    db: Session = Depends(get_db),
    _current_user: User = Depends(require_role("operator")),
):
    """Создать заказ (и отправить в iiko)"""
    rec = db.query(IikoSettings).filter(IikoSettings.id == setting_id).first()
    if not rec:
        raise HTTPException(status_code=404, detail="Настройка iiko не найдена")

    order = Order(
        organization_id=order_in.organization_id,
        customer_name=order_in.customer_name,
        customer_phone=order_in.customer_phone,
        delivery_address=order_in.delivery_address,
        order_data=json.dumps(order_in.items),
        status="new",
    )
    db.add(order)
    db.commit()
    db.refresh(order)

    # Отправить в iiko
    try:
        svc = IikoService(db, rec)
        iiko_resp = await svc.create_order(order_in.organization_id, {"items": order_in.items})
        iiko_id = iiko_resp.get("orderInfo", {}).get("id")
        if iiko_id:
            order.iiko_order_id = iiko_id
            order.status = "confirmed"
            db.commit()
            db.refresh(order)
    except Exception as e:
        logger.warning("Failed to send order %d to iiko: %s", order.id, e)

    return order


@api_router.get("/orders/{order_id}", tags=["orders"], response_model=OrderResponse)
async def get_order(
    order_id: int,
    db: Session = Depends(get_db),
    _current_user: User = Depends(require_role("operator")),
):
    """Получить заказ по ID"""
    order = db.query(Order).filter(Order.id == order_id).first()
    if not order:
        raise HTTPException(status_code=404, detail="Заказ не найден")
    return order


# ─── Webhooks ────────────────────────────────────────────────────────────
@api_router.post("/webhooks/iiko", tags=["webhooks"])
async def iiko_webhook(request: Request, db: Session = Depends(get_db)):
    """Прием вебхуков от iiko"""
    # Проверка токена авторизации вебхука (защита от поддельных запросов)
    auth_header = request.headers.get("Authorization") or request.headers.get("authToken") or ""
    stored_secrets = [
        s.webhook_secret
        for s in db.query(IikoSettings).filter(IikoSettings.webhook_secret.isnot(None)).all()
    ]
    if stored_secrets:
        token_valid = any(secrets.compare_digest(auth_header, s) for s in stored_secrets)
        if not auth_header or not token_valid:
            logger.warning("Webhook rejected: invalid auth token")
            raise HTTPException(status_code=401, detail="Invalid webhook auth token")

    body = await request.body()
    try:
        payload = json.loads(body)
    except Exception as e:
        logger.warning("Failed to parse webhook JSON: %s", e)
        payload = {"raw": body.decode("utf-8", errors="replace"), "parse_error": str(e)}

    event_type = payload.get("eventType") or payload.get("type") or "unknown"
    logger.info("Получено событие от iiko webhook: %s", event_type)

    event = WebhookEvent(
        event_type=event_type,
        payload=json.dumps(payload, ensure_ascii=False),
        processed=False,
    )
    db.add(event)
    db.commit()

    # Обработка событий по статусам заказов (eventInfo format from iiko webhooks)
    if event_type in ("DeliveryOrderUpdate", "DeliveryOrderError"):
        event_info = payload.get("eventInfo", {})
        order_id = event_info.get("id")
        new_status = event_info.get("status", "")
        order_data = event_info
        if order_id:
            order = db.query(Order).filter(Order.iiko_order_id == order_id).first()
            if order:
                order.status = new_status
                order.order_data = json.dumps(order_data, ensure_ascii=False)
                logger.info("Обновлен статус заказа в БД: %s -> %s", order_id, new_status)
            else:
                logger.info("Заказ %s не найден в БД, событие просто залогировано", order_id)
        event.processed = True
        db.commit()

    # Обработка событий в формате order/data (OrderChanged, etc.)
    elif event_type in (WEBHOOK_EVENT_ORDER_CHANGED, WEBHOOK_EVENT_DELIVERY_ORDER_CHANGED, WEBHOOK_EVENT_ORDER):
        order_data = payload.get("order") or payload.get("data") or {}
        order_id = order_data.get("id") or order_data.get("orderId")
        order_status = order_data.get("status") or order_data.get("orderStatus")
        if order_id:
            existing_order = db.query(Order).filter(Order.iiko_order_id == order_id).first()
            if existing_order:
                existing_order.status = order_status or "unknown"
                existing_order.order_data = json.dumps(order_data, ensure_ascii=False)
                logger.info("Обновлен статус заказа в БД: %s -> %s", order_id, order_status)
            else:
                logger.info("Заказ %s не найден в БД, событие просто залогировано", order_id)
        event.processed = True
        db.commit()

    # Обработка дополнительных типов событий
    elif event_type in ("StopListUpdate", "PersonalShift", "ReserveUpdate", "ReserveError", "TableOrderError"):
        logger.info("Обработано событие типа %s", event_type)
        event.processed = True
        db.commit()

    return {"status": "ok"}


@api_router.get("/webhooks/events", tags=["webhooks"], response_model=TypingList[WebhookEventResponse])
async def get_webhook_events(
    skip: int = 0,
    limit: int = 50,
    db: Session = Depends(get_db),
    _current_user: User = Depends(require_role("manager")),
):
    """Получить историю вебхук-событий"""
    return db.query(WebhookEvent).order_by(WebhookEvent.created_at.desc()).offset(skip).limit(limit).all()


# ─── API Logs ────────────────────────────────────────────────────────────
@api_router.get("/logs", tags=["logs"], response_model=TypingList[ApiLogResponse])
async def get_api_logs(
    skip: int = 0,
    limit: int = 50,
    db: Session = Depends(get_db),
    _current_user: User = Depends(require_role("admin")),
):
    """Получить журнал запросов к iiko API"""
    return db.query(ApiLog).order_by(ApiLog.created_at.desc()).offset(skip).limit(limit).all()


# ─── Server Status / Maintenance ─────────────────────────────────────────
@api_router.get("/status", tags=["maintenance"])
async def get_server_status(db: Session = Depends(get_db)):
    """Статус сервера и компонентов"""
    uptime = int(time.time() - _app_start_time)
    # Check DB connection
    db_ok = True
    try:
        db.execute(sa_func.now())
    except Exception:
        db_ok = False

    # Counts
    total_orders = db.query(sa_func.count(Order.id)).scalar() or 0
    total_webhooks = db.query(sa_func.count(WebhookEvent.id)).scalar() or 0
    total_logs = db.query(sa_func.count(ApiLog.id)).scalar() or 0
    total_users = db.query(sa_func.count(User.id)).scalar() or 0
    total_settings = db.query(sa_func.count(IikoSettings.id)).scalar() or 0

    # Recent errors from API logs
    recent_errors = (
        db.query(ApiLog)
        .filter(ApiLog.response_status >= 400)
        .order_by(ApiLog.created_at.desc())
        .limit(10)
        .all()
    )
    errors = [
        {
            "id": e.id,
            "method": e.method,
            "url": e.url,
            "status": e.response_status,
            "duration_ms": e.duration_ms,
            "created_at": str(e.created_at) if e.created_at else None,
        }
        for e in recent_errors
    ]

    return {
        "server": {
            "status": "running",
            "uptime_seconds": uptime,
            "version": "1.0.0",
        },
        "components": {
            "database": {"status": "ok" if db_ok else "error"},
            "iiko_api": {"configured": total_settings > 0},
        },
        "stats": {
            "orders": total_orders,
            "webhook_events": total_webhooks,
            "api_logs": total_logs,
            "users": total_users,
            "iiko_settings": total_settings,
        },
        "recent_errors": errors,
    }


@api_router.post("/iiko/terminal-groups", tags=["iiko"])
async def get_iiko_terminal_groups(
    setting_id: int,
    organization_id: str,
    db: Session = Depends(get_db),
    _current_user: User = Depends(require_role("operator")),
):
    """Получить терминальные группы (точки/заведения)"""
    rec = db.query(IikoSettings).filter(IikoSettings.id == setting_id).first()
    if not rec:
        raise HTTPException(status_code=404, detail="Настройка не найдена")
    svc = IikoService(db, rec)
    return await svc.get_terminal_groups([organization_id])


@api_router.post("/iiko/payment-types", tags=["iiko"])
async def get_iiko_payment_types(
    setting_id: int,
    organization_id: str,
    db: Session = Depends(get_db),
    _current_user: User = Depends(require_role("operator")),
):
    """Получить доступные типы оплат"""
    rec = db.query(IikoSettings).filter(IikoSettings.id == setting_id).first()
    if not rec:
        raise HTTPException(status_code=404, detail="Настройка не найдена")
    svc = IikoService(db, rec)
    return await svc.get_payment_types([organization_id])


@api_router.post("/iiko/couriers", tags=["iiko"])
async def get_iiko_couriers(
    setting_id: int,
    organization_id: str,
    db: Session = Depends(get_db),
    _current_user: User = Depends(require_role("operator")),
):
    """Получить список курьеров"""
    rec = db.query(IikoSettings).filter(IikoSettings.id == setting_id).first()
    if not rec:
        raise HTTPException(status_code=404, detail="Настройка не найдена")
    svc = IikoService(db, rec)
    return await svc.get_couriers(organization_id)


@api_router.post("/iiko/order-types", tags=["iiko"])
async def get_iiko_order_types(
    setting_id: int,
    organization_id: str,
    db: Session = Depends(get_db),
    _current_user: User = Depends(require_role("operator")),
):
    """Получить типы заказов"""
    rec = db.query(IikoSettings).filter(IikoSettings.id == setting_id).first()
    if not rec:
        raise HTTPException(status_code=404, detail="Настройка не найдена")
    svc = IikoService(db, rec)
    return await svc.get_order_types([organization_id])


@api_router.post("/iiko/discount-types", tags=["iiko"])
async def get_iiko_discount_types(
    setting_id: int,
    organization_id: str,
    db: Session = Depends(get_db),
    _current_user: User = Depends(require_role("operator")),
):
    """Получить типы скидок"""
    rec = db.query(IikoSettings).filter(IikoSettings.id == setting_id).first()
    if not rec:
        raise HTTPException(status_code=404, detail="Настройка не найдена")
    svc = IikoService(db, rec)
    return await svc.get_discount_types([organization_id])


@api_router.post("/iiko/register-webhook", tags=["iiko"])
async def register_iiko_webhook(
    setting_id: int,
    domain: str = None,
    webhook_url: str = None,
    db: Session = Depends(get_db),
    _current_user: User = Depends(require_role("admin")),
):
    """Зарегистрировать вебхук в iiko и сохранить настройки.

    Принимает домен (например, example.com) и автоматически формирует
    URL вебхука и токен авторизации, затем регистрирует их в iiko Cloud.
    """
    rec = db.query(IikoSettings).filter(IikoSettings.id == setting_id).first()
    if not rec:
        raise HTTPException(status_code=404, detail="Настройка не найдена")

    if not rec.organization_id:
        raise HTTPException(status_code=400, detail="organization_id не задан в настройках iiko")

    # Автоматически формировать URL вебхука из домена
    if domain:
        domain = domain.strip().rstrip("/")
        # Убрать протокол, если пользователь его добавил
        if domain.startswith("http://") or domain.startswith("https://"):
            domain = domain.split("://", 1)[1]
        webhook_url = f"https://{domain}{settings.API_V1_PREFIX}/webhooks/iiko"
    elif not webhook_url:
        if not settings.WEBHOOK_BASE_URL:
            raise HTTPException(status_code=400, detail="Не указан домен и не задан WEBHOOK_BASE_URL")
        webhook_url = f"{settings.WEBHOOK_BASE_URL.rstrip('/')}{settings.API_V1_PREFIX}/webhooks/iiko"

    # Автоматически генерировать токен авторизации для проверки входящих вебхуков
    auth_token = secrets.token_urlsafe(32)

    svc = IikoService(db, rec)
    try:
        result = await svc.register_webhook(rec.organization_id, webhook_url, auth_token)
    except Exception as e:
        raise HTTPException(status_code=502, detail=f"Ошибка регистрации вебхука: {str(e)}")

    rec.webhook_url = webhook_url
    rec.webhook_secret = auth_token
    db.commit()
    db.refresh(rec)

    return {
        "status": "ok",
        "webhook_url": webhook_url,
        "auth_token": auth_token,
        "iiko_response": result,
    }


@api_router.post("/iiko/stop-lists", tags=["iiko"])
async def get_iiko_stop_lists(
    setting_id: int,
    organization_id: str,
    db: Session = Depends(get_db),
    _current_user: User = Depends(require_role("operator")),
):
    """Получить стоп-листы"""
    rec = db.query(IikoSettings).filter(IikoSettings.id == setting_id).first()
    if not rec:
        raise HTTPException(status_code=404, detail="Настройка не найдена")
    svc = IikoService(db, rec)
    return await svc.get_stop_lists(organization_id)


@api_router.post("/iiko/cancel-causes", tags=["iiko"])
async def get_iiko_cancel_causes(
    setting_id: int,
    organization_id: str,
    db: Session = Depends(get_db),
    _current_user: User = Depends(require_role("operator")),
):
    """Получить причины отмены заказов"""
    rec = db.query(IikoSettings).filter(IikoSettings.id == setting_id).first()
    if not rec:
        raise HTTPException(status_code=404, detail="Настройка не найдена")
    svc = IikoService(db, rec)
    return await svc.get_cancel_causes([organization_id])


@api_router.post("/iiko/removal-types", tags=["iiko"])
async def get_iiko_removal_types(
    setting_id: int,
    organization_id: str,
    db: Session = Depends(get_db),
    _current_user: User = Depends(require_role("operator")),
):
    """Получить типы удалений позиций"""
    rec = db.query(IikoSettings).filter(IikoSettings.id == setting_id).first()
    if not rec:
        raise HTTPException(status_code=404, detail="Настройка не найдена")
    svc = IikoService(db, rec)
    return await svc.get_removal_types([organization_id])


@api_router.post("/iiko/tips-types", tags=["iiko"])
async def get_iiko_tips_types(
    setting_id: int,
    organization_id: str,
    db: Session = Depends(get_db),
    _current_user: User = Depends(require_role("operator")),
):
    """Получить типы чаевых"""
    rec = db.query(IikoSettings).filter(IikoSettings.id == setting_id).first()
    if not rec:
        raise HTTPException(status_code=404, detail="Настройка не найдена")
    svc = IikoService(db, rec)
    return await svc.get_tips_types([organization_id])


@api_router.post("/iiko/delivery-restrictions", tags=["iiko"])
async def get_iiko_delivery_restrictions(
    setting_id: int,
    organization_id: str,
    db: Session = Depends(get_db),
    _current_user: User = Depends(require_role("operator")),
):
    """Получить ограничения доставки"""
    rec = db.query(IikoSettings).filter(IikoSettings.id == setting_id).first()
    if not rec:
        raise HTTPException(status_code=404, detail="Настройка не найдена")
    svc = IikoService(db, rec)
    return await svc.get_delivery_restrictions([organization_id])


@api_router.post("/iiko/webhook-settings", tags=["iiko"])
async def get_iiko_webhook_settings(
    setting_id: int,
    organization_id: str,
    db: Session = Depends(get_db),
    _current_user: User = Depends(require_role("admin")),
):
    """Получить текущие настройки вебхука из iiko"""
    rec = db.query(IikoSettings).filter(IikoSettings.id == setting_id).first()
    if not rec:
        raise HTTPException(status_code=404, detail="Настройка не найдена")
    svc = IikoService(db, rec)
    try:
        return await svc.get_webhook_settings(organization_id)
    except Exception as e:
        raise HTTPException(status_code=502, detail=f"Ошибка получения настроек вебхука: {str(e)}")


@api_router.post("/iiko/deliveries", tags=["iiko"])
async def get_iiko_deliveries(
    setting_id: int,
    organization_id: str,
    statuses: str = "Unconfirmed,WaitCooking,ReadyForCooking,CookingStarted,CookingCompleted,Waiting,OnWay,Delivered,Closed,Cancelled",
    db: Session = Depends(get_db),
    _current_user: User = Depends(require_role("operator")),
):
    """Получить заказы доставки из iiko по статусам"""
    rec = db.query(IikoSettings).filter(IikoSettings.id == setting_id).first()
    if not rec:
        raise HTTPException(status_code=404, detail="Настройка не найдена")
    svc = IikoService(db, rec)
    status_list = [s.strip() for s in statuses.split(",") if s.strip()]
    try:
        return await svc.get_deliveries_by_statuses(organization_id, status_list)
    except Exception as e:
        raise HTTPException(status_code=502, detail=f"Ошибка получения заказов: {str(e)}")


# ─── Loyalty / iikoCard ──────────────────────────────────────────────────
@api_router.post("/iiko/loyalty/programs", tags=["loyalty"])
async def get_loyalty_programs(
    setting_id: int,
    organization_id: str,
    db: Session = Depends(get_db),
    _current_user: User = Depends(require_role("operator")),
):
    """Получить список программ лояльности (бонусных программ)"""
    rec = db.query(IikoSettings).filter(IikoSettings.id == setting_id).first()
    if not rec:
        raise HTTPException(status_code=404, detail="Настройка не найдена")
    svc = IikoService(db, rec)
    try:
        return await svc.get_loyalty_programs(organization_id)
    except Exception as e:
        raise HTTPException(status_code=502, detail=f"Ошибка получения программ лояльности: {str(e)}")


@api_router.post("/iiko/loyalty/customer-info", tags=["loyalty"])
async def get_loyalty_customer_info(
    data: CustomerSearch,
    setting_id: int,
    db: Session = Depends(get_db),
    _current_user: User = Depends(require_role("operator")),
):
    """Получить информацию о госте программы лояльности"""
    rec = db.query(IikoSettings).filter(IikoSettings.id == setting_id).first()
    if not rec:
        raise HTTPException(status_code=404, detail="Настройка не найдена")
    svc = IikoService(db, rec)
    try:
        return await svc.get_customer_info(
            organization_id=data.organization_id,
            customer_id=data.customer_id,
            phone=data.phone,
            card_track=data.card_track,
            card_number=data.card_number,
            email=data.email,
        )
    except Exception as e:
        raise HTTPException(status_code=502, detail=f"Ошибка получения данных гостя: {str(e)}")


@api_router.post("/iiko/loyalty/customer", tags=["loyalty"])
async def create_or_update_loyalty_customer(
    data: CustomerCreate,
    setting_id: int,
    db: Session = Depends(get_db),
    _current_user: User = Depends(require_role("manager")),
):
    """Создать или обновить гостя в программе лояльности"""
    rec = db.query(IikoSettings).filter(IikoSettings.id == setting_id).first()
    if not rec:
        raise HTTPException(status_code=404, detail="Настройка не найдена")
    svc = IikoService(db, rec)
    try:
        return await svc.create_or_update_customer(
            organization_id=data.organization_id,
            name=data.name,
            phone=data.phone,
            email=data.email,
            card_track=data.card_track,
            card_number=data.card_number,
            birthday=data.birthday,
        )
    except Exception as e:
        raise HTTPException(status_code=502, detail=f"Ошибка создания/обновления гостя: {str(e)}")


@api_router.post("/iiko/loyalty/balance", tags=["loyalty"])
async def get_loyalty_balance(
    setting_id: int,
    organization_id: str,
    customer_id: str,
    db: Session = Depends(get_db),
    _current_user: User = Depends(require_role("operator")),
):
    """Получить баланс бонусов гостя"""
    rec = db.query(IikoSettings).filter(IikoSettings.id == setting_id).first()
    if not rec:
        raise HTTPException(status_code=404, detail="Настройка не найдена")
    svc = IikoService(db, rec)
    try:
        return await svc.get_customer_balance(organization_id, customer_id)
    except Exception as e:
        raise HTTPException(status_code=502, detail=f"Ошибка получения баланса: {str(e)}")


def _save_bonus_transaction(db: Session, data: LoyaltyBalanceOperation, operation_type: str, username: str):
    tx = BonusTransaction(
        organization_id=data.organization_id, customer_id=data.customer_id,
        wallet_id=data.wallet_id, operation_type=operation_type, amount=data.amount,
        comment=data.comment or "", performed_by=username,
    )
    db.add(tx)
    db.commit()


@api_router.post("/iiko/loyalty/topup", tags=["loyalty"])
async def topup_loyalty(
    data: LoyaltyBalanceOperation,
    setting_id: int,
    db: Session = Depends(get_db),
    _current_user: User = Depends(require_role("manager")),
):
    """Пополнить бонусный баланс гостя"""
    rec = db.query(IikoSettings).filter(IikoSettings.id == setting_id).first()
    if not rec:
        raise HTTPException(status_code=404, detail="Настройка не найдена")
    svc = IikoService(db, rec)
    try:
        result = await svc.topup_loyalty_balance(
            data.organization_id, data.customer_id,
            data.wallet_id, data.amount, data.comment or "",
        )
        _save_bonus_transaction(db, data, "topup", _current_user.username)
        return result
    except Exception as e:
        raise HTTPException(status_code=502, detail=f"Ошибка пополнения баланса: {str(e)}")


@api_router.post("/iiko/loyalty/withdraw", tags=["loyalty"])
async def withdraw_loyalty(
    data: LoyaltyBalanceOperation,
    setting_id: int,
    db: Session = Depends(get_db),
    _current_user: User = Depends(require_role("manager")),
):
    """Списать бонусы с баланса гостя"""
    rec = db.query(IikoSettings).filter(IikoSettings.id == setting_id).first()
    if not rec:
        raise HTTPException(status_code=404, detail="Настройка не найдена")
    svc = IikoService(db, rec)
    try:
        result = await svc.withdraw_loyalty_balance(
            data.organization_id, data.customer_id,
            data.wallet_id, data.amount, data.comment or "",
        )
        _save_bonus_transaction(db, data, "withdraw", _current_user.username)
        return result
    except Exception as e:
        raise HTTPException(status_code=502, detail=f"Ошибка списания бонусов: {str(e)}")


@api_router.post("/iiko/loyalty/hold", tags=["loyalty"])
async def hold_loyalty(
    data: LoyaltyBalanceOperation,
    setting_id: int,
    db: Session = Depends(get_db),
    _current_user: User = Depends(require_role("manager")),
):
    """Холдировать (заморозить) бонусы гостя"""
    rec = db.query(IikoSettings).filter(IikoSettings.id == setting_id).first()
    if not rec:
        raise HTTPException(status_code=404, detail="Настройка не найдена")
    svc = IikoService(db, rec)
    try:
        result = await svc.hold_loyalty_balance(
            data.organization_id, data.customer_id,
            data.wallet_id, data.amount, data.comment or "",
        )
        _save_bonus_transaction(db, data, "hold", _current_user.username)
        return result
    except Exception as e:
        raise HTTPException(status_code=502, detail=f"Ошибка холдирования бонусов: {str(e)}")


@api_router.get("/iiko/loyalty/transactions", tags=["loyalty"])
async def get_bonus_transactions(
    setting_id: int,
    organization_id: str,
    customer_id: str = None,
    limit: int = 50,
    db: Session = Depends(get_db),
    _current_user: User = Depends(require_role("operator")),
):
    """Получить историю операций с бонусами"""
    rec = db.query(IikoSettings).filter(IikoSettings.id == setting_id).first()
    if not rec:
        raise HTTPException(status_code=404, detail="Настройка не найдена")
    query = db.query(BonusTransaction).filter(
        BonusTransaction.organization_id == organization_id,
    )
    if customer_id:
        query = query.filter(BonusTransaction.customer_id == customer_id)
    transactions = query.order_by(BonusTransaction.created_at.desc()).limit(min(limit, 200)).all()
    return [BonusTransactionResponse.model_validate(t).model_dump() for t in transactions]


# ─── Admin User Management ──────────────────────────────────────────────
@api_router.post("/users", tags=["users"], response_model=UserResponse, status_code=201)
async def admin_create_user(
    user_in: AdminUserCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(require_role("admin")),
):
    """Создать пользователя с назначением роли (только admin)"""
    if db.query(User).filter(User.email == user_in.email).first():
        raise HTTPException(status_code=400, detail="Email уже зарегистрирован")
    if db.query(User).filter(User.username == user_in.username).first():
        raise HTTPException(status_code=400, detail="Имя пользователя занято")
    user = User(
        email=user_in.email,
        username=user_in.username,
        hashed_password=get_password_hash(user_in.password),
        role=user_in.role,
        is_active=user_in.is_active,
        is_superuser=(user_in.role == "admin"),
    )
    db.add(user)
    db.commit()
    db.refresh(user)
    return user


@api_router.delete("/users/{user_id}", tags=["users"])
async def admin_delete_user(
    user_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(require_role("admin")),
):
    """Удалить пользователя (только admin, нельзя удалить себя)"""
    if current_user.id == user_id:
        raise HTTPException(status_code=400, detail="Нельзя удалить собственную учетную запись")
    user = db.query(User).filter(User.id == user_id).first()
    if not user:
        raise HTTPException(status_code=404, detail="Пользователь не найден")
    db.delete(user)
    db.commit()
    return {"detail": "Пользователь удален"}


@api_router.put("/users/{user_id}/toggle-active", tags=["users"])
async def admin_toggle_user_active(
    user_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(require_role("admin")),
):
    """Включить/выключить пользователя (только admin)"""
    if current_user.id == user_id:
        raise HTTPException(status_code=400, detail="Нельзя деактивировать собственную учетную запись")
    user = db.query(User).filter(User.id == user_id).first()
    if not user:
        raise HTTPException(status_code=404, detail="Пользователь не найден")
    user.is_active = not user.is_active
    db.commit()
    db.refresh(user)
    return {"detail": f"Пользователь {'активирован' if user.is_active else 'деактивирован'}", "is_active": user.is_active}


# ─── Webhook ────────────────────────────────────────────────────────────
@api_router.post("/webhook/iiko", tags=["webhook"])
async def iiko_webhook_legacy(
    request: Request,
    db: Session = Depends(get_db),
):
    """
    Webhook endpoint для получения событий от iiko (legacy).
    Принимает данные от iiko, проверяет секретный ключ и логирует события.
    """
    # Получаем секретный ключ из заголовков
    auth_token = request.headers.get("Authorization") or request.headers.get("authToken")
    
    # Проверяем секретный ключ
    expected_secret = settings.WEBHOOK_SECRET_KEY
    if not expected_secret:
        logger.error("WEBHOOK_SECRET_KEY не настроен в переменных окружения")
        raise HTTPException(
            status_code=500,
            detail="Server configuration error: webhook secret not configured"
        )
    
    if auth_token != expected_secret:
        logger.warning(f"Неверный секретный ключ вебхука: {auth_token}")
        raise HTTPException(status_code=401, detail="Unauthorized: неверный секретный ключ")
    
    # Получаем данные от iiko
    try:
        payload = await request.json()
    except Exception as e:
        logger.error(f"Ошибка парсинга JSON от iiko webhook: {e}")
        raise HTTPException(status_code=400, detail="Invalid JSON payload")
    
    # Определяем тип события
    event_type = payload.get("eventType") or payload.get("type") or "unknown"
    
    # Логируем событие
    logger.info(f"Получено событие от iiko webhook: {event_type}")
    logger.debug(f"Данные события: {json.dumps(payload, ensure_ascii=False)[:500]}")
    
    # Сохраняем событие в БД
    webhook_event = WebhookEvent(
        event_type=event_type,
        payload=json.dumps(payload, ensure_ascii=False),
        processed=False,
    )
    db.add(webhook_event)
    
    # Обрабатываем событие заказа
    if event_type in [WEBHOOK_EVENT_ORDER_CHANGED, WEBHOOK_EVENT_DELIVERY_ORDER_CHANGED, WEBHOOK_EVENT_ORDER]:
        try:
            # Извлекаем информацию о заказе
            order_data = payload.get("order") or payload.get("data") or {}
            order_id = order_data.get("id") or order_data.get("orderId")
            order_status = order_data.get("status") or order_data.get("orderStatus")
            
            logger.info(f"Статус заказа {order_id}: {order_status}")
            
            # Обновляем статус заказа в БД, если заказ существует
            if order_id:
                existing_order = db.query(Order).filter(Order.iiko_order_id == order_id).first()
                if existing_order:
                    existing_order.status = order_status or "unknown"
                    existing_order.order_data = json.dumps(order_data, ensure_ascii=False)
                    logger.info(f"Обновлен статус заказа в БД: {order_id} -> {order_status}")
                else:
                    logger.info(f"Заказ {order_id} не найден в БД, событие просто залогировано")
            
            # Помечаем событие как обработанное
            webhook_event.processed = True
            
        except Exception as e:
            logger.error(f"Ошибка при обработке события заказа: {e}")
    
    # Сохраняем изменения
    db.commit()
    
    # Возвращаем успешный ответ
    return {"status": "ok", "message": "Событие получено и обработано"}

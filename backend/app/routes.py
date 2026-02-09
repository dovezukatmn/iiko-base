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
    MenuItem, User, IikoSettings, Order, WebhookEvent, ApiLog,
)
from app.schemas import (
    UserCreate, UserLogin, UserResponse, Token, RoleUpdate,
    IikoSettingsCreate, IikoSettingsResponse, IikoSettingsUpdate,
    OrderResponse, OrderCreate, PasswordChange,
    WebhookEventResponse, ApiLogResponse,
)
from app.auth import (
    get_password_hash, verify_password, create_access_token,
    get_current_user, require_role,
)
from app.iiko_service import IikoService
from config.settings import settings

_app_start_time = time.time()

api_router = APIRouter()


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
    db.commit()
    db.refresh(rec)
    return rec


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
        return {"status": "ok", "message": "Подключение успешно"}
    except Exception as e:
        raise HTTPException(status_code=502, detail=f"Ошибка подключения: {str(e)}")


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
    body = await request.body()
    try:
        payload = json.loads(body)
    except Exception as e:
        logger.warning("Failed to parse webhook JSON: %s", e)
        payload = {"raw": body.decode("utf-8", errors="replace"), "parse_error": str(e)}

    event_type = payload.get("eventType", "unknown")
    event = WebhookEvent(
        event_type=event_type,
        payload=json.dumps(payload),
        processed=False,
    )
    db.add(event)
    db.commit()

    # Обработка событий по статусам заказов
    if event_type in ("DeliveryOrderUpdate", "DeliveryOrderError"):
        order_id = payload.get("eventInfo", {}).get("id")
        new_status = payload.get("eventInfo", {}).get("status", "")
        if order_id:
            order = db.query(Order).filter(Order.iiko_order_id == order_id).first()
            if order:
                order.status = new_status
                db.commit()
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
    webhook_url: str,
    db: Session = Depends(get_db),
    _current_user: User = Depends(require_role("admin")),
):
    """Зарегистрировать вебхук в iiko и сохранить настройки"""
    rec = db.query(IikoSettings).filter(IikoSettings.id == setting_id).first()
    if not rec:
        raise HTTPException(status_code=404, detail="Настройка не найдена")

    auth_token = secrets.token_urlsafe(32)
    svc = IikoService(db, rec)
    try:
        result = await svc.register_webhook(webhook_url, auth_token)
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

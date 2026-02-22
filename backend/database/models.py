"""
Модели базы данных
"""
from sqlalchemy import Column, Integer, String, DateTime, Boolean, Text, Float
from sqlalchemy.sql import func
from database.connection import Base


class User(Base):
    """Модель пользователя"""
    __tablename__ = "users"
    
    id = Column(Integer, primary_key=True, index=True)
    email = Column(String(255), unique=True, index=True, nullable=False)
    username = Column(String(100), unique=True, index=True, nullable=False)
    hashed_password = Column(String(255), nullable=False)
    role = Column(String(50), default="viewer")
    is_active = Column(Boolean, default=True)
    is_superuser = Column(Boolean, default=False)
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    updated_at = Column(DateTime(timezone=True), onupdate=func.now())


class MenuItem(Base):
    """Модель элемента меню (для примера работы с iiko)"""
    __tablename__ = "menu_items"
    
    id = Column(Integer, primary_key=True, index=True)
    name = Column(String(255), nullable=False)
    description = Column(Text)
    price = Column(Integer)  # Цена в копейках
    category = Column(String(100))
    is_available = Column(Boolean, default=True)
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    updated_at = Column(DateTime(timezone=True), onupdate=func.now())


class IikoSettings(Base):
    """Настройки интеграции с iiko"""
    __tablename__ = "iiko_settings"
    
    id = Column(Integer, primary_key=True, index=True)
    organization_id = Column(String(255), nullable=True)
    organization_name = Column(String(255), nullable=True)
    api_key = Column(String(500), nullable=False)
    api_url = Column(String(500), default="https://api-ru.iiko.services/api/1")
    webhook_url = Column(String(500), nullable=True)
    webhook_secret = Column(String(255), nullable=True)
    is_active = Column(Boolean, default=True)
    last_token_refresh = Column(DateTime(timezone=True), nullable=True)
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    updated_at = Column(DateTime(timezone=True), onupdate=func.now())


class Order(Base):
    """Заказы"""
    __tablename__ = "orders"
    
    id = Column(Integer, primary_key=True, index=True)
    iiko_order_id = Column(String(255), unique=True, nullable=True, index=True)
    external_order_id = Column(String(255), nullable=True, index=True)  # orderExternalId from SOI webhook
    readable_number = Column(String(100), nullable=True)  # readableNumber from SOI webhook
    organization_id = Column(String(255), nullable=True)
    status = Column(String(50), default="new")
    customer_name = Column(String(255), nullable=True)
    customer_phone = Column(String(50), nullable=True)
    delivery_address = Column(Text, nullable=True)
    total_amount = Column(Integer, default=0)
    promised_time = Column(DateTime(timezone=True), nullable=True)  # promisedTime from webhook
    courier_id = Column(String(255), nullable=True)  # ID курьера
    courier_name = Column(String(255), nullable=True)  # Имя курьера
    order_type = Column(String(50), nullable=True)  # DELIVERY, PICKUP, etc.
    restaurant_name = Column(String(255), nullable=True)  # restaurantName from webhook
    problem = Column(Text, nullable=True)  # описание проблемы заказа
    creation_status = Column(String(50), nullable=True)  # OK, Error from SOI
    error_info = Column(Text, nullable=True)  # errorInfo from SOI
    order_data = Column(Text, nullable=True)  # Полные данные заказа в JSON
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    updated_at = Column(DateTime(timezone=True), onupdate=func.now())


class WebhookEvent(Base):
    """Входящие вебхук-события от iiko"""
    __tablename__ = "webhook_events"
    
    id = Column(Integer, primary_key=True, index=True)
    event_type = Column(String(100), nullable=False, index=True)  # CREATE, UPDATE, etc.
    order_external_id = Column(String(255), nullable=True, index=True)  # для быстрого поиска
    organization_id = Column(String(255), nullable=True, index=True)
    payload = Column(Text, nullable=True)
    processed = Column(Boolean, default=False, index=True)
    processing_error = Column(Text, nullable=True)  # ошибки обработки
    created_at = Column(DateTime(timezone=True), server_default=func.now())


class ApiLog(Base):
    """Журнал запросов к iiko API"""
    __tablename__ = "api_logs"
    
    id = Column(Integer, primary_key=True, index=True)
    method = Column(String(10), nullable=False)
    url = Column(String(500), nullable=False)
    request_body = Column(Text, nullable=True)
    response_status = Column(Integer, nullable=True)
    response_body = Column(Text, nullable=True)
    duration_ms = Column(Integer, nullable=True)
    created_at = Column(DateTime(timezone=True), server_default=func.now())


class BonusTransaction(Base):
    """История операций с бонусами"""
    __tablename__ = "bonus_transactions"

    id = Column(Integer, primary_key=True, index=True)
    organization_id = Column(String(255), nullable=False, index=True)
    customer_id = Column(String(255), nullable=False, index=True)
    customer_name = Column(String(255), nullable=True)
    customer_phone = Column(String(50), nullable=True)
    wallet_id = Column(String(255), nullable=False)
    wallet_name = Column(String(255), nullable=True)
    operation_type = Column(String(50), nullable=False, index=True)  # topup, withdraw, hold
    amount = Column(Float, nullable=False)
    comment = Column(Text, nullable=True)
    order_id = Column(String(255), nullable=True, index=True)
    performed_by = Column(String(100), nullable=True)
    created_at = Column(DateTime(timezone=True), server_default=func.now())


class WebhookConfig(Base):
    """Конфигурация вебхуков для разных организаций"""
    __tablename__ = "webhook_configs"

    id = Column(Integer, primary_key=True, index=True)
    organization_id = Column(String(255), nullable=False, index=True)
    webhook_url = Column(String(500), nullable=False)
    auth_token = Column(String(255), nullable=True)
    is_active = Column(Boolean, default=True, index=True)
    last_registration = Column(DateTime(timezone=True), nullable=True)
    registration_status = Column(String(50), nullable=True)
    registration_error = Column(Text, nullable=True)
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    updated_at = Column(DateTime(timezone=True), onupdate=func.now())


class OutgoingWebhook(Base):
    """Исходящие вебхуки для отправки данных на внешние сервисы"""
    __tablename__ = "outgoing_webhooks"

    id = Column(Integer, primary_key=True, index=True)
    name = Column(String(255), nullable=False)
    description = Column(Text, nullable=True)
    webhook_url = Column(String(500), nullable=False)
    is_active = Column(Boolean, default=True, index=True)
    
    # Authentication
    auth_type = Column(String(50), default='none')
    auth_token = Column(String(500), nullable=True)
    auth_username = Column(String(255), nullable=True)
    auth_password = Column(String(255), nullable=True)
    custom_headers = Column(Text, nullable=True)  # JSON
    
    # Event configuration
    send_on_order_created = Column(Boolean, default=True)
    send_on_order_updated = Column(Boolean, default=True)
    send_on_order_status_changed = Column(Boolean, default=True)
    send_on_order_cancelled = Column(Boolean, default=False)
    
    # Filter configuration
    filter_organization_ids = Column(Text, nullable=True)  # JSON
    filter_order_types = Column(Text, nullable=True)  # JSON
    filter_statuses = Column(Text, nullable=True)  # JSON
    
    # Payload configuration
    payload_format = Column(String(50), default='iiko_soi')
    include_fields = Column(Text, nullable=True)  # JSON
    custom_payload_template = Column(Text, nullable=True)
    
    # Retry configuration
    retry_count = Column(Integer, default=3)
    retry_delay_seconds = Column(Integer, default=5)
    timeout_seconds = Column(Integer, default=30)
    
    # Statistics
    total_sent = Column(Integer, default=0)
    total_success = Column(Integer, default=0)
    total_failed = Column(Integer, default=0)
    last_sent_at = Column(DateTime(timezone=True), nullable=True)
    last_success_at = Column(DateTime(timezone=True), nullable=True)
    last_error = Column(Text, nullable=True)
    
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    updated_at = Column(DateTime(timezone=True), onupdate=func.now())


class OutgoingWebhookLog(Base):
    """Логи отправки исходящих вебхуков"""
    __tablename__ = "outgoing_webhook_logs"

    id = Column(Integer, primary_key=True, index=True)
    webhook_id = Column(Integer, nullable=False, index=True)
    webhook_name = Column(String(255), nullable=True)
    
    # Order information
    order_id = Column(Integer, nullable=True, index=True)
    order_external_id = Column(String(255), nullable=True)
    event_type = Column(String(100), nullable=True)
    
    # Request details
    request_url = Column(String(500), nullable=True)
    request_method = Column(String(10), default='POST')
    request_headers = Column(Text, nullable=True)  # JSON
    request_body = Column(Text, nullable=True)
    
    # Response details
    response_status = Column(Integer, nullable=True)
    response_headers = Column(Text, nullable=True)  # JSON
    response_body = Column(Text, nullable=True)
    
    # Execution details
    attempt_number = Column(Integer, default=1)
    duration_ms = Column(Integer, nullable=True)
    success = Column(Boolean, default=False, index=True)
    error_message = Column(Text, nullable=True)
    
    created_at = Column(DateTime(timezone=True), server_default=func.now())


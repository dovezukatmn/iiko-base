"""
Pydantic схемы для валидации запросов и ответов
"""
from pydantic import BaseModel, Field
from typing import Optional, List
from datetime import datetime


# --- Auth ---
class UserCreate(BaseModel):
    email: str = Field(..., min_length=5, max_length=255)
    username: str = Field(..., min_length=3, max_length=100)
    password: str = Field(..., min_length=6, max_length=128)


class UserLogin(BaseModel):
    username: str
    password: str


class UserResponse(BaseModel):
    id: int
    email: str
    username: str
    role: str
    is_active: bool
    created_at: Optional[datetime] = None

    class Config:
        from_attributes = True


class Token(BaseModel):
    access_token: str
    token_type: str = "bearer"


class RoleUpdate(BaseModel):
    role: str = Field(..., pattern="^(admin|manager|operator|viewer)$")


# --- iiko Settings ---
class IikoSettingsCreate(BaseModel):
    api_key: str = Field(..., min_length=1)
    api_url: str = "https://api-ru.iiko.services/api/1"
    organization_id: Optional[str] = None
    organization_name: Optional[str] = None


class IikoSettingsResponse(BaseModel):
    id: int
    organization_id: Optional[str] = None
    organization_name: Optional[str] = None
    api_url: str
    webhook_url: Optional[str] = None
    webhook_secret: Optional[str] = None
    is_active: bool
    last_token_refresh: Optional[datetime] = None
    created_at: Optional[datetime] = None

    class Config:
        from_attributes = True


class IikoSettingsUpdate(BaseModel):
    api_key: Optional[str] = None
    api_url: Optional[str] = None
    organization_id: Optional[str] = None
    organization_name: Optional[str] = None


# --- Orders ---
class OrderResponse(BaseModel):
    id: int
    iiko_order_id: Optional[str] = None
    organization_id: Optional[str] = None
    status: str
    customer_name: Optional[str] = None
    customer_phone: Optional[str] = None
    delivery_address: Optional[str] = None
    total_amount: int
    created_at: Optional[datetime] = None
    updated_at: Optional[datetime] = None

    class Config:
        from_attributes = True


class OrderCreate(BaseModel):
    organization_id: str
    customer_name: Optional[str] = None
    customer_phone: Optional[str] = None
    delivery_address: Optional[str] = None
    items: List[dict]  # List of iiko order items


class PasswordChange(BaseModel):
    old_password: str = Field(..., min_length=1)
    new_password: str = Field(..., min_length=6, max_length=128)


# --- Webhook ---
class WebhookEventResponse(BaseModel):
    id: int
    event_type: str
    order_external_id: Optional[str] = None
    organization_id: Optional[str] = None
    payload: Optional[str] = None
    processed: bool
    processing_error: Optional[str] = None
    created_at: Optional[datetime] = None

    class Config:
        from_attributes = True


# --- API Log ---
class ApiLogResponse(BaseModel):
    id: int
    method: str
    url: str
    request_body: Optional[str] = None
    response_status: Optional[int] = None
    response_body: Optional[str] = None
    duration_ms: Optional[int] = None
    created_at: Optional[datetime] = None

    class Config:
        from_attributes = True


# --- Loyalty / iikoCard ---
class CustomerSearch(BaseModel):
    organization_id: str
    customer_id: Optional[str] = None
    phone: Optional[str] = None
    card_track: Optional[str] = None
    card_number: Optional[str] = None
    email: Optional[str] = None


class CustomerCreate(BaseModel):
    organization_id: str
    name: Optional[str] = None
    phone: Optional[str] = None
    email: Optional[str] = None
    card_track: Optional[str] = None
    card_number: Optional[str] = None
    birthday: Optional[str] = None


class LoyaltyBalanceOperation(BaseModel):
    organization_id: str
    customer_id: str
    wallet_id: str
    amount: float = Field(..., gt=0)
    comment: Optional[str] = ""


class BonusTransactionResponse(BaseModel):
    id: int
    organization_id: str
    customer_id: str
    customer_name: Optional[str] = None
    customer_phone: Optional[str] = None
    wallet_id: str
    wallet_name: Optional[str] = None
    operation_type: str
    amount: float
    comment: Optional[str] = None
    order_id: Optional[str] = None
    performed_by: Optional[str] = None
    created_at: Optional[datetime] = None

    class Config:
        from_attributes = True


# --- Admin User Management ---
class AdminUserCreate(BaseModel):
    email: str = Field(..., min_length=5, max_length=255)
    username: str = Field(..., min_length=3, max_length=100)
    password: str = Field(..., min_length=6, max_length=128)
    role: str = Field("viewer", pattern="^(admin|manager|operator|viewer)$")
    is_active: bool = True


# --- Outgoing Webhooks ---
class OutgoingWebhookCreate(BaseModel):
    name: str = Field(..., min_length=1, max_length=255)
    description: Optional[str] = None
    webhook_url: str = Field(..., min_length=1)
    is_active: bool = True
    
    # Authentication
    auth_type: str = Field("none", pattern="^(none|bearer|basic|custom)$")
    auth_token: Optional[str] = None
    auth_username: Optional[str] = None
    auth_password: Optional[str] = None
    custom_headers: Optional[str] = None  # JSON string
    
    # Event configuration
    send_on_order_created: bool = True
    send_on_order_updated: bool = True
    send_on_order_status_changed: bool = True
    send_on_order_cancelled: bool = False
    
    # Filter configuration
    filter_organization_ids: Optional[str] = None  # JSON array
    filter_order_types: Optional[str] = None  # JSON array
    filter_statuses: Optional[str] = None  # JSON array
    
    # Payload configuration
    payload_format: str = Field("iiko_soi", pattern="^(iiko_soi|iiko_cloud|custom)$")
    include_fields: Optional[str] = None  # JSON array
    custom_payload_template: Optional[str] = None
    
    # Retry configuration
    retry_count: int = Field(3, ge=0, le=10)
    retry_delay_seconds: int = Field(5, ge=1, le=300)
    timeout_seconds: int = Field(30, ge=5, le=300)


class OutgoingWebhookUpdate(BaseModel):
    name: Optional[str] = None
    description: Optional[str] = None
    webhook_url: Optional[str] = None
    is_active: Optional[bool] = None
    auth_type: Optional[str] = None
    auth_token: Optional[str] = None
    auth_username: Optional[str] = None
    auth_password: Optional[str] = None
    custom_headers: Optional[str] = None
    send_on_order_created: Optional[bool] = None
    send_on_order_updated: Optional[bool] = None
    send_on_order_status_changed: Optional[bool] = None
    send_on_order_cancelled: Optional[bool] = None
    filter_organization_ids: Optional[str] = None
    filter_order_types: Optional[str] = None
    filter_statuses: Optional[str] = None
    payload_format: Optional[str] = None
    include_fields: Optional[str] = None
    custom_payload_template: Optional[str] = None
    retry_count: Optional[int] = None
    retry_delay_seconds: Optional[int] = None
    timeout_seconds: Optional[int] = None


class OutgoingWebhookResponse(BaseModel):
    id: int
    name: str
    description: Optional[str] = None
    webhook_url: str
    is_active: bool
    auth_type: str
    send_on_order_created: bool
    send_on_order_updated: bool
    send_on_order_status_changed: bool
    send_on_order_cancelled: bool
    filter_organization_ids: Optional[str] = None
    filter_order_types: Optional[str] = None
    filter_statuses: Optional[str] = None
    payload_format: str
    include_fields: Optional[str] = None
    retry_count: int
    retry_delay_seconds: int
    timeout_seconds: int
    total_sent: int
    total_success: int
    total_failed: int
    last_sent_at: Optional[datetime] = None
    last_success_at: Optional[datetime] = None
    last_error: Optional[str] = None
    created_at: Optional[datetime] = None
    updated_at: Optional[datetime] = None

    class Config:
        from_attributes = True


class OutgoingWebhookLogResponse(BaseModel):
    id: int
    webhook_id: int
    webhook_name: Optional[str] = None
    order_id: Optional[int] = None
    order_external_id: Optional[str] = None
    event_type: Optional[str] = None
    request_url: Optional[str] = None
    request_method: Optional[str] = None
    response_status: Optional[int] = None
    attempt_number: int
    duration_ms: Optional[int] = None
    success: bool
    error_message: Optional[str] = None
    created_at: Optional[datetime] = None

    class Config:
        from_attributes = True


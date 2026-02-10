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
    payload: Optional[str] = None
    processed: bool
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

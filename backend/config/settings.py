"""
Настройки приложения
"""
from typing import List
from pydantic import Field
from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_file=".env", case_sensitive=True)

    # Database
    DATABASE_URL: str = "postgresql://iiko_user:12101991Qq!@localhost:5432/iiko_db"
    
    # Application
    APP_NAME: str = "iiko-base"
    APP_ENV: str = "production"
    DEBUG: bool = False
    SECRET_KEY: str = "change-this-secret-key"
    
    # API
    API_V1_PREFIX: str = "/api/v1"
    BACKEND_CORS_ORIGINS: List[str] = ["http://localhost:3000", "https://vezuroll.ru", "https://b1d8d8270d0f.vps.myjino.ru", "http://vezuroll.ru", "http://b1d8d8270d0f.vps.myjino.ru"]
    
    # iiko API
    IIKO_API_URL: str = "https://api-ru.iiko.services/api/1"
    IIKO_API_KEY: str = ""
    
    # JWT
    ACCESS_TOKEN_EXPIRE_MINUTES: int = 30
    ALGORITHM: str = "HS256"

    # Default admin (bootstrap account)
    DEFAULT_ADMIN_USERNAME: str = Field("admin")
    DEFAULT_ADMIN_PASSWORD: str = Field("12101991Qq!", repr=False)
    DEFAULT_ADMIN_EMAIL: str = Field("admin@example.com")

    # Webhook
    WEBHOOK_BASE_URL: str = ""
    
    # Server
    HOST: str = "0.0.0.0"
    PORT: int = 8000


settings = Settings()

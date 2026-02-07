"""
Главный файл приложения FastAPI
"""
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from config.settings import settings
from app.routes import api_router

# Создание приложения
app = FastAPI(
    title=settings.APP_NAME,
    openapi_url=f"{settings.API_V1_PREFIX}/openapi.json",
    docs_url=f"{settings.API_V1_PREFIX}/docs",
    redoc_url=f"{settings.API_V1_PREFIX}/redoc"
)

# Настройка CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=settings.BACKEND_CORS_ORIGINS,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Подключение роутов
app.include_router(api_router, prefix=settings.API_V1_PREFIX)


@app.get("/")
async def root():
    """Корневой эндпоинт"""
    return {
        "message": "iiko-base API",
        "version": "1.0.0",
        "docs": f"{settings.API_V1_PREFIX}/docs"
    }


@app.get("/health")
async def health_check():
    """Проверка здоровья приложения"""
    return {"status": "healthy"}

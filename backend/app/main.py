"""
Главный файл приложения FastAPI
"""
import asyncio
import logging
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from config.settings import settings
from app.routes import api_router
from app.iiko_auth import get_access_token, refresh_token_periodically

logger = logging.getLogger(__name__)

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


@app.on_event("startup")
async def startup_event():
    """
    Событие при запуске приложения.
    Инициализирует токен iiko и запускает фоновые задачи.
    """
    logger.info("Запуск приложения iiko-base")
    
    # Получаем начальный токен доступа
    try:
        if settings.IIKO_API_KEY:
            logger.info("Получение начального токена доступа iiko...")
            await get_access_token()
            logger.info("Токен доступа iiko успешно получен")
            
            # Запускаем фоновую задачу обновления токена
            asyncio.create_task(refresh_token_periodically())
            logger.info("Фоновая задача обновления токена запущена")
            
            # Регистрируем вебхук, если настроен
            if settings.WEBHOOK_BASE_URL:
                asyncio.create_task(register_webhook_on_startup())
        else:
            logger.warning("IIKO_API_KEY не настроен, пропускаем инициализацию iiko")
    except Exception as e:
        logger.error(f"Ошибка при инициализации iiko: {e}")
        # Не прерываем запуск приложения, токен можно получить позже


async def register_webhook_on_startup():
    """
    Регистрирует вебхук в iiko при запуске приложения.
    """
    try:
        # Небольшая задержка, чтобы дать приложению полностью запуститься
        await asyncio.sleep(5)
        
        logger.info("Регистрация вебхука в iiko...")
        
        # Проверяем, настроен ли webhook secret
        if not settings.WEBHOOK_SECRET_KEY:
            logger.warning("WEBHOOK_SECRET_KEY не настроен, пропускаем регистрацию вебхука")
            return
        
        # Импортируем здесь, чтобы избежать циклических зависимостей
        from database.connection import SessionLocal
        from database.models import IikoSettings
        from app.iiko_service import IikoService
        
        db = SessionLocal()
        try:
            # Получаем активные настройки iiko
            iiko_settings = db.query(IikoSettings).filter(
                IikoSettings.is_active == True
            ).first()
            
            if not iiko_settings or not iiko_settings.organization_id:
                logger.warning("Настройки iiko не найдены или organization_id не указан, пропускаем регистрацию вебхука")
                return
            
            # Создаем сервис для работы с iiko API
            service = IikoService(db, iiko_settings)
            
            # Формируем URL вебхука
            webhook_url = f"{settings.WEBHOOK_BASE_URL.rstrip('/')}/api/v1/webhooks/iiko"
            
            # Регистрируем вебхук
            result = await service.register_webhook(
                iiko_settings.organization_id,
                webhook_url,
                settings.WEBHOOK_SECRET_KEY
            )
            
            logger.info(f"Вебхук успешно зарегистрирован: {webhook_url}")
            logger.debug(f"Результат регистрации: {result}")
            
        finally:
            db.close()
            
    except Exception as e:
        logger.error(f"Ошибка при регистрации вебхука: {e}")
        # Не прерываем работу приложения

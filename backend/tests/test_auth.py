"""
Тесты для аутентификации и авторизации
"""
import pytest
from fastapi.testclient import TestClient
from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker
from sqlalchemy.pool import StaticPool
from app.auth import get_password_hash, verify_password, create_access_token, ROLE_HIERARCHY
from app.main import app
from jose import jwt
from config.settings import settings
from database.connection import Base, get_db
from database.models import User


# Тестовая in-memory база данных
engine = create_engine(
    "sqlite:///:memory:",
    connect_args={"check_same_thread": False},
    poolclass=StaticPool,
)
TestingSessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)
DEFAULT_PASSWORD = settings.DEFAULT_ADMIN_PASSWORD


def override_get_db():
    db = TestingSessionLocal()
    try:
        yield db
    finally:
        db.close()


def test_password_hashing():
    password = "secure_password_123"
    hashed = get_password_hash(password)
    assert hashed != password
    assert verify_password(password, hashed)
    assert not verify_password("wrong_password", hashed)


def test_create_access_token():
    data = {"sub": "testuser", "role": "admin"}
    token = create_access_token(data)
    payload = jwt.decode(token, settings.SECRET_KEY, algorithms=[settings.ALGORITHM])
    assert payload["sub"] == "testuser"
    assert payload["role"] == "admin"
    assert "exp" in payload


def test_role_hierarchy():
    assert ROLE_HIERARCHY["admin"] > ROLE_HIERARCHY["manager"]
    assert ROLE_HIERARCHY["manager"] > ROLE_HIERARCHY["operator"]
    assert ROLE_HIERARCHY["operator"] > ROLE_HIERARCHY["viewer"]


def test_login_creates_default_admin_and_returns_token():
    # Подготовка in-memory базы
    Base.metadata.drop_all(bind=engine)
    Base.metadata.create_all(bind=engine)
    app.dependency_overrides[get_db] = override_get_db
    client = TestClient(app)

    try:
        response = client.post(
            f"{settings.API_V1_PREFIX}/auth/login",
            json={
                "username": settings.DEFAULT_ADMIN_USERNAME,
                "password": DEFAULT_PASSWORD,
            },
        )

        assert response.status_code == 200
        token = response.json().get("access_token")
        assert token

        db = TestingSessionLocal()
        try:
            user = db.query(User).filter(User.username == settings.DEFAULT_ADMIN_USERNAME).first()
            assert user is not None
            assert user.role == "admin"
            assert user.is_superuser is True
            assert verify_password(DEFAULT_PASSWORD, user.hashed_password)
        finally:
            db.close()
    finally:
        app.dependency_overrides.pop(get_db, None)


def test_login_denies_default_credentials_when_admin_exists():
    Base.metadata.drop_all(bind=engine)
    Base.metadata.create_all(bind=engine)
    app.dependency_overrides[get_db] = override_get_db

    db = TestingSessionLocal()
    try:
        db.add(
            User(
                email="other@example.com",
                username="otheradmin",
                hashed_password=get_password_hash("another-secret"),
                role="admin",
            )
        )
        db.commit()
    finally:
        db.close()

    client = TestClient(app)
    try:
        response = client.post(
            f"{settings.API_V1_PREFIX}/auth/login",
            json={
                "username": settings.DEFAULT_ADMIN_USERNAME,
                "password": DEFAULT_PASSWORD,
            },
        )
        assert response.status_code == 401
    finally:
        app.dependency_overrides.pop(get_db, None)

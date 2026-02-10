"""
Тесты для управления пользователями (admin)
"""
import pytest
from fastapi.testclient import TestClient
from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker
from sqlalchemy.pool import StaticPool
from app.auth import get_password_hash, verify_password
from app.main import app
from config.settings import settings
from database.connection import Base, get_db
from database.models import User


engine = create_engine(
    "sqlite:///:memory:",
    connect_args={"check_same_thread": False},
    poolclass=StaticPool,
)
TestingSessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)


def override_get_db():
    db = TestingSessionLocal()
    try:
        yield db
    finally:
        db.close()


def _setup_admin_and_get_token():
    """Helper: create admin user and return JWT token"""
    Base.metadata.drop_all(bind=engine)
    Base.metadata.create_all(bind=engine)
    app.dependency_overrides[get_db] = override_get_db

    db = TestingSessionLocal()
    try:
        admin = User(
            email="admin@example.com",
            username="admin",
            hashed_password=get_password_hash("admin123456"),
            role="admin",
            is_active=True,
            is_superuser=True,
        )
        db.add(admin)
        db.commit()
    finally:
        db.close()

    client = TestClient(app)
    response = client.post(
        f"{settings.API_V1_PREFIX}/auth/login",
        json={"username": "admin", "password": "admin123456"},
    )
    token = response.json()["access_token"]
    return client, token


def test_admin_create_user():
    client, token = _setup_admin_and_get_token()
    try:
        response = client.post(
            f"{settings.API_V1_PREFIX}/users",
            json={
                "username": "newuser",
                "email": "newuser@example.com",
                "password": "password123",
                "role": "operator",
            },
            headers={"Authorization": f"Bearer {token}"},
        )
        assert response.status_code == 201
        data = response.json()
        assert data["username"] == "newuser"
        assert data["role"] == "operator"
        assert data["is_active"] is True
    finally:
        app.dependency_overrides.pop(get_db, None)


def test_admin_create_user_duplicate_email():
    client, token = _setup_admin_and_get_token()
    try:
        response = client.post(
            f"{settings.API_V1_PREFIX}/users",
            json={
                "username": "another",
                "email": "admin@example.com",  # duplicate
                "password": "password123",
                "role": "viewer",
            },
            headers={"Authorization": f"Bearer {token}"},
        )
        assert response.status_code == 400
        assert "Email" in response.json()["detail"]
    finally:
        app.dependency_overrides.pop(get_db, None)


def test_admin_create_admin_user():
    client, token = _setup_admin_and_get_token()
    try:
        response = client.post(
            f"{settings.API_V1_PREFIX}/users",
            json={
                "username": "admin2",
                "email": "admin2@example.com",
                "password": "password123",
                "role": "admin",
            },
            headers={"Authorization": f"Bearer {token}"},
        )
        assert response.status_code == 201
        data = response.json()
        assert data["role"] == "admin"
    finally:
        app.dependency_overrides.pop(get_db, None)


def test_admin_delete_user():
    client, token = _setup_admin_and_get_token()
    try:
        # Create a user first
        client.post(
            f"{settings.API_V1_PREFIX}/users",
            json={
                "username": "todelete",
                "email": "del@example.com",
                "password": "password123",
                "role": "viewer",
            },
            headers={"Authorization": f"Bearer {token}"},
        )
        # Get user list to find user id
        users_resp = client.get(
            f"{settings.API_V1_PREFIX}/users",
            headers={"Authorization": f"Bearer {token}"},
        )
        users = users_resp.json()
        target = next(u for u in users if u["username"] == "todelete")

        # Delete user
        response = client.delete(
            f"{settings.API_V1_PREFIX}/users/{target['id']}",
            headers={"Authorization": f"Bearer {token}"},
        )
        assert response.status_code == 200
        assert "удален" in response.json()["detail"]
    finally:
        app.dependency_overrides.pop(get_db, None)


def test_admin_cannot_delete_self():
    client, token = _setup_admin_and_get_token()
    try:
        # Get admin's own id
        me_resp = client.get(
            f"{settings.API_V1_PREFIX}/auth/me",
            headers={"Authorization": f"Bearer {token}"},
        )
        admin_id = me_resp.json()["id"]

        response = client.delete(
            f"{settings.API_V1_PREFIX}/users/{admin_id}",
            headers={"Authorization": f"Bearer {token}"},
        )
        assert response.status_code == 400
    finally:
        app.dependency_overrides.pop(get_db, None)


def test_admin_toggle_user_active():
    client, token = _setup_admin_and_get_token()
    try:
        # Create user
        client.post(
            f"{settings.API_V1_PREFIX}/users",
            json={
                "username": "toggleuser",
                "email": "toggle@example.com",
                "password": "password123",
                "role": "viewer",
            },
            headers={"Authorization": f"Bearer {token}"},
        )
        users_resp = client.get(
            f"{settings.API_V1_PREFIX}/users",
            headers={"Authorization": f"Bearer {token}"},
        )
        users = users_resp.json()
        target = next(u for u in users if u["username"] == "toggleuser")

        # Toggle active (should deactivate since default is active)
        response = client.put(
            f"{settings.API_V1_PREFIX}/users/{target['id']}/toggle-active",
            headers={"Authorization": f"Bearer {token}"},
        )
        assert response.status_code == 200
        assert response.json()["is_active"] is False

        # Toggle again (should reactivate)
        response = client.put(
            f"{settings.API_V1_PREFIX}/users/{target['id']}/toggle-active",
            headers={"Authorization": f"Bearer {token}"},
        )
        assert response.status_code == 200
        assert response.json()["is_active"] is True
    finally:
        app.dependency_overrides.pop(get_db, None)

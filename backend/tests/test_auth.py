"""
Тесты для аутентификации и авторизации
"""
import pytest
from app.auth import get_password_hash, verify_password, create_access_token, ROLE_HIERARCHY
from jose import jwt
from config.settings import settings


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

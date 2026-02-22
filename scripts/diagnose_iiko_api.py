#!/usr/bin/env python3
"""
Скрипт диагностики подключения к iiko Cloud API.

Выполняет пошаговую проверку:
  1. Наличие и формат API-ключа (apiLogin)
  2. Корректность URL API
  3. Сетевая доступность сервера iiko
  4. Аутентификация (получение токена)
  5. Пробный запрос списка организаций

Использование:
  # Из корня проекта (значения берутся из .env / переменных окружения)
  python scripts/diagnose_iiko_api.py

  # С явным указанием ключа
  python scripts/diagnose_iiko_api.py --api-key "ваш-api-ключ"

  # С указанием другого URL API
  python scripts/diagnose_iiko_api.py --api-key "ключ" --api-url "https://api-ru.iiko.services/api/1"

  # Показать подробный вывод
  python scripts/diagnose_iiko_api.py --api-key "ключ" --verbose
"""

import argparse
import asyncio
import json
import os
import sys
import time

# ---------------------------------------------------------------------------
# Попытка импортировать httpx; если не установлен — сообщить
# ---------------------------------------------------------------------------
try:
    import httpx
except ImportError:
    print("ОШИБКА: библиотека httpx не установлена.")
    print("Установите её: pip install httpx")
    sys.exit(1)

DEFAULT_API_URL = "https://api-ru.iiko.services/api/1"


def _header(title: str) -> None:
    print(f"\n{'─' * 60}")
    print(f"  {title}")
    print(f"{'─' * 60}")


def _ok(msg: str) -> None:
    print(f"  ✅ {msg}")


def _warn(msg: str) -> None:
    print(f"  ⚠️  {msg}")


def _fail(msg: str) -> None:
    print(f"  ❌ {msg}")


def _info(msg: str) -> None:
    print(f"  ℹ️  {msg}")


async def run_diagnostics(api_key: str, api_url: str, verbose: bool = False) -> bool:
    """Выполнить полную диагностику. Возвращает True если всё ОК."""
    all_ok = True

    # ── 1. Проверка API-ключа ───────────────────────────────────────────
    _header("1. Проверка API-ключа (apiLogin)")
    api_key = api_key.strip()
    if not api_key:
        _fail("API-ключ пуст. Укажите через --api-key или переменную окружения IIKO_API_KEY.")
        return False
    if api_key in ("your-iiko-api-key", "change-me"):
        _fail(f"API-ключ содержит значение-заглушку: '{api_key}'. Замените на настоящий ключ.")
        return False
    if len(api_key) < 10:
        _warn(f"API-ключ очень короткий ({len(api_key)} символов). Обычно ключ iiko содержит 30+ символов.")
        all_ok = False
    else:
        _ok(f"API-ключ задан ({len(api_key)} символов, начинается с '{api_key[:8]}...')")

    # ── 2. Проверка URL API ─────────────────────────────────────────────
    _header("2. Проверка URL API")
    if not api_url:
        _fail("URL API не задан.")
        return False
    if not api_url.startswith("https://"):
        _warn(f"URL API не использует HTTPS: {api_url}")
        all_ok = False
    _ok(f"URL API: {api_url}")

    # ── 3. Сетевая доступность ──────────────────────────────────────────
    _header("3. Проверка сетевой доступности")
    base = api_url.rstrip("/")
    try:
        t0 = time.time()
        async with httpx.AsyncClient(timeout=15.0) as client:
            resp = await client.get(base)
        elapsed = int((time.time() - t0) * 1000)
        _ok(f"Сервер доступен — HTTP {resp.status_code} ({elapsed} мс)")
    except httpx.TimeoutException:
        _fail(f"Тайм-аут подключения к {base} (>15 с). Проверьте сеть/DNS.")
        return False
    except httpx.ConnectError as e:
        _fail(f"Не удалось подключиться к {base}: {e}")
        return False
    except Exception as e:
        _fail(f"Ошибка при подключении к {base}: {e}")
        return False

    # ── 4. Аутентификация ───────────────────────────────────────────────
    _header("4. Аутентификация (получение токена)")
    auth_url = f"{base}/access_token"
    payload = {"apiLogin": api_key}
    token = None
    try:
        t0 = time.time()
        async with httpx.AsyncClient(timeout=30.0) as client:
            resp = await client.post(
                auth_url,
                json=payload,
                headers={"Content-Type": "application/json", "Timeout": "45"},
            )
        elapsed = int((time.time() - t0) * 1000)

        if verbose:
            _info(f"Запрос: POST {auth_url}")
            _info(f"Тело запроса: {json.dumps(payload)}")
            _info(f"HTTP статус: {resp.status_code} ({elapsed} мс)")
            _info(f"Тело ответа: {resp.text[:500]}")

        if resp.status_code == 401 or resp.status_code == 403:
            _fail(
                f"Сервер iiko вернул {resp.status_code} — неверный API-ключ.\n"
                "     Возможные причины:\n"
                "       • Ключ скопирован не полностью или содержит лишние символы\n"
                "       • Ключ был отозван или заменён в личном кабинете iiko Cloud\n"
                "       • Ключ создан для другого окружения (тестовый/боевой)\n"
                "     Решение: откройте https://api-ru.iiko.services, войдите в личный кабинет,\n"
                "     скопируйте актуальный API Login и вставьте его в настройки."
            )
            all_ok = False
        elif resp.status_code == 200:
            # Parse token
            try:
                data = resp.json()
                if isinstance(data, dict):
                    token = data.get("token") or data.get("access_token") or ""
                elif isinstance(data, str):
                    token = data.strip().strip('"')
            except Exception:
                token = resp.text.strip().strip('"')

            if token:
                _ok(f"Токен получен успешно! (длина: {len(token)} символов)")
            else:
                _warn("Сервер вернул 200, но токен пуст. Это может быть ошибкой ключа.")
                all_ok = False
        else:
            _fail(f"Неожиданный HTTP статус: {resp.status_code}. Ответ: {resp.text[:300]}")
            all_ok = False
    except httpx.TimeoutException:
        _fail(f"Тайм-аут запроса аутентификации к {auth_url}")
        return False
    except Exception as e:
        _fail(f"Ошибка запроса аутентификации: {e}")
        return False

    # ── 5. Пробный запрос (организации) ─────────────────────────────────
    if token:
        _header("5. Пробный запрос — список организаций")
        orgs_url = f"{base}/organizations"
        try:
            async with httpx.AsyncClient(timeout=30.0) as client:
                resp = await client.post(
                    orgs_url,
                    json={},
                    headers={
                        "Content-Type": "application/json",
                        "Timeout": "45",
                        "Authorization": f"Bearer {token}",
                    },
                )
            if resp.status_code == 200:
                data = resp.json()
                org_list = data.get("organizations", [])
                _ok(f"Организации получены: {len(org_list)} шт.")
                for org in org_list[:5]:
                    name = org.get("name", "—")
                    org_id = org.get("id", "—")
                    _info(f"  • {name} (id: {org_id})")
                if len(org_list) > 5:
                    _info(f"  ... и ещё {len(org_list) - 5}")
            else:
                _warn(f"Запрос организаций вернул HTTP {resp.status_code}: {resp.text[:200]}")
        except Exception as e:
            _warn(f"Не удалось получить организации: {e}")
    else:
        _header("5. Пробный запрос — пропущен (нет токена)")

    # ── Итог ────────────────────────────────────────────────────────────
    _header("ИТОГ")
    if all_ok and token:
        _ok("Все проверки пройдены. Подключение к iiko API работает корректно!")
    else:
        _fail(
            "Обнаружены проблемы. Исправьте ошибки выше и запустите скрипт повторно.\n"
            "     Используйте --verbose для подробного вывода."
        )
    return all_ok and token is not None


def _try_load_env_key() -> str:
    """Попробовать загрузить IIKO_API_KEY из переменных окружения или .env файла."""
    key = os.environ.get("IIKO_API_KEY", "")
    if key:
        return key
    # Try loading from .env in backend directory
    for env_path in [
        os.path.join(os.path.dirname(__file__), "..", "backend", ".env"),
        os.path.join(os.path.dirname(__file__), "..", ".env"),
    ]:
        env_path = os.path.abspath(env_path)
        if os.path.isfile(env_path):
            with open(env_path) as f:
                for line in f:
                    line = line.strip()
                    if line.startswith("IIKO_API_KEY="):
                        return line.split("=", 1)[1].strip().strip('"').strip("'")
    return ""


def main():
    parser = argparse.ArgumentParser(
        description="Диагностика подключения к iiko Cloud API",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog=__doc__,
    )
    parser.add_argument(
        "--api-key",
        default=None,
        help="API-ключ (apiLogin) iiko Cloud. Если не указан — берётся из IIKO_API_KEY.",
    )
    parser.add_argument(
        "--api-url",
        default=DEFAULT_API_URL,
        help=f"URL iiko Cloud API (по умолчанию: {DEFAULT_API_URL})",
    )
    parser.add_argument(
        "--verbose", "-v",
        action="store_true",
        help="Показать подробный вывод (тела запросов/ответов)",
    )
    args = parser.parse_args()

    api_key = args.api_key or _try_load_env_key()

    print("=" * 60)
    print("  ДИАГНОСТИКА ПОДКЛЮЧЕНИЯ К iiko Cloud API")
    print("=" * 60)

    ok = asyncio.run(run_diagnostics(api_key, args.api_url, args.verbose))
    sys.exit(0 if ok else 1)


if __name__ == "__main__":
    main()

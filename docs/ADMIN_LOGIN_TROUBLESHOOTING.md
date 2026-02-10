# Руководство по решению проблем с авторизацией администратора

## Проблемы
1. При попытке войти в админ-панель с учетными данными по умолчанию появляется ошибка "Неверные учетные данные", даже после сброса пароля.
2. Ошибка при запуске скрипта: `python3: can't open file '/var/www/iiko-base/scripts/verify_admin_login.py': [Errno 2] No such file or directory`

## Учетные данные по умолчанию
- **Имя пользователя:** `admin`
- **Пароль:** `12101991Qq!`

⚠️ **ВАЖНО:** После успешного входа немедленно смените пароль на уникальный и безопасный!

## Пошаговая диагностика и решение

### Шаг 1: Проверка состояния системы

Убедитесь, что все необходимые сервисы запущены:

```bash
# Проверка PostgreSQL
sudo systemctl status postgresql

# Проверка backend
sudo systemctl status iiko-backend

# Проверка PHP-FPM
sudo systemctl status php8.3-fpm  # или php-fpm

# Проверка Nginx
sudo systemctl status nginx
```

Если какой-то сервис не запущен, запустите его:
```bash
sudo systemctl start postgresql
sudo systemctl start iiko-backend
```

### Шаг 2: Автоматическая проверка

**Вариант A: Простая проверка базы данных (рекомендуется):**

```bash
cd /var/www/iiko-base  # или ваш путь к проекту
./scripts/check_admin_db.sh
```

Этот скрипт:
- Не требует Python зависимостей
- Проверяет подключение к базе данных
- Проверяет наличие пользователя admin
- Сравнивает хэш пароля с ожидаемым
- Показывает статус пользователя (активен/неактивен)

**Вариант B: Полная проверка с Python:**

```bash
cd /var/www/iiko-base  # или ваш путь к проекту
python3 scripts/verify_admin_login.py
```

⚠️ **Примечание:** Если файл `verify_admin_login.py` не найден или возникают ошибки импорта, используйте Вариант A.

Этот скрипт проверит:
- Подключение к базе данных
- Наличие пользователя admin
- Корректность хэша пароля
- Активность пользователя
- Возможность верификации пароля через bcrypt

Если скрипт показывает ✅ на всех проверках, значит логин должен работать.

### Шаг 3: Сброс пароля администратора

Если проверка показала, что пароль не совпадает, сбросьте его:

**Способ 1: Автоматический скрипт (рекомендуется)**
```bash
cd /var/www/iiko-base
./scripts/reset_admin_password.sh
```

**Способ 2: SQL файл**
```bash
cd /var/www/iiko-base
psql -h localhost -U iiko_user -d iiko_db -f database/reset_admin.sql
```

При запросе пароля введите: `12101991Qq!`

**Способ 3: Прямая команда SQL**
```bash
PGPASSWORD="12101991Qq!" psql -h localhost -U iiko_user -d iiko_db << 'SQL'
DELETE FROM users WHERE username = 'admin';
INSERT INTO users (email, username, hashed_password, role, is_active, is_superuser)
VALUES (
    'admin@example.com',
    'admin',
    '$2b$12$y4QVNPhuZfpLp1.xM6.NSeDnpD6I/wm.dSOXGrxV.HtXj6izHJLPa',
    'admin',
    TRUE,
    TRUE
);
SQL
```

### Шаг 4: Проверка логов

Если логин все еще не работает, проверьте логи backend:

```bash
# Последние 50 строк логов
sudo journalctl -u iiko-backend -n 50

# Мониторинг логов в реальном времени
sudo journalctl -u iiko-backend -f
```

Попробуйте войти и посмотрите, какая ошибка появляется в логах.

### Шаг 5: Проверка конфигурации frontend

Убедитесь, что Laravel правильно настроен для подключения к backend API:

```bash
cd /var/www/iiko-base/frontend
cat .env | grep BACKEND_API_URL
```

Должно быть что-то вроде:
```
BACKEND_API_URL=http://localhost:8000/api/v1
```
или
```
BACKEND_API_URL=https://api.vezuroll.ru/api/v1
```

Если URL неправильный, исправьте его в файле `.env`.

### Шаг 6: Тестирование API напрямую

Проверьте, что backend API работает:

```bash
# Тест health check
curl http://localhost:8000/health

# Тест логина (замените на свой backend URL если нужно)
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"12101991Qq!"}'
```

Ответ должен содержать `access_token`. Если получаете ошибку, значит проблема в backend.

## Частые проблемы и решения

### Проблема: "Failed to connect to database"
**Решение:**
1. Проверьте, что PostgreSQL запущен: `sudo systemctl start postgresql`
2. Проверьте credentials в `/var/www/iiko-base/backend/.env`
3. Убедитесь, что БД существует: `psql -h localhost -U iiko_user -l`

### Проблема: "Admin user NOT found"
**Решение:**
```bash
# Запустите schema.sql для создания пользователя
psql -h localhost -U iiko_user -d iiko_db -f database/schema.sql
```

### Проблема: "Admin user is INACTIVE"
**Решение:**
```bash
PGPASSWORD="12101991Qq!" psql -h localhost -U iiko_user -d iiko_db -c \
  "UPDATE users SET is_active = TRUE WHERE username = 'admin';"
```

### Проблема: "Password does NOT match"
**Решение:**
Запустите скрипт сброса пароля (см. Шаг 3).

### Проблема: Backend не запускается
**Решение:**
```bash
# Проверьте логи
sudo journalctl -u iiko-backend -n 100

# Попробуйте запустить вручную для отладки
cd /var/www/iiko-base/backend
source venv/bin/activate
uvicorn app.main:app --host 0.0.0.0 --port 8000
```

### Проблема: "Connection refused" при логине
**Решение:**
1. Убедитесь что backend запущен: `sudo systemctl status iiko-backend`
2. Проверьте URL в `frontend/.env`
3. Проверьте, что порт 8000 открыт: `sudo netstat -tlnp | grep 8000`

## Важные замечания

1. **Пробелы в пароле:** Убедитесь, что при вводе пароля нет лишних пробелов в начале или конце.

2. **Регистр:** Пароль `12101991Qq!` чувствителен к регистру. Убедитесь, что используете заглавные буквы Q в правильных местах.

3. **Безопасность:** После успешного входа **обязательно смените пароль** на новый, уникальный.

4. **Кодировка:** Если копируете пароль из документации, убедитесь, что не копируете лишние символы или пробелы.

## Дополнительная помощь

Если проблема не решается:

1. Запустите полную диагностику:
```bash
python3 scripts/verify_admin_login.py > admin_check.log 2>&1
cat admin_check.log
```

2. Проверьте версии зависимостей:
```bash
cd /var/www/iiko-base/backend
source venv/bin/activate
pip list | grep -E "(bcrypt|passlib)"
```

Должно быть:
- `bcrypt==4.0.1`
- `passlib==1.7.4`

3. Создайте issue на GitHub с:
   - Выводом команды `python3 scripts/verify_admin_login.py`
   - Логами backend: `sudo journalctl -u iiko-backend -n 100`
   - Версией системы: `lsb_release -a`

## Контакты

- GitHub Issues: https://github.com/dovezukatmn/iiko-base/issues
- Документация: https://github.com/dovezukatmn/iiko-base/tree/main/docs

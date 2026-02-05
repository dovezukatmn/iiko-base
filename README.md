# iiko-base

![Python](https://img.shields.io/badge/Python-3.10+-blue.svg)
![FastAPI](https://img.shields.io/badge/FastAPI-0.109.1+-green.svg)
![Laravel](https://img.shields.io/badge/Laravel-10.x-red.svg)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-12+-blue.svg)
![License](https://img.shields.io/badge/license-MIT-green.svg)
![Security](https://img.shields.io/badge/security-patched-brightgreen.svg)

Полностью настроенное рабочее пространство для разработки на стеке **Python + Laravel + PostgreSQL** с упором на новичков и автоматический деплой на VPS.

> 🔒 **Security Update**: All dependencies updated to patched versions. See [SECURITY.md](SECURITY.md) for details.

## 🚀 Особенности

- ✅ **Python Backend (FastAPI 0.109.1+)** - современный REST API с безопасными зависимостями
- ✅ **Laravel Admin Panel** - административная панель
- ✅ **PostgreSQL** - надежная база данных
- ✅ **Nginx** - производительный веб-сервер
- ✅ **Автоматическая установка** - один скрипт для всего
- ✅ **Автодеплой** - GitHub Actions и Git hooks
- ✅ **Готово для production** - SSL, systemd, мониторинг, безопасные зависимости
- ✅ **Документация для новичков** - пошаговые инструкции

## 📋 Требования

- **VPS**: Ubuntu 20.04/22.04 (Jino, DigitalOcean, Hetzner и др.)
- **RAM**: минимум 1 GB (рекомендуется 2 GB)
- **Disk**: минимум 10 GB
- **Доступ**: SSH с правами root/sudo

## 🎯 Быстрый старт

### 1. Клонируйте репозиторий

```bash
cd /var/www
git clone https://github.com/dovezukatmn/iiko-base.git
cd iiko-base
```

### 2. Запустите автоматическую установку

```bash
chmod +x scripts/*.sh
sudo ./scripts/install.sh
```

### 3. Настройте окружение

```bash
./scripts/setup.sh
```

### 4. Деплой

```bash
sudo ./scripts/deploy.sh
```

## 📚 Документация

- **[Руководство по установке](docs/INSTALLATION.md)** - подробная инструкция по установке
- **[Руководство для новичков](docs/BEGINNER_GUIDE.md)** - если вы только начинаете
- **[Архитектура проекта](docs/ARCHITECTURE.md)** - как все устроено

## 🏗️ Структура проекта

```
iiko-base/
├── backend/              # Python FastAPI приложение
│   ├── app/             # Логика приложения
│   ├── config/          # Конфигурация
│   ├── database/        # Модели и подключение к БД
│   └── requirements.txt # Python зависимости
├── frontend/            # Laravel административная панель
│   ├── app/            # Логика Laravel
│   ├── config/         # Конфигурация Laravel
│   └── composer.json   # PHP зависимости
├── database/           # SQL скрипты
│   ├── init.sql       # Инициализация БД
│   └── schema.sql     # Схема таблиц
├── nginx/              # Конфигурация Nginx
│   ├── iiko-base.conf     # HTTP конфигурация
│   └── iiko-base-ssl.conf # HTTPS конфигурация
├── scripts/            # Скрипты автоматизации
│   ├── install.sh     # Установка зависимостей
│   ├── setup.sh       # Настройка окружения
│   ├── deploy.sh      # Деплой приложения
│   ├── backup.sh      # Резервное копирование
│   └── restore.sh     # Восстановление из backup
├── docs/               # Документация
│   ├── INSTALLATION.md    # Руководство по установке
│   ├── BEGINNER_GUIDE.md  # Руководство для новичков
│   └── ARCHITECTURE.md    # Архитектура проекта
└── SECURITY.md         # Информация о безопасности
```

## 🔐 Безопасность

**Все зависимости обновлены до безопасных версий:**
- ✅ FastAPI 0.109.1+ (устранена уязвимость ReDoS)
- ✅ python-multipart 0.0.22+ (устранены 4 критические уязвимости)
- ✅ Подробности в [SECURITY.md](SECURITY.md)

**Встроенные меры безопасности:**
- ✅ PostgreSQL с изолированным пользователем
- ✅ Шифрование паролей (bcrypt)
- ✅ SSL/TLS поддержка (Let's Encrypt)
- ✅ CORS настройки
- ✅ Firewall инструкции

## 🔧 Конфигурация

### Backend (.env)

```ini
DATABASE_URL=postgresql://iiko_user:password@localhost:5432/iiko_db
SECRET_KEY=your-secret-key
BACKEND_CORS_ORIGINS=["https://yourdomain.com"]
```

### Frontend (.env)

```ini
APP_URL=https://yourdomain.com
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_DATABASE=iiko_db
DB_USERNAME=iiko_user
DB_PASSWORD=password
BACKEND_API_URL=https://api.yourdomain.com/api/v1
```

## 🌐 API Endpoints

После запуска доступны:

- **API Documentation**: `http://your-server:8000/docs`
- **Health Check**: `http://your-server:8000/health`
- **Menu API**: `http://your-server:8000/api/v1/menu`
- **Users API**: `http://your-server:8000/api/v1/users`
- **Admin Panel**: `http://your-domain.com`

## 🔐 Безопасность

**Обновленные зависимости (2024-02-05):**
- ✅ FastAPI 0.109.1+ - устранены уязвимости ReDoS
- ✅ python-multipart 0.0.22+ - устранены критические уязвимости
- ✅ См. [SECURITY.md](SECURITY.md) для подробностей

**Встроенная безопасность:**
- ✅ PostgreSQL с отдельным пользователем
- ✅ Шифрование паролей (bcrypt)
- ✅ SSL/TLS поддержка (Let's Encrypt)
- ✅ CORS настройки
- ✅ Firewall правила

## 📊 Мониторинг

### Проверка статуса сервисов

```bash
systemctl status iiko-backend
systemctl status nginx
systemctl status postgresql
```

### Просмотр логов

```bash
# Backend логи
journalctl -u iiko-backend -f

# Nginx логи
tail -f /var/log/nginx/error.log

# Laravel логи
tail -f frontend/storage/logs/laravel.log
```

## 🔄 Автодеплой

### GitHub Actions

Настройте секреты в GitHub:
- `VPS_HOST` - IP адрес сервера
- `VPS_USERNAME` - SSH пользователь
- `VPS_SSH_KEY` - SSH приватный ключ

Деплой происходит автоматически при push в main/master ветку.

### Git Hooks

```bash
# На сервере
cp scripts/post-receive.hook ~/iiko-base-repo.git/hooks/post-receive
chmod +x ~/iiko-base-repo.git/hooks/post-receive
```

## 💾 Backup

### Создание backup

```bash
./scripts/backup.sh
```

Backup сохраняются в `/var/backups/iiko-base/`

### Восстановление

```bash
./scripts/restore.sh /path/to/backup.sql.gz
```

### Автоматический backup (cron)

```bash
# Ежедневный backup в 2:00
0 2 * * * /var/www/iiko-base/scripts/backup.sh
```

## 🛠️ Разработка

### Запуск локально (Python backend)

```bash
cd backend
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
uvicorn app.main:app --reload
```

### Запуск локально (Laravel frontend)

```bash
cd frontend
composer install
php artisan serve
```

## 🐛 Решение проблем

### Backend не запускается

```bash
# Проверьте логи
journalctl -u iiko-backend -n 100

# Проверьте .env файл
cat backend/.env

# Перезапустите сервис
systemctl restart iiko-backend
```

### Nginx показывает 502

```bash
# Проверьте, запущен ли backend
systemctl status iiko-backend

# Проверьте конфигурацию Nginx
nginx -t

# Перезапустите Nginx
systemctl restart nginx
```

### Ошибки базы данных

```bash
# Проверьте статус PostgreSQL
systemctl status postgresql

# Проверьте подключение
psql -U iiko_user -d iiko_db -h localhost
```

## 📝 TODO

- [ ] Добавить JWT аутентификацию
- [ ] Интеграция с iiko API
- [ ] WebSocket поддержка для real-time уведомлений
- [ ] Docker контейнеризация
- [ ] Тесты (pytest для Python, PHPUnit для Laravel)
- [ ] CI/CD pipeline
- [ ] Monitoring dashboard (Grafana)

## 🤝 Вклад

Contributions, issues и feature requests приветствуются!

1. Fork проекта
2. Создайте feature ветку (`git checkout -b feature/AmazingFeature`)
3. Commit изменения (`git commit -m 'Add some AmazingFeature'`)
4. Push в ветку (`git push origin feature/AmazingFeature`)
5. Откройте Pull Request

## 📄 Лицензия

Этот проект распространяется под лицензией MIT. См. файл `LICENSE` для подробностей.

## 👥 Автор

**dovezukatmn**

## 🌟 Поддержка

Если этот проект был полезен, поставьте ⭐️!

## 📞 Контакты

- GitHub Issues: [создать issue](https://github.com/dovezukatmn/iiko-base/issues)
- Email: [ваш email]

---

**Создано с ❤️ для сообщества разработчиков**
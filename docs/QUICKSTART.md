# Быстрый старт - 5 минут

Это самое короткое руководство для запуска проекта. Для подробностей смотрите [INSTALLATION.md](INSTALLATION.md).

## Для VPS (Production)

### 1. Подключитесь к серверу
```bash
ssh root@your-server-ip
```

### 2. Установите проект
```bash
cd /var/www
git clone https://github.com/dovezukatmn/iiko-base.git
cd iiko-base
chmod +x scripts/*.sh
./scripts/install.sh
```

⏱️ Займет 5-10 минут

### 3. Настройте
```bash
./scripts/setup.sh
```

Ответьте на вопросы:
- Инициализировать БД? → `y`
- Пользователь → `Enter` (по умолчанию)
- Пароль → введите и запомните
- База данных → `Enter` (по умолчанию)

### 4. Измените настройки

**Backend:**
```bash
nano backend/.env
```
Измените `your_password` на пароль 12101991Qq!

**Frontend:**
```bash
nano frontend/.env
```
Измените `your_password` на пароль 12101991Qq!

**Nginx:**
```bash
nano nginx/iiko-base.conf
```
Замените `yourdomain.com` на ваш домен (vezuroll.ru или b1d8d8270d0f.vps.myjino.ru) или IP.

### 5. Запустите
```bash
./scripts/deploy.sh
```

### 6. Проверьте
Откройте в браузере:
- `http://your-server-ip:8000/docs` - API документация
- `http://your-server-ip:8000/health` - Проверка здоровья

✅ **Готово!** Проект запущен!

## Для локальной разработки (Docker)

### 1. Установите Docker
- Windows/Mac: https://www.docker.com/products/docker-desktop
- Linux: `sudo apt install docker.io docker-compose`

### 2. Клонируйте и запустите
```bash
git clone https://github.com/dovezukatmn/iiko-base.git
cd iiko-base
docker-compose up -d
```

### 3. Проверьте
- `http://localhost:8000/docs` - API
- `http://localhost/health` - через Nginx

✅ **Готово!** Работает локально!

## Следующие шаги

1. **Настройте домен** - см. [INSTALLATION.md#настройка-домена](INSTALLATION.md#настройка-домена)
2. **Получите SSL** - см. [INSTALLATION.md#ssl-сертификаты](INSTALLATION.md#ssl-сертификаты)
3. **Настройте автодеплой** - см. [INSTALLATION.md#автодеплой](INSTALLATION.md#автодеплой)

## Помощь

**Что-то не работает?**
- [Решение проблем](INSTALLATION.md#решение-проблем)
- [Руководство для новичков](BEGINNER_GUIDE.md)
- [Создайте issue](https://github.com/dovezukatmn/iiko-base/issues)

## Полезные команды

```bash
# Проверка статуса
systemctl status iiko-backend
systemctl status nginx

# Просмотр логов
journalctl -u iiko-backend -f
tail -f /var/log/nginx/error.log

# Перезапуск
systemctl restart iiko-backend
systemctl restart nginx

# Backup базы данных
./scripts/backup.sh
```

**Создано для разработчиков, с ❤️**

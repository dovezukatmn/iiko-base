# 🚨 Быстрое решение ошибки 502 Bad Gateway

## Самое быстрое решение (90% случаев)

```bash
sudo systemctl start php8.3-fpm
sudo systemctl enable php8.3-fpm
sudo systemctl restart nginx
```

Затем откройте http://vezuroll.ru в браузере.

---

## Если не помогло - полная диагностика

### 1️⃣ Проверьте все сервисы

```bash
sudo systemctl status php8.3-fpm
sudo systemctl status iiko-backend
sudo systemctl status nginx
```

### 2️⃣ Если что-то не запущено - запустите

```bash
# PHP-FPM
sudo systemctl start php8.3-fpm

# Python Backend
sudo systemctl start iiko-backend

# Nginx
sudo systemctl start nginx
```

### 3️⃣ Посмотрите логи

```bash
# Логи Nginx (показывает причину 502)
sudo tail -50 /var/log/nginx/error.log

# Логи PHP-FPM
sudo journalctl -u php8.3-fpm -n 50

# Логи Backend
sudo journalctl -u iiko-backend -n 50
```

### 4️⃣ Обновите код и перезапустите

```bash
cd /var/www/iiko-base
git pull origin main
sudo ./scripts/deploy.sh
```

---

## 📖 Подробная документация

Если проблема не решена:
1. Прочитайте [docs/502_ERROR_FIX.md](docs/502_ERROR_FIX.md)
2. Прочитайте [ИСПРАВЛЕНИЕ_502.md](ИСПРАВЛЕНИЕ_502.md)

---

## ⚡ Команды для копирования

**Проверка статуса всех сервисов:**
```bash
sudo systemctl status php8.3-fpm iiko-backend nginx postgresql
```

**Перезапуск всех сервисов:**
```bash
sudo systemctl restart php8.3-fpm iiko-backend nginx
```

**Просмотр всех логов:**
```bash
sudo tail -50 /var/log/nginx/error.log
sudo journalctl -u php8.3-fpm -n 50
sudo journalctl -u iiko-backend -n 50
```

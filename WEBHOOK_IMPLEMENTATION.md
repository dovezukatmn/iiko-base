# Полная реализация вебхуков iiko

## Обзор

Данная реализация полностью соответствует документации iiko SOI API и Cloud API для приема и обработки вебхуков в реальном времени.

## Основные возможности

### 1. Прием вебхуков (Webhook Handler)

**Endpoint:** `POST /api/v1/webhooks/iiko`

Обрабатывает все типы событий от iiko:

#### Форматы событий:

1. **SOI API (Simple Order Injection)**
   - `CREATE` - создание заказа
   - `UPDATE` - обновление заказа
   
2. **Cloud API**
   - `DeliveryOrderUpdate` - обновление статуса доставки
   - `DeliveryOrderError` - ошибка заказа
   
3. **Другие события**
   - `OrderChanged` - изменение заказа
   - `DeliveryOrderChanged` - изменение доставки
   - `StopListUpdate` - обновление стоп-листа
   - `PersonalShift` - смены сотрудников
   - `ReserveUpdate` - обновление резерва
   - `TableOrderError` - ошибка заказа за столом

### 2. Поддерживаемые данные заказа

Система сохраняет и обрабатывает:

- **Основные данные:**
  - `orderExternalId` - внешний ID заказа
  - `readableNumber` - человекочитаемый номер заказа
  - `iikoOrderId` - ID заказа в системе iiko
  - `organizationId` - ID организации
  
- **Статусы заказа (согласно SOI API):**
  - `Unconfirmed` - неподтвержденный
  - `WaitCooking` - ожидает начала приготовления
  - `ReadyForCooking` - готов к приготовлению
  - `CookingStarted` - приготовление начато
  - `CookingCompleted` - приготовление завершено
  - `Waiting` - ожидает выдачи
  - `OnWay` - в пути (курьер)
  - `Delivered` - доставлен
  - `Closed` - закрыт
  - `Cancelled` - отменен

- **Детали заказа:**
  - `promisedTime` - обещанное время доставки
  - `orderAmount` - сумма заказа
  - `orderType` - тип заказа (DELIVERY, PICKUP, etc.)
  - `restaurantName` - название ресторана/терминала
  - `problem` - описание проблемы (если есть)
  - `creationStatus` - статус создания (OK/Error)
  - `errorInfo` - информация об ошибке

- **Курьер:**
  - `courierId` - ID курьера
  - `courierName` - имя курьера

### 3. Асинхронная обработка

Вебхуки обрабатываются асинхронно:

1. Входящий запрос сразу возвращает `{"status": "OK"}` (чтобы iiko не повторял отправку)
2. Событие сохраняется в БД (`webhook_events`)
3. Обработка запускается в фоновой задаче
4. При ошибках обработки - сохраняется `processing_error`

### 4. Валидация и безопасность

- **Проверка токена авторизации:**
  - Заголовок `Authorization` или `authToken`
  - Сравнение с `webhook_secret` из настроек iiko
  - Используется `secrets.compare_digest()` для защиты от timing attacks

- **Обработка ошибок:**
  - Некорректный JSON → возврат 200 OK с `{"status": "ignored"}`
  - Это предотвращает повторные попытки iiko при ошибках парсинга

### 5. Логирование

Все события логируются с деталями:

```
[WEBHOOK] Получено событие: CREATE, external_id: 20200831-515
[SOI WEBHOOK] CREATE - заказ 20200831-515 (iiko: uuid), статус: CookingStarted, сумма: 1520.50
```

---

## Двусторонняя интеграция (Отправка изменений в iiko)

### API Endpoints для управления заказами:

#### 1. Обновить статус заказа

```
POST /api/v1/orders/{order_id}/update-status
Body: { "status": "Delivered" }
```

**Доступные статусы:** Unconfirmed, WaitCooking, ReadyForCooking, CookingStarted, CookingCompleted, Waiting, OnWay, Delivered, Closed, Cancelled

#### 2. Назначить курьера

```
POST /api/v1/orders/{order_id}/assign-courier
Body: { 
  "courier_id": "uuid",
  "courier_name": "Иван Иванов"
}
```

#### 3. Изменить способ оплаты

```
POST /api/v1/orders/{order_id}/update-payment
Body: {
  "payments": [
    {
      "paymentTypeKind": "Card",
      "sum": 1200.0
    }
  ]
}
```

**Типы оплаты:** Card, Cash, Online, etc.

#### 4. Применить скидку

```
POST /api/v1/orders/{order_id}/apply-discount
Body: {
  "discount_id": "uuid",      // ID скидки из номенклатуры
  "discount_sum": 150.0,      // или фиксированная сумма
  "discount_percent": 10.0    // или процент
}
```

#### 5. Отменить заказ

```
POST /api/v1/orders/{order_id}/cancel
Body: {
  "cancel_reason": "Клиент отказался от заказа"
}
```

---

## Схема базы данных

### Расширенная таблица `orders`

Новые поля для полной поддержки SOI API:

```sql
- external_order_id VARCHAR(255)     -- orderExternalId
- readable_number VARCHAR(100)       -- readableNumber
- promised_time TIMESTAMP            -- promisedTime
- courier_id VARCHAR(255)            -- ID курьера
- courier_name VARCHAR(255)          -- Имя курьера
- order_type VARCHAR(50)             -- DELIVERY, PICKUP, etc.
- restaurant_name VARCHAR(255)       -- restaurantName
- problem TEXT                       -- описание проблемы
- creation_status VARCHAR(50)        -- OK, Error
- error_info TEXT                    -- errorInfo from SOI
```

### Расширенная таблица `webhook_events`

```sql
- order_external_id VARCHAR(255)     -- для быстрого поиска
- organization_id VARCHAR(255)       -- из webhook payload
- processing_error TEXT              -- ошибки обработки
```

### Новая таблица `webhook_configs`

Для управления вебхуками нескольких организаций:

```sql
CREATE TABLE webhook_configs (
    id SERIAL PRIMARY KEY,
    organization_id VARCHAR(255) NOT NULL,
    webhook_url VARCHAR(500) NOT NULL,
    auth_token VARCHAR(255),
    is_active BOOLEAN DEFAULT true,
    last_registration TIMESTAMP,
    registration_status VARCHAR(50),
    registration_error TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP
);
```

---

## Регистрация вебхуков

### Автоматическая регистрация при запуске

В `backend/app/main.py` настроена автоматическая регистрация вебхука:

```python
async def register_webhook_on_startup():
    # Формируем URL вебхука
    webhook_url = f"{WEBHOOK_BASE_URL}/api/v1/webhooks/iiko"
    
    # Регистрируем в iiko
    result = await service.register_webhook(
        organization_id,
        webhook_url,
        auth_token
    )
```

### Фильтры событий

При регистрации включаются все важные события:

**Для заказов доставки:**
- Все статусы заказа
- Все статусы позиций (Added, CookingStarted, etc.)
- Ошибки

**Для столов:**
- Новые заказы
- Изменения позиций
- Ошибки

**Другие:**
- Обновления стоп-листа
- Резервы
- Смены персонала

---

## Интеграция с бонусной системой (iikoCard)

### Методы работы с бонусами:

#### 1. Начислить бонусы

```python
await service.topup_loyalty_balance(
    organization_id,
    customer_id,
    wallet_id,
    amount,
    comment="Бонусы за заказ #123"
)
```

#### 2. Списать бонусы

```python
await service.withdraw_loyalty_balance(
    organization_id,
    customer_id,
    wallet_id,
    amount,
    comment="Оплата заказа #123"
)
```

#### 3. Холдировать бонусы

```python
await service.hold_loyalty_balance(
    organization_id,
    customer_id,
    wallet_id,
    amount,
    comment="Холд для заказа #123"
)
```

---

## Примеры использования

### Пример 1: Прием CREATE события

**Входящий webhook от iiko:**

```json
{
  "type": "CREATE",
  "orderExternalId": "20200831-515",
  "readableNumber": "515",
  "creationStatus": "OK",
  "errorInfo": null,
  "transactionDetails": {
    "correlationId": "uuid",
    "organizationId": "org-uuid"
  },
  "iikoOrderDetails": {
    "iikoOrderId": "iiko-uuid",
    "iikoOrderNumber": "51",
    "restaurantName": "Ресторан №1",
    "orderType": "DELIVERY",
    "orderStatus": "COOKING_STARTED",
    "receivedAt": "2020-08-31T16:23:31+01:00",
    "promisedTime": "2020-08-31T18:08:31+01:00",
    "problem": null,
    "orderAmount": 1520.50
  }
}
```

**Результат:**
- Заказ создается в БД
- Статус нормализуется: `COOKING_STARTED` → `CookingStarted`
- Сумма конвертируется: 1520.50 руб → 152050 копеек
- Событие сохраняется в `webhook_events`

### Пример 2: Назначение курьера из админки

```python
# Оператор назначает курьера через API
POST /api/v1/orders/123/assign-courier
{
  "courier_id": "courier-uuid-123",
  "courier_name": "Петр Петров"
}

# → Отправляется в iiko Cloud API
# → Обновляется локальная БД
# → iiko отправляет обратный webhook с подтверждением
```

---

## Мониторинг и отладка

### Просмотр истории вебхуков

```
GET /api/v1/webhooks/events?skip=0&limit=50
```

Возвращает список всех полученных событий с:
- Типом события
- Временем получения
- Статусом обработки
- Ошибками (если были)

### Просмотр логов API

```
GET /api/v1/logs?skip=0&limit=50
```

Показывает все запросы к iiko API:
- URL запроса
- Тело запроса
- Статус ответа
- Тело ответа
- Время выполнения (ms)

---

## Требования к инфраструктуре

### 1. Переменные окружения

В `.env` файле:

```bash
# iiko Cloud API
IIKO_API_KEY=ваш_api_key_от_iiko
IIKO_API_URL=https://api-ru.iiko.services/api/1

# Вебхуки
WEBHOOK_BASE_URL=https://ваш-домен.ru
WEBHOOK_SECRET_KEY=случайная_строка_для_защиты
```

### 2. Публичный домен

Вебхук должен быть доступен по HTTPS из интернета:
- iiko отправляет запросы на ваш webhook URL
- URL должен быть стабильным и доступным 24/7
- Рекомендуется использовать nginx или аналог для reverse proxy

### 3. База данных

Миграция для добавления новых полей:

```bash
psql -U postgres -d iiko_base < database/migrations/002_webhook_improvements.sql
```

---

## Рекомендации по использованию

### 1. Обработка статусов

Используйте маппинг статусов для единообразия:

```python
STATUS_MAPPING = {
    "COOKING_STARTED": "CookingStarted",
    "ON_WAY": "OnWay",
    # etc.
}
```

### 2. Идемпотентность

Вебхуки могут приходить повторно. Обработка должна быть идемпотентной:
- Проверяйте существование заказа по `external_order_id`
- Обновляйте, а не создавайте дубликаты

### 3. Асинхронность

Не выполняйте тяжелые операции в основном потоке обработки вебхука:
- Используйте фоновые задачи
- Быстро возвращайте 200 OK

### 4. Логирование

Логируйте все важные события:
- Получение вебхука
- Создание/обновление заказа
- Ошибки обработки
- Отправку изменений в iiko

---

## Справочные ссылки

1. **iiko Cloud API Settings:** https://ru.iiko.help
2. **iiko SOI API:** https://documenter.getpostman.com/view/1488033/iiko-soi-api
3. **iiko Web API:** https://documenter.getpostman.com/view/1488033/iiko-web-api
4. **Настройка источника заказов:** https://mini.iiko.help
5. **iikoFront API:** https://rapid.iiko.ru

---

## Поддержка

Все основные компоненты реализованы и готовы к работе:

✅ Прием вебхуков SOI API (CREATE/UPDATE)  
✅ Прием вебхуков Cloud API  
✅ Асинхронная обработка событий  
✅ Сохранение полных данных заказа  
✅ Обработка всех статусов  
✅ Обработка курьеров  
✅ Двусторонняя интеграция (отправка изменений в iiko)  
✅ Работа с бонусами iikoCard  
✅ Валидация и безопасность  
✅ Логирование и мониторинг  

Система готова к production использованию!

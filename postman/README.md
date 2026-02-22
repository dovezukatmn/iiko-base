# Postman Collection для тестирования вебхуков iiko

## Описание

Эта коллекция содержит примеры запросов для тестирования системы вебхуков iiko и двусторонней интеграции.

## Переменные окружения

Создайте новое окружение в Postman и добавьте следующие переменные:

```
baseUrl=http://localhost:8000/api/v1
webhookAuthToken=your_webhook_secret_key
token=
orgId=
orderId=
```

## Основные запросы

### 1. Отправка тестового вебхука CREATE

```bash
POST {{baseUrl}}/webhooks/iiko
Headers:
  authToken: {{webhookAuthToken}}
  Content-Type: application/json

Body:
{
  "type": "CREATE",
  "orderExternalId": "TEST-001",
  "readableNumber": "001",
  "creationStatus": "OK",
  "transactionDetails": {
    "organizationId": "{{orgId}}"
  },
  "iikoOrderDetails": {
    "iikoOrderId": "test-001",
    "orderStatus": "COOKING_STARTED",
    "orderAmount": 1500.00
  }
}
```

### 2. Отправка тестового вебхука UPDATE

```bash
POST {{baseUrl}}/webhooks/iiko
Headers:
  authToken: {{webhookAuthToken}}

Body:
{
  "type": "UPDATE",
  "orderExternalId": "TEST-001",
  "iikoOrderDetails": {
    "iikoOrderId": "test-001",
    "orderStatus": "DELIVERED"
  }
}
```

### 3. Обновление статуса заказа в iiko

```bash
POST {{baseUrl}}/orders/{{orderId}}/update-status?status=Delivered
Headers:
  Authorization: Bearer {{token}}
```

### 4. Назначение курьера

```bash
POST {{baseUrl}}/orders/{{orderId}}/assign-courier?courier_id=UUID&courier_name=Иван Иванов
Headers:
  Authorization: Bearer {{token}}
```

### 5. Применение скидки

```bash
POST {{baseUrl}}/orders/{{orderId}}/apply-discount?discount_sum=150.0
Headers:
  Authorization: Bearer {{token}}
```

## Использование с curl

### Отправка тестового вебхука:

```bash
curl -X POST http://localhost:8000/api/v1/webhooks/iiko \
  -H "authToken: your_webhook_secret" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "CREATE",
    "orderExternalId": "TEST-001",
    "readableNumber": "001",
    "creationStatus": "OK",
    "transactionDetails": {
      "organizationId": "your-org-id"
    },
    "iikoOrderDetails": {
      "iikoOrderId": "test-001",
      "orderStatus": "COOKING_STARTED",
      "orderAmount": 1500.00
    }
  }'
```

### Получение истории вебхуков:

```bash
curl -X GET "http://localhost:8000/api/v1/webhooks/events?skip=0&limit=10" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## Последовательность тестирования

1. **Авторизация** - получите токен через `/auth/login`
2. **Отправка CREATE вебхука** - создайте тестовый заказ
3. **Проверка истории** - убедитесь что событие сохранилось
4. **Просмотр заказов** - найдите созданный заказ
5. **Отправка UPDATE вебхука** - измените статус
6. **Двусторонняя интеграция** - попробуйте обновить заказ из админки

## Автоматические тесты

В коллекции настроены автоматические тесты для проверки:

- Статус ответа 200 OK
- Наличие обязательных полей в ответе
- Сохранение переменных для последующих запросов


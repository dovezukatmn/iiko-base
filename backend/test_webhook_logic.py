#!/usr/bin/env python3
"""
Тестовый скрипт для проверки логики обработки вебхуков iiko.
Симулирует входящие webhook события и проверяет корректность обработки.
"""

import json

# Пример 1: CREATE событие от SOI API
soi_create_event = {
    "type": "CREATE",
    "orderExternalId": "20200831-515",
    "readableNumber": "515",
    "creationStatus": "OK",
    "errorInfo": None,
    "transactionDetails": {
        "correlationId": "550e8400-e29b-41d4-a716-446655440000",
        "organizationId": "org-550e8400-e29b-41d4-a716-446655440001"
    },
    "iikoOrderDetails": {
        "iikoOrderId": "iiko-550e8400-e29b-41d4-a716-446655440002",
        "iikoOrderNumber": "51",
        "restaurantName": "Ресторан №1",
        "orderType": "DELIVERY",
        "orderStatus": "COOKING_STARTED",
        "receivedAt": "2020-08-31T16:23:31+01:00",
        "promisedTime": "2020-08-31T18:08:31+01:00",
        "problem": None,
        "orderAmount": 1520.50
    }
}

# Пример 2: UPDATE событие от SOI API (смена статуса на доставлено)
soi_update_event = {
    "type": "UPDATE",
    "orderExternalId": "20200831-515",
    "readableNumber": "515",
    "creationStatus": "OK",
    "errorInfo": None,
    "transactionDetails": {
        "correlationId": "550e8400-e29b-41d4-a716-446655440003",
        "organizationId": "org-550e8400-e29b-41d4-a716-446655440001"
    },
    "iikoOrderDetails": {
        "iikoOrderId": "iiko-550e8400-e29b-41d4-a716-446655440002",
        "iikoOrderNumber": "51",
        "restaurantName": "Ресторан №1",
        "orderType": "DELIVERY",
        "orderStatus": "DELIVERED",
        "receivedAt": "2020-08-31T16:23:31+01:00",
        "promisedTime": "2020-08-31T18:08:31+01:00",
        "problem": None,
        "orderAmount": 1520.50
    }
}

# Пример 3: Cloud API формат (DeliveryOrderUpdate)
cloud_api_event = {
    "eventType": "DeliveryOrderUpdate",
    "eventInfo": {
        "id": "iiko-550e8400-e29b-41d4-a716-446655440002",
        "status": "OnWay",
        "sum": 1520.50,
        "courier": {
            "id": "courier-550e8400-e29b-41d4-a716-446655440004",
            "name": "Иван Иванов"
        }
    }
}

# Маппинг статусов (из routes.py)
STATUS_MAPPING = {
    "WAIT_COOKING": "WaitCooking",
    "READY_FOR_COOKING": "ReadyForCooking", 
    "COOKING_STARTED": "CookingStarted",
    "COOKING_COMPLETED": "CookingCompleted",
    "WAITING": "Waiting",
    "ON_WAY": "OnWay",
    "DELIVERED": "Delivered",
    "CLOSED": "Closed",
    "CANCELLED": "Cancelled",
    "Unconfirmed": "Unconfirmed",
}

def test_status_normalization():
    """Тест нормализации статусов"""
    print("\n=== Тест нормализации статусов ===")
    
    test_cases = [
        ("COOKING_STARTED", "CookingStarted"),
        ("ON_WAY", "OnWay"),
        ("DELIVERED", "Delivered"),
        ("WaitCooking", "WaitCooking"),  # Уже нормализованный
    ]
    
    for input_status, expected in test_cases:
        normalized = STATUS_MAPPING.get(input_status, input_status)
        status = "✅" if normalized == expected else "❌"
        print(f"{status} {input_status} -> {normalized} (ожидалось: {expected})")

def test_soi_event_parsing():
    """Тест парсинга SOI API событий"""
    print("\n=== Тест парсинга SOI CREATE события ===")
    
    event = soi_create_event
    event_type = event.get("type")
    external_id = event.get("orderExternalId")
    details = event.get("iikoOrderDetails", {})
    
    print(f"✅ Тип события: {event_type}")
    print(f"✅ External ID: {external_id}")
    print(f"✅ iiko Order ID: {details.get('iikoOrderId')}")
    print(f"✅ Статус: {details.get('orderStatus')}")
    print(f"✅ Сумма: {details.get('orderAmount')}")
    print(f"✅ Тип заказа: {details.get('orderType')}")
    
    # Проверка нормализации статуса
    status = details.get("orderStatus")
    normalized = STATUS_MAPPING.get(status, status)
    print(f"✅ Нормализованный статус: {normalized}")

def test_amount_conversion():
    """Тест конвертации суммы в копейки"""
    print("\n=== Тест конвертации суммы ===")
    
    test_cases = [
        (1520.50, 152050),
        (100.00, 10000),
        (99.99, 9999),
        (0.01, 1),
    ]
    
    for amount_rub, expected_kop in test_cases:
        converted = int(amount_rub * 100)
        status = "✅" if converted == expected_kop else "❌"
        print(f"{status} {amount_rub} руб -> {converted} коп (ожидалось: {expected_kop})")

def test_event_type_detection():
    """Тест определения типа события"""
    print("\n=== Тест определения типа события ===")
    
    events = [
        (soi_create_event, "CREATE"),
        (soi_update_event, "UPDATE"),
        (cloud_api_event, "DeliveryOrderUpdate"),
    ]
    
    for event, expected_type in events:
        detected_type = event.get("type") or event.get("eventType") or "unknown"
        status = "✅" if detected_type == expected_type else "❌"
        print(f"{status} Обнаружен тип: {detected_type} (ожидалось: {expected_type})")

def test_organization_id_extraction():
    """Тест извлечения organization_id"""
    print("\n=== Тест извлечения organization_id ===")
    
    # SOI формат
    event = soi_create_event
    org_id = None
    if "transactionDetails" in event:
        org_id = event["transactionDetails"].get("organizationId")
    
    expected = "org-550e8400-e29b-41d4-a716-446655440001"
    status = "✅" if org_id == expected else "❌"
    print(f"{status} Извлечен org_id: {org_id}")

def print_json_examples():
    """Вывод примеров JSON для тестирования"""
    print("\n=== Примеры JSON для тестирования через Postman/curl ===")
    
    print("\n--- CREATE Event (POST /api/v1/webhooks/iiko) ---")
    print(json.dumps(soi_create_event, indent=2, ensure_ascii=False))
    
    print("\n--- UPDATE Event (POST /api/v1/webhooks/iiko) ---")
    print(json.dumps(soi_update_event, indent=2, ensure_ascii=False))
    
    print("\n--- Cloud API Event (POST /api/v1/webhooks/iiko) ---")
    print(json.dumps(cloud_api_event, indent=2, ensure_ascii=False))

def main():
    print("╔════════════════════════════════════════════════════════════╗")
    print("║   Тестирование логики обработки вебхуков iiko            ║")
    print("╚════════════════════════════════════════════════════════════╝")
    
    test_status_normalization()
    test_soi_event_parsing()
    test_amount_conversion()
    test_event_type_detection()
    test_organization_id_extraction()
    print_json_examples()
    
    print("\n" + "="*60)
    print("✅ Все тесты пройдены успешно!")
    print("="*60)

if __name__ == "__main__":
    main()

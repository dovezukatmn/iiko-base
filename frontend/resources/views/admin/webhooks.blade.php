@extends('layouts.admin')

@section('title', 'Вебхуки и Заказы')
@section('page-title', 'Управление Вебхуками и Заказами')

@section('styles')
<style>
    .section-gap { margin-bottom: 20px; }

    /* Webhook Setup Card */
    .webhook-setup-status {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 16px 20px;
        border-radius: 12px;
        margin-bottom: 20px;
    }
    .webhook-setup-status.connected {
        background: rgba(34,197,94,0.1);
        border: 1px solid rgba(34,197,94,0.3);
    }
    .webhook-setup-status.disconnected {
        background: rgba(245,158,11,0.1);
        border: 1px solid rgba(245,158,11,0.3);
    }
    .webhook-setup-status.error {
        background: rgba(239,68,68,0.1);
        border: 1px solid rgba(239,68,68,0.3);
    }
    .webhook-setup-status .status-icon {
        font-size: 28px;
        flex-shrink: 0;
    }
    .webhook-setup-status .status-text {
        flex: 1;
    }
    .webhook-setup-status .status-title {
        font-weight: 700;
        font-size: 15px;
        color: var(--text-bright);
        margin-bottom: 2px;
    }
    .webhook-setup-status .status-subtitle {
        font-size: 12px;
        color: var(--muted);
    }
    .webhook-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 12px;
        margin-bottom: 16px;
    }
    .webhook-info-item {
        padding: 14px;
        border-radius: 10px;
        background: rgba(255,255,255,0.03);
        border: 1px solid var(--border);
    }
    .webhook-info-item .info-label {
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--muted);
        margin-bottom: 6px;
    }
    .webhook-info-item .info-value {
        font-size: 13px;
        font-weight: 600;
        color: var(--text-bright);
        word-break: break-all;
        font-family: 'SF Mono', 'Fira Code', monospace;
    }
    .webhook-info-item .info-value.accent { color: var(--accent-light); }
    .webhook-info-item .info-value.accent2 { color: var(--accent-2); }
    .setup-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 16px;
    }
    .btn-lg {
        padding: 12px 28px;
        font-size: 15px;
        font-weight: 700;
        border-radius: 10px;
    }
    .webhook-events-summary {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 16px;
    }
    .event-stat-card {
        flex: 1;
        min-width: 120px;
        padding: 12px;
        border-radius: 10px;
        background: rgba(255,255,255,0.03);
        border: 1px solid var(--border);
        text-align: center;
    }
    .event-stat-card .stat-num {
        font-size: 22px;
        font-weight: 700;
        color: var(--text-bright);
    }
    .event-stat-card .stat-label {
        font-size: 11px;
        color: var(--muted);
        margin-top: 2px;
    }
    .iiko-settings-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 8px;
        background: rgba(99,102,241,0.1);
        border: 1px solid rgba(99,102,241,0.3);
        font-size: 12px;
        color: var(--accent-light);
        font-weight: 600;
    }
    .webhook-filter-list {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 8px;
        margin-top: 12px;
    }
    .webhook-filter-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        border-radius: 8px;
        background: rgba(34,197,94,0.06);
        border: 1px solid rgba(34,197,94,0.15);
        font-size: 12px;
        color: var(--text);
    }
    .webhook-filter-item .check-icon { color: var(--success); font-weight: 700; }

    /* Webhook Event Cards */
    .webhook-card {
        padding: 14px;
        border-radius: 12px;
        border: 1px solid var(--border);
        background: rgba(255,255,255,0.03);
        margin-bottom: 8px;
        transition: all .15s;
    }
    .webhook-card:hover { background: rgba(255,255,255,0.06); transform: translateY(-1px); }
    .webhook-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }
    .webhook-type {
        font-weight: 700;
        font-size: 14px;
        color: var(--accent-light);
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .webhook-status {
        padding: 4px 10px;
        border-radius: 8px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
    }
    .webhook-processed { background: rgba(34,197,94,0.15); color: var(--success); }
    .webhook-failed { background: rgba(239,68,68,0.15); color: var(--danger); }
    .webhook-pending { background: rgba(245,158,11,0.15); color: var(--warning); }
    
    .webhook-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 8px;
        font-size: 12px;
        margin-top: 8px;
    }
    .webhook-detail-label {
        color: var(--muted);
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    .webhook-detail-value {
        color: var(--text);
        font-weight: 500;
        word-break: break-all;
    }
    
    /* Enhanced Order Cards */
    .order-card-enhanced {
        padding: 16px;
        border-radius: 12px;
        border: 1px solid var(--border);
        background: rgba(255,255,255,0.03);
        margin-bottom: 12px;
        transition: all .15s;
    }
    .order-card-enhanced:hover {
        background: rgba(255,255,255,0.06);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    }
    
    .order-main-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 12px;
        flex-wrap: wrap;
        gap: 12px;
    }
    .order-title-block {
        flex: 1;
        min-width: 200px;
    }
    .order-number {
        font-weight: 700;
        font-size: 18px;
        color: var(--text-bright);
        margin-bottom: 4px;
    }
    .order-external-id {
        font-size: 11px;
        color: var(--muted);
        font-family: 'SF Mono', monospace;
    }
    
    .order-actions {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
    }
    .order-action-btn {
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        border: 1px solid var(--border);
        background: rgba(255,255,255,0.05);
        color: var(--text);
        cursor: pointer;
        transition: all .15s;
    }
    .order-action-btn:hover {
        background: rgba(99,102,241,0.2);
        border-color: var(--accent);
        color: var(--accent-light);
    }
    
    .order-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 12px;
        margin-bottom: 12px;
    }
    .order-field {
        background: rgba(0,0,0,0.2);
        padding: 10px;
        border-radius: 8px;
    }
    .order-field-label {
        color: var(--muted);
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        margin-bottom: 4px;
    }
    .order-field-value {
        color: var(--text-bright);
        font-weight: 600;
        font-size: 13px;
    }
    .order-field-value.large {
        font-size: 16px;
        color: var(--accent-light);
    }
    
    .status-pill {
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .status-unconfirmed { background: rgba(148,163,184,0.15); color: var(--muted); }
    .status-waitcooking { background: rgba(245,158,11,0.15); color: var(--warning); }
    .status-cookingstarted { background: rgba(245,158,11,0.2); color: var(--warning); }
    .status-cookingcompleted { background: rgba(34,197,94,0.15); color: var(--success); }
    .status-waiting { background: rgba(99,102,241,0.15); color: var(--accent-light); }
    .status-onway { background: rgba(34,211,238,0.15); color: var(--accent-2); }
    .status-delivered { background: rgba(34,197,94,0.2); color: var(--success); }
    .status-closed { background: rgba(148,163,184,0.12); color: var(--muted); }
    .status-cancelled { background: rgba(239,68,68,0.15); color: var(--danger); }
    
    .courier-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        background: rgba(34,211,238,0.1);
        border-radius: 8px;
        font-weight: 600;
        font-size: 12px;
        color: var(--accent-2);
    }
    
    .filter-bar {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 16px;
        align-items: center;
    }
    
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.8);
        z-index: 10000;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .modal.active { display: flex; }
    .modal-content {
        background: var(--card-bg);
        border-radius: 16px;
        padding: 24px;
        max-width: 600px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
        border: 1px solid var(--border);
    }
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    .modal-title {
        font-size: 18px;
        font-weight: 700;
        color: var(--text-bright);
    }
    .modal-close {
        background: none;
        border: none;
        font-size: 24px;
        color: var(--muted);
        cursor: pointer;
        padding: 4px 8px;
    }
    .modal-close:hover { color: var(--text-bright); }
    
    .json-viewer {
        background: rgba(0,0,0,0.3);
        padding: 14px;
        border-radius: 8px;
        font-family: 'SF Mono', 'Fira Code', monospace;
        font-size: 11px;
        max-height: 400px;
        overflow: auto;
        white-space: pre-wrap;
        word-break: break-all;
    }
    
    .courier-assign-form {
        display: grid;
        gap: 12px;
    }
    
    .stat-mini {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        background: rgba(99,102,241,0.1);
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
    }
</style>
@endsection

@section('content')
{{-- Tab Bar --}}
<div class="tab-bar">
    <button class="tab-btn active" onclick="switchTab('setup', event)">⚙️ Настройка Вебхука</button>
    <button class="tab-btn" onclick="switchTab('orders', event)">📦 Заказы</button>
    <button class="tab-btn" onclick="switchTab('webhooks', event)">🔗 История Вебхуков</button>
    <button class="tab-btn" onclick="switchTab('outgoing', event)">📤 Исходящие Вебхуки</button>
    <button class="tab-btn" onclick="switchTab('couriers', event)">🚗 Курьеры</button>
    <button class="tab-btn" onclick="switchTab('bonuses', event)">🎁 Бонусы</button>
</div>

{{-- ═══ TAB: Webhook Setup ═══ --}}
<div class="tab-content active" id="tab-setup">
    {{-- Connection Status --}}
    <div id="webhook-connection-status">
        <div class="webhook-setup-status disconnected">
            <div class="status-icon">⏳</div>
            <div class="status-text">
                <div class="status-title">Загрузка...</div>
                <div class="status-subtitle">Проверка состояния вебхука</div>
            </div>
        </div>
    </div>

    {{-- Active Setting Info --}}
    <div class="card section-gap">
        <div class="card-header">
            <div>
                <div class="card-title">🔑 Активная настройка iiko</div>
                <div class="card-subtitle">Вебхук будет привязан к выбранной настройке API и организации</div>
            </div>
        </div>
        <div style="padding:0 16px 16px;">
            <div class="form-group" style="margin-bottom:12px;">
                <label class="form-label">Настройка iiko API</label>
                <select class="form-input" id="setup-setting-select" onchange="onSetupSettingChange()">
                    <option value="">Загрузка...</option>
                </select>
            </div>
            <div id="setup-setting-info">
                <span class="badge badge-muted">Загрузка настроек...</span>
            </div>
        </div>
    </div>

    {{-- One-Click Setup --}}
    <div class="card section-gap">
        <div class="card-header">
            <div>
                <div class="card-title">🚀 Автоматическая настройка вебхука</div>
                <div class="card-subtitle">Нажмите одну кнопку — система сама сгенерирует URL, токен и зарегистрирует вебхук в iiko Cloud</div>
            </div>
        </div>
        <div style="padding:0 16px 16px;">
            <div class="webhook-info-grid">
                <div class="webhook-info-item">
                    <div class="info-label">URL вебхука (генерируется автоматически)</div>
                    <div class="info-value accent" id="setup-webhook-url">—</div>
                </div>
                <div class="webhook-info-item">
                    <div class="info-label">Токен авторизации (генерируется автоматически)</div>
                    <div class="info-value accent2" id="setup-auth-token">—</div>
                </div>
            </div>

            <div id="setup-message" style="margin-bottom:12px;"></div>

            <div class="setup-actions">
                <button class="btn btn-primary btn-lg" id="btn-auto-setup" onclick="autoSetupWebhook()">
                    🔗 Подключить вебхук автоматически
                </button>
                <button class="btn btn-lg" id="btn-check-connection" onclick="checkWebhookConnection()">
                    🧪 Проверить подключение
                </button>
                <button class="btn btn-lg" id="btn-load-iiko-settings" onclick="loadIikoWebhookSettings()">
                    📋 Настройки в iiko
                </button>
            </div>
        </div>
    </div>

    {{-- Webhook Events Summary --}}
    <div class="card section-gap">
        <div class="card-header">
            <div>
                <div class="card-title">📊 Статистика вебхука</div>
                <div class="card-subtitle">Обзор входящих событий в реальном времени</div>
            </div>
            <button class="btn btn-sm" onclick="loadWebhookSetupStats()">🔄 Обновить</button>
        </div>
        <div style="padding:0 16px 16px;">
            <div class="webhook-events-summary" id="setup-events-stats">
                <div class="event-stat-card">
                    <div class="stat-num" id="stat-setup-total">0</div>
                    <div class="stat-label">Всего событий</div>
                </div>
                <div class="event-stat-card">
                    <div class="stat-num" id="stat-setup-processed" style="color:var(--success);">0</div>
                    <div class="stat-label">Обработано</div>
                </div>
                <div class="event-stat-card">
                    <div class="stat-num" id="stat-setup-errors" style="color:var(--danger);">0</div>
                    <div class="stat-label">Ошибки</div>
                </div>
                <div class="event-stat-card">
                    <div class="stat-num" id="stat-setup-last">—</div>
                    <div class="stat-label">Последнее событие</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Current iiko Webhook Config --}}
    <div class="card section-gap">
        <div class="card-header">
            <div>
                <div class="card-title">📡 Конфигурация в iiko Cloud</div>
                <div class="card-subtitle">Текущие настройки вебхука, зарегистрированные в iiko</div>
            </div>
        </div>
        <div style="padding:0 16px 16px;" id="iiko-cloud-webhook-config">
            <span class="badge badge-muted">Нажмите «Настройки в iiko» для загрузки</span>
        </div>
    </div>

    {{-- Guide --}}
    <div class="card section-gap">
        <div class="card-header">
            <div>
                <div class="card-title">📖 Как это работает</div>
                <div class="card-subtitle">Автоматическая настройка вебхука</div>
            </div>
        </div>
        <div style="padding:0 16px 16px;font-size:13px;color:var(--muted);">
            <ol style="margin-left:20px;line-height:1.8;">
                <li><strong>Выберите настройку iiko API</strong> с указанным Organization ID</li>
                <li>Нажмите <strong>«Подключить вебхук автоматически»</strong></li>
                <li>Система автоматически:
                    <ul style="margin-left:20px;">
                        <li>Определит домен из адресной строки вашего браузера</li>
                        <li>Сгенерирует URL вебхука: <code>https://ваш-домен/api/v1/webhooks/iiko</code></li>
                        <li>Сгенерирует безопасный токен авторизации</li>
                        <li>Зарегистрирует вебхук в iiko Cloud API</li>
                    </ul>
                </li>
                <li>Проверьте подключение кнопкой <strong>«Проверить подключение»</strong></li>
            </ol>
            <div style="margin-top:12px;padding:12px;background:rgba(99,102,241,0.08);border-radius:8px;border:1px solid rgba(99,102,241,0.2);">
                <strong style="color:var(--accent-light);">ℹ️ Отслеживаемые события:</strong>
                <div class="webhook-filter-list">
                    <div class="webhook-filter-item"><span class="check-icon">✓</span> Статусы заказов доставки</div>
                    <div class="webhook-filter-item"><span class="check-icon">✓</span> Статусы приготовления блюд</div>
                    <div class="webhook-filter-item"><span class="check-icon">✓</span> Обновления стоп-листов</div>
                    <div class="webhook-filter-item"><span class="check-icon">✓</span> Ошибки обработки</div>
                    <div class="webhook-filter-item"><span class="check-icon">✓</span> Персональные смены</div>
                    <div class="webhook-filter-item"><span class="check-icon">✓</span> Изменения курьеров</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ═══ TAB: Enhanced Orders ═══ --}}
<div class="tab-content" id="tab-orders">
    <div class="card section-gap">
        <div class="card-header">
            <div>
                <div class="card-title">Управление Заказами</div>
                <div class="card-subtitle">Полная информация о заказах с вебхуков и возможностью управления</div>
            </div>
            <button class="btn btn-primary btn-sm" onclick="loadEnhancedOrders()">🔄 Обновить</button>
        </div>
        
        <div class="filter-bar">
            <select class="form-input" id="order-status-filter-enhanced" style="max-width:200px;" onchange="loadEnhancedOrders()">
                <option value="">Все статусы</option>
                <option value="Unconfirmed">Не подтвержден</option>
                <option value="WaitCooking">Ожидает готовки</option>
                <option value="CookingStarted">Готовится</option>
                <option value="CookingCompleted">Приготовлен</option>
                <option value="Waiting">Ожидает</option>
                <option value="OnWay">В пути</option>
                <option value="Delivered">Доставлен</option>
                <option value="Closed">Закрыт</option>
                <option value="Cancelled">Отменен</option>
            </select>
            
            <select class="form-input" id="order-type-filter" style="max-width:150px;" onchange="loadEnhancedOrders()">
                <option value="">Все типы</option>
                <option value="DELIVERY">Доставка</option>
                <option value="PICKUP">Самовывоз</option>
                <option value="DINE_IN">В зале</option>
            </select>
            
            <input type="text" class="form-input" id="order-search" placeholder="Поиск по номеру, телефону..." style="max-width:250px;" onkeyup="if(event.key==='Enter')loadEnhancedOrders()">
            <button class="btn btn-sm" onclick="loadEnhancedOrders()">🔍 Найти</button>
        </div>
        
        <div id="stats-row" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
            <span class="stat-mini">📊 Всего: <span id="stat-total-orders">0</span></span>
            <span class="stat-mini" style="background:rgba(34,211,238,0.1);">🚗 С курьером: <span id="stat-with-courier">0</span></span>
            <span class="stat-mini" style="background:rgba(245,158,11,0.1);">⏳ Активных: <span id="stat-active-orders">0</span></span>
        </div>
        
        <div id="enhanced-orders-list">
            <div class="loading-overlay"><span class="spinner"></span> Загрузка заказов...</div>
        </div>
    </div>
</div>

{{-- ═══ TAB: Webhook History ═══ --}}
<div class="tab-content" id="tab-webhooks">
    <div class="card section-gap">
        <div class="card-header">
            <div>
                <div class="card-title">История Входящих Вебхуков</div>
                <div class="card-subtitle">Все события, полученные от iiko в реальном времени</div>
            </div>
            <button class="btn btn-primary btn-sm" onclick="loadWebhookEvents()">🔄 Обновить</button>
        </div>
        
        <div class="filter-bar">
            <select class="form-input" id="webhook-type-filter" style="max-width:200px;" onchange="loadWebhookEvents()">
                <option value="">Все типы</option>
                <option value="CREATE">CREATE</option>
                <option value="UPDATE">UPDATE</option>
                <option value="DeliveryOrderUpdate">DeliveryOrderUpdate</option>
                <option value="DeliveryOrderError">DeliveryOrderError</option>
                <option value="StopListUpdate">StopListUpdate</option>
            </select>
            
            <select class="form-input" id="webhook-status-filter" style="max-width:150px;" onchange="loadWebhookEvents()">
                <option value="">Все</option>
                <option value="true">Обработано</option>
                <option value="false">Не обработано</option>
            </select>
            
            <input type="text" class="form-input" id="webhook-search" placeholder="Поиск по external_id..." style="max-width:250px;" onkeyup="if(event.key==='Enter')loadWebhookEvents()">
            <button class="btn btn-sm" onclick="loadWebhookEvents()">🔍 Найти</button>
        </div>
        
        <div id="webhook-stats" style="margin-bottom:16px;">
            <span class="stat-mini">📊 Всего: <span id="stat-total-webhooks">0</span></span>
            <span class="stat-mini" style="background:rgba(34,197,94,0.1);">✅ Обработано: <span id="stat-processed">0</span></span>
            <span class="stat-mini" style="background:rgba(239,68,68,0.1);">❌ Ошибок: <span id="stat-failed">0</span></span>
        </div>
        
        <div id="webhook-events-list">
            <div class="loading-overlay"><span class="spinner"></span> Загрузка событий...</div>
        </div>
    </div>
</div>

{{-- ═══ TAB: Outgoing Webhooks ═══ --}}
<div class="tab-content" id="tab-outgoing">
    <div class="card section-gap">
        <div class="card-header">
            <div>
                <div class="card-title">Исходящие Вебхуки</div>
                <div class="card-subtitle">Отправка данных на внешние сервисы (Senler, VK, и др.)</div>
            </div>
            <button class="btn btn-primary btn-sm" onclick="openOutgoingWebhookModal()">➕ Добавить Вебхук</button>
        </div>
        
        <div id="outgoing-webhooks-list">
            <div class="loading-overlay"><span class="spinner"></span> Загрузка вебхуков...</div>
        </div>
    </div>

    {{-- Webhook Logs --}}
    <div class="card section-gap">
        <div class="card-header">
            <div>
                <div class="card-title">Логи Отправок</div>
                <div class="card-subtitle">История отправки вебхуков на внешние сервисы</div>
            </div>
            <button class="btn btn-sm" onclick="loadOutgoingWebhookLogs()">🔄 Обновить</button>
        </div>
        
        <div class="filter-bar">
            <select class="form-input" id="outgoing-webhook-filter" style="max-width:200px;" onchange="loadOutgoingWebhookLogs()">
                <option value="">Все вебхуки</option>
            </select>
            
            <select class="form-input" id="outgoing-log-status-filter" style="max-width:150px;" onchange="loadOutgoingWebhookLogs()">
                <option value="">Все</option>
                <option value="true">Успешно</option>
                <option value="false">Ошибки</option>
            </select>
        </div>
        
        <div id="outgoing-webhook-logs-list">
            <div class="loading-overlay"><span class="spinner"></span> Загрузка логов...</div>
        </div>
    </div>
</div>

{{-- ═══ TAB: Couriers ═══ --}}
<div class="tab-content" id="tab-couriers">
    <div class="card section-gap">
        <div class="card-header">
            <div>
                <div class="card-title">Управление Курьерами</div>
                <div class="card-subtitle">Просмотр заказов по курьерам и назначение</div>
            </div>
            <button class="btn btn-primary btn-sm" onclick="loadCourierStats()">🔄 Обновить</button>
        </div>
        
        <div id="courier-stats-list">
            <div class="loading-overlay"><span class="spinner"></span> Загрузка данных курьеров...</div>
        </div>
    </div>
</div>

{{-- ═══ TAB: Bonuses ═══ --}}
<div class="tab-content" id="tab-bonuses">
    <div class="card section-gap">
        <div class="card-header">
            <div>
                <div class="card-title">История Бонусных Транзакций</div>
                <div class="card-subtitle">Начисления и списания бонусов</div>
            </div>
            <button class="btn btn-primary btn-sm" onclick="loadBonusTransactions()">🔄 Обновить</button>
        </div>
        
        <div class="filter-bar">
            <select class="form-input" id="bonus-type-filter" style="max-width:200px;" onchange="loadBonusTransactions()">
                <option value="">Все операции</option>
                <option value="topup">Начисление</option>
                <option value="withdraw">Списание</option>
                <option value="hold">Холдирование</option>
            </select>
            
            <input type="text" class="form-input" id="bonus-search" placeholder="Поиск по телефону, имени..." style="max-width:250px;" onkeyup="if(event.key==='Enter')loadBonusTransactions()">
            <button class="btn btn-sm" onclick="loadBonusTransactions()">🔍 Найти</button>
        </div>
        
        <div id="bonus-transactions-list">
            <div class="loading-overlay"><span class="spinner"></span> Загрузка транзакций...</div>
        </div>
    </div>
</div>

{{-- Modals --}}
<div id="order-details-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">Детали Заказа</div>
            <button class="modal-close" onclick="closeModal('order-details-modal')">×</button>
        </div>
        <div id="order-details-content"></div>
    </div>
</div>

<div id="courier-assign-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">Назначить Курьера</div>
            <button class="modal-close" onclick="closeModal('courier-assign-modal')">×</button>
        </div>
        <div class="courier-assign-form">
            <input type="hidden" id="assign-order-id">
            <div class="form-group">
                <label class="form-label">ID Курьера</label>
                <input type="text" class="form-input" id="assign-courier-id" placeholder="UUID курьера">
            </div>
            <div class="form-group">
                <label class="form-label">Имя Курьера</label>
                <input type="text" class="form-input" id="assign-courier-name" placeholder="Иван Иванов">
            </div>
            <button class="btn btn-primary" onclick="submitCourierAssignment()">✅ Назначить</button>
        </div>
    </div>
</div>

<div id="status-change-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">Изменить Статус</div>
            <button class="modal-close" onclick="closeModal('status-change-modal')">×</button>
        </div>
        <div class="courier-assign-form">
            <input type="hidden" id="status-order-id">
            <div class="form-group">
                <label class="form-label">Новый Статус</label>
                <select class="form-input" id="new-status-select">
                    <option value="WaitCooking">Ожидает готовки</option>
                    <option value="CookingStarted">Готовится</option>
                    <option value="CookingCompleted">Приготовлен</option>
                    <option value="Waiting">Ожидает</option>
                    <option value="OnWay">В пути</option>
                    <option value="Delivered">Доставлен</option>
                    <option value="Closed">Закрыт</option>
                    <option value="Cancelled">Отменен</option>
                </select>
            </div>
            <button class="btn btn-primary" onclick="submitStatusChange()">✅ Обновить</button>
        </div>
    </div>
</div>

<div id="webhook-details-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">Детали Вебхука</div>
            <button class="modal-close" onclick="closeModal('webhook-details-modal')">×</button>
        </div>
        <div id="webhook-details-content"></div>
    </div>
</div>

<div id="outgoing-webhook-modal" class="modal">
    <div class="modal-content" style="max-width:700px;">
        <div class="modal-header">
            <div class="modal-title" id="outgoing-webhook-modal-title">Добавить Исходящий Вебхук</div>
            <button class="modal-close" onclick="closeModal('outgoing-webhook-modal')">×</button>
        </div>
        <div class="courier-assign-form">
            <input type="hidden" id="outgoing-webhook-id">
            
            <div class="form-group">
                <label class="form-label">Название *</label>
                <input type="text" class="form-input" id="outgoing-webhook-name" placeholder="Senler VK Integration" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Описание</label>
                <textarea class="form-input" id="outgoing-webhook-description" placeholder="Интеграция с Senler для отправки заказов в VK" rows="2"></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label">URL Вебхука *</label>
                <input type="url" class="form-input" id="outgoing-webhook-url" placeholder="https://senler.ru/api/webhook" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Формат Данных</label>
                <select class="form-input" id="outgoing-webhook-format">
                    <option value="iiko_soi">iiko SOI API (как от iiko)</option>
                    <option value="iiko_cloud">iiko Cloud API</option>
                    <option value="custom">Пользовательский</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Тип Авторизации</label>
                <select class="form-input" id="outgoing-webhook-auth-type" onchange="toggleAuthFields()">
                    <option value="none">Без авторизации</option>
                    <option value="bearer">Bearer Token</option>
                    <option value="basic">Basic Auth</option>
                    <option value="custom">Пользовательские заголовки</option>
                </select>
            </div>
            
            <div class="form-group" id="auth-token-group" style="display:none;">
                <label class="form-label">Token</label>
                <input type="text" class="form-input" id="outgoing-webhook-auth-token" placeholder="ваш_bearer_token">
            </div>
            
            <div class="form-group" id="auth-basic-group" style="display:none;">
                <label class="form-label">Username</label>
                <input type="text" class="form-input" id="outgoing-webhook-auth-username" placeholder="username">
                <label class="form-label" style="margin-top:8px;">Password</label>
                <input type="password" class="form-input" id="outgoing-webhook-auth-password" placeholder="password">
            </div>
            
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px;">
                        <input type="checkbox" id="outgoing-webhook-on-created" checked>
                        При создании заказа
                    </label>
                </div>
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px;">
                        <input type="checkbox" id="outgoing-webhook-on-updated" checked>
                        При обновлении заказа
                    </label>
                </div>
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px;">
                        <input type="checkbox" id="outgoing-webhook-on-status-changed" checked>
                        При смене статуса
                    </label>
                </div>
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px;">
                        <input type="checkbox" id="outgoing-webhook-on-cancelled">
                        При отмене заказа
                    </label>
                </div>
            </div>
            
            <div class="form-group">
                <label style="display:flex;align-items:center;gap:8px;">
                    <input type="checkbox" id="outgoing-webhook-is-active" checked>
                    Активен
                </label>
            </div>
            
            <div style="display:flex;gap:8px;margin-top:16px;">
                <button class="btn btn-primary" onclick="saveOutgoingWebhook()">✅ Сохранить</button>
                <button class="btn" onclick="testOutgoingWebhook()" id="test-webhook-btn" style="display:none;">🧪 Тестировать</button>
                <button class="btn" onclick="closeModal('outgoing-webhook-modal')">Отмена</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
let setupSettingsList = [];
let setupCurrentSettingId = null;
let setupCurrentOrgId = null;

// Tab switching
function switchTab(name, evt) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    if (evt && evt.target) evt.target.classList.add('active');
    
    // Auto-load data
    if (name === 'setup') { loadSetupTab(); }
    if (name === 'orders') loadEnhancedOrders();
    if (name === 'webhooks') loadWebhookEvents();
    if (name === 'outgoing') { loadOutgoingWebhooks(); loadOutgoingWebhookLogs(); }
    if (name === 'couriers') loadCourierStats();
    if (name === 'bonuses') loadBonusTransactions();
}

// API helpers
async function apiGet(url) {
    const res = await fetch(url, {
        headers: { 'X-CSRF-TOKEN': csrfToken }
    });
    return res.json();
}

async function apiPost(url, body = {}, method = 'POST') {
    const res = await fetch(url, {
        method: method,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(body),
    });
    return { status: res.status, data: await res.json() };
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = String(str);
    return div.innerHTML;
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    return d.toLocaleDateString('ru-RU') + ' ' + d.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
}

function getStatusClass(status) {
    const s = (status || '').toLowerCase();
    return 'status-' + s.replace('_', '');
}

function getStatusLabel(status) {
    const map = {
        'Unconfirmed': 'Не подтвержден',
        'WaitCooking': 'Ожидает готовки',
        'ReadyForCooking': 'Готов к готовке',
        'CookingStarted': 'Готовится',
        'CookingCompleted': 'Приготовлен',
        'Waiting': 'Ожидает',
        'OnWay': 'В пути',
        'Delivered': 'Доставлен',
        'Closed': 'Закрыт',
        'Cancelled': 'Отменен',
    };
    return map[status] || status || '—';
}

// Modal functions
function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

// ─── Enhanced Orders ────────────────────────────────────────
async function loadEnhancedOrders() {
    const container = document.getElementById('enhanced-orders-list');
    const statusFilter = document.getElementById('order-status-filter-enhanced').value;
    const typeFilter = document.getElementById('order-type-filter').value;
    const search = document.getElementById('order-search').value;
    
    container.innerHTML = '<div class="loading-overlay"><span class="spinner"></span> Загрузка...</div>';
    
    try {
        let url = '/admin/api/orders?limit=100';
        if (statusFilter) url += '&status=' + encodeURIComponent(statusFilter);
        if (typeFilter) url += '&order_type=' + encodeURIComponent(typeFilter);
        if (search) url += '&search=' + encodeURIComponent(search);
        
        const orders = await apiGet(url);
        const ordersList = Array.isArray(orders) ? orders : [];
        
        // Update stats
        document.getElementById('stat-total-orders').textContent = ordersList.length;
        document.getElementById('stat-with-courier').textContent = ordersList.filter(o => o.courier_id).length;
        document.getElementById('stat-active-orders').textContent = ordersList.filter(o => !['Closed', 'Cancelled', 'Delivered'].includes(o.status)).length;
        
        if (ordersList.length === 0) {
            container.innerHTML = '<span class="badge badge-muted">Заказов не найдено</span>';
            return;
        }
        
        let html = '';
        ordersList.forEach(order => {
            const amount = ((order.total_amount || 0) / 100).toFixed(2);
            html += `
                <div class="order-card-enhanced">
                    <div class="order-main-header">
                        <div class="order-title-block">
                            <div class="order-number">
                                ${order.readable_number || '#' + order.id}
                                ${order.external_order_id ? '<span class="order-external-id">EXT: ' + escapeHtml(order.external_order_id) + '</span>' : ''}
                            </div>
                            <span class="status-pill ${getStatusClass(order.status)}">${getStatusLabel(order.status)}</span>
                        </div>
                        <div class="order-actions">
                            <button class="order-action-btn" onclick="viewOrderDetails(${order.id})" title="Детали">📋</button>
                            <button class="order-action-btn" onclick="openStatusChange(${order.id})" title="Изменить статус">🔄</button>
                            <button class="order-action-btn" onclick="openCourierAssign(${order.id})" title="Назначить курьера">🚗</button>
                            <button class="order-action-btn" onclick="cancelOrder(${order.id})" title="Отменить">❌</button>
                        </div>
                    </div>
                    
                    <div class="order-grid">
                        <div class="order-field">
                            <div class="order-field-label">Сумма</div>
                            <div class="order-field-value large">${amount} ₽</div>
                        </div>
                        ${order.courier_name ? `
                        <div class="order-field">
                            <div class="order-field-label">Курьер</div>
                            <div class="order-field-value">
                                <span class="courier-badge">🚗 ${escapeHtml(order.courier_name)}</span>
                            </div>
                        </div>
                        ` : ''}
                        ${order.order_type ? `
                        <div class="order-field">
                            <div class="order-field-label">Тип</div>
                            <div class="order-field-value">${escapeHtml(order.order_type)}</div>
                        </div>
                        ` : ''}
                        ${order.restaurant_name ? `
                        <div class="order-field">
                            <div class="order-field-label">Ресторан</div>
                            <div class="order-field-value">${escapeHtml(order.restaurant_name)}</div>
                        </div>
                        ` : ''}
                        ${order.promised_time ? `
                        <div class="order-field">
                            <div class="order-field-label">Обещанное время</div>
                            <div class="order-field-value">${formatDate(order.promised_time)}</div>
                        </div>
                        ` : ''}
                        <div class="order-field">
                            <div class="order-field-label">Создан</div>
                            <div class="order-field-value">${formatDate(order.created_at)}</div>
                        </div>
                        ${order.customer_name ? `
                        <div class="order-field">
                            <div class="order-field-label">Клиент</div>
                            <div class="order-field-value">${escapeHtml(order.customer_name)}</div>
                        </div>
                        ` : ''}
                        ${order.customer_phone ? `
                        <div class="order-field">
                            <div class="order-field-label">Телефон</div>
                            <div class="order-field-value">${escapeHtml(order.customer_phone)}</div>
                        </div>
                        ` : ''}
                    </div>
                    
                    ${order.problem ? `
                    <div style="padding:10px;background:rgba(239,68,68,0.1);border-radius:8px;border:1px solid var(--danger);margin-top:8px;">
                        <div style="font-size:11px;color:var(--danger);font-weight:700;margin-bottom:4px;">⚠️ ПРОБЛЕМА</div>
                        <div style="font-size:12px;color:var(--text);">${escapeHtml(order.problem)}</div>
                    </div>
                    ` : ''}
                </div>
            `;
        });
        
        container.innerHTML = html;
    } catch (err) {
        container.innerHTML = `<div class="alert alert-danger">⚠️ Ошибка загрузки: ${escapeHtml(err.message)}</div>`;
    }
}

async function viewOrderDetails(orderId) {
    try {
        const order = await apiGet(`/admin/api/orders/${orderId}`);
        const content = document.getElementById('order-details-content');
        
        let html = '<div class="json-viewer">' + JSON.stringify(order, null, 2) + '</div>';
        content.innerHTML = html;
        openModal('order-details-modal');
    } catch (err) {
        alert('Ошибка загрузки деталей: ' + err.message);
    }
}

function openCourierAssign(orderId) {
    document.getElementById('assign-order-id').value = orderId;
    document.getElementById('assign-courier-id').value = '';
    document.getElementById('assign-courier-name').value = '';
    openModal('courier-assign-modal');
}

async function submitCourierAssignment() {
    const orderId = document.getElementById('assign-order-id').value;
    const courierId = document.getElementById('assign-courier-id').value;
    const courierName = document.getElementById('assign-courier-name').value;
    
    if (!courierId || !courierName) {
        alert('Заполните все поля');
        return;
    }
    
    try {
        const result = await apiPost(`/admin/api/orders/${orderId}/assign-courier`, {
            courier_id: courierId,
            courier_name: courierName
        });
        
        if (result.status === 200) {
            alert('✅ Курьер успешно назначен!');
            closeModal('courier-assign-modal');
            loadEnhancedOrders();
        } else {
            alert('❌ Ошибка: ' + JSON.stringify(result.data));
        }
    } catch (err) {
        alert('❌ Ошибка: ' + err.message);
    }
}

function openStatusChange(orderId) {
    document.getElementById('status-order-id').value = orderId;
    openModal('status-change-modal');
}

async function submitStatusChange() {
    const orderId = document.getElementById('status-order-id').value;
    const newStatus = document.getElementById('new-status-select').value;
    
    try {
        const result = await apiPost(`/admin/api/orders/${orderId}/update-status`, {
            status: newStatus
        });
        
        if (result.status === 200) {
            alert('✅ Статус успешно обновлен!');
            closeModal('status-change-modal');
            loadEnhancedOrders();
        } else {
            alert('❌ Ошибка: ' + JSON.stringify(result.data));
        }
    } catch (err) {
        alert('❌ Ошибка: ' + err.message);
    }
}

async function cancelOrder(orderId) {
    if (!confirm('Вы уверены, что хотите отменить заказ #' + orderId + '?')) return;
    
    const reason = prompt('Укажите причину отмены:');
    if (!reason) return;
    
    try {
        const result = await apiPost(`/admin/api/orders/${orderId}/cancel`, {
            cancel_reason: reason
        });
        
        if (result.status === 200) {
            alert('✅ Заказ отменен!');
            loadEnhancedOrders();
        } else {
            alert('❌ Ошибка: ' + JSON.stringify(result.data));
        }
    } catch (err) {
        alert('❌ Ошибка: ' + err.message);
    }
}

// ─── Webhook Events ─────────────────────────────────────────
async function loadWebhookEvents() {
    const container = document.getElementById('webhook-events-list');
    const typeFilter = document.getElementById('webhook-type-filter').value;
    const statusFilter = document.getElementById('webhook-status-filter').value;
    const search = document.getElementById('webhook-search').value;
    
    container.innerHTML = '<div class="loading-overlay"><span class="spinner"></span> Загрузка...</div>';
    
    try {
        let url = '/admin/api/webhooks/events?limit=100';
        if (typeFilter) url += '&event_type=' + encodeURIComponent(typeFilter);
        if (statusFilter) url += '&processed=' + encodeURIComponent(statusFilter);
        if (search) url += '&search=' + encodeURIComponent(search);
        
        const events = await apiGet(url);
        const eventsList = Array.isArray(events) ? events : [];
        
        // Update stats
        document.getElementById('stat-total-webhooks').textContent = eventsList.length;
        document.getElementById('stat-processed').textContent = eventsList.filter(e => e.processed).length;
        document.getElementById('stat-failed').textContent = eventsList.filter(e => e.processing_error).length;
        
        if (eventsList.length === 0) {
            container.innerHTML = '<span class="badge badge-muted">События не найдены</span>';
            return;
        }
        
        let html = '';
        eventsList.forEach(event => {
            const statusClass = event.processing_error ? 'webhook-failed' : (event.processed ? 'webhook-processed' : 'webhook-pending');
            const statusText = event.processing_error ? '❌ Ошибка' : (event.processed ? '✅ Обработано' : '⏳ Ожидает');
            
            html += `
                <div class="webhook-card" onclick="viewWebhookDetails(${event.id})">
                    <div class="webhook-header">
                        <div class="webhook-type">📡 ${escapeHtml(event.event_type)}</div>
                        <span class="webhook-status ${statusClass}">${statusText}</span>
                    </div>
                    <div class="webhook-details">
                        ${event.order_external_id ? `
                        <div>
                            <div class="webhook-detail-label">External ID</div>
                            <div class="webhook-detail-value">${escapeHtml(event.order_external_id)}</div>
                        </div>
                        ` : ''}
                        ${event.organization_id ? `
                        <div>
                            <div class="webhook-detail-label">Organization ID</div>
                            <div class="webhook-detail-value">${escapeHtml(event.organization_id).substring(0, 12)}...</div>
                        </div>
                        ` : ''}
                        <div>
                            <div class="webhook-detail-label">Получено</div>
                            <div class="webhook-detail-value">${formatDate(event.created_at)}</div>
                        </div>
                        ${event.processing_error ? `
                        <div>
                            <div class="webhook-detail-label">Ошибка</div>
                            <div class="webhook-detail-value" style="color:var(--danger);">${escapeHtml(event.processing_error).substring(0, 50)}...</div>
                        </div>
                        ` : ''}
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = html;
    } catch (err) {
        container.innerHTML = `<div class="alert alert-danger">⚠️ Ошибка загрузки: ${escapeHtml(err.message)}</div>`;
    }
}

async function viewWebhookDetails(eventId) {
    try {
        const events = await apiGet('/admin/api/webhooks/events?limit=100');
        const event = events.find(e => e.id === eventId);
        if (!event) {
            alert('Событие не найдено');
            return;
        }
        
        const content = document.getElementById('webhook-details-content');
        let payload = {};
        try {
            payload = JSON.parse(event.payload);
        } catch {
            payload = { raw: event.payload };
        }
        
        let html = `
            <div style="margin-bottom:16px;">
                <strong>ID:</strong> ${event.id}<br>
                <strong>Тип:</strong> ${escapeHtml(event.event_type)}<br>
                <strong>Статус:</strong> ${event.processed ? '✅ Обработано' : '⏳ Ожидает'}<br>
                <strong>Время:</strong> ${formatDate(event.created_at)}
            </div>
            <div class="json-viewer">${JSON.stringify(payload, null, 2)}</div>
        `;
        
        content.innerHTML = html;
        openModal('webhook-details-modal');
    } catch (err) {
        alert('Ошибка загрузки деталей: ' + err.message);
    }
}

// ─── Couriers ───────────────────────────────────────────────
async function loadCourierStats() {
    const container = document.getElementById('courier-stats-list');
    container.innerHTML = '<div class="loading-overlay"><span class="spinner"></span> Загрузка...</div>';
    
    try {
        const orders = await apiGet('/admin/api/orders?limit=1000');
        const ordersList = Array.isArray(orders) ? orders : [];
        
        // Group by courier
        const courierMap = {};
        ordersList.forEach(order => {
            if (order.courier_id || order.courier_name) {
                const key = order.courier_id || order.courier_name;
                if (!courierMap[key]) {
                    courierMap[key] = {
                        id: order.courier_id,
                        name: order.courier_name || 'Неизвестно',
                        orders: []
                    };
                }
                courierMap[key].orders.push(order);
            }
        });
        
        const couriers = Object.values(courierMap);
        
        if (couriers.length === 0) {
            container.innerHTML = '<span class="badge badge-muted">Нет заказов с назначенными курьерами</span>';
            return;
        }
        
        let html = '';
        couriers.forEach(courier => {
            const activeOrders = courier.orders.filter(o => !['Closed', 'Cancelled', 'Delivered'].includes(o.status));
            const totalAmount = courier.orders.reduce((sum, o) => sum + (o.total_amount || 0), 0) / 100;
            
            html += `
                <div class="card" style="margin-bottom:12px;">
                    <div style="padding:16px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                            <div>
                                <div style="font-size:16px;font-weight:700;color:var(--text-bright);">🚗 ${escapeHtml(courier.name)}</div>
                                ${courier.id ? '<div style="font-size:11px;color:var(--muted);font-family:monospace;">ID: ' + escapeHtml(courier.id).substring(0,20) + '...</div>' : ''}
                            </div>
                            <div style="text-align:right;">
                                <div class="stat-mini">📦 ${courier.orders.length} заказов</div>
                                <div class="stat-mini" style="background:rgba(245,158,11,0.1);margin-top:4px;">⏳ ${activeOrders.length} активных</div>
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px;">
                            <div class="order-field">
                                <div class="order-field-label">Общая сумма</div>
                                <div class="order-field-value large">${totalAmount.toFixed(2)} ₽</div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = html;
    } catch (err) {
        container.innerHTML = `<div class="alert alert-danger">⚠️ Ошибка загрузки: ${escapeHtml(err.message)}</div>`;
    }
}

// ─── Bonuses ────────────────────────────────────────────────
async function loadBonusTransactions() {
    const container = document.getElementById('bonus-transactions-list');
    const typeFilter = document.getElementById('bonus-type-filter').value;
    const search = document.getElementById('bonus-search').value;
    
    container.innerHTML = '<div class="loading-overlay"><span class="spinner"></span> Загрузка...</div>';
    
    try {
        let url = '/admin/api/loyalty/transactions?limit=100';
        if (typeFilter) url += '&operation_type=' + encodeURIComponent(typeFilter);
        if (search) url += '&search=' + encodeURIComponent(search);
        
        const transactions = await apiGet(url);
        const txList = Array.isArray(transactions) ? transactions : [];
        
        if (txList.length === 0) {
            container.innerHTML = '<span class="badge badge-muted">Транзакций не найдено</span>';
            return;
        }
        
        let html = '';
        txList.forEach(tx => {
            const typeLabel = tx.operation_type === 'topup' ? '➕ Начисление' :
                             tx.operation_type === 'withdraw' ? '➖ Списание' :
                             '🔒 Холд';
            const typeClass = tx.operation_type === 'topup' ? 'status-delivered' :
                             tx.operation_type === 'withdraw' ? 'status-cancelled' :
                             'status-warning';
            
            html += `
                <div class="webhook-card">
                    <div class="webhook-header">
                        <div class="webhook-type">${typeLabel}</div>
                        <span class="status-pill ${typeClass}">${tx.amount} бонусов</span>
                    </div>
                    <div class="webhook-details">
                        ${tx.customer_name ? `
                        <div>
                            <div class="webhook-detail-label">Клиент</div>
                            <div class="webhook-detail-value">${escapeHtml(tx.customer_name)}</div>
                        </div>
                        ` : ''}
                        ${tx.customer_phone ? `
                        <div>
                            <div class="webhook-detail-label">Телефон</div>
                            <div class="webhook-detail-value">${escapeHtml(tx.customer_phone)}</div>
                        </div>
                        ` : ''}
                        ${tx.comment ? `
                        <div>
                            <div class="webhook-detail-label">Комментарий</div>
                            <div class="webhook-detail-value">${escapeHtml(tx.comment)}</div>
                        </div>
                        ` : ''}
                        <div>
                            <div class="webhook-detail-label">Дата</div>
                            <div class="webhook-detail-value">${formatDate(tx.created_at)}</div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = html;
    } catch (err) {
        container.innerHTML = `<div class="alert alert-danger">⚠️ Ошибка загрузки: ${escapeHtml(err.message)}</div>`;
    }
}

// ─── Outgoing Webhooks ──────────────────────────────────────
async function loadOutgoingWebhooks() {
    const container = document.getElementById('outgoing-webhooks-list');
    container.innerHTML = '<div class="loading-overlay"><span class="spinner"></span> Загрузка...</div>';
    
    try {
        const webhooks = await apiGet('/admin/api/outgoing-webhooks');
        
        if (!Array.isArray(webhooks) || webhooks.length === 0) {
            container.innerHTML = '<span class="badge badge-muted">Исходящие вебхуки не настроены</span>';
            return;
        }
        
        // Populate filter dropdown
        const filterSelect = document.getElementById('outgoing-webhook-filter');
        filterSelect.innerHTML = '<option value="">Все вебхуки</option>';
        webhooks.forEach(w => {
            filterSelect.innerHTML += `<option value="${w.id}">${escapeHtml(w.name)}</option>`;
        });
        
        let html = '';
        webhooks.forEach(webhook => {
            const successRate = webhook.total_sent > 0 
                ? ((webhook.total_success / webhook.total_sent) * 100).toFixed(1)
                : 0;
            const statusClass = webhook.is_active ? 'webhook-processed' : 'webhook-pending';
            const statusText = webhook.is_active ? '✅ Активен' : '⏸ Неактивен';
            
            html += `
                <div class="webhook-card">
                    <div class="webhook-header">
                        <div class="webhook-type">📤 ${escapeHtml(webhook.name)}</div>
                        <span class="webhook-status ${statusClass}">${statusText}</span>
                    </div>
                    ${webhook.description ? `<div style="font-size:12px;color:var(--muted);margin-bottom:8px;">${escapeHtml(webhook.description)}</div>` : ''}
                    <div class="webhook-details">
                        <div>
                            <div class="webhook-detail-label">URL</div>
                            <div class="webhook-detail-value">${escapeHtml(webhook.webhook_url).substring(0, 40)}...</div>
                        </div>
                        <div>
                            <div class="webhook-detail-label">Формат</div>
                            <div class="webhook-detail-value">${webhook.payload_format}</div>
                        </div>
                        <div>
                            <div class="webhook-detail-label">Отправлено</div>
                            <div class="webhook-detail-value">${webhook.total_sent} (${successRate}% успех)</div>
                        </div>
                        ${webhook.last_sent_at ? `
                        <div>
                            <div class="webhook-detail-label">Последняя отправка</div>
                            <div class="webhook-detail-value">${formatDate(webhook.last_sent_at)}</div>
                        </div>
                        ` : ''}
                    </div>
                    <div style="display:flex;gap:8px;margin-top:12px;">
                        <button class="order-action-btn" onclick="editOutgoingWebhook(${webhook.id})" title="Редактировать">✏️</button>
                        <button class="order-action-btn" onclick="testOutgoingWebhookById(${webhook.id})" title="Тестировать">🧪</button>
                        <button class="order-action-btn" onclick="toggleOutgoingWebhook(${webhook.id}, ${!webhook.is_active})" title="${webhook.is_active ? 'Деактивировать' : 'Активировать'}">${webhook.is_active ? '⏸' : '▶️'}</button>
                        <button class="order-action-btn" onclick="deleteOutgoingWebhook(${webhook.id})" title="Удалить">🗑️</button>
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = html;
    } catch (err) {
        container.innerHTML = `<div class="alert alert-danger">⚠️ Ошибка загрузки: ${escapeHtml(err.message)}</div>`;
    }
}

async function loadOutgoingWebhookLogs() {
    const container = document.getElementById('outgoing-webhook-logs-list');
    const webhookFilter = document.getElementById('outgoing-webhook-filter').value;
    const statusFilter = document.getElementById('outgoing-log-status-filter').value;
    
    container.innerHTML = '<div class="loading-overlay"><span class="spinner"></span> Загрузка...</div>';
    
    try {
        let url = '/admin/api/outgoing-webhook-logs?limit=50';
        if (webhookFilter) url += '&webhook_id=' + webhookFilter;
        if (statusFilter) url += '&success=' + statusFilter;
        
        const logs = await apiGet(url);
        
        if (!Array.isArray(logs) || logs.length === 0) {
            container.innerHTML = '<span class="badge badge-muted">Логов не найдено</span>';
            return;
        }
        
        let html = '';
        logs.forEach(log => {
            const statusClass = log.success ? 'webhook-processed' : 'webhook-failed';
            const statusText = log.success ? `✅ ${log.response_status}` : `❌ Ошибка`;
            
            html += `
                <div class="webhook-card">
                    <div class="webhook-header">
                        <div class="webhook-type">${escapeHtml(log.webhook_name || 'Webhook')}</div>
                        <span class="webhook-status ${statusClass}">${statusText}</span>
                    </div>
                    <div class="webhook-details">
                        ${log.order_external_id ? `
                        <div>
                            <div class="webhook-detail-label">Заказ</div>
                            <div class="webhook-detail-value">${escapeHtml(log.order_external_id)}</div>
                        </div>
                        ` : ''}
                        <div>
                            <div class="webhook-detail-label">Событие</div>
                            <div class="webhook-detail-value">${escapeHtml(log.event_type || '—')}</div>
                        </div>
                        <div>
                            <div class="webhook-detail-label">Попытка</div>
                            <div class="webhook-detail-value">#${log.attempt_number}</div>
                        </div>
                        <div>
                            <div class="webhook-detail-label">Время</div>
                            <div class="webhook-detail-value">${log.duration_ms}ms</div>
                        </div>
                        <div>
                            <div class="webhook-detail-label">Дата</div>
                            <div class="webhook-detail-value">${formatDate(log.created_at)}</div>
                        </div>
                        ${log.error_message ? `
                        <div>
                            <div class="webhook-detail-label">Ошибка</div>
                            <div class="webhook-detail-value" style="color:var(--danger);">${escapeHtml(log.error_message).substring(0, 100)}...</div>
                        </div>
                        ` : ''}
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = html;
    } catch (err) {
        container.innerHTML = `<div class="alert alert-danger">⚠️ Ошибка загрузки: ${escapeHtml(err.message)}</div>`;
    }
}

function openOutgoingWebhookModal(webhookId = null) {
    document.getElementById('outgoing-webhook-modal-title').textContent = webhookId ? 'Редактировать Вебхук' : 'Добавить Исходящий Вебхук';
    document.getElementById('outgoing-webhook-id').value = webhookId || '';
    
    if (!webhookId) {
        // Clear form for new webhook
        document.getElementById('outgoing-webhook-name').value = '';
        document.getElementById('outgoing-webhook-description').value = '';
        document.getElementById('outgoing-webhook-url').value = '';
        document.getElementById('outgoing-webhook-format').value = 'iiko_soi';
        document.getElementById('outgoing-webhook-auth-type').value = 'none';
        document.getElementById('outgoing-webhook-auth-token').value = '';
        document.getElementById('outgoing-webhook-auth-username').value = '';
        document.getElementById('outgoing-webhook-auth-password').value = '';
        document.getElementById('outgoing-webhook-on-created').checked = true;
        document.getElementById('outgoing-webhook-on-updated').checked = true;
        document.getElementById('outgoing-webhook-on-status-changed').checked = true;
        document.getElementById('outgoing-webhook-on-cancelled').checked = false;
        document.getElementById('outgoing-webhook-is-active').checked = true;
        document.getElementById('test-webhook-btn').style.display = 'none';
    } else {
        document.getElementById('test-webhook-btn').style.display = 'inline-block';
    }
    
    toggleAuthFields();
    openModal('outgoing-webhook-modal');
}

async function editOutgoingWebhook(webhookId) {
    try {
        const webhook = await apiGet(`/admin/api/outgoing-webhooks/${webhookId}`);
        
        document.getElementById('outgoing-webhook-id').value = webhook.id;
        document.getElementById('outgoing-webhook-name').value = webhook.name;
        document.getElementById('outgoing-webhook-description').value = webhook.description || '';
        document.getElementById('outgoing-webhook-url').value = webhook.webhook_url;
        document.getElementById('outgoing-webhook-format').value = webhook.payload_format;
        document.getElementById('outgoing-webhook-auth-type').value = webhook.auth_type;
        document.getElementById('outgoing-webhook-auth-token').value = webhook.auth_token || '';
        document.getElementById('outgoing-webhook-auth-username').value = webhook.auth_username || '';
        document.getElementById('outgoing-webhook-on-created').checked = webhook.send_on_order_created;
        document.getElementById('outgoing-webhook-on-updated').checked = webhook.send_on_order_updated;
        document.getElementById('outgoing-webhook-on-status-changed').checked = webhook.send_on_order_status_changed;
        document.getElementById('outgoing-webhook-on-cancelled').checked = webhook.send_on_order_cancelled;
        document.getElementById('outgoing-webhook-is-active').checked = webhook.is_active;
        
        openOutgoingWebhookModal(webhook.id);
    } catch (err) {
        alert('Ошибка загрузки вебхука: ' + err.message);
    }
}

async function saveOutgoingWebhook() {
    const webhookId = document.getElementById('outgoing-webhook-id').value;
    const data = {
        name: document.getElementById('outgoing-webhook-name').value,
        description: document.getElementById('outgoing-webhook-description').value,
        webhook_url: document.getElementById('outgoing-webhook-url').value,
        payload_format: document.getElementById('outgoing-webhook-format').value,
        auth_type: document.getElementById('outgoing-webhook-auth-type').value,
        auth_token: document.getElementById('outgoing-webhook-auth-token').value,
        auth_username: document.getElementById('outgoing-webhook-auth-username').value,
        auth_password: document.getElementById('outgoing-webhook-auth-password').value,
        send_on_order_created: document.getElementById('outgoing-webhook-on-created').checked,
        send_on_order_updated: document.getElementById('outgoing-webhook-on-updated').checked,
        send_on_order_status_changed: document.getElementById('outgoing-webhook-on-status-changed').checked,
        send_on_order_cancelled: document.getElementById('outgoing-webhook-on-cancelled').checked,
        is_active: document.getElementById('outgoing-webhook-is-active').checked,
    };
    
    if (!data.name || !data.webhook_url) {
        alert('Заполните обязательные поля');
        return;
    }
    
    try {
        let result;
        if (webhookId) {
            result = await apiPost(`/admin/api/outgoing-webhooks/${webhookId}`, data, 'PUT');
        } else {
            result = await apiPost('/admin/api/outgoing-webhooks', data);
        }
        
        if (result.status === 200) {
            alert('✅ Вебхук успешно сохранен!');
            closeModal('outgoing-webhook-modal');
            loadOutgoingWebhooks();
        } else {
            alert('❌ Ошибка: ' + JSON.stringify(result.data));
        }
    } catch (err) {
        alert('❌ Ошибка: ' + err.message);
    }
}

async function testOutgoingWebhook() {
    const webhookId = document.getElementById('outgoing-webhook-id').value;
    if (!webhookId) {
        alert('Сначала сохраните вебхук');
        return;
    }
    
    await testOutgoingWebhookById(webhookId);
}

async function testOutgoingWebhookById(webhookId) {
    try {
        const result = await apiPost(`/admin/api/outgoing-webhooks/${webhookId}/test`, {});
        
        if (result.status === 200 && result.data.success) {
            alert(`✅ Тест успешен!\nСтатус: ${result.data.status_code}\nВремя: ${result.data.duration_ms}ms`);
        } else {
            alert(`❌ Тест не пройден\nОшибка: ${result.data.error || 'Unknown error'}`);
        }
    } catch (err) {
        alert('❌ Ошибка тестирования: ' + err.message);
    }
}

async function toggleOutgoingWebhook(webhookId, newActiveState) {
    try {
        const result = await apiPost(`/admin/api/outgoing-webhooks/${webhookId}`, {
            is_active: newActiveState
        }, 'PUT');
        
        if (result.status === 200) {
            loadOutgoingWebhooks();
        } else {
            alert('Ошибка изменения статуса');
        }
    } catch (err) {
        alert('Ошибка: ' + err.message);
    }
}

async function deleteOutgoingWebhook(webhookId) {
    if (!confirm('Вы уверены, что хотите удалить этот вебхук?')) return;
    
    try {
        const result = await apiPost(`/admin/api/outgoing-webhooks/${webhookId}`, {}, 'DELETE');
        
        if (result.status === 200) {
            alert('✅ Вебхук удален');
            loadOutgoingWebhooks();
        } else {
            alert('Ошибка удаления');
        }
    } catch (err) {
        alert('Ошибка: ' + err.message);
    }
}

function toggleAuthFields() {
    const authType = document.getElementById('outgoing-webhook-auth-type').value;
    document.getElementById('auth-token-group').style.display = authType === 'bearer' ? 'block' : 'none';
    document.getElementById('auth-basic-group').style.display = authType === 'basic' ? 'block' : 'none';
}

// ─── Webhook Setup Tab ──────────────────────────────────────
async function loadSetupTab() {
    await loadSetupSettings();
    loadWebhookSetupStats();
    updateSetupConnectionStatus();
}

async function loadSetupSettings() {
    try {
        const settings = await apiGet('/admin/api/iiko-settings');
        setupSettingsList = Array.isArray(settings) ? settings : (Array.isArray(settings?.data) ? settings.data : []);
        
        const select = document.getElementById('setup-setting-select');
        select.innerHTML = '';
        
        if (setupSettingsList.length === 0) {
            select.innerHTML = '<option value="">Нет настроек iiko API</option>';
            return;
        }
        
        // Auto-select: prefer with organization_id
        if (!setupCurrentSettingId || !setupSettingsList.find(s => s.id === setupCurrentSettingId)) {
            const withOrg = setupSettingsList.find(s => s.organization_id);
            setupCurrentSettingId = withOrg ? withOrg.id : setupSettingsList[0].id;
        }
        
        setupSettingsList.forEach(s => {
            const orgLabel = s.organization_id ? ` (${s.organization_name || s.organization_id.substring(0, Math.min(8, s.organization_id.length)) + '...'})` : ' (нет орг.)';
            const selected = s.id === setupCurrentSettingId ? 'selected' : '';
            select.innerHTML += `<option value="${s.id}" ${selected}>${escapeHtml(s.name || 'API #' + s.id)}${orgLabel}</option>`;
        });
        
        onSetupSettingChange();
    } catch (err) {
        document.getElementById('setup-setting-info').innerHTML = 
            '<div class="alert alert-danger">❌ Ошибка загрузки настроек: ' + escapeHtml(err.message) + '</div>';
    }
}

function onSetupSettingChange() {
    const selectEl = document.getElementById('setup-setting-select');
    setupCurrentSettingId = parseInt(selectEl.value) || null;
    const setting = setupSettingsList.find(s => s.id === setupCurrentSettingId);
    const infoEl = document.getElementById('setup-setting-info');
    
    if (!setting) {
        infoEl.innerHTML = '<span class="badge badge-muted">Выберите настройку iiko API</span>';
        return;
    }
    
    setupCurrentOrgId = setting.organization_id || null;
    
    let html = '<div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">';
    html += '<span class="iiko-settings-badge">🔑 ' + escapeHtml(setting.name || 'API #' + setting.id) + '</span>';
    if (setting.organization_id) {
        html += '<span class="iiko-settings-badge" style="background:rgba(34,197,94,0.1);border-color:rgba(34,197,94,0.3);color:var(--success);">🏢 ' + escapeHtml(setting.organization_name || setting.organization_id.substring(0, 12) + '...') + '</span>';
    } else {
        html += '<span class="badge badge-warning">⚠️ Organization ID не задан — сначала выберите организацию на странице Обслуживание</span>';
    }
    if (setting.webhook_url) {
        html += '<span class="iiko-settings-badge" style="background:rgba(34,197,94,0.1);border-color:rgba(34,197,94,0.3);color:var(--success);">✓ Вебхук настроен</span>';
    }
    html += '</div>';
    
    infoEl.innerHTML = html;
    
    // Update generated URL preview
    const domain = window.location.hostname;
    const generatedUrl = `https://${domain}/api/v1/webhooks/iiko`;
    document.getElementById('setup-webhook-url').textContent = setting.webhook_url || generatedUrl;
    document.getElementById('setup-auth-token').textContent = setting.webhook_secret ? '••••••••••••••••' : '(будет сгенерирован)';
    
    updateSetupConnectionStatus();
}

function updateSetupConnectionStatus() {
    const statusEl = document.getElementById('webhook-connection-status');
    const setting = setupSettingsList.find(s => s.id === setupCurrentSettingId);
    
    if (!setting) {
        statusEl.innerHTML = `
            <div class="webhook-setup-status disconnected">
                <div class="status-icon">⚠️</div>
                <div class="status-text">
                    <div class="status-title">Нет настройки iiko API</div>
                    <div class="status-subtitle">Создайте настройку API на странице «Обслуживание»</div>
                </div>
            </div>`;
        return;
    }
    
    if (!setting.organization_id) {
        statusEl.innerHTML = `
            <div class="webhook-setup-status disconnected">
                <div class="status-icon">⚠️</div>
                <div class="status-text">
                    <div class="status-title">Organization ID не задан</div>
                    <div class="status-subtitle">Выберите организацию в настройках API на странице «Обслуживание»</div>
                </div>
            </div>`;
        return;
    }
    
    if (setting.webhook_url && setting.webhook_secret) {
        statusEl.innerHTML = `
            <div class="webhook-setup-status connected">
                <div class="status-icon">✅</div>
                <div class="status-text">
                    <div class="status-title">Вебхук подключен и активен</div>
                    <div class="status-subtitle">URL: ${escapeHtml(setting.webhook_url)}</div>
                </div>
            </div>`;
    } else {
        statusEl.innerHTML = `
            <div class="webhook-setup-status disconnected">
                <div class="status-icon">🔌</div>
                <div class="status-text">
                    <div class="status-title">Вебхук не настроен</div>
                    <div class="status-subtitle">Нажмите «Подключить вебхук автоматически» для настройки</div>
                </div>
            </div>`;
    }
}

async function autoSetupWebhook() {
    const setting = setupSettingsList.find(s => s.id === setupCurrentSettingId);
    const msgEl = document.getElementById('setup-message');
    
    if (!setting) {
        msgEl.innerHTML = '<div class="alert alert-warning">⚠️ Выберите настройку iiko API</div>';
        return;
    }
    if (!setting.organization_id) {
        msgEl.innerHTML = '<div class="alert alert-warning">⚠️ Укажите Organization ID в настройках API на странице «Обслуживание»</div>';
        return;
    }
    
    const btn = document.getElementById('btn-auto-setup');
    btn.disabled = true;
    btn.textContent = '⏳ Подключение...';
    msgEl.innerHTML = '<div style="padding:12px;background:rgba(99,102,241,0.1);border-radius:8px;display:flex;align-items:center;gap:8px;"><span class="spinner" style="width:16px;height:16px;"></span> Регистрация вебхука в iiko Cloud...</div>';
    
    try {
        // Auto-detect domain from current page
        const domain = window.location.hostname;
        
        const result = await apiPost('/admin/api/webhooks/register', {
            setting_id: setupCurrentSettingId,
            webhook_url: `https://${domain}/api/v1/webhooks/iiko`
        });
        
        if (result.status >= 400) {
            msgEl.innerHTML = '<div class="alert alert-danger">❌ ' + escapeHtml(result.data?.detail || JSON.stringify(result.data)) + '</div>';
        } else {
            const data = result.data;
            msgEl.innerHTML = '<div class="alert alert-success">✅ Вебхук успешно зарегистрирован в iiko Cloud!</div>';
            document.getElementById('setup-webhook-url').textContent = data.webhook_url || '—';
            document.getElementById('setup-auth-token').textContent = data.auth_token && data.auth_token.length >= 6 ? '••••••••' + data.auth_token.substring(data.auth_token.length - 6) : '••••••••';
            
            // Reload settings to reflect changes
            await loadSetupSettings();
        }
    } catch (err) {
        msgEl.innerHTML = '<div class="alert alert-danger">❌ ' + escapeHtml(err.message) + '</div>';
    } finally {
        btn.disabled = false;
        btn.textContent = '🔗 Подключить вебхук автоматически';
    }
}

async function checkWebhookConnection() {
    const setting = setupSettingsList.find(s => s.id === setupCurrentSettingId);
    const msgEl = document.getElementById('setup-message');
    
    if (!setting) {
        msgEl.innerHTML = '<div class="alert alert-warning">⚠️ Выберите настройку iiko API</div>';
        return;
    }
    if (!setting.webhook_url) {
        msgEl.innerHTML = '<div class="alert alert-warning">⚠️ Сначала подключите вебхук</div>';
        return;
    }
    
    const btn = document.getElementById('btn-check-connection');
    btn.disabled = true;
    btn.textContent = '⏳ Проверка...';
    msgEl.innerHTML = '<div style="padding:12px;background:rgba(99,102,241,0.1);border-radius:8px;display:flex;align-items:center;gap:8px;"><span class="spinner" style="width:16px;height:16px;"></span> Тестирование вебхука...</div>';
    
    try {
        const result = await apiPost('/admin/api/webhooks/test', { setting_id: setupCurrentSettingId });
        
        if (result.status >= 400) {
            msgEl.innerHTML = '<div class="alert alert-danger">❌ ' + escapeHtml(result.data?.detail || JSON.stringify(result.data)) + '</div>';
            updateConnectionStatusError();
        } else {
            const data = result.data;
            if (data.status === 'success') {
                msgEl.innerHTML = `<div class="alert alert-success">✅ Вебхук работает! Статус ответа: ${data.response_status}</div>`;
            } else {
                msgEl.innerHTML = `<div class="alert alert-danger">❌ Ошибка тестирования: ${escapeHtml(data.error || 'Нет ответа')}</div>`;
            }
        }
    } catch (err) {
        msgEl.innerHTML = '<div class="alert alert-danger">❌ ' + escapeHtml(err.message) + '</div>';
    } finally {
        btn.disabled = false;
        btn.textContent = '🧪 Проверить подключение';
    }
}

function updateConnectionStatusError() {
    const statusEl = document.getElementById('webhook-connection-status');
    statusEl.innerHTML = `
        <div class="webhook-setup-status error">
            <div class="status-icon">❌</div>
            <div class="status-text">
                <div class="status-title">Ошибка подключения</div>
                <div class="status-subtitle">Проверьте настройки домена и доступность сервера</div>
            </div>
        </div>`;
}

async function loadIikoWebhookSettings() {
    const setting = setupSettingsList.find(s => s.id === setupCurrentSettingId);
    const container = document.getElementById('iiko-cloud-webhook-config');
    
    if (!setting) {
        container.innerHTML = '<div class="alert alert-warning">⚠️ Выберите настройку iiko API</div>';
        return;
    }
    if (!setting.organization_id) {
        container.innerHTML = '<div class="alert alert-warning">⚠️ Organization ID не задан</div>';
        return;
    }
    
    container.innerHTML = '<div style="display:flex;align-items:center;gap:8px;padding:8px;"><span class="spinner" style="width:16px;height:16px;"></span> Загрузка настроек из iiko Cloud...</div>';
    
    try {
        const result = await apiGet(`/admin/api/webhooks/settings?setting_id=${setupCurrentSettingId}`);
        const data = result.data || result;
        
        let html = '';
        if (data.webHooksUri) {
            html += '<div class="webhook-info-grid">';
            html += '<div class="webhook-info-item"><div class="info-label">Webhook URL в iiko</div><div class="info-value accent">' + escapeHtml(data.webHooksUri) + '</div></div>';
            html += '</div>';
            
            if (data.webHooksFilter) {
                html += '<div style="margin-top:12px;"><strong style="font-size:13px;color:var(--text-bright);">Фильтры событий:</strong></div>';
                
                const filter = data.webHooksFilter;
                html += '<div class="webhook-filter-list">';
                
                if (filter.deliveryOrderFilter) {
                    const statuses = filter.deliveryOrderFilter.orderStatuses || [];
                    html += '<div class="webhook-filter-item"><span class="check-icon">✓</span> Статусы заказов: ' + statuses.length + '</div>';
                    const items = filter.deliveryOrderFilter.itemStatuses || [];
                    html += '<div class="webhook-filter-item"><span class="check-icon">✓</span> Статусы блюд: ' + items.length + '</div>';
                    if (filter.deliveryOrderFilter.errors) {
                        html += '<div class="webhook-filter-item"><span class="check-icon">✓</span> Ошибки</div>';
                    }
                }
                if (filter.stopListUpdateFilter?.updates) {
                    html += '<div class="webhook-filter-item"><span class="check-icon">✓</span> Стоп-листы</div>';
                }
                if (filter.personalShiftFilter?.updates) {
                    html += '<div class="webhook-filter-item"><span class="check-icon">✓</span> Персональные смены</div>';
                }
                if (filter.reserveFilter?.updates) {
                    html += '<div class="webhook-filter-item"><span class="check-icon">✓</span> Резервации</div>';
                }
                html += '</div>';
                
                html += '<details style="margin-top:12px;"><summary style="cursor:pointer;font-size:12px;color:var(--muted);">Показать полный JSON</summary>';
                html += '<pre style="font-size:11px;max-height:300px;overflow:auto;margin-top:8px;padding:12px;background:rgba(0,0,0,0.2);border-radius:8px;">' + escapeHtml(JSON.stringify(data.webHooksFilter, null, 2)) + '</pre>';
                html += '</details>';
            }
        } else {
            html = '<span class="badge badge-warning">⚠️ Вебхук не зарегистрирован в iiko Cloud</span>';
        }
        
        container.innerHTML = html;
    } catch (err) {
        container.innerHTML = '<div class="alert alert-danger">❌ ' + escapeHtml(err.message) + '</div>';
    }
}

async function loadWebhookSetupStats() {
    try {
        let url = '/admin/api/webhooks/events?limit=100';
        const events = await apiGet(url);
        const eventsList = Array.isArray(events) ? events : [];
        
        document.getElementById('stat-setup-total').textContent = eventsList.length;
        document.getElementById('stat-setup-processed').textContent = eventsList.filter(e => e.processed).length;
        document.getElementById('stat-setup-errors').textContent = eventsList.filter(e => e.processing_error).length;
        
        if (eventsList.length > 0) {
            const last = eventsList[0];
            const lastDate = last.created_at ? new Date(last.created_at) : null;
            if (lastDate) {
                const now = new Date();
                const diffMs = now - lastDate;
                const diffMin = Math.floor(diffMs / 60000);
                if (diffMin < 1) {
                    document.getElementById('stat-setup-last').textContent = 'только что';
                } else if (diffMin < 60) {
                    document.getElementById('stat-setup-last').textContent = diffMin + ' мин назад';
                } else if (diffMin < 1440) {
                    document.getElementById('stat-setup-last').textContent = Math.floor(diffMin / 60) + ' ч назад';
                } else {
                    document.getElementById('stat-setup-last').textContent = Math.floor(diffMin / 1440) + ' дн назад';
                }
            } else {
                document.getElementById('stat-setup-last').textContent = '—';
            }
        }
    } catch (err) {
        // Silent fail for stats
    }
}

// Auto-load on page load
document.addEventListener('DOMContentLoaded', () => {
    loadSetupTab();
});
</script>
@endsection

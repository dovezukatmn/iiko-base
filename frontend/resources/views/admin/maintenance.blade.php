@extends('layouts.admin')

@section('title', 'Обслуживание')
@section('page-title', 'Обслуживание')

@section('styles')
<style>
    .section-gap { margin-bottom: 20px; }
    .component-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 0;
        border-bottom: 1px solid var(--border);
    }
    .component-row:last-child { border-bottom: none; }
    .component-name {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
    }
    .mono { font-family: 'SF Mono', 'Fira Code', monospace; font-size: 12px; }
    .settings-form { max-width: 520px; }
    .webhook-result {
        padding: 14px;
        border-radius: 12px;
        border: 1px solid var(--border);
        background: rgba(0,0,0,0.2);
        margin-top: 12px;
    }
    .data-section {
        margin-top: 12px;
        padding: 14px;
        border-radius: 12px;
        border: 1px solid var(--border);
        background: rgba(0,0,0,0.15);
    }
</style>
@endsection

@section('content')
{{-- Tab Bar --}}
<div class="tab-bar">
    <button class="tab-btn active" onclick="switchTab('status', event)">📡 Статус</button>
    <button class="tab-btn" onclick="switchTab('settings', event)">⚙️ Настройки API</button>
    <button class="tab-btn" onclick="switchTab('webhooks', event)">🔗 Вебхуки</button>
    <button class="tab-btn" onclick="switchTab('data', event)">📋 Данные iiko</button>
    <button class="tab-btn" onclick="switchTab('loyalty', event)">🎁 Лояльность</button>
    <button class="tab-btn" onclick="switchTab('logs', event)">📝 Логи</button>
</div>

{{-- ═══ TAB: Server Status ═══ --}}
<div class="tab-content active" id="tab-status">
    <div class="grid-4 section-gap" id="stat-cards">
        <div class="card stat-card">
            <span class="stat-label">Сервер</span>
            <span class="stat-value" id="stat-server" style="font-size:18px;">
                <span class="spinner"></span>
            </span>
        </div>
        <div class="card stat-card">
            <span class="stat-label">Аптайм</span>
            <span class="stat-value" id="stat-uptime" style="font-size:18px;">—</span>
        </div>
        <div class="card stat-card">
            <span class="stat-label">Заказы</span>
            <span class="stat-value" id="stat-orders" style="font-size:24px;">—</span>
        </div>
        <div class="card stat-card">
            <span class="stat-label">Вебхук-события</span>
            <span class="stat-value" id="stat-webhooks" style="font-size:24px;">—</span>
        </div>
    </div>

    <div class="grid-2 section-gap">
        {{-- Components Status --}}
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">Компоненты</div>
                    <div class="card-subtitle">Статус работы сервисов</div>
                </div>
                <button class="btn btn-sm" onclick="loadStatus()">🔄 Обновить</button>
            </div>
            <div id="components-list">
                <div class="loading-overlay"><span class="spinner"></span> Загрузка...</div>
            </div>
        </div>

        {{-- Statistics --}}
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">Статистика</div>
                    <div class="card-subtitle">Общие показатели системы</div>
                </div>
            </div>
            <div id="stats-details">
                <div class="loading-overlay"><span class="spinner"></span> Загрузка...</div>
            </div>
        </div>
    </div>

    {{-- Recent Errors --}}
    <div class="card section-gap">
        <div class="card-header">
            <div>
                <div class="card-title">Последние ошибки</div>
                <div class="card-subtitle">Ошибки в запросах к iiko API</div>
            </div>
        </div>
        <div id="errors-list">
            <div class="loading-overlay"><span class="spinner"></span> Загрузка...</div>
        </div>
    </div>
</div>

{{-- ═══ TAB: API Settings ═══ --}}
<div class="tab-content" id="tab-settings">
    <div class="grid-2 section-gap">
        {{-- Add / Edit iiko API Login --}}
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">IIKO API Логин</div>
                    <div class="card-subtitle">Добавьте или измените API ключ интеграции</div>
                </div>
            </div>
            <div class="settings-form">
                <div class="form-group">
                    <label class="form-label">API ключ (apiLogin)</label>
                    <div style="position:relative;">
                        <input type="password" class="form-input" id="api-key-input" placeholder="Введите ваш iiko API логин" autocomplete="new-password" style="padding-right:40px;">
                        <button type="button" id="api-key-toggle-btn" onclick="toggleApiKeyVisibility()" aria-label="Показать API ключ" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:18px;padding:0;width:24px;height:24px;display:flex;align-items:center;justify-content:center;outline:2px solid transparent;outline-offset:2px;border-radius:4px;transition:outline 0.2s;" onfocus="this.style.outline='2px solid var(--accent)'" onblur="this.style.outline='2px solid transparent'">
                            <span id="api-key-toggle-icon" aria-hidden="true">👁</span>
                        </button>
                    </div>
                    <div style="font-size:11px;color:var(--muted);margin-top:4px;">
                        💡 При редактировании оставьте пустым, чтобы сохранить текущий ключ
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">API URL</label>
                    <input type="text" class="form-input" id="api-url-input" value="https://api-ru.iiko.services/api/1" placeholder="https://api-ru.iiko.services/api/1">
                </div>
                <div class="form-group">
                    <label class="form-label">Organization ID (необязательно)</label>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <select class="form-input" id="org-id-select" style="flex:1;">
                            <option value="">— Не выбрано —</option>
                        </select>
                        <button type="button" class="btn btn-sm" id="btn-load-orgs" onclick="loadOrganizations()" title="Загрузить организации по API ключу">🔄 Загрузить</button>
                    </div>
                    <input type="text" class="form-input" id="org-id-input" placeholder="Или введите UUID вручную" style="margin-top:6px;font-size:12px;">
                    <div id="org-load-message" style="margin-top:4px;font-size:11px;"></div>
                </div>
                <div style="display:flex;gap:8px;">
                    <button class="btn btn-primary" id="btn-save-settings" onclick="saveSettings()">💾 Сохранить</button>
                    <button class="btn btn-success" id="btn-test-connection" onclick="testConnection()" disabled>🔌 Проверить</button>
                </div>
                <div id="settings-message" style="margin-top:12px;"></div>
            </div>
        </div>

        {{-- Existing Settings List --}}
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">Сохраненные настройки</div>
                    <div class="card-subtitle">Активные интеграции с iiko</div>
                </div>
                <button class="btn btn-sm" onclick="loadSettings()">🔄</button>
            </div>
            <div id="settings-list">
                <div class="loading-overlay"><span class="spinner"></span> Загрузка...</div>
            </div>
        </div>
    </div>

    {{-- Connection Status --}}
    <div class="card section-gap">
        <div class="card-header">
            <div>
                <div class="card-title">Статус подключения к IIKO API</div>
                <div class="card-subtitle">Проверьте работоспособность соединения</div>
            </div>
        </div>
        <div id="connection-status">
            <span class="badge badge-muted">Выберите настройку и нажмите «Проверить» для тестирования</span>
        </div>
    </div>
</div>

{{-- ═══ TAB: Webhooks ═══ --}}
<div class="tab-content" id="tab-webhooks">
    <div class="grid-2 section-gap">
        {{-- Webhook Configuration --}}
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">Настройка вебхука</div>
                    <div class="card-subtitle">Введите домен — URL и токен создадутся автоматически</div>
                </div>
            </div>
            <div class="settings-form">
                <div class="form-group">
                    <label class="form-label">Настройка iiko</label>
                    <select class="form-input" id="webhook-setting-select" onchange="onWebhookSettingChange()">
                        <option value="">Загрузка...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Домен вашего сервера</label>
                    <input type="text" class="form-input" id="webhook-domain-input" placeholder="example.com">
                    <div style="font-size:11px;color:var(--muted);margin-top:4px;">
                        Введите только домен (например: vezuroll.ru). URL вебхука и токен авторизации будут сгенерированы автоматически.
                    </div>
                </div>
                <button class="btn btn-primary" onclick="registerWebhook()">🔗 Привязать вебхук</button>
                <div id="webhook-result" style="margin-top:12px;display:none;">
                    <div class="webhook-result">
                        <div style="margin-bottom:8px;">
                            <span class="form-label">URL вебхука (создан автоматически):</span>
                        </div>
                        <div class="mono" id="webhook-generated-url" style="color:var(--accent);word-break:break-all;margin-bottom:10px;"></div>
                        <div style="margin-bottom:8px;">
                            <span class="form-label">Токен авторизации вебхука (создан автоматически):</span>
                        </div>
                        <div class="mono" id="webhook-auth-token" style="color:var(--accent-2);word-break:break-all;"></div>
                        <div style="margin-top:8px;">
                            <span class="badge badge-success">✓ Вебхук зарегистрирован в iiko</span>
                        </div>
                    </div>
                </div>
                <div id="webhook-error" style="margin-top:12px;"></div>
            </div>
        </div>

        <div>
            {{-- Current Webhook Settings --}}
            <div class="card" style="margin-bottom:16px;">
                <div class="card-header">
                    <div>
                        <div class="card-title">Текущие настройки вебхука</div>
                        <div class="card-subtitle">Сохраненные URL и токен для выбранной интеграции</div>
                    </div>
                </div>
                <div id="current-webhook-info">
                    <span class="badge badge-muted">Выберите настройку iiko для просмотра</span>
                </div>
            </div>

            {{-- Webhook Events --}}
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Входящие события</div>
                        <div class="card-subtitle">Последние вебхук-события от iiko</div>
                    </div>
                    <button class="btn btn-sm" onclick="loadWebhookEvents()">🔄</button>
                </div>
                <div id="webhook-events-list">
                    <div class="loading-overlay"><span class="spinner"></span> Загрузка...</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ═══ TAB: iiko Data ═══ --}}
<div class="tab-content" id="tab-data">
    <div class="card section-gap">
        <div class="card-header">
            <div>
                <div class="card-title">Данные из iiko</div>
                <div class="card-subtitle">Просматривайте доступные данные интеграции</div>
            </div>
        </div>

        <div class="grid-3" style="margin-bottom:16px;">
            <div class="form-group">
                <label class="form-label">Настройка iiko</label>
                <select class="form-input" id="data-setting-select">
                    <option value="">Загрузка...</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Организация</label>
                <select class="form-input" id="data-org-select" disabled>
                    <option value="">Сначала загрузите организации</option>
                </select>
            </div>
            <div class="form-group" style="display:flex;align-items:flex-end;">
                <button class="btn btn-primary" onclick="loadDataOrganizations()">📡 Загрузить организации</button>
            </div>
        </div>
    </div>

    <div class="grid-2 section-gap">
        {{-- Terminal Groups / Venues --}}
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">🏪 Точки / Заведения</div>
                    <div class="card-subtitle">Терминальные группы</div>
                </div>
                <button class="btn btn-sm" onclick="loadDataSection('terminal-groups')">Загрузить</button>
            </div>
            <div id="data-terminal-groups">
                <span class="badge badge-muted">Нажмите «Загрузить» для получения данных</span>
            </div>
        </div>

        {{-- Payment Types --}}
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">💳 Типы оплат</div>
                    <div class="card-subtitle">Доступные способы оплаты</div>
                </div>
                <button class="btn btn-sm" onclick="loadDataSection('payment-types')">Загрузить</button>
            </div>
            <div id="data-payment-types">
                <span class="badge badge-muted">Нажмите «Загрузить» для получения данных</span>
            </div>
        </div>

        {{-- Couriers --}}
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">🚴 Курьеры</div>
                    <div class="card-subtitle">Доступные курьеры</div>
                </div>
                <button class="btn btn-sm" onclick="loadDataSection('couriers')">Загрузить</button>
            </div>
            <div id="data-couriers">
                <span class="badge badge-muted">Нажмите «Загрузить» для получения данных</span>
            </div>
        </div>

        {{-- Order Types --}}
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">📦 Типы заказов</div>
                    <div class="card-subtitle">Доступные типы заказов доставки</div>
                </div>
                <button class="btn btn-sm" onclick="loadDataSection('order-types')">Загрузить</button>
            </div>
            <div id="data-order-types">
                <span class="badge badge-muted">Нажмите «Загрузить» для получения данных</span>
            </div>
        </div>

        {{-- Discount Types --}}
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">🏷️ Скидки</div>
                    <div class="card-subtitle">Доступные типы скидок</div>
                </div>
                <button class="btn btn-sm" onclick="loadDataSection('discount-types')">Загрузить</button>
            </div>
            <div id="data-discount-types">
                <span class="badge badge-muted">Нажмите «Загрузить» для получения данных</span>
            </div>
        </div>

        {{-- Stop Lists --}}
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">🚫 Стоп-листы</div>
                    <div class="card-subtitle">Позиции в стоп-листе</div>
                </div>
                <button class="btn btn-sm" onclick="loadDataSection('stop-lists')">Загрузить</button>
            </div>
            <div id="data-stop-lists">
                <span class="badge badge-muted">Нажмите «Загрузить» для получения данных</span>
            </div>
        </div>
    </div>
</div>

{{-- ═══ TAB: Loyalty / iikoCard ═══ --}}
<div class="tab-content" id="tab-loyalty">
    <div class="grid-2 section-gap">
        {{-- Loyalty Programs --}}
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">🎁 Программы лояльности</div>
                    <div class="card-subtitle">Бонусные программы из iiko</div>
                </div>
                <button class="btn btn-sm" onclick="loadLoyaltyPrograms()" id="btn-load-programs">📥 Загрузить</button>
            </div>
            <div id="loyalty-programs-list">
                <span class="badge badge-muted">Выберите настройку API и нажмите «Загрузить»</span>
            </div>
            <div id="loyalty-wallet-select-section" style="display:none;margin-top:12px;">
                <div class="form-group">
                    <label class="form-label">Активная бонусная программа</label>
                    <select class="form-input" id="loyalty-active-program" onchange="onProgramSelected()">
                        <option value="">— Выберите программу —</option>
                    </select>
                </div>
                <div id="loyalty-program-detail" style="margin-top:8px;"></div>
            </div>
        </div>

        {{-- Customer Search --}}
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">🔍 Поиск гостя</div>
                    <div class="card-subtitle">Поиск в программе лояльности</div>
                </div>
            </div>
            <div class="settings-form">
                <div class="form-group">
                    <label class="form-label">Телефон или email</label>
                    <input class="form-input" id="loyalty-search-query" placeholder="+7XXXXXXXXXX или email">
                </div>
                <div class="form-group">
                    <label class="form-label">Тип поиска</label>
                    <select class="form-input" id="loyalty-search-type">
                        <option value="phone">По телефону</option>
                        <option value="email">По email</option>
                        <option value="cardNumber">По номеру карты</option>
                        <option value="cardTrack">По треку карты</option>
                    </select>
                </div>
                <button class="btn btn-primary" onclick="searchLoyaltyCustomer()">🔍 Найти</button>
            </div>
            <div id="loyalty-customer-info" style="margin-top:12px;"></div>
        </div>
    </div>

    {{-- Customer Balance & Operations --}}
    <div class="card section-gap">
        <div class="card-header">
            <div>
                <div class="card-title">💰 Управление бонусами</div>
                <div class="card-subtitle">Пополнение, списание и холдирование бонусов</div>
            </div>
        </div>
        <div id="loyalty-balance-section">
            <span class="badge badge-muted">Найдите гостя для работы с бонусами</span>
        </div>

        <div id="loyalty-operations" style="display:none;margin-top:16px;">
            <div class="grid-3">
                <div class="form-group">
                    <label class="form-label">ID кошелька</label>
                    <select class="form-input" id="loyalty-wallet-id"></select>
                </div>
                <div class="form-group">
                    <label class="form-label">Сумма</label>
                    <input class="form-input" id="loyalty-amount" type="number" step="0.01" min="0.01" placeholder="100.00">
                </div>
                <div class="form-group">
                    <label class="form-label">Комментарий</label>
                    <input class="form-input" id="loyalty-comment" placeholder="Необязательно">
                </div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button class="btn btn-success" onclick="loyaltyTopup()">➕ Пополнить</button>
                <button class="btn btn-danger" onclick="loyaltyWithdraw()">➖ Списать</button>
                <button class="btn" onclick="loyaltyHold()">🔒 Холдировать</button>
            </div>
            <div id="loyalty-operation-result" style="margin-top:12px;"></div>
        </div>
    </div>

    {{-- Transaction History --}}
    <div class="card section-gap">
        <div class="card-header">
            <div>
                <div class="card-title">📋 История операций с бонусами</div>
                <div class="card-subtitle">Начисления и списания в режиме реального времени</div>
            </div>
            <div style="display:flex;gap:8px;align-items:center;">
                <label style="font-size:12px;color:var(--muted);display:flex;align-items:center;gap:4px;">
                    <input type="checkbox" id="loyalty-auto-refresh" onchange="toggleAutoRefresh()"> Авто-обновление
                </label>
                <button class="btn btn-sm" onclick="loadTransactionHistory()">🔄 Обновить</button>
            </div>
        </div>
        <div id="loyalty-transactions-list">
            <span class="badge badge-muted">Загрузите программы лояльности для просмотра истории</span>
        </div>
    </div>

    {{-- Create/Update Customer --}}
    <div class="card section-gap">
        <div class="card-header">
            <div>
                <div class="card-title">➕ Создать / обновить гостя</div>
                <div class="card-subtitle">Добавление нового гостя в программу лояльности</div>
            </div>
        </div>
        <div class="settings-form">
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Имя</label>
                    <input class="form-input" id="new-customer-name" placeholder="Иван Петров">
                </div>
                <div class="form-group">
                    <label class="form-label">Телефон</label>
                    <input class="form-input" id="new-customer-phone" placeholder="+79001234567">
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input class="form-input" id="new-customer-email" placeholder="email@example.com">
                </div>
                <div class="form-group">
                    <label class="form-label">Дата рождения</label>
                    <input class="form-input" id="new-customer-birthday" type="date">
                </div>
            </div>
            <button class="btn btn-primary" onclick="createOrUpdateCustomer()">💾 Сохранить</button>
            <div id="new-customer-result" style="margin-top:12px;"></div>
        </div>
    </div>
</div>

{{-- ═══ TAB: Logs ═══ --}}
<div class="tab-content" id="tab-logs">
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Журнал API запросов</div>
                <div class="card-subtitle">Последние запросы к iiko API</div>
            </div>
            <button class="btn btn-sm" onclick="loadLogs()">🔄 Обновить</button>
        </div>
        <div id="logs-list">
            <div class="loading-overlay"><span class="spinner"></span> Загрузка...</div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

// ─── State ───────────────────────────────────────────────
let currentSettingId = null;
let settingsList = [];

// ─── Tabs ────────────────────────────────────────────────
function switchTab(name, evt) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    if (evt && evt.target) evt.target.classList.add('active');

    if (name === 'status') loadStatus();
    if (name === 'settings') loadSettings();
    if (name === 'webhooks') { loadSettings(); loadWebhookEvents(); }
    if (name === 'data') loadSettings();
    if (name === 'loyalty') loadSettings();
    if (name === 'logs') loadLogs();
}

// ─── HTTP Helpers ─────────────────────────────────────────
async function apiGet(url) {
    const res = await fetch(url, { headers: { 'X-CSRF-TOKEN': csrfToken } });
    return res.json();
}

async function apiPost(url, body = {}) {
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(body),
    });
    return { status: res.status, data: await res.json() };
}

async function apiPut(url, body = {}) {
    const res = await fetch(url, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(body),
    });
    return { status: res.status, data: await res.json() };
}

async function apiDelete(url) {
    const res = await fetch(url, {
        method: 'DELETE',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
        },
    });
    return { status: res.status, data: await res.json() };
}

// ─── Format helpers ──────────────────────────────────────
function formatUptime(seconds) {
    const d = Math.floor(seconds / 86400);
    const h = Math.floor((seconds % 86400) / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    if (d > 0) return d + 'д ' + h + 'ч';
    if (h > 0) return h + 'ч ' + m + 'м';
    return m + 'м ' + (seconds % 60) + 'с';
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = String(str);
    return div.innerHTML;
}

// ─── Status Tab ──────────────────────────────────────────
async function loadStatus() {
    try {
        const data = await apiGet('/admin/api/status');
        // Server status
        const serverStatus = data.server?.status === 'running';
        document.getElementById('stat-server').innerHTML =
            '<span class="status-dot ' + (serverStatus ? 'online' : 'offline') + '"></span>' +
            (serverStatus ? 'Работает' : 'Ошибка');
        document.getElementById('stat-uptime').textContent = formatUptime(data.server?.uptime_seconds || 0);
        document.getElementById('stat-orders').textContent = data.stats?.orders ?? '—';
        document.getElementById('stat-webhooks').textContent = data.stats?.webhook_events ?? '—';

        // Components
        const comps = data.components || {};
        let compHtml = '';
        compHtml += '<div class="component-row">' +
            '<div class="component-name"><span class="status-dot ' + (serverStatus ? 'online' : 'offline') + '"></span> FastAPI Сервер</div>' +
            '<span class="badge ' + (serverStatus ? 'badge-success' : 'badge-danger') + '">' + (serverStatus ? 'Работает' : 'Ошибка') + '</span></div>';
        compHtml += '<div class="component-row">' +
            '<div class="component-name"><span class="status-dot ' + (comps.database?.status === 'ok' ? 'online' : 'offline') + '"></span> PostgreSQL</div>' +
            '<span class="badge ' + (comps.database?.status === 'ok' ? 'badge-success' : 'badge-danger') + '">' + (comps.database?.status === 'ok' ? 'Подключена' : 'Ошибка') + '</span></div>';
        compHtml += '<div class="component-row">' +
            '<div class="component-name"><span class="status-dot ' + (comps.iiko_api?.configured ? 'online' : 'warning') + '"></span> iiko Cloud API</div>' +
            '<span class="badge ' + (comps.iiko_api?.configured ? 'badge-success' : 'badge-warning') + '">' + (comps.iiko_api?.configured ? 'Настроено' : 'Не настроено') + '</span></div>';
        document.getElementById('components-list').innerHTML = compHtml;

        // Stats details
        const stats = data.stats || {};
        let statsHtml = '';
        statsHtml += '<div class="component-row"><div class="component-name">📦 Заказы</div><strong>' + (stats.orders ?? 0) + '</strong></div>';
        statsHtml += '<div class="component-row"><div class="component-name">🔗 Вебхук-события</div><strong>' + (stats.webhook_events ?? 0) + '</strong></div>';
        statsHtml += '<div class="component-row"><div class="component-name">📝 API логов</div><strong>' + (stats.api_logs ?? 0) + '</strong></div>';
        statsHtml += '<div class="component-row"><div class="component-name">👥 Пользователи</div><strong>' + (stats.users ?? 0) + '</strong></div>';
        statsHtml += '<div class="component-row"><div class="component-name">⚙️ Интеграции iiko</div><strong>' + (stats.iiko_settings ?? 0) + '</strong></div>';
        document.getElementById('stats-details').innerHTML = statsHtml;

        // Errors
        const errors = data.recent_errors || [];
        if (errors.length === 0) {
            document.getElementById('errors-list').innerHTML = '<span class="badge badge-success">✓ Ошибок нет</span>';
        } else {
            let errHtml = '<div class="table-wrap"><table><thead><tr><th>ID</th><th>Метод</th><th>URL</th><th>Статус</th><th>Время</th><th>Дата</th></tr></thead><tbody>';
            errors.forEach(e => {
                errHtml += '<tr>' +
                    '<td>' + e.id + '</td>' +
                    '<td><span class="badge badge-muted">' + escapeHtml(e.method) + '</span></td>' +
                    '<td class="mono" style="max-width:250px;overflow:hidden;text-overflow:ellipsis;">' + escapeHtml(e.url) + '</td>' +
                    '<td><span class="badge badge-danger">' + e.status + '</span></td>' +
                    '<td>' + (e.duration_ms || '—') + ' мс</td>' +
                    '<td style="font-size:12px;color:var(--muted);">' + (e.created_at || '—') + '</td>' +
                    '</tr>';
            });
            errHtml += '</tbody></table></div>';
            document.getElementById('errors-list').innerHTML = errHtml;
        }
    } catch (err) {
        document.getElementById('stat-server').innerHTML = '<span class="status-dot offline"></span> Недоступен';
        document.getElementById('components-list').innerHTML = '<div class="alert alert-danger">⚠️ Не удалось загрузить статус: ' + escapeHtml(err.message) + '</div>';
    }
}

// ─── Settings Tab ────────────────────────────────────────
async function loadSettings() {
    try {
        const data = await apiGet('/admin/api/iiko-settings');
        settingsList = Array.isArray(data) ? data : [];
        renderSettingsList();
        populateSettingSelects();
    } catch (err) {
        document.getElementById('settings-list').innerHTML = '<div class="alert alert-danger">⚠️ Ошибка: ' + escapeHtml(err.message) + '</div>';
    }
}

function renderSettingsList() {
    const container = document.getElementById('settings-list');
    if (settingsList.length === 0) {
        container.innerHTML = '<span class="badge badge-muted">Нет сохраненных настроек. Добавьте API ключ.</span>';
        document.getElementById('btn-test-connection').disabled = true;
        return;
    }
    let html = '';
    settingsList.forEach(s => {
        const isSelected = currentSettingId === s.id;
        const orgDisplay = s.organization_name 
            ? escapeHtml(s.organization_name)
            : (s.organization_id ? escapeHtml(s.organization_id) : null);
        html += '<div class="component-row" style="cursor:pointer;' + (isSelected ? 'background:rgba(99,102,241,0.08);border-radius:8px;padding:10px;' : '') + '" onclick="selectSetting(' + s.id + ')">' +
            '<div class="component-name">' +
                '<span class="status-dot ' + (s.is_active ? 'online' : 'offline') + '"></span>' +
                '<div>' +
                    '<div style="font-weight:600;">Интеграция #' + s.id + '</div>' +
                    '<div style="font-size:11px;color:var(--muted);">' + escapeHtml(s.api_url) + '</div>' +
                    (orgDisplay ? '<div style="font-size:11px;color:var(--accent-2);">Org: ' + orgDisplay + '</div>' : '') +
                    (s.webhook_url ? '<div style="font-size:11px;color:var(--success);">Webhook: ✓</div>' : '') +
                '</div>' +
            '</div>' +
            '<div style="display:flex;gap:8px;align-items:center;">' +
                '<span class="badge ' + (isSelected ? 'badge-success' : 'badge-muted') + '">' + (isSelected ? '✓ Выбрано' : 'Выбрать') + '</span>' +
                '<button type="button" class="btn btn-sm" onclick="deleteSetting(event, ' + s.id + ')" title="Удалить настройку" aria-label="Удалить настройку интеграции #' + s.id + '" style="background:var(--danger);color:white;padding:4px 8px;">🗑️</button>' +
            '</div>' +
            '</div>';
    });
    container.innerHTML = html;
    document.getElementById('btn-test-connection').disabled = !currentSettingId;
}

function selectSetting(id) {
    currentSettingId = id;
    renderSettingsList();
    const setting = settingsList.find(s => s.id === id);
    if (setting) {
        document.getElementById('api-url-input').value = setting.api_url || '';
        // Set dropdown if matching option exists, otherwise set manual input
        const sel = document.getElementById('org-id-select');
        const manualInput = document.getElementById('org-id-input');
        if (setting.organization_id) {
            let found = false;
            for (let i = 0; i < sel.options.length; i++) {
                if (sel.options[i].value === setting.organization_id) {
                    sel.value = setting.organization_id;
                    found = true;
                    break;
                }
            }
            if (!found) {
                manualInput.value = setting.organization_id;
                sel.value = '';
            } else {
                manualInput.value = '';
            }
        } else {
            sel.value = '';
            manualInput.value = '';
        }
    }
}

async function loadOrganizations() {
    const apiKey = document.getElementById('api-key-input').value.trim();
    const apiUrl = document.getElementById('api-url-input').value.trim();
    const sel = document.getElementById('org-id-select');
    const msgEl = document.getElementById('org-load-message');

    // If we have a saved setting selected, use setting_id endpoint
    if (currentSettingId && !apiKey) {
        msgEl.innerHTML = '<span class="spinner" style="width:14px;height:14px;"></span> Загрузка организаций...';
        try {
            const result = await apiPost('/admin/api/iiko-organizations', { setting_id: currentSettingId });
            if (result.status >= 400) {
                msgEl.innerHTML = '<span style="color:var(--danger);">⚠️ ' + escapeHtml(result.data.detail || JSON.stringify(result.data)) + '</span>';
                return;
            }
            const orgs = result.data.organizations || [];
            populateOrgSelect(sel, orgs);
            msgEl.innerHTML = '<span style="color:var(--success);">✓ Загружено организаций: ' + orgs.length + '</span>';
        } catch (err) {
            msgEl.innerHTML = '<span style="color:var(--danger);">⚠️ ' + escapeHtml(err.message) + '</span>';
        }
        return;
    }

    // Otherwise use API key directly
    if (!apiKey) {
        msgEl.innerHTML = '<span style="color:var(--danger);">⚠️ Введите API ключ для загрузки организаций</span>';
        return;
    }

    msgEl.innerHTML = '<span class="spinner" style="width:14px;height:14px;"></span> Загрузка организаций...';
    try {
        const result = await apiPost('/admin/api/iiko-organizations-by-key', {
            api_key: apiKey,
            api_url: apiUrl || 'https://api-ru.iiko.services/api/1',
        });
        if (result.status >= 400) {
            msgEl.innerHTML = '<span style="color:var(--danger);">⚠️ ' + escapeHtml(result.data.detail || JSON.stringify(result.data)) + '</span>';
            return;
        }
        const orgs = result.data.organizations || [];
        populateOrgSelect(sel, orgs);
        msgEl.innerHTML = '<span style="color:var(--success);">✓ Загружено организаций: ' + orgs.length + '</span>';
    } catch (err) {
        msgEl.innerHTML = '<span style="color:var(--danger);">⚠️ ' + escapeHtml(err.message) + '</span>';
    }
}

function populateOrgSelect(sel, orgs) {
    const currentVal = sel.value;
    sel.innerHTML = '';
    const defaultOpt = document.createElement('option');
    defaultOpt.value = '';
    defaultOpt.textContent = '— Не выбрано —';
    sel.appendChild(defaultOpt);
    orgs.forEach(org => {
        const id = org.id || '';
        const name = org.name || id;
        const opt = document.createElement('option');
        opt.value = id;
        opt.setAttribute('data-org-name', name);
        opt.textContent = name + ' (' + id.substring(0, 8) + '...)';
        sel.appendChild(opt);
    });
    // Restore previous selection if it still exists
    if (currentVal) {
        for (let i = 0; i < sel.options.length; i++) {
            if (sel.options[i].value === currentVal) {
                sel.value = currentVal;
                break;
            }
        }
    }
    // Clear manual input when dropdown is populated
    document.getElementById('org-id-input').value = '';
}

function populateSettingSelects() {
    const selects = ['webhook-setting-select', 'data-setting-select'];
    selects.forEach(selId => {
        const sel = document.getElementById(selId);
        if (!sel) return;
        sel.innerHTML = '<option value="">Выберите настройку...</option>';
        settingsList.forEach(s => {
            const label = s.organization_name 
                ? escapeHtml(s.organization_name) + ' (ID: #' + s.id + ')'
                : 'Интеграция #' + s.id + (s.organization_id ? ' (' + escapeHtml(s.organization_id).substring(0,8) + '...)' : '');
            sel.innerHTML += '<option value="' + s.id + '">' + label + '</option>';
        });
    });
}

async function saveSettings() {
    const apiKey = document.getElementById('api-key-input').value.trim();
    const apiUrl = document.getElementById('api-url-input').value.trim();
    const orgIdFromSelect = document.getElementById('org-id-select').value;
    const orgIdFromInput = document.getElementById('org-id-input').value.trim();
    const orgId = orgIdFromSelect || orgIdFromInput;
    const msgEl = document.getElementById('settings-message');

    // When updating existing settings, API key is optional
    // When creating new settings, API key is required
    if (!currentSettingId && !apiKey) {
        msgEl.innerHTML = '<div class="alert alert-warning">⚠️ Введите API ключ для новой интеграции</div>';
        return;
    }

    // Get organization name from the selected option's data attribute
    let orgName = null;
    if (orgIdFromSelect) {
        const sel = document.getElementById('org-id-select');
        if (sel && sel.selectedIndex >= 0) {
            const selectedOption = sel.options[sel.selectedIndex];
            orgName = selectedOption ? selectedOption.getAttribute('data-org-name') : null;
        }
    }

    const body = {
        api_url: apiUrl || 'https://api-ru.iiko.services/api/1',
        organization_id: orgId || null,
        organization_name: orgName || null,
    };

    // Only include api_key if it's provided (non-empty)
    if (apiKey) {
        body.api_key = apiKey;
    }

    try {
        let result;
        if (currentSettingId) {
            result = await apiPut('/admin/api/iiko-settings/' + currentSettingId, body);
        } else {
            result = await apiPost('/admin/api/iiko-settings', body);
        }

        if (result.status >= 400) {
            msgEl.innerHTML = '<div class="alert alert-danger">⚠️ Ошибка: ' + escapeHtml(JSON.stringify(result.data)) + '</div>';
        } else {
            msgEl.innerHTML = '<div class="alert alert-success">✓ Настройки сохранены</div>';
            currentSettingId = result.data.id || currentSettingId;
            // Clear the API key input after successful save for security
            document.getElementById('api-key-input').value = '';
            loadSettings();
        }
    } catch (err) {
        msgEl.innerHTML = '<div class="alert alert-danger">⚠️ ' + escapeHtml(err.message) + '</div>';
    }
}

async function deleteSetting(event, settingId) {
    // Prevent the row click event from firing
    event.stopPropagation();
    
    // Show confirmation dialog
    if (!confirm('Вы уверены, что хотите удалить эту настройку? Это действие нельзя отменить.')) {
        return;
    }
    
    try {
        const result = await apiDelete('/admin/api/iiko-settings/' + settingId);
        
        if (result.status >= 400) {
            alert('⚠️ Ошибка при удалении: ' + (result.data.detail || JSON.stringify(result.data)));
        } else {
            // If the deleted setting was selected, clear the selection
            if (currentSettingId === settingId) {
                currentSettingId = null;
                document.getElementById('api-key-input').value = '';
                document.getElementById('api-url-input').value = 'https://api-ru.iiko.services/api/1';
                document.getElementById('org-id-select').value = '';
                document.getElementById('org-id-input').value = '';
                document.getElementById('settings-message').innerHTML = '';
            }
            // Reload settings list
            loadSettings();
        }
    } catch (err) {
        alert('⚠️ Ошибка при удалении: ' + err.message);
    }
}

async function testConnection() {
    if (!currentSettingId) return;
    const statusEl = document.getElementById('connection-status');
    statusEl.innerHTML = '<div class="loading-overlay"><span class="spinner"></span> Проверка подключения...</div>';

    try {
        const result = await apiPost('/admin/api/iiko-test', { setting_id: currentSettingId });
        if (result.status >= 400) {
            let errorMsg = result.data.detail || JSON.stringify(result.data);
            
            // Add helpful hints based on error type
            if (errorMsg.includes('401') || errorMsg.includes('Неверные') || errorMsg.includes('Invalid')) {
                errorMsg += '<br><br><strong>Решение:</strong><br>' +
                    '1. Проверьте API ключ в личном кабинете iiko Cloud<br>' +
                    '2. Убедитесь что ключ активен и не истёк<br>' +
                    '3. Скопируйте ключ полностью, без пробелов<br>' +
                    '4. Используйте новый API ключ из раздела API в iiko Cloud';
            } else if (errorMsg.includes('timeout') || errorMsg.includes('Тайм-аут')) {
                errorMsg += '<br><br><strong>Решение:</strong> Проверьте доступность сервера iiko и интернет-соединение';
            } else if (errorMsg.includes('DNS') || errorMsg.includes('подключения')) {
                errorMsg += '<br><br><strong>Решение:</strong> Проверьте URL API и сетевые настройки';
            }
            
            statusEl.innerHTML = '<div class="alert alert-danger">❌ Ошибка: ' + errorMsg + '</div>';
        } else {
            statusEl.innerHTML = '<div class="alert alert-success">✓ Подключение к iiko API успешно! Токен получен.</div>';
        }
    } catch (err) {
        statusEl.innerHTML = '<div class="alert alert-danger">❌ Ошибка подключения: ' + escapeHtml(err.message) + '<br><small>Проверьте что Backend API запущен</small></div>';
    }
}

// ─── Webhooks Tab ────────────────────────────────────────
async function registerWebhook() {
    const settingId = document.getElementById('webhook-setting-select').value;
    const domain = document.getElementById('webhook-domain-input').value.trim();
    const errorEl = document.getElementById('webhook-error');
    const resultEl = document.getElementById('webhook-result');

    if (!settingId) {
        errorEl.innerHTML = '<div class="alert alert-warning">⚠️ Выберите настройку iiko</div>';
        return;
    }
    if (!domain) {
        errorEl.innerHTML = '<div class="alert alert-warning">⚠️ Введите домен вашего сервера (например: vezuroll.ru)</div>';
        return;
    }

    errorEl.innerHTML = '<div class="loading-overlay"><span class="spinner"></span> Регистрация вебхука в iiko Cloud...</div>';
    resultEl.style.display = 'none';

    try {
        const result = await apiPost('/admin/api/iiko-register-webhook', {
            setting_id: settingId,
            domain: domain,
        });

        if (result.status >= 400) {
            errorEl.innerHTML = '<div class="alert alert-danger">❌ ' + escapeHtml(result.data.detail || JSON.stringify(result.data)) + '</div>';
        } else {
            errorEl.innerHTML = '';
            resultEl.style.display = 'block';
            document.getElementById('webhook-generated-url').textContent = result.data.webhook_url || '—';
            document.getElementById('webhook-auth-token').textContent = result.data.auth_token || '—';
        }
    } catch (err) {
        errorEl.innerHTML = '<div class="alert alert-danger">❌ ' + escapeHtml(err.message) + '</div>';
    }
}

function onWebhookSettingChange() {
    const settingId = document.getElementById('webhook-setting-select').value;
    const container = document.getElementById('current-webhook-info');
    if (!settingId) {
        container.innerHTML = '<span class="badge badge-muted">Выберите настройку iiko для просмотра</span>';
        return;
    }
    const setting = settingsList.find(s => s.id == settingId);
    if (setting) {
        let html = '';
        if (setting.webhook_url) {
            html += '<div class="component-row"><div class="component-name" style="flex-direction:column;align-items:flex-start;">' +
                '<span class="form-label" style="margin-bottom:2px;">URL вебхука:</span>' +
                '<span class="mono" style="color:var(--accent);word-break:break-all;">' + escapeHtml(setting.webhook_url) + '</span>' +
            '</div></div>';
            html += '<div class="component-row"><div class="component-name">' +
                '<span class="badge badge-success">✓ Вебхук настроен</span>' +
            '</div></div>';
        } else {
            html += '<span class="badge badge-warning">⚠️ Вебхук не настроен для этой интеграции</span>';
        }
        container.innerHTML = html;
    }
}

async function loadWebhookEvents() {
    const container = document.getElementById('webhook-events-list');
    container.innerHTML = '<div class="loading-overlay"><span class="spinner"></span> Загрузка...</div>';

    try {
        const data = await apiGet('/admin/api/webhook-events');
        const events = Array.isArray(data) ? data : [];
        if (events.length === 0) {
            container.innerHTML = '<span class="badge badge-muted">Нет входящих событий</span>';
            return;
        }
        let html = '<div class="table-wrap"><table><thead><tr><th>ID</th><th>Тип</th><th>Обработан</th><th>Дата</th></tr></thead><tbody>';
        events.forEach(e => {
            html += '<tr>' +
                '<td>' + e.id + '</td>' +
                '<td><span class="badge badge-muted">' + escapeHtml(e.event_type) + '</span></td>' +
                '<td><span class="badge ' + (e.processed ? 'badge-success' : 'badge-warning') + '">' + (e.processed ? '✓' : '⏳') + '</span></td>' +
                '<td style="font-size:12px;color:var(--muted);">' + (e.created_at || '—') + '</td>' +
                '</tr>';
        });
        html += '</tbody></table></div>';
        container.innerHTML = html;
    } catch (err) {
        container.innerHTML = '<div class="alert alert-danger">⚠️ ' + escapeHtml(err.message) + '</div>';
    }
}

// ─── Data Tab ────────────────────────────────────────────
async function loadDataOrganizations() {
    const settingId = document.getElementById('data-setting-select').value;
    if (!settingId) {
        alert('Выберите настройку iiko');
        return;
    }
    const orgSelect = document.getElementById('data-org-select');
    orgSelect.innerHTML = '<option value="">Загрузка...</option>';
    orgSelect.disabled = true;

    try {
        const result = await apiPost('/admin/api/iiko-organizations', { setting_id: settingId });
        const orgs = result.data?.organizations || [];
        orgSelect.innerHTML = '<option value="">Выберите организацию...</option>';
        orgs.forEach(org => {
            orgSelect.innerHTML += '<option value="' + escapeHtml(org.id) + '">' + escapeHtml(org.name || org.id) + '</option>';
        });
        orgSelect.disabled = false;
    } catch (err) {
        orgSelect.innerHTML = '<option value="">Ошибка загрузки</option>';
    }
}

async function loadDataSection(type) {
    const settingId = document.getElementById('data-setting-select').value;
    const orgId = document.getElementById('data-org-select').value;
    const container = document.getElementById('data-' + type);

    if (!settingId || !orgId) {
        container.innerHTML = '<div class="alert alert-warning">⚠️ Выберите настройку и организацию</div>';
        return;
    }

    container.innerHTML = '<div class="loading-overlay"><span class="spinner"></span> Загрузка...</div>';

    const endpoints = {
        'terminal-groups': '/admin/api/iiko-terminal-groups',
        'payment-types': '/admin/api/iiko-payment-types',
        'couriers': '/admin/api/iiko-couriers',
        'order-types': '/admin/api/iiko-order-types',
        'discount-types': '/admin/api/iiko-discount-types',
        'stop-lists': '/admin/api/iiko-stop-lists',
    };

    try {
        const result = await apiPost(endpoints[type], {
            setting_id: settingId,
            organization_id: orgId,
        });

        if (result.status >= 400) {
            container.innerHTML = '<div class="alert alert-danger">❌ ' + escapeHtml(result.data.detail || JSON.stringify(result.data)) + '</div>';
            return;
        }

        const data = result.data;
        let html = '<div class="data-section">';

        if (type === 'terminal-groups') {
            const groups = data.terminalGroups || [];
            if (groups.length === 0) {
                html += '<span class="badge badge-muted">Нет данных</span>';
            } else {
                groups.forEach(g => {
                    const items = g.items || [];
                    html += '<div style="margin-bottom:8px;font-weight:600;">Организация: ' + escapeHtml(g.organizationId || '').substring(0,8) + '...</div>';
                    items.forEach(item => {
                        html += '<div class="component-row"><div class="component-name"><span class="status-dot online"></span>' + escapeHtml(item.name || item.id) + '</div><span class="mono" style="color:var(--muted);font-size:11px;">' + escapeHtml(item.id || '') + '</span></div>';
                    });
                });
            }
        } else if (type === 'payment-types') {
            const ptGroups = data.paymentTypes || [];
            if (ptGroups.length === 0) {
                html += '<span class="badge badge-muted">Нет данных</span>';
            } else {
                ptGroups.forEach(pt => {
                    const items = pt.items || pt.paymentTypes || [];
                    if (items.length > 0) {
                        html += '<div class="table-wrap" style="margin-bottom:8px;"><table><thead><tr><th>Название</th><th>Тип</th><th>Код</th><th>ID</th></tr></thead><tbody>';
                        items.forEach(item => {
                            html += '<tr>' +
                                '<td><strong>💳 ' + escapeHtml(item.name || '—') + '</strong></td>' +
                                '<td><span class="badge badge-muted">' + escapeHtml(item.paymentTypeKind || item.code || '') + '</span></td>' +
                                '<td class="mono" style="font-size:11px;">' + escapeHtml(item.code || '') + '</td>' +
                                '<td class="mono" style="font-size:11px;color:var(--muted);">' + escapeHtml((item.id || '').substring(0,8)) + '...</td>' +
                            '</tr>';
                        });
                        html += '</tbody></table></div>';
                    }
                });
            }
        } else if (type === 'couriers') {
            const couriers = data.employees || [];
            if (couriers.length === 0) {
                html += '<span class="badge badge-muted">Нет курьеров</span>';
            } else {
                html += '<div class="table-wrap"><table><thead><tr><th>Имя</th><th>Телефон</th><th>ID</th></tr></thead><tbody>';
                couriers.forEach(c => {
                    html += '<tr>' +
                        '<td><strong>🚴 ' + escapeHtml(c.displayName || c.name || c.firstName || '—') + '</strong></td>' +
                        '<td>' + escapeHtml(c.phone || '—') + '</td>' +
                        '<td class="mono" style="font-size:11px;color:var(--muted);">' + escapeHtml((c.id || '').substring(0,8)) + '...</td>' +
                    '</tr>';
                });
                html += '</tbody></table></div>';
            }
        } else if (type === 'order-types') {
            const otGroups = data.orderTypes || [];
            if (otGroups.length === 0) {
                html += '<span class="badge badge-muted">Нет типов заказов</span>';
            } else {
                otGroups.forEach(og => {
                    const items = og.items || og.orderTypes || [];
                    if (items.length > 0) {
                        html += '<div class="table-wrap" style="margin-bottom:8px;"><table><thead><tr><th>Название</th><th>Тип</th><th>Внешнее</th><th>ID</th></tr></thead><tbody>';
                        items.forEach(item => {
                            html += '<tr>' +
                                '<td><strong>📦 ' + escapeHtml(item.name || '—') + '</strong></td>' +
                                '<td><span class="badge badge-muted">' + escapeHtml(item.orderServiceType || '') + '</span></td>' +
                                '<td>' + escapeHtml(item.externalRevision ? 'Да' : 'Нет') + '</td>' +
                                '<td class="mono" style="font-size:11px;color:var(--muted);">' + escapeHtml((item.id || '').substring(0,8)) + '...</td>' +
                            '</tr>';
                        });
                        html += '</tbody></table></div>';
                    }
                });
            }
        } else if (type === 'discount-types') {
            const discounts = data.discounts || data.discountTypes || [];
            if (discounts.length === 0 && !data.discounts) {
                // Try alternate format
                const dgGroups = Object.values(data).flat();
                if (dgGroups.length === 0) {
                    html += '<span class="badge badge-muted">Нет скидок/акций</span>';
                } else {
                    html += '<div class="json-view">' + escapeHtml(JSON.stringify(data, null, 2)) + '</div>';
                }
            } else {
                const items = Array.isArray(discounts) ? discounts : [];
                if (items.length === 0) {
                    html += '<span class="badge badge-muted">Нет скидок/акций</span>';
                } else {
                    html += '<div class="table-wrap"><table><thead><tr><th>Название</th><th>Тип</th><th>Процент / Сумма</th><th>ID</th></tr></thead><tbody>';
                    items.forEach(item => {
                        html += '<tr>' +
                            '<td><strong>🏷️ ' + escapeHtml(item.name || '—') + '</strong></td>' +
                            '<td><span class="badge badge-muted">' + escapeHtml(item.type || item.discountType || '') + '</span></td>' +
                            '<td>' + escapeHtml(item.percent ? item.percent + '%' : (item.sum || '—')) + '</td>' +
                            '<td class="mono" style="font-size:11px;color:var(--muted);">' + escapeHtml((item.id || '').substring(0,8)) + '...</td>' +
                        '</tr>';
                    });
                    html += '</tbody></table></div>';
                }
            }
        } else if (type === 'stop-lists') {
            const stopLists = data.terminalGroupStopLists || [];
            if (stopLists.length === 0) {
                html += '<span class="badge badge-muted">Нет данных стоп-листов</span>';
            } else {
                stopLists.forEach(tg => {
                    const tgItems = tg.items || [];
                    tgItems.forEach(terminal => {
                        html += '<div style="margin-bottom:8px;font-weight:600;">Терминал: ' + escapeHtml(terminal.terminalGroupId || '').substring(0,8) + '...</div>';
                        const stopItems = terminal.items || [];
                        if (stopItems.length === 0) {
                            html += '<span class="badge badge-success" style="margin-bottom:8px;">✓ Стоп-лист пуст</span>';
                        } else {
                            html += '<div class="table-wrap" style="margin-bottom:8px;"><table><thead><tr><th>Позиция</th><th>Баланс</th></tr></thead><tbody>';
                            stopItems.forEach(si => {
                                html += '<tr><td>🚫 ' + escapeHtml(si.productId || si.name || '—') + '</td><td>' + (si.balance || 0) + '</td></tr>';
                            });
                            html += '</tbody></table></div>';
                        }
                    });
                });
            }
        } else {
            // Generic JSON display for other types
            html += '<div class="json-view">' + escapeHtml(JSON.stringify(data, null, 2)) + '</div>';
        }

        html += '</div>';
        container.innerHTML = html;
    } catch (err) {
        container.innerHTML = '<div class="alert alert-danger">❌ ' + escapeHtml(err.message) + '</div>';
    }
}

// ─── Logs Tab ────────────────────────────────────────────
async function loadLogs() {
    const container = document.getElementById('logs-list');
    container.innerHTML = '<div class="loading-overlay"><span class="spinner"></span> Загрузка...</div>';

    try {
        const data = await apiGet('/admin/api/logs');
        const logs = Array.isArray(data) ? data : [];
        if (logs.length === 0) {
            container.innerHTML = '<span class="badge badge-muted">Нет записей</span>';
            return;
        }
        let html = '<div class="table-wrap"><table><thead><tr><th>ID</th><th>Метод</th><th>URL</th><th>Статус</th><th>Время</th><th>Дата</th></tr></thead><tbody>';
        logs.forEach(l => {
            const isError = l.response_status >= 400;
            html += '<tr>' +
                '<td>' + l.id + '</td>' +
                '<td><span class="badge badge-muted">' + escapeHtml(l.method) + '</span></td>' +
                '<td class="mono" style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + escapeHtml(l.url) + '</td>' +
                '<td><span class="badge ' + (isError ? 'badge-danger' : 'badge-success') + '">' + (l.response_status || '—') + '</span></td>' +
                '<td>' + (l.duration_ms || '—') + ' мс</td>' +
                '<td style="font-size:12px;color:var(--muted);">' + (l.created_at || '—') + '</td>' +
                '</tr>';
        });
        html += '</tbody></table></div>';
        container.innerHTML = html;
    } catch (err) {
        container.innerHTML = '<div class="alert alert-danger">⚠️ ' + escapeHtml(err.message) + '</div>';
    }
}

// ─── Loyalty Tab ─────────────────────────────────────────
let currentCustomerId = null;
let currentCustomerName = null;
let currentCustomerPhone = null;
let loyaltyProgramsList = [];
let autoRefreshInterval = null;

async function loadLoyaltyPrograms() {
    if (!currentSettingId) { alert('Сначала создайте или выберите настройку API'); return; }
    const setting = settingsList.find(s => s.id === currentSettingId);
    if (!setting || !setting.organization_id) { alert('Укажите organization_id в настройках API'); return; }
    const container = document.getElementById('loyalty-programs-list');
    container.innerHTML = '<div class="loading-overlay"><span class="spinner"></span> Загрузка...</div>';
    try {
        const result = await apiPost('/admin/api/iiko-loyalty-programs', { setting_id: currentSettingId, organization_id: setting.organization_id });
        if (result.status >= 400) { container.innerHTML = '<div class="alert alert-danger">⚠️ ' + escapeHtml(result.data.detail || JSON.stringify(result.data)) + '</div>'; return; }
        const programs = result.data.programs || result.data || [];
        loyaltyProgramsList = Array.isArray(programs) ? programs : [];
        if (loyaltyProgramsList.length === 0) {
            container.innerHTML = '<span class="badge badge-muted">Программы лояльности не найдены</span>';
            document.getElementById('loyalty-wallet-select-section').style.display = 'none';
            return;
        }
        let html = '';
        const programSelect = document.getElementById('loyalty-active-program');
        programSelect.innerHTML = '<option value="">— Выберите программу —</option>';
        loyaltyProgramsList.forEach((p, idx) => {
            html += '<div style="padding:10px;border-bottom:1px solid var(--border);">' +
                '<div style="font-weight:600;color:var(--text-bright);">' + escapeHtml(p.name || p.id || '—') + '</div>' +
                '<div style="font-size:12px;color:var(--muted);">ID: ' + escapeHtml(p.id || '—') + '</div>' +
                (p.description ? '<div style="font-size:12px;color:var(--text);margin-top:4px;">' + escapeHtml(p.description) + '</div>' : '') +
                '</div>';
            programSelect.innerHTML += '<option value="' + idx + '" data-program-id="' + escapeHtml(p.id || '') + '">' + escapeHtml(p.name || p.id || 'Программа ' + (idx + 1)) + '</option>';
        });
        container.innerHTML = html;
        document.getElementById('loyalty-wallet-select-section').style.display = 'block';
        loadTransactionHistory();
    } catch (err) { container.innerHTML = '<div class="alert alert-danger">❌ ' + escapeHtml(err.message) + '</div>'; }
}

function onProgramSelected() {
    const select = document.getElementById('loyalty-active-program');
    const idx = select.value;
    const detail = document.getElementById('loyalty-program-detail');
    if (idx === '' || !loyaltyProgramsList[idx]) {
        detail.innerHTML = '';
        return;
    }
    const p = loyaltyProgramsList[idx];
    let html = '<div class="data-section" style="padding:8px;">' +
        '<div style="font-weight:600;color:var(--accent);">✅ ' + escapeHtml(p.name || '—') + '</div>' +
        '<div style="font-size:12px;color:var(--muted);margin-top:4px;">ID: <span class="mono">' + escapeHtml(p.id || '—') + '</span></div>';
    if (p.wallets && Array.isArray(p.wallets) && p.wallets.length > 0) {
        html += '<div style="margin-top:8px;font-size:13px;color:var(--text);">Кошельки программы:</div>';
        p.wallets.forEach(w => {
            html += '<div style="font-size:12px;color:var(--muted);padding:2px 0;">• ' + escapeHtml(w.name || w.id || '—') + ' (ID: ' + escapeHtml(w.id || '—') + ')</div>';
        });
    }
    if (p.marketingCampaigns && Array.isArray(p.marketingCampaigns) && p.marketingCampaigns.length > 0) {
        html += '<div style="margin-top:8px;font-size:13px;color:var(--text);">Маркетинговые кампании: ' + p.marketingCampaigns.length + '</div>';
    }
    html += '</div>';
    detail.innerHTML = html;
}

async function searchLoyaltyCustomer() {
    if (!currentSettingId) { alert('Сначала создайте или выберите настройку API'); return; }
    const setting = settingsList.find(s => s.id === currentSettingId);
    if (!setting || !setting.organization_id) { alert('Укажите organization_id в настройках API'); return; }
    const query = document.getElementById('loyalty-search-query').value.trim();
    const searchType = document.getElementById('loyalty-search-type').value;
    if (!query) { alert('Введите значение для поиска'); return; }
    const container = document.getElementById('loyalty-customer-info');
    container.innerHTML = '<div class="loading-overlay"><span class="spinner"></span> Поиск...</div>';
    const body = { setting_id: currentSettingId, organization_id: setting.organization_id };
    body[searchType] = query;
    try {
        const result = await apiPost('/admin/api/iiko-loyalty-customer-info', body);
        if (result.status >= 400) { container.innerHTML = '<div class="alert alert-danger">⚠️ ' + escapeHtml(result.data.detail || JSON.stringify(result.data)) + '</div>'; return; }
        const customer = result.data;
        currentCustomerId = customer.id || null;
        currentCustomerName = customer.name || null;
        currentCustomerPhone = customer.phone || null;
        let html = '<div class="data-section">' +
            '<div style="font-weight:600;color:var(--text-bright);margin-bottom:8px;">👤 ' + escapeHtml(customer.name || '—') + '</div>' +
            '<div style="font-size:13px;color:var(--text);">ID: <span class="mono">' + escapeHtml(customer.id || '—') + '</span></div>' +
            '<div style="font-size:13px;color:var(--text);">Телефон: ' + escapeHtml(customer.phone || '—') + '</div>' +
            '<div style="font-size:13px;color:var(--text);">Email: ' + escapeHtml(customer.email || '—') + '</div>' +
            '</div>';
        container.innerHTML = html;
        if (currentCustomerId) {
            loadCustomerBalance();
            loadTransactionHistory();
        }
    } catch (err) { container.innerHTML = '<div class="alert alert-danger">❌ ' + escapeHtml(err.message) + '</div>'; }
}

async function loadCustomerBalance() {
    if (!currentSettingId || !currentCustomerId) return;
    const setting = settingsList.find(s => s.id === currentSettingId);
    if (!setting || !setting.organization_id) return;
    const container = document.getElementById('loyalty-balance-section');
    container.innerHTML = '<div class="loading-overlay"><span class="spinner"></span> Загрузка баланса...</div>';
    try {
        const result = await apiPost('/admin/api/iiko-loyalty-balance', { setting_id: currentSettingId, organization_id: setting.organization_id, customer_id: currentCustomerId });
        if (result.status >= 400) { container.innerHTML = '<div class="alert alert-danger">⚠️ ' + escapeHtml(result.data.detail || JSON.stringify(result.data)) + '</div>'; return; }
        const wallets = result.data.wallets || result.data || [];
        let html = '<div class="grid-3">';
        const walletSelect = document.getElementById('loyalty-wallet-id');
        walletSelect.innerHTML = '';
        if (Array.isArray(wallets) && wallets.length > 0) {
            wallets.forEach(w => {
                html += '<div class="card stat-card">' +
                    '<span class="stat-label">' + escapeHtml(w.name || w.walletId || 'Кошелек') + '</span>' +
                    '<span class="stat-value" style="font-size:24px;">' + (w.balance != null ? w.balance : '—') + '</span>' +
                    '</div>';
                walletSelect.innerHTML += '<option value="' + escapeHtml(w.walletId || w.id || '') + '">' + escapeHtml(w.name || w.walletId || 'Кошелек') + ' (баланс: ' + (w.balance || 0) + ')</option>';
            });
        } else {
            html += '<span class="badge badge-muted">Кошельки не найдены</span>';
        }
        html += '</div>';
        container.innerHTML = html;
        document.getElementById('loyalty-operations').style.display = (Array.isArray(wallets) && wallets.length > 0) ? 'block' : 'none';
    } catch (err) { container.innerHTML = '<div class="alert alert-danger">❌ ' + escapeHtml(err.message) + '</div>'; }
}

async function loyaltyTopup() { await loyaltyOperation('topup', '➕ Пополнение'); }
async function loyaltyWithdraw() { await loyaltyOperation('withdraw', '➖ Списание'); }
async function loyaltyHold() { await loyaltyOperation('hold', '🔒 Холдирование'); }

async function loyaltyOperation(type, label) {
    if (!currentSettingId || !currentCustomerId) { alert('Сначала найдите гостя'); return; }
    const setting = settingsList.find(s => s.id === currentSettingId);
    if (!setting || !setting.organization_id) return;
    const walletId = document.getElementById('loyalty-wallet-id').value;
    const amount = parseFloat(document.getElementById('loyalty-amount').value);
    const comment = document.getElementById('loyalty-comment').value;
    if (!walletId || !amount || amount <= 0) { alert('Укажите кошелек и сумму'); return; }
    const container = document.getElementById('loyalty-operation-result');
    container.innerHTML = '<div class="loading-overlay"><span class="spinner"></span> Выполнение...</div>';
    try {
        const result = await apiPost('/admin/api/iiko-loyalty-' + type, {
            setting_id: currentSettingId, organization_id: setting.organization_id,
            customer_id: currentCustomerId, wallet_id: walletId, amount: amount, comment: comment,
        });
        if (result.status >= 400) { container.innerHTML = '<div class="alert alert-danger">⚠️ ' + escapeHtml(result.data.detail || JSON.stringify(result.data)) + '</div>'; return; }
        container.innerHTML = '<div class="alert alert-success">✅ ' + label + ' выполнено успешно</div>';
        loadCustomerBalance();
        loadTransactionHistory();
    } catch (err) { container.innerHTML = '<div class="alert alert-danger">❌ ' + escapeHtml(err.message) + '</div>'; }
}

async function loadTransactionHistory() {
    if (!currentSettingId) return;
    const setting = settingsList.find(s => s.id === currentSettingId);
    if (!setting || !setting.organization_id) return;
    const container = document.getElementById('loyalty-transactions-list');
    const params = new URLSearchParams({
        setting_id: currentSettingId,
        organization_id: setting.organization_id,
        limit: 50,
    });
    if (currentCustomerId) params.append('customer_id', currentCustomerId);
    try {
        const result = await apiGet('/admin/api/iiko-loyalty-transactions?' + params.toString());
        const transactions = Array.isArray(result) ? result : [];
        if (transactions.length === 0) {
            container.innerHTML = '<span class="badge badge-muted">Нет операций' + (currentCustomerId ? ' для данного гостя' : '') + '</span>';
            return;
        }
        const opLabels = { topup: '➕ Пополнение', withdraw: '➖ Списание', hold: '🔒 Холд' };
        const opBadge = { topup: 'badge-success', withdraw: 'badge-danger', hold: 'badge-muted' };
        let html = '<div class="table-wrap"><table><thead><tr>' +
            '<th>Дата</th><th>Тип</th><th>Сумма</th><th>Гость</th><th>Кошелек</th><th>Комментарий</th><th>Оператор</th>' +
            '</tr></thead><tbody>';
        transactions.forEach(t => {
            const dt = t.created_at ? new Date(t.created_at).toLocaleString('ru-RU') : '—';
            const custInfo = (t.customer_name || t.customer_phone) ?
                escapeHtml(t.customer_name || '') + (t.customer_phone ? ' (' + escapeHtml(t.customer_phone) + ')' : '') :
                '<span class="mono" style="font-size:11px;">' + escapeHtml(t.customer_id || '—') + '</span>';
            html += '<tr>' +
                '<td style="font-size:12px;white-space:nowrap;">' + dt + '</td>' +
                '<td><span class="badge ' + (opBadge[t.operation_type] || 'badge-muted') + '">' + (opLabels[t.operation_type] || t.operation_type) + '</span></td>' +
                '<td style="font-weight:600;">' + (t.amount != null ? t.amount.toFixed(2) : '—') + '</td>' +
                '<td>' + custInfo + '</td>' +
                '<td style="font-size:12px;">' + escapeHtml(t.wallet_name || t.wallet_id || '—') + '</td>' +
                '<td style="font-size:12px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + escapeHtml(t.comment || '—') + '</td>' +
                '<td style="font-size:12px;">' + escapeHtml(t.performed_by || '—') + '</td>' +
                '</tr>';
        });
        html += '</tbody></table></div>';
        container.innerHTML = html;
    } catch (err) { container.innerHTML = '<div class="alert alert-danger">❌ ' + escapeHtml(err.message) + '</div>'; }
}

function toggleAutoRefresh() {
    const checked = document.getElementById('loyalty-auto-refresh').checked;
    if (checked) {
        autoRefreshInterval = setInterval(() => {
            loadTransactionHistory();
            if (currentCustomerId) loadCustomerBalance();
        }, 10000);
    } else {
        if (autoRefreshInterval) { clearInterval(autoRefreshInterval); autoRefreshInterval = null; }
    }
}

async function createOrUpdateCustomer() {
    if (!currentSettingId) { alert('Сначала создайте или выберите настройку API'); return; }
    const setting = settingsList.find(s => s.id === currentSettingId);
    if (!setting || !setting.organization_id) { alert('Укажите organization_id в настройках API'); return; }
    const container = document.getElementById('new-customer-result');
    container.innerHTML = '<div class="loading-overlay"><span class="spinner"></span> Сохранение...</div>';
    const body = {
        setting_id: currentSettingId,
        organization_id: setting.organization_id,
        name: document.getElementById('new-customer-name').value.trim(),
        phone: document.getElementById('new-customer-phone').value.trim(),
        email: document.getElementById('new-customer-email').value.trim(),
        birthday: document.getElementById('new-customer-birthday').value || null,
    };
    if (!body.name && !body.phone) { container.innerHTML = '<div class="alert alert-danger">Укажите имя или телефон</div>'; return; }
    try {
        const result = await apiPost('/admin/api/iiko-loyalty-customer', body);
        if (result.status >= 400) { container.innerHTML = '<div class="alert alert-danger">⚠️ ' + escapeHtml(result.data.detail || JSON.stringify(result.data)) + '</div>'; return; }
        container.innerHTML = '<div class="alert alert-success">✅ Гость сохранен. ID: ' + escapeHtml(result.data.id || JSON.stringify(result.data)) + '</div>';
    } catch (err) { container.innerHTML = '<div class="alert alert-danger">❌ ' + escapeHtml(err.message) + '</div>'; }
}

function toggleApiKeyVisibility() {
    const input = document.getElementById('api-key-input');
    const icon = document.getElementById('api-key-toggle-icon');
    const button = document.getElementById('api-key-toggle-btn');
    if (input.type === 'password') {
        input.type = 'text';
        icon.textContent = '🙈';
        button.setAttribute('aria-label', 'Скрыть API ключ');
    } else {
        input.type = 'password';
        icon.textContent = '👁';
        button.setAttribute('aria-label', 'Показать API ключ');
    }
}

// ─── Init ────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    loadStatus();
});
</script>
@endsection

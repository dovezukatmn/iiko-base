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
                    <input type="text" class="form-input" id="api-key-input" placeholder="Введите ваш iiko API логин">
                </div>
                <div class="form-group">
                    <label class="form-label">API URL</label>
                    <input type="text" class="form-input" id="api-url-input" value="https://api-ru.iiko.services/api/1" placeholder="https://api-ru.iiko.services/api/1">
                </div>
                <div class="form-group">
                    <label class="form-label">Organization ID (необязательно)</label>
                    <input type="text" class="form-input" id="org-id-input" placeholder="UUID организации">
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
                <button class="btn btn-primary" onclick="loadOrganizations()">📡 Загрузить организации</button>
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
        html += '<div class="component-row" style="cursor:pointer;' + (isSelected ? 'background:rgba(99,102,241,0.08);border-radius:8px;padding:10px;' : '') + '" onclick="selectSetting(' + s.id + ')">' +
            '<div class="component-name">' +
                '<span class="status-dot ' + (s.is_active ? 'online' : 'offline') + '"></span>' +
                '<div>' +
                    '<div style="font-weight:600;">Интеграция #' + s.id + '</div>' +
                    '<div style="font-size:11px;color:var(--muted);">' + escapeHtml(s.api_url) + '</div>' +
                    (s.organization_id ? '<div style="font-size:11px;color:var(--accent-2);">Org: ' + escapeHtml(s.organization_id) + '</div>' : '') +
                    (s.webhook_url ? '<div style="font-size:11px;color:var(--success);">Webhook: ✓</div>' : '') +
                '</div>' +
            '</div>' +
            '<span class="badge ' + (isSelected ? 'badge-success' : 'badge-muted') + '">' + (isSelected ? '✓ Выбрано' : 'Выбрать') + '</span>' +
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
        document.getElementById('org-id-input').value = setting.organization_id || '';
    }
}

function populateSettingSelects() {
    const selects = ['webhook-setting-select', 'data-setting-select'];
    selects.forEach(selId => {
        const sel = document.getElementById(selId);
        if (!sel) return;
        sel.innerHTML = '<option value="">Выберите настройку...</option>';
        settingsList.forEach(s => {
            sel.innerHTML += '<option value="' + s.id + '">Интеграция #' + s.id + (s.organization_id ? ' (' + escapeHtml(s.organization_id).substring(0,8) + '...)' : '') + '</option>';
        });
    });
}

async function saveSettings() {
    const apiKey = document.getElementById('api-key-input').value.trim();
    const apiUrl = document.getElementById('api-url-input').value.trim();
    const orgId = document.getElementById('org-id-input').value.trim();
    const msgEl = document.getElementById('settings-message');

    if (!apiKey) {
        msgEl.innerHTML = '<div class="alert alert-warning">⚠️ Введите API ключ</div>';
        return;
    }

    const body = {
        api_key: apiKey,
        api_url: apiUrl || 'https://api-ru.iiko.services/api/1',
        organization_id: orgId || null,
    };

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
            loadSettings();
        }
    } catch (err) {
        msgEl.innerHTML = '<div class="alert alert-danger">⚠️ ' + escapeHtml(err.message) + '</div>';
    }
}

async function testConnection() {
    if (!currentSettingId) return;
    const statusEl = document.getElementById('connection-status');
    statusEl.innerHTML = '<div class="loading-overlay"><span class="spinner"></span> Проверка подключения...</div>';

    try {
        const result = await apiPost('/admin/api/iiko-test', { setting_id: currentSettingId });
        if (result.status >= 400) {
            statusEl.innerHTML = '<div class="alert alert-danger">❌ Ошибка подключения: ' + escapeHtml(result.data.detail || JSON.stringify(result.data)) + '</div>';
        } else {
            statusEl.innerHTML = '<div class="alert alert-success">✓ Подключение к iiko API успешно! Токен получен.</div>';
        }
    } catch (err) {
        statusEl.innerHTML = '<div class="alert alert-danger">❌ ' + escapeHtml(err.message) + '</div>';
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
async function loadOrganizations() {
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

// ─── Init ────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    loadStatus();
});
</script>
@endsection

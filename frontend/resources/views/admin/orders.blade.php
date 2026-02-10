@extends('layouts.admin')

@section('title', 'Заказы')
@section('page-title', 'Заказы')

@section('styles')
<style>
    .section-gap { margin-bottom: 20px; }
    .order-card {
        padding: 16px;
        border-radius: 12px;
        border: 1px solid var(--border);
        background: rgba(255,255,255,0.03);
        margin-bottom: 10px;
        transition: background .15s;
    }
    .order-card:hover { background: rgba(255,255,255,0.06); }
    .order-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
    .order-id { font-weight: 700; font-size: 15px; color: var(--text-bright); }
    .order-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 8px; font-size: 13px; }
    .order-detail-label { color: var(--muted); font-size: 11px; text-transform: uppercase; letter-spacing: 0.03em; }
    .order-detail-value { color: var(--text); font-weight: 500; }
    .status-pill { padding: 4px 10px; border-radius: 8px; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; }
    .status-new { background: rgba(99,102,241,0.15); color: var(--accent-light); }
    .status-confirmed { background: rgba(34,197,94,0.15); color: var(--success); }
    .status-cooking { background: rgba(245,158,11,0.15); color: var(--warning); }
    .status-onway { background: rgba(34,211,238,0.15); color: var(--accent-2); }
    .status-delivered { background: rgba(34,197,94,0.15); color: var(--success); }
    .status-closed { background: rgba(148,163,184,0.12); color: var(--muted); }
    .status-cancelled { background: rgba(239,68,68,0.15); color: var(--danger); }
    .status-default { background: rgba(148,163,184,0.12); color: var(--muted); }
    .filter-bar { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; align-items: center; }
    .iiko-order-card {
        padding: 14px;
        border-radius: 12px;
        border: 1px solid var(--border);
        background: rgba(255,255,255,0.03);
        margin-bottom: 8px;
    }
    .iiko-order-items { margin-top: 8px; padding: 8px 12px; border-radius: 8px; background: rgba(0,0,0,0.15); font-size: 12px; }
</style>
@endsection

@section('content')
{{-- Tab Bar --}}
<div class="tab-bar">
    <button class="tab-btn active" onclick="switchOrderTab('local', event)">📦 Локальные заказы</button>
    <button class="tab-btn" onclick="switchOrderTab('iiko', event)">☁️ Заказы из iiko</button>
</div>

{{-- ═══ TAB: Local Orders ═══ --}}
<div class="tab-content active" id="tab-local">
    <div class="card section-gap">
        <div class="card-header">
            <div>
                <div class="card-title">Заказы в системе</div>
                <div class="card-subtitle">Заказы, сохраненные в локальной базе данных</div>
            </div>
            <button class="btn btn-sm" onclick="loadLocalOrders()">🔄 Обновить</button>
        </div>
        <div class="filter-bar">
            <select class="form-input" id="order-status-filter" style="max-width:200px;" onchange="loadLocalOrders()">
                <option value="">Все статусы</option>
                <option value="new">Новый</option>
                <option value="confirmed">Подтвержден</option>
                <option value="cooking">Готовится</option>
                <option value="onway">В пути</option>
                <option value="delivered">Доставлен</option>
                <option value="closed">Закрыт</option>
                <option value="cancelled">Отменен</option>
            </select>
        </div>
        <div id="local-orders-list">
            <div class="loading-overlay"><span class="spinner"></span> Загрузка...</div>
        </div>
    </div>
</div>

{{-- ═══ TAB: iiko Orders ═══ --}}
<div class="tab-content" id="tab-iiko">
    <div class="card section-gap">
        <div class="card-header">
            <div>
                <div class="card-title">Заказы доставки из iiko Cloud</div>
                <div class="card-subtitle">Заказы доставки в реальном времени</div>
            </div>
        </div>
        <div class="grid-3" style="margin-bottom:16px;">
            <div class="form-group">
                <label class="form-label">Настройка iiko</label>
                <select class="form-input" id="orders-setting-select">
                    <option value="">Загрузка...</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Организация</label>
                <select class="form-input" id="orders-org-select" disabled>
                    <option value="">Сначала загрузите организации</option>
                </select>
            </div>
            <div class="form-group" style="display:flex;align-items:flex-end;gap:8px;">
                <button class="btn btn-sm" onclick="loadOrderOrganizations()">📡 Организации</button>
                <button class="btn btn-primary btn-sm" onclick="loadIikoOrders()">📦 Загрузить заказы</button>
            </div>
        </div>
        <div class="filter-bar">
            <label class="form-label" style="margin-bottom:0;">Период:</label>
            <select class="form-input" id="orders-days-select" style="max-width:150px;">
                <option value="1" selected>1 день</option>
                <option value="2">2 дня</option>
                <option value="3">3 дня</option>
                <option value="7">7 дней</option>
            </select>
            <label class="form-label" style="margin-bottom:0;margin-left:16px;">Статусы:</label>
            <label style="font-size:12px;display:flex;align-items:center;gap:4px;"><input type="checkbox" class="iiko-status-cb" value="Unconfirmed" checked> Не подтвержден</label>
            <label style="font-size:12px;display:flex;align-items:center;gap:4px;"><input type="checkbox" class="iiko-status-cb" value="WaitCooking" checked> Ожидает</label>
            <label style="font-size:12px;display:flex;align-items:center;gap:4px;"><input type="checkbox" class="iiko-status-cb" value="CookingStarted" checked> Готовится</label>
            <label style="font-size:12px;display:flex;align-items:center;gap:4px;"><input type="checkbox" class="iiko-status-cb" value="OnWay" checked> В пути</label>
            <label style="font-size:12px;display:flex;align-items:center;gap:4px;"><input type="checkbox" class="iiko-status-cb" value="Delivered" checked> Доставлен</label>
            <label style="font-size:12px;display:flex;align-items:center;gap:4px;"><input type="checkbox" class="iiko-status-cb" value="Closed"> Закрыт</label>
            <label style="font-size:12px;display:flex;align-items:center;gap:4px;"><input type="checkbox" class="iiko-status-cb" value="Cancelled"> Отменен</label>
        </div>
        <div id="iiko-orders-list">
            <span class="badge badge-muted">Выберите настройку и организацию, затем нажмите «Загрузить заказы»</span>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

function switchOrderTab(name, evt) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    if (evt && evt.target) evt.target.classList.add('active');
    if (name === 'local') loadLocalOrders();
    if (name === 'iiko') loadOrderSettings();
}

async function apiGet(url) {
    const res = await fetch(url, { headers: { 'X-CSRF-TOKEN': csrfToken } });
    return res.json();
}

async function apiPost(url, body = {}) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
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

function getStatusClass(status) {
    const s = (status || '').toLowerCase();
    if (s === 'new' || s === 'unconfirmed') return 'status-new';
    if (s === 'confirmed' || s === 'waitcooking' || s === 'readyforcooking') return 'status-confirmed';
    if (s === 'cooking' || s === 'cookingstarted' || s === 'cookingcompleted') return 'status-cooking';
    if (s === 'onway' || s === 'waiting') return 'status-onway';
    if (s === 'delivered') return 'status-delivered';
    if (s === 'closed') return 'status-closed';
    if (s === 'cancelled') return 'status-cancelled';
    return 'status-default';
}

function getStatusLabel(status) {
    const map = {
        'new': 'Новый', 'confirmed': 'Подтвержден', 'cooking': 'Готовится',
        'onway': 'В пути', 'delivered': 'Доставлен', 'closed': 'Закрыт', 'cancelled': 'Отменен',
        'Unconfirmed': 'Не подтвержден', 'WaitCooking': 'Ожидает готовки', 'ReadyForCooking': 'Готов к готовке',
        'CookingStarted': 'Готовится', 'CookingCompleted': 'Приготовлен', 'Waiting': 'Ожидает',
        'OnWay': 'В пути', 'Delivered': 'Доставлен', 'Closed': 'Закрыт', 'Cancelled': 'Отменен',
    };
    return map[status] || status || '—';
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    return d.toLocaleDateString('ru-RU') + ' ' + d.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
}

// ─── Local Orders ────────────────────────────────────────
async function loadLocalOrders() {
    const container = document.getElementById('local-orders-list');
    const statusFilter = document.getElementById('order-status-filter').value;
    container.innerHTML = '<div class="loading-overlay"><span class="spinner"></span> Загрузка...</div>';
    try {
        let url = '/admin/api/orders?limit=100';
        if (statusFilter) url += '&status_filter=' + encodeURIComponent(statusFilter);
        const data = await apiGet(url);
        const orders = Array.isArray(data) ? data : [];
        if (orders.length === 0) { container.innerHTML = '<span class="badge badge-muted">Нет заказов</span>'; return; }
        let html = '';
        orders.forEach(o => {
            html += '<div class="order-card">' +
                '<div class="order-header">' +
                    '<div class="order-id">Заказ #' + o.id + (o.iiko_order_id ? ' <span style="font-size:11px;color:var(--muted);">iiko: ' + escapeHtml(o.iiko_order_id).substring(0,8) + '...</span>' : '') + '</div>' +
                    '<span class="status-pill ' + getStatusClass(o.status) + '">' + getStatusLabel(o.status) + '</span>' +
                '</div>' +
                '<div class="order-details">' +
                    '<div><div class="order-detail-label">Клиент</div><div class="order-detail-value">' + escapeHtml(o.customer_name || '—') + '</div></div>' +
                    '<div><div class="order-detail-label">Телефон</div><div class="order-detail-value">' + escapeHtml(o.customer_phone || '—') + '</div></div>' +
                    '<div><div class="order-detail-label">Адрес</div><div class="order-detail-value">' + escapeHtml(o.delivery_address || '—') + '</div></div>' +
                    '<div><div class="order-detail-label">Сумма</div><div class="order-detail-value" style="color:var(--accent-light);font-weight:700;">' + ((o.total_amount || 0) / 100).toFixed(2) + ' ₽</div></div>' +
                    '<div><div class="order-detail-label">Дата создания</div><div class="order-detail-value">' + formatDate(o.created_at) + '</div></div>' +
                    '<div><div class="order-detail-label">Обновлен</div><div class="order-detail-value">' + formatDate(o.updated_at) + '</div></div>' +
                '</div>' +
            '</div>';
        });
        container.innerHTML = html;
    } catch (err) { container.innerHTML = '<div class="alert alert-danger">⚠️ ' + escapeHtml(err.message) + '</div>'; }
}

// ─── iiko Orders ─────────────────────────────────────────
async function loadOrderSettings() {
    try {
        const data = await apiGet('/admin/api/iiko-settings');
        const settings = Array.isArray(data) ? data : [];
        const sel = document.getElementById('orders-setting-select');
        sel.innerHTML = '<option value="">Выберите настройку...</option>';
        settings.forEach(s => {
            sel.innerHTML += '<option value="' + s.id + '">Интеграция #' + s.id + (s.organization_id ? ' (' + escapeHtml(s.organization_id).substring(0,8) + '...)' : '') + '</option>';
        });
    } catch (err) { /* ignore */ }
}

async function loadOrderOrganizations() {
    const settingId = document.getElementById('orders-setting-select').value;
    if (!settingId) { alert('Выберите настройку iiko'); return; }
    const orgSelect = document.getElementById('orders-org-select');
    orgSelect.innerHTML = '<option value="">Загрузка...</option>';
    orgSelect.disabled = true;
    try {
        const result = await apiPost('/admin/api/iiko-organizations', { setting_id: settingId });
        const orgs = result.data?.organizations || [];
        orgSelect.innerHTML = '<option value="">Выберите организацию...</option>';
        orgs.forEach(org => { orgSelect.innerHTML += '<option value="' + escapeHtml(org.id) + '">' + escapeHtml(org.name || org.id) + '</option>'; });
        orgSelect.disabled = false;
    } catch (err) { orgSelect.innerHTML = '<option value="">Ошибка загрузки</option>'; }
}

async function loadIikoOrders() {
    const settingId = document.getElementById('orders-setting-select').value;
    const orgId = document.getElementById('orders-org-select').value;
    const days = document.getElementById('orders-days-select').value || 1;
    const container = document.getElementById('iiko-orders-list');
    if (!settingId || !orgId) { container.innerHTML = '<div class="alert alert-warning">⚠️ Выберите настройку и организацию</div>'; return; }

    const checkboxes = document.querySelectorAll('.iiko-status-cb:checked');
    const statuses = Array.from(checkboxes).map(cb => cb.value).join(',');
    if (!statuses) { container.innerHTML = '<div class="alert alert-warning">⚠️ Выберите хотя бы один статус</div>'; return; }

    container.innerHTML = '<div class="loading-overlay"><span class="spinner"></span> Загрузка заказов из iiko Cloud...</div>';
    try {
        const result = await apiPost('/admin/api/iiko-deliveries', { setting_id: settingId, organization_id: orgId, statuses: statuses, days: parseInt(days) });
        if (result.status >= 400) { container.innerHTML = '<div class="alert alert-danger">❌ ' + escapeHtml(result.data.detail || JSON.stringify(result.data)) + '</div>'; return; }
        const data = result.data;
        const orders = data.orders || [];
        if (orders.length === 0) { container.innerHTML = '<span class="badge badge-muted">Нет заказов по выбранным статусам за выбранный период</span>'; return; }
        let html = '<div style="margin-bottom:8px;"><span class="badge badge-success">Найдено заказов: ' + orders.length + '</span></div>';
        orders.forEach(o => {
            const order = o.order || o;
            const status = order.deliveryStatus || order.status || '—';
            const customer = order.customer || {};
            const items = order.items || [];
            const completeBefore = order.completeBefore || '';
            html += '<div class="iiko-order-card">' +
                '<div class="order-header">' +
                    '<div class="order-id">Заказ ' + escapeHtml(order.number || order.id || '').substring(0,12) + '</div>' +
                    '<span class="status-pill ' + getStatusClass(status) + '">' + getStatusLabel(status) + '</span>' +
                '</div>' +
                '<div class="order-details">' +
                    '<div><div class="order-detail-label">Клиент</div><div class="order-detail-value">' + escapeHtml(customer.name || '—') + '</div></div>' +
                    '<div><div class="order-detail-label">Телефон</div><div class="order-detail-value">' + escapeHtml(order.phone || customer.phone || '—') + '</div></div>' +
                    '<div><div class="order-detail-label">Сумма</div><div class="order-detail-value" style="color:var(--accent-light);font-weight:700;">' + (order.sum || 0) + ' ₽</div></div>' +
                    (completeBefore ? '<div><div class="order-detail-label">Доставить до</div><div class="order-detail-value">' + formatDate(completeBefore) + '</div></div>' : '') +
                    '<div><div class="order-detail-label">Комментарий</div><div class="order-detail-value">' + escapeHtml(order.comment || '—') + '</div></div>' +
                '</div>' +
                (items.length > 0 ? '<div class="iiko-order-items"><strong>Позиции:</strong><br>' +
                    items.map(it => escapeHtml(it.name || it.product?.name || '—') + ' × ' + (it.amount || 1) + (it.sum ? ' — ' + it.sum + ' ₽' : '')).join('<br>') +
                '</div>' : '') +
            '</div>';
        });
        container.innerHTML = html;
    } catch (err) { container.innerHTML = '<div class="alert alert-danger">❌ ' + escapeHtml(err.message) + '</div>'; }
}

// ─── Init ────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() { loadLocalOrders(); });
</script>
@endsection

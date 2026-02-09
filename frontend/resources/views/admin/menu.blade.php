@extends('layouts.admin')

@section('title', 'Меню')
@section('page-title', 'Меню')

@section('styles')
<style>
    .section-gap { margin-bottom: 20px; }
    .menu-item-card {
        padding: 14px;
        border-radius: 12px;
        border: 1px solid var(--border);
        background: rgba(255,255,255,0.03);
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
        transition: background .15s;
    }
    .menu-item-card:hover { background: rgba(255,255,255,0.06); }
    .menu-item-info { flex: 1; min-width: 0; }
    .menu-item-name { font-weight: 600; font-size: 14px; color: var(--text-bright); }
    .menu-item-desc { font-size: 12px; color: var(--muted); margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 400px; }
    .menu-item-meta { display: flex; gap: 8px; align-items: center; }
    .menu-price { font-weight: 700; font-size: 15px; color: var(--accent-light); }
    .menu-category-tag { padding: 3px 8px; border-radius: 6px; font-size: 11px; background: rgba(99,102,241,0.12); color: var(--accent-light); }
    .menu-group-header { font-size: 16px; font-weight: 600; color: var(--text-bright); margin: 16px 0 8px; padding-bottom: 6px; border-bottom: 1px solid var(--border); }
    .filter-bar { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
    .search-input { max-width: 300px; }
</style>
@endsection

@section('content')
{{-- Tab Bar --}}
<div class="tab-bar">
    <button class="tab-btn active" onclick="switchMenuTab('local', event)">📦 Локальное меню</button>
    <button class="tab-btn" onclick="switchMenuTab('iiko', event)">☁️ Меню из iiko</button>
    <button class="tab-btn" onclick="switchMenuTab('sync', event)">🔄 Синхронизация</button>
</div>

{{-- ═══ TAB: Local Menu ═══ --}}
<div class="tab-content active" id="tab-local">
    <div class="card section-gap">
        <div class="card-header">
            <div>
                <div class="card-title">Локальное меню</div>
                <div class="card-subtitle">Позиции, сохраненные в базе данных</div>
            </div>
            <button class="btn btn-sm" onclick="loadLocalMenu()">🔄 Обновить</button>
        </div>
        <div class="filter-bar">
            <input type="text" class="form-input search-input" id="menu-search" placeholder="🔍 Поиск по названию..." oninput="filterLocalMenu()">
        </div>
        <div id="local-menu-list">
            <div class="loading-overlay"><span class="spinner"></span> Загрузка...</div>
        </div>
    </div>
</div>

{{-- ═══ TAB: iiko Menu ═══ --}}
<div class="tab-content" id="tab-iiko">
    <div class="card section-gap">
        <div class="card-header">
            <div>
                <div class="card-title">Меню из iiko Cloud</div>
                <div class="card-subtitle">Номенклатура из iiko в реальном времени</div>
            </div>
        </div>
        <div class="grid-3" style="margin-bottom:16px;">
            <div class="form-group">
                <label class="form-label">Настройка iiko</label>
                <select class="form-input" id="menu-setting-select">
                    <option value="">Загрузка...</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Организация</label>
                <select class="form-input" id="menu-org-select" disabled>
                    <option value="">Сначала загрузите организации</option>
                </select>
            </div>
            <div class="form-group" style="display:flex;align-items:flex-end;gap:8px;">
                <button class="btn btn-sm" onclick="loadMenuOrganizations()">📡 Организации</button>
                <button class="btn btn-primary btn-sm" onclick="loadIikoMenu()">📋 Загрузить меню</button>
            </div>
        </div>
        <div class="filter-bar">
            <input type="text" class="form-input search-input" id="iiko-menu-search" placeholder="🔍 Поиск по названию..." oninput="filterIikoMenu()">
        </div>
        <div id="iiko-menu-list">
            <span class="badge badge-muted">Выберите настройку и организацию, затем нажмите «Загрузить меню»</span>
        </div>
    </div>
</div>

{{-- ═══ TAB: Sync ═══ --}}
<div class="tab-content" id="tab-sync">
    <div class="card section-gap">
        <div class="card-header">
            <div>
                <div class="card-title">Синхронизация меню</div>
                <div class="card-subtitle">Синхронизируйте меню из iiko Cloud в локальную базу данных</div>
            </div>
        </div>
        <div class="grid-3" style="margin-bottom:16px;">
            <div class="form-group">
                <label class="form-label">Настройка iiko</label>
                <select class="form-input" id="sync-setting-select">
                    <option value="">Загрузка...</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Организация</label>
                <select class="form-input" id="sync-org-select" disabled>
                    <option value="">Сначала загрузите организации</option>
                </select>
            </div>
            <div class="form-group" style="display:flex;align-items:flex-end;gap:8px;">
                <button class="btn btn-sm" onclick="loadSyncOrganizations()">📡 Организации</button>
                <button class="btn btn-primary" onclick="syncMenu()">🔄 Синхронизировать</button>
            </div>
        </div>
        <div id="sync-result"></div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

let localMenuData = [];
let iikoMenuData = [];

function switchMenuTab(name, evt) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    if (evt && evt.target) evt.target.classList.add('active');
    if (name === 'local') loadLocalMenu();
    if (name === 'iiko' || name === 'sync') loadMenuSettings();
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

function formatPrice(kopecks) {
    return (kopecks / 100).toFixed(2) + ' ₽';
}

// ─── Local Menu ──────────────────────────────────────────
async function loadLocalMenu() {
    const container = document.getElementById('local-menu-list');
    container.innerHTML = '<div class="loading-overlay"><span class="spinner"></span> Загрузка...</div>';
    try {
        const data = await apiGet('/admin/api/menu?limit=500');
        localMenuData = data.items || [];
        renderLocalMenu(localMenuData);
    } catch (err) {
        container.innerHTML = '<div class="alert alert-danger">⚠️ ' + escapeHtml(err.message) + '</div>';
    }
}

function renderLocalMenu(items) {
    const container = document.getElementById('local-menu-list');
    if (items.length === 0) {
        container.innerHTML = '<span class="badge badge-muted">Нет позиций в меню. Синхронизируйте из iiko.</span>';
        return;
    }
    // Group by category
    const groups = {};
    items.forEach(item => {
        const cat = item.category || 'Без категории';
        if (!groups[cat]) groups[cat] = [];
        groups[cat].push(item);
    });
    let html = '';
    Object.keys(groups).sort().forEach(cat => {
        html += '<div class="menu-group-header">' + escapeHtml(cat) + ' <span class="badge badge-muted">' + groups[cat].length + '</span></div>';
        groups[cat].forEach(item => {
            html += '<div class="menu-item-card">' +
                '<div class="menu-item-info">' +
                    '<div class="menu-item-name">' + escapeHtml(item.name) + '</div>' +
                    '<div class="menu-item-desc">' + escapeHtml(item.description || '') + '</div>' +
                '</div>' +
                '<div class="menu-item-meta">' +
                    '<span class="badge ' + (item.is_available ? 'badge-success' : 'badge-danger') + '">' + (item.is_available ? 'Доступно' : 'Недоступно') + '</span>' +
                    '<span class="menu-price">' + formatPrice(item.price || 0) + '</span>' +
                '</div>' +
            '</div>';
        });
    });
    container.innerHTML = html;
}

function filterLocalMenu() {
    const q = document.getElementById('menu-search').value.toLowerCase();
    const filtered = localMenuData.filter(i => (i.name || '').toLowerCase().includes(q) || (i.description || '').toLowerCase().includes(q));
    renderLocalMenu(filtered);
}

// ─── Settings loading ────────────────────────────────────
async function loadMenuSettings() {
    try {
        const data = await apiGet('/admin/api/iiko-settings');
        const settings = Array.isArray(data) ? data : [];
        ['menu-setting-select', 'sync-setting-select'].forEach(selId => {
            const sel = document.getElementById(selId);
            if (!sel) return;
            sel.innerHTML = '<option value="">Выберите настройку...</option>';
            settings.forEach(s => {
                sel.innerHTML += '<option value="' + s.id + '">Интеграция #' + s.id + (s.organization_id ? ' (' + escapeHtml(s.organization_id).substring(0,8) + '...)' : '') + '</option>';
            });
        });
    } catch (err) { /* ignore */ }
}

async function loadMenuOrganizations() {
    const settingId = document.getElementById('menu-setting-select').value;
    if (!settingId) { alert('Выберите настройку iiko'); return; }
    const orgSelect = document.getElementById('menu-org-select');
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

async function loadSyncOrganizations() {
    const settingId = document.getElementById('sync-setting-select').value;
    if (!settingId) { alert('Выберите настройку iiko'); return; }
    const orgSelect = document.getElementById('sync-org-select');
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

// ─── iiko Menu ───────────────────────────────────────────
async function loadIikoMenu() {
    const settingId = document.getElementById('menu-setting-select').value;
    const orgId = document.getElementById('menu-org-select').value;
    const container = document.getElementById('iiko-menu-list');
    if (!settingId || !orgId) { container.innerHTML = '<div class="alert alert-warning">⚠️ Выберите настройку и организацию</div>'; return; }
    container.innerHTML = '<div class="loading-overlay"><span class="spinner"></span> Загрузка меню из iiko Cloud...</div>';
    try {
        const result = await apiPost('/admin/api/iiko-menu', { setting_id: settingId, organization_id: orgId });
        if (result.status >= 400) { container.innerHTML = '<div class="alert alert-danger">❌ ' + escapeHtml(result.data.detail || JSON.stringify(result.data)) + '</div>'; return; }
        const data = result.data;
        const products = data.products || [];
        const groups = data.groups || [];
        iikoMenuData = products;
        renderIikoMenu(products, groups);
    } catch (err) { container.innerHTML = '<div class="alert alert-danger">❌ ' + escapeHtml(err.message) + '</div>'; }
}

function renderIikoMenu(products, groups) {
    const container = document.getElementById('iiko-menu-list');
    if (products.length === 0) { container.innerHTML = '<span class="badge badge-muted">Нет позиций в меню iiko</span>'; return; }
    // Build group map
    const groupMap = {};
    (groups || []).forEach(g => { groupMap[g.id] = g.name || g.id; });
    // Group products by parentGroup
    const grouped = {};
    products.forEach(p => {
        const gName = groupMap[p.parentGroup] || groupMap[p.groupId] || 'Без группы';
        if (!grouped[gName]) grouped[gName] = [];
        grouped[gName].push(p);
    });
    let html = '<div style="margin-bottom:8px;"><span class="badge badge-success">Найдено позиций: ' + products.length + '</span></div>';
    Object.keys(grouped).sort().forEach(gName => {
        html += '<div class="menu-group-header">' + escapeHtml(gName) + ' <span class="badge badge-muted">' + grouped[gName].length + '</span></div>';
        grouped[gName].forEach(p => {
            let price = 0;
            const sizes = p.sizePrices || [];
            if (sizes.length > 0) {
                const priceVal = sizes[0].price;
                price = typeof priceVal === 'object' ? (priceVal.currentPrice || 0) : (priceVal || 0);
            }
            html += '<div class="menu-item-card">' +
                '<div class="menu-item-info">' +
                    '<div class="menu-item-name">' + escapeHtml(p.name || '') + '</div>' +
                    '<div class="menu-item-desc">' + escapeHtml(p.description || '') + '</div>' +
                '</div>' +
                '<div class="menu-item-meta">' +
                    (p.type ? '<span class="menu-category-tag">' + escapeHtml(p.type) + '</span>' : '') +
                    '<span class="menu-price">' + Number(price).toFixed(2) + ' ₽</span>' +
                '</div>' +
            '</div>';
        });
    });
    container.innerHTML = html;
}

function filterIikoMenu() {
    const q = document.getElementById('iiko-menu-search').value.toLowerCase();
    const filtered = iikoMenuData.filter(i => (i.name || '').toLowerCase().includes(q) || (i.description || '').toLowerCase().includes(q));
    renderIikoMenu(filtered, []);
}

// ─── Sync ────────────────────────────────────────────────
async function syncMenu() {
    const settingId = document.getElementById('sync-setting-select').value;
    const orgId = document.getElementById('sync-org-select').value;
    const container = document.getElementById('sync-result');
    if (!settingId || !orgId) { container.innerHTML = '<div class="alert alert-warning">⚠️ Выберите настройку и организацию</div>'; return; }
    container.innerHTML = '<div class="loading-overlay"><span class="spinner"></span> Синхронизация меню...</div>';
    try {
        const result = await apiPost('/admin/api/iiko-sync-menu', { setting_id: settingId, organization_id: orgId });
        if (result.status >= 400) { container.innerHTML = '<div class="alert alert-danger">❌ ' + escapeHtml(result.data.detail || JSON.stringify(result.data)) + '</div>'; return; }
        container.innerHTML = '<div class="alert alert-success">✓ ' + escapeHtml(result.data.detail || 'Синхронизация завершена') + '</div>';
    } catch (err) { container.innerHTML = '<div class="alert alert-danger">❌ ' + escapeHtml(err.message) + '</div>'; }
}

// ─── Init ────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() { loadLocalMenu(); });
</script>
@endsection

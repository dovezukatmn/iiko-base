@extends('layouts.admin')

@section('title', 'Пользователи')
@section('page-title', 'Пользователи')

@section('styles')
<style>
    .section-gap { margin-bottom: 20px; }
    .user-card {
        padding: 16px;
        border-radius: 12px;
        border: 1px solid var(--border);
        background: rgba(255,255,255,0.03);
        margin-bottom: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: background .15s;
    }
    .user-card:hover { background: rgba(255,255,255,0.06); }
    .user-info { display: flex; align-items: center; gap: 12px; flex: 1; }
    .user-avatar {
        width: 40px; height: 40px;
        border-radius: 10px;
        background: linear-gradient(135deg, var(--accent), var(--accent-2));
        display: grid; place-items: center;
        color: white; font-weight: 700; font-size: 14px; flex-shrink: 0;
    }
    .user-name { font-weight: 600; font-size: 14px; color: var(--text-bright); }
    .user-email { font-size: 12px; color: var(--muted); }
    .user-meta { display: flex; gap: 8px; align-items: center; }
    .role-select {
        padding: 5px 10px; border-radius: 8px; border: 1px solid var(--border);
        background: rgba(255,255,255,0.04); color: var(--text); font-size: 12px;
        font-family: inherit; outline: none; cursor: pointer;
    }
    .role-select:focus { border-color: rgba(99,102,241,0.5); }
    .role-badge { padding: 4px 10px; border-radius: 8px; font-size: 12px; font-weight: 600; }
    .role-admin { background: rgba(239,68,68,0.15); color: var(--danger); }
    .role-manager { background: rgba(245,158,11,0.15); color: var(--warning); }
    .role-operator { background: rgba(99,102,241,0.15); color: var(--accent-light); }
    .role-viewer { background: rgba(148,163,184,0.12); color: var(--muted); }
</style>
@endsection

@section('content')
{{-- Create User Form --}}
<div class="card section-gap">
    <div class="card-header">
        <div>
            <div class="card-title">➕ Создать пользователя</div>
            <div class="card-subtitle">Добавить нового пользователя в систему</div>
        </div>
    </div>
    <div style="max-width:520px;">
        <div class="grid-2">
            <div class="form-group">
                <label class="form-label">Имя пользователя *</label>
                <input class="form-input" id="new-user-username" placeholder="username" autocomplete="off">
            </div>
            <div class="form-group">
                <label class="form-label">Email *</label>
                <input class="form-input" id="new-user-email" type="email" placeholder="user@example.com" autocomplete="off">
            </div>
            <div class="form-group">
                <label class="form-label">Пароль *</label>
                <input class="form-input" id="new-user-password" type="password" placeholder="Минимум 6 символов" autocomplete="new-password">
            </div>
            <div class="form-group">
                <label class="form-label">Роль</label>
                <select class="form-input" id="new-user-role">
                    <option value="viewer">viewer — Наблюдатель</option>
                    <option value="operator">operator — Оператор</option>
                    <option value="manager">manager — Менеджер</option>
                    <option value="admin">admin — Администратор</option>
                </select>
            </div>
        </div>
        <button class="btn btn-primary" onclick="createUser()">💾 Создать пользователя</button>
        <div id="create-user-result" style="margin-top:12px;"></div>
    </div>
</div>

<div class="card section-gap">
    <div class="card-header">
        <div>
            <div class="card-title">Пользователи системы</div>
            <div class="card-subtitle">Управление доступом и ролями</div>
        </div>
        <button class="btn btn-sm" onclick="loadUsers()">🔄 Обновить</button>
    </div>

    <div class="grid-4" style="margin-bottom:16px;" id="user-stats">
        <div class="card stat-card">
            <span class="stat-label">Всего</span>
            <span class="stat-value" id="stat-total" style="font-size:24px;">—</span>
        </div>
        <div class="card stat-card">
            <span class="stat-label">Администраторы</span>
            <span class="stat-value" id="stat-admins" style="font-size:24px;">—</span>
        </div>
        <div class="card stat-card">
            <span class="stat-label">Менеджеры</span>
            <span class="stat-value" id="stat-managers" style="font-size:24px;">—</span>
        </div>
        <div class="card stat-card">
            <span class="stat-label">Активные</span>
            <span class="stat-value" id="stat-active" style="font-size:24px;">—</span>
        </div>
    </div>

    <div id="users-list">
        <div class="loading-overlay"><span class="spinner"></span> Загрузка...</div>
    </div>
</div>

<div class="card section-gap">
    <div class="card-header">
        <div>
            <div class="card-title">Роли и доступ</div>
            <div class="card-subtitle">Описание уровней доступа</div>
        </div>
    </div>
    <div class="grid-2">
        <div style="padding:10px;">
            <span class="role-badge role-admin">admin</span>
            <span style="font-size:13px;color:var(--text);margin-left:8px;">Полный доступ: настройки, пользователи, вебхуки, все данные</span>
        </div>
        <div style="padding:10px;">
            <span class="role-badge role-manager">manager</span>
            <span style="font-size:13px;color:var(--text);margin-left:8px;">Управление: меню, синхронизация, вебхуки</span>
        </div>
        <div style="padding:10px;">
            <span class="role-badge role-operator">operator</span>
            <span style="font-size:13px;color:var(--text);margin-left:8px;">Работа: заказы, просмотр данных iiko</span>
        </div>
        <div style="padding:10px;">
            <span class="role-badge role-viewer">viewer</span>
            <span style="font-size:13px;color:var(--text);margin-left:8px;">Только просмотр: меню, статусы</span>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

async function apiGet(url) {
    const res = await fetch(url, { headers: { 'X-CSRF-TOKEN': csrfToken } });
    return res.json();
}

async function apiPut(url, body = {}) {
    const res = await fetch(url, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(body),
    });
    return { status: res.status, data: await res.json() };
}

async function apiPost(url, body = {}) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(body),
    });
    return { status: res.status, data: await res.json() };
}

async function apiDelete(url) {
    const res = await fetch(url, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
    });
    return { status: res.status, data: await res.json() };
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = String(str);
    return div.innerHTML;
}

function getRoleClass(role) {
    return 'role-' + (role || 'viewer');
}

function getRoleLabel(role) {
    const map = { 'admin': 'Администратор', 'manager': 'Менеджер', 'operator': 'Оператор', 'viewer': 'Наблюдатель' };
    return map[role] || role || '—';
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    return d.toLocaleDateString('ru-RU') + ' ' + d.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
}

async function loadUsers() {
    const container = document.getElementById('users-list');
    container.innerHTML = '<div class="loading-overlay"><span class="spinner"></span> Загрузка...</div>';
    try {
        const data = await apiGet('/admin/api/users');
        const users = Array.isArray(data) ? data : [];
        // Stats
        document.getElementById('stat-total').textContent = users.length;
        document.getElementById('stat-admins').textContent = users.filter(u => u.role === 'admin').length;
        document.getElementById('stat-managers').textContent = users.filter(u => u.role === 'manager').length;
        document.getElementById('stat-active').textContent = users.filter(u => u.is_active).length;

        if (users.length === 0) { container.innerHTML = '<span class="badge badge-muted">Нет пользователей</span>'; return; }
        let html = '';
        users.forEach(u => {
            const initials = (u.username || 'U').substring(0, 2).toUpperCase();
            html += '<div class="user-card">' +
                '<div class="user-info">' +
                    '<div class="user-avatar">' + escapeHtml(initials) + '</div>' +
                    '<div>' +
                        '<div class="user-name">' + escapeHtml(u.username) + '</div>' +
                        '<div class="user-email">' + escapeHtml(u.email) + '</div>' +
                        '<div style="font-size:11px;color:var(--muted);margin-top:2px;">Создан: ' + formatDate(u.created_at) + '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="user-meta">' +
                    '<span class="badge ' + (u.is_active ? 'badge-success' : 'badge-danger') + '" style="cursor:pointer;" onclick="toggleUserActive(' + u.id + ')" title="Нажмите для переключения">' + (u.is_active ? 'Активен' : 'Неактивен') + '</span>' +
                    '<select class="role-select" onchange="updateRole(' + u.id + ', this.value)" data-user-id="' + u.id + '">' +
                        '<option value="admin" ' + (u.role === 'admin' ? 'selected' : '') + '>admin</option>' +
                        '<option value="manager" ' + (u.role === 'manager' ? 'selected' : '') + '>manager</option>' +
                        '<option value="operator" ' + (u.role === 'operator' ? 'selected' : '') + '>operator</option>' +
                        '<option value="viewer" ' + (u.role === 'viewer' ? 'selected' : '') + '>viewer</option>' +
                    '</select>' +
                    '<button class="btn btn-danger btn-sm" onclick="deleteUser(' + u.id + ', \'' + escapeHtml(u.username) + '\')" title="Удалить">🗑</button>' +
                '</div>' +
            '</div>';
        });
        container.innerHTML = html;
    } catch (err) { container.innerHTML = '<div class="alert alert-danger">⚠️ ' + escapeHtml(err.message) + '</div>'; }
}

async function updateRole(userId, newRole) {
    try {
        const result = await apiPut('/admin/api/users/' + userId + '/role', { role: newRole });
        if (result.status >= 400) {
            alert('Ошибка: ' + (result.data.detail || JSON.stringify(result.data)));
            loadUsers();
        }
    } catch (err) {
        alert('Ошибка: ' + err.message);
        loadUsers();
    }
}

async function createUser() {
    const username = document.getElementById('new-user-username').value.trim();
    const email = document.getElementById('new-user-email').value.trim();
    const password = document.getElementById('new-user-password').value;
    const role = document.getElementById('new-user-role').value;
    const container = document.getElementById('create-user-result');

    if (!username || !email || !password) {
        container.innerHTML = '<div class="alert alert-danger">Заполните все обязательные поля</div>';
        return;
    }
    if (password.length < 6) {
        container.innerHTML = '<div class="alert alert-danger">Пароль должен содержать минимум 6 символов</div>';
        return;
    }

    container.innerHTML = '<div class="loading-overlay"><span class="spinner"></span> Создание...</div>';
    try {
        const result = await apiPost('/admin/api/users', { username, email, password, role, is_active: true });
        if (result.status >= 400) {
            let errorMsg = 'Неизвестная ошибка';
            if (result.data.detail) {
                errorMsg = result.data.detail;
            } else if (result.data.error) {
                errorMsg = result.data.error;
            } else if (typeof result.data === 'string') {
                errorMsg = result.data;
            } else {
                errorMsg = JSON.stringify(result.data);
            }
            
            // Add diagnostic info for "Method Not Allowed" errors
            if (result.status === 405) {
                errorMsg += '<br><br><small>Диагностика: HTTP 405 Method Not Allowed. Проверьте:<br>' +
                    '1. Backend API доступен (проверьте настройку BACKEND_API_URL в .env)<br>' +
                    '2. Backend сервис запущен<br>' +
                    '3. Правильные роуты зарегистрированы</small>';
            } else if (result.status === 502 || result.status === 503) {
                errorMsg += '<br><br><small>Диагностика: Сервер недоступен. Backend API не отвечает.</small>';
            }
            
            container.innerHTML = '<div class="alert alert-danger">⚠️ ' + errorMsg + '</div>';
            return;
        }
        container.innerHTML = '<div class="alert alert-success">✅ Пользователь "' + escapeHtml(result.data.username) + '" создан с ролью ' + escapeHtml(result.data.role) + '</div>';
        document.getElementById('new-user-username').value = '';
        document.getElementById('new-user-email').value = '';
        document.getElementById('new-user-password').value = '';
        loadUsers();
    } catch (err) {
        container.innerHTML = '<div class="alert alert-danger">❌ Ошибка подключения: ' + escapeHtml(err.message) + '<br><small>Проверьте, что Backend API запущен и доступен</small></div>';
    }
}

async function deleteUser(userId, username) {
    if (!confirm('Удалить пользователя "' + username + '"? Это действие необратимо.')) return;
    try {
        const result = await apiDelete('/admin/api/users/' + userId);
        if (result.status >= 400) {
            alert('Ошибка: ' + (result.data.detail || JSON.stringify(result.data)));
        }
        loadUsers();
    } catch (err) {
        alert('Ошибка: ' + err.message);
        loadUsers();
    }
}

async function toggleUserActive(userId) {
    try {
        const result = await apiPut('/admin/api/users/' + userId + '/toggle-active');
        if (result.status >= 400) {
            alert('Ошибка: ' + (result.data.detail || JSON.stringify(result.data)));
        }
        loadUsers();
    } catch (err) {
        alert('Ошибка: ' + err.message);
        loadUsers();
    }
}

// ─── Init ────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() { loadUsers(); });
</script>
@endsection

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Админ-панель') | iiko-base</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0f172a;
            --surface: rgba(15, 23, 42, 0.7);
            --surface-2: rgba(30, 41, 59, 0.6);
            --border: rgba(255,255,255,0.08);
            --border-hover: rgba(255,255,255,0.15);
            --muted: #94a3b8;
            --text: #e2e8f0;
            --text-bright: #f8fafc;
            --accent: #6366f1;
            --accent-light: #818cf8;
            --accent-2: #22d3ee;
            --success: #22c55e;
            --success-bg: rgba(34,197,94,0.12);
            --warning: #f59e0b;
            --warning-bg: rgba(245,158,11,0.12);
            --danger: #ef4444;
            --danger-bg: rgba(239,68,68,0.12);
            --sidebar-w: 260px;
            --header-h: 64px;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            background:
                radial-gradient(circle at 20% 20%, rgba(99, 102, 241, 0.25), transparent 30%),
                radial-gradient(circle at 85% 15%, rgba(34, 211, 238, 0.15), transparent 25%),
                radial-gradient(circle at 80% 80%, rgba(99, 102, 241, 0.15), transparent 35%),
                var(--bg);
            color: var(--text);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            display: flex;
        }

        /* ─── Sidebar ─────────────────────────────────────── */
        .sidebar {
            position: fixed;
            left: 0; top: 0;
            width: var(--sidebar-w);
            height: 100vh;
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(16px);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            z-index: 100;
            transition: transform .3s ease;
        }
        .sidebar-brand {
            padding: 20px 20px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid var(--border);
        }
        .sidebar-logo {
            width: 40px; height: 40px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            display: grid;
            place-items: center;
            color: white;
            font-weight: 700;
            font-size: 14px;
            flex-shrink: 0;
        }
        .sidebar-brand-text {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-bright);
        }
        .sidebar-brand-sub {
            font-size: 11px;
            color: var(--muted);
            margin-top: 2px;
        }
        .sidebar-nav {
            flex: 1;
            padding: 12px 10px;
            overflow-y: auto;
        }
        .sidebar-section {
            font-size: 11px;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 12px 10px 6px;
        }
        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 10px;
            color: var(--muted);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all .15s ease;
            margin-bottom: 2px;
        }
        .sidebar-link:hover {
            background: rgba(255,255,255,0.05);
            color: var(--text);
        }
        .sidebar-link.active {
            background: rgba(99, 102, 241, 0.15);
            color: var(--accent-light);
        }
        .sidebar-link .icon {
            width: 20px;
            text-align: center;
            font-size: 16px;
        }
        .sidebar-footer {
            padding: 12px 14px;
            border-top: 1px solid var(--border);
        }
        .sidebar-user {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px;
            border-radius: 10px;
            background: rgba(255,255,255,0.03);
        }
        .sidebar-avatar {
            width: 34px; height: 34px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            display: grid;
            place-items: center;
            color: white;
            font-weight: 600;
            font-size: 13px;
            flex-shrink: 0;
        }
        .sidebar-user-info {
            flex: 1;
            min-width: 0;
        }
        .sidebar-user-name {
            font-size: 13px;
            font-weight: 600;
            color: var(--text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .sidebar-user-role {
            font-size: 11px;
            color: var(--muted);
        }

        /* ─── Main Content ─────────────────────────────────── */
        .main-content {
            margin-left: var(--sidebar-w);
            flex: 1;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .main-header {
            height: var(--header-h);
            padding: 0 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border);
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(8px);
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .main-header h2 {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-bright);
        }
        .main-body {
            flex: 1;
            padding: 24px 28px;
        }

        /* ─── Cards ─────────────────────────────────────── */
        .card {
            border: 1px solid var(--border);
            background: var(--surface);
            border-radius: 16px;
            padding: 20px;
            backdrop-filter: blur(10px);
        }
        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }
        .card-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-bright);
        }
        .card-subtitle {
            font-size: 12px;
            color: var(--muted);
            margin-top: 2px;
        }

        /* ─── Grid ─────────────────────────────────────── */
        .grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; }
        .grid-3 { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
        .grid-4 { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; }

        /* ─── Badges / Pills ──────────────────────────── */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
        }
        .badge-success { background: var(--success-bg); color: var(--success); }
        .badge-warning { background: var(--warning-bg); color: var(--warning); }
        .badge-danger { background: var(--danger-bg); color: var(--danger); }
        .badge-muted { background: rgba(148,163,184,0.12); color: var(--muted); }

        /* ─── Buttons ─────────────────────────────────── */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 16px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: rgba(255,255,255,0.05);
            color: var(--text);
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all .15s ease;
            text-decoration: none;
            font-family: inherit;
        }
        .btn:hover { background: rgba(255,255,255,0.08); border-color: var(--border-hover); }
        .btn-primary {
            background: linear-gradient(135deg, var(--accent), #4f46e5);
            border-color: transparent;
            color: white;
        }
        .btn-primary:hover { opacity: 0.9; }
        .btn-success {
            background: rgba(34,197,94,0.15);
            border-color: rgba(34,197,94,0.3);
            color: var(--success);
        }
        .btn-danger {
            background: rgba(239,68,68,0.15);
            border-color: rgba(239,68,68,0.3);
            color: var(--danger);
        }
        .btn-sm { padding: 6px 12px; font-size: 12px; border-radius: 8px; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }

        /* ─── Form ─────────────────────────────────────── */
        .form-group { margin-bottom: 14px; }
        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: var(--muted);
            margin-bottom: 6px;
        }
        .form-input {
            width: 100%;
            padding: 10px 14px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: rgba(255,255,255,0.04);
            color: var(--text);
            font-size: 14px;
            font-family: inherit;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
        }
        .form-input:focus {
            border-color: rgba(99, 102, 241, 0.5);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
        }
        .form-input::placeholder { color: rgba(148,163,184,0.5); }

        /* ─── Tables ──────────────────────────────────── */
        .table-wrap {
            overflow-x: auto;
            border-radius: 12px;
            border: 1px solid var(--border);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        th {
            text-align: left;
            padding: 10px 14px;
            font-weight: 600;
            font-size: 12px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border-bottom: 1px solid var(--border);
            background: rgba(255,255,255,0.02);
        }
        td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border);
            color: var(--text);
        }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(255,255,255,0.02); }

        /* ─── Stat Cards ─────────────────────────────── */
        .stat-card {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-bright);
            line-height: 1.2;
        }
        .stat-label {
            font-size: 13px;
            color: var(--muted);
        }

        /* ─── Status Indicators ──────────────────────── */
        .status-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 6px;
        }
        .status-dot.online { background: var(--success); box-shadow: 0 0 6px rgba(34,197,94,0.5); }
        .status-dot.offline { background: var(--danger); box-shadow: 0 0 6px rgba(239,68,68,0.5); }
        .status-dot.warning { background: var(--warning); box-shadow: 0 0 6px rgba(245,158,11,0.5); }

        /* ─── Loading ─────────────────────────────────── */
        .spinner {
            width: 18px; height: 18px;
            border: 2px solid var(--border);
            border-top-color: var(--accent);
            border-radius: 50%;
            animation: spin .7s linear infinite;
            display: inline-block;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .loading-overlay {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 32px;
            color: var(--muted);
            font-size: 14px;
        }

        /* ─── Alerts ──────────────────────────────────── */
        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 13px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .alert-success { background: var(--success-bg); color: var(--success); border: 1px solid rgba(34,197,94,0.25); }
        .alert-danger { background: var(--danger-bg); color: var(--danger); border: 1px solid rgba(239,68,68,0.25); }
        .alert-warning { background: var(--warning-bg); color: var(--warning); border: 1px solid rgba(245,158,11,0.25); }

        /* ─── Collapsible sections ────────────────────── */
        .collapse-trigger {
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            padding: 12px 16px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: rgba(255,255,255,0.03);
            color: var(--text);
            font-size: 14px;
            font-weight: 500;
            transition: all .15s ease;
            width: 100%;
            text-align: left;
            font-family: inherit;
        }
        .collapse-trigger:hover { background: rgba(255,255,255,0.06); }
        .collapse-trigger .arrow { transition: transform .2s ease; }
        .collapse-trigger.open .arrow { transform: rotate(180deg); }
        .collapse-body {
            max-height: 0;
            overflow: hidden;
            transition: max-height .3s ease;
        }
        .collapse-body.open {
            max-height: 2000px;
        }

        /* ─── JSON Viewer ─────────────────────────────── */
        .json-view {
            background: rgba(0,0,0,0.3);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 14px;
            font-family: 'SF Mono', 'Fira Code', monospace;
            font-size: 12px;
            color: var(--accent-2);
            overflow-x: auto;
            white-space: pre-wrap;
            word-break: break-all;
            max-height: 400px;
            overflow-y: auto;
        }

        /* ─── Tabs ─────────────────────────────────────── */
        .tab-bar {
            display: flex;
            gap: 4px;
            padding: 4px;
            background: rgba(255,255,255,0.03);
            border-radius: 12px;
            border: 1px solid var(--border);
            margin-bottom: 16px;
            overflow-x: auto;
        }
        .tab-btn {
            padding: 8px 16px;
            border-radius: 8px;
            border: none;
            background: transparent;
            color: var(--muted);
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all .15s ease;
            white-space: nowrap;
            font-family: inherit;
        }
        .tab-btn:hover { color: var(--text); background: rgba(255,255,255,0.05); }
        .tab-btn.active { background: rgba(99,102,241,0.15); color: var(--accent-light); }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* ─── Logout button ──────────────────────────── */
        .btn-logout {
            padding: 8px 14px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: rgba(255,255,255,0.05);
            color: var(--muted);
            cursor: pointer;
            font-size: 13px;
            font-family: inherit;
            transition: all .15s ease;
        }
        .btn-logout:hover { color: var(--danger); border-color: rgba(239,68,68,0.3); }

        /* ─── Mobile ──────────────────────────────────── */
        .mobile-toggle {
            display: none;
            padding: 8px;
            border: none;
            background: none;
            color: var(--text);
            font-size: 22px;
            cursor: pointer;
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .mobile-toggle { display: block; }
            .main-body { padding: 16px; }
        }
    </style>
    @yield('styles')
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="sidebar-logo">IB</div>
            <div>
                <div class="sidebar-brand-text">iiko-base</div>
                <div class="sidebar-brand-sub">Админ-панель v1.0</div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="sidebar-section">Главное</div>
            <a href="{{ route('admin.dashboard') }}" class="sidebar-link @if(request()->routeIs('admin.dashboard')) active @endif">
                <span class="icon">📊</span> Обзор
            </a>
            <a href="{{ route('admin.maintenance') }}" class="sidebar-link @if(request()->routeIs('admin.maintenance')) active @endif">
                <span class="icon">🔧</span> Обслуживание
            </a>

            <div class="sidebar-section">Управление</div>
            <a href="{{ route('admin.menu') }}" class="sidebar-link @if(request()->routeIs('admin.menu')) active @endif">
                <span class="icon">📋</span> Меню
            </a>
            <a href="{{ route('admin.orders') }}" class="sidebar-link @if(request()->routeIs('admin.orders')) active @endif">
                <span class="icon">🛒</span> Заказы
            </a>
            <a href="{{ route('admin.users') }}" class="sidebar-link @if(request()->routeIs('admin.users')) active @endif">
                <span class="icon">👥</span> Пользователи
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="sidebar-avatar">{{ strtoupper(substr($displayName ?? 'A', 0, 2)) }}</div>
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name">{{ $displayName ?? 'Администратор' }}</div>
                    <div class="sidebar-user-role">{{ $user['role'] ?? 'admin' }}</div>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="main-content">
        <header class="main-header">
            <div style="display:flex;align-items:center;gap:12px;">
                <button class="mobile-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
                <h2>@yield('page-title', 'Админ-панель')</h2>
            </div>
            <form method="POST" action="{{ route('logout') }}" style="margin:0;">
                @csrf
                <button type="submit" class="btn-logout">Выйти</button>
            </form>
        </header>

        <main class="main-body">
            @yield('content')
        </main>
    </div>

    <script>
        // Close sidebar on mobile when clicking outside
        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.querySelector('.mobile-toggle');
            if (window.innerWidth <= 768 && sidebar.classList.contains('open')
                && !sidebar.contains(e.target) && !toggle.contains(e.target)) {
                sidebar.classList.remove('open');
            }
        });
    </script>
    @yield('scripts')
</body>
</html>

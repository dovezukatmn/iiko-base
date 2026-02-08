<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель | iiko-base</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0f172a;
            --surface: rgba(15, 23, 42, 0.7);
            --border: rgba(255,255,255,0.08);
            --muted: #94a3b8;
            --text: #e2e8f0;
            --accent: #6366f1;
            --accent-2: #22d3ee;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            padding: 32px;
            background:
                radial-gradient(circle at 20% 20%, rgba(99, 102, 241, 0.35), transparent 30%),
                radial-gradient(circle at 85% 15%, rgba(34, 211, 238, 0.25), transparent 25%),
                radial-gradient(circle at 80% 80%, rgba(99, 102, 241, 0.25), transparent 35%),
                var(--bg);
            color: var(--text);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }
        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 24px;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .logo {
            width: 44px; height: 44px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            display: grid;
            place-items: center;
            color: white;
            font-weight: 700;
        }
        h1 { margin: 0; font-size: 24px; letter-spacing: -0.01em; }
        .muted { color: var(--muted); }
        .card {
            border: 1px solid var(--border);
            background: var(--surface);
            border-radius: 18px;
            padding: 20px;
            backdrop-filter: blur(10px);
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 16px;
        }
        .stat {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .stat strong { font-size: 20px; }
        .actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
        }
        .action {
            padding: 14px;
            border-radius: 14px;
            border: 1px solid var(--border);
            background: rgba(255,255,255,0.03);
            display: flex;
            align-items: center;
            gap: 12px;
            color: inherit;
            text-decoration: none;
            transition: transform .1s ease, border-color .2s ease;
        }
        .action:hover { transform: translateY(-1px); border-color: rgba(99, 102, 241, 0.4); }
        .pill {
            padding: 8px 10px;
            border-radius: 12px;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border);
            font-size: 13px;
            color: var(--muted);
        }
        form { margin: 0; }
        button {
            padding: 10px 12px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: rgba(255,255,255,0.05);
            color: white;
            cursor: pointer;
        }
        @media (max-width: 720px) { body { padding: 18px; } }
    </style>
</head>
<body>
    <header>
        <div class="brand">
            <div class="logo">IB</div>
            <div>
                <h1>Админ-панель</h1>
                <div class="muted">Добро пожаловать, {{ $displayName }}</div>
            </div>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit">Выйти</button>
        </form>
    </header>

    <section class="grid">
        <div class="card stat">
            <span class="muted">Роль</span>
            <strong>{{ $user['role'] ?? 'admin' }}</strong>
            <span class="pill">Доступ к настройкам и пользователям</span>
        </div>
        <div class="card stat">
            <span class="muted">Интеграции</span>
            <strong>iiko</strong>
            <span class="pill">API готово к настройке</span>
        </div>
        <div class="card stat">
            <span class="muted">Сессия</span>
            <strong>Активна</strong>
            <span class="pill">Токен сохранен в браузере</span>
        </div>
    </section>

    <section class="card" style="margin-top:16px;">
        <h2 style="margin-top:0; margin-bottom:12px;">Быстрые действия</h2>
        <div class="actions">
            <div class="action">🧑‍💼 Управление пользователями</div>
            <div class="action">📋 Настройка меню iiko</div>
            <div class="action">⚙️ Конфигурация API</div>
            <div class="action">📈 Мониторинг вебхуков</div>
        </div>
    </section>
</body>
</html>

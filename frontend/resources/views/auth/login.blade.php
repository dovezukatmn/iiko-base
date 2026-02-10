<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход в админку | iiko-base</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: radial-gradient(circle at 10% 20%, rgba(99, 102, 241, 0.35), transparent 35%),
                  radial-gradient(circle at 90% 10%, rgba(52, 211, 153, 0.35), transparent 25%),
                  radial-gradient(circle at 80% 80%, rgba(129, 140, 248, 0.25), transparent 35%),
                  #0f172a;
            --card: rgba(15, 23, 42, 0.6);
            --accent: #6366f1;
            --accent-2: #22d3ee;
            --text: #e2e8f0;
            --muted: #94a3b8;
            --border: rgba(255, 255, 255, 0.08);
            --success: #22c55e;
            --danger: #ef4444;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: var(--bg);
            color: var(--text);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }
        .shell {
            width: min(1100px, 95vw);
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 24px;
            align-items: stretch;
        }
        .panel {
            position: relative;
            padding: 32px;
            border-radius: 24px;
            border: 1px solid var(--border);
            background: var(--card);
            backdrop-filter: blur(12px);
            box-shadow: 0 25px 80px rgba(0,0,0,0.35);
            overflow: hidden;
        }
        .panel::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(34, 211, 238, 0.12));
            opacity: 0.65;
            pointer-events: none;
        }
        .panel > * { position: relative; z-index: 1; }
        h1 {
            margin: 0 0 12px;
            font-size: 32px;
            font-weight: 700;
            letter-spacing: -0.02em;
        }
        p.lead { margin: 0 0 24px; color: var(--muted); line-height: 1.6; }
        .badges {
            display: flex; flex-wrap: wrap; gap: 10px; margin-top: 8px;
        }
        .badge {
            padding: 8px 12px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: rgba(255,255,255,0.05);
            color: var(--muted);
            font-size: 13px;
            display: inline-flex;
            gap: 6px;
            align-items: center;
        }
        form { display: flex; flex-direction: column; gap: 16px; }
        label {
            font-size: 14px;
            color: var(--muted);
            margin-bottom: 6px;
            display: block;
        }
        .field {
            position: relative;
        }
        .field input {
            width: 100%;
            padding: 14px 14px 14px 46px;
            border-radius: 14px;
            border: 1px solid var(--border);
            background: rgba(255,255,255,0.04);
            color: var(--text);
            font-size: 15px;
            transition: border-color .2s, background .2s, box-shadow .2s;
            outline: none;
        }
        .field input:focus {
            border-color: rgba(99, 102, 241, 0.65);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
        }
        .field .icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
        }
        button {
            padding: 14px 16px;
            border: none;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: transform .15s ease, box-shadow .2s ease;
            box-shadow: 0 12px 30px rgba(79, 70, 229, 0.35);
        }
        button:hover { transform: translateY(-1px); }
        button:active { transform: translateY(0); }
        .footer {
            margin-top: 12px;
            color: var(--muted);
            font-size: 13px;
        }
        .status, .errors {
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: rgba(255,255,255,0.05);
            font-size: 14px;
            line-height: 1.5;
        }
        .status.success { border-color: rgba(34, 197, 94, 0.35); color: #bbf7d0; }
        .status.muted { color: var(--muted); }
        .errors { border-color: rgba(239, 68, 68, 0.45); color: #fecdd3; }
        ul { margin: 0; padding-left: 18px; }
        @media (max-width: 700px) {
            .shell { grid-template-columns: 1fr; }
            h1 { font-size: 28px; }
        }
    </style>
</head>
<body>
    <div class="shell">
        <section class="panel">
            <h1>Админ-панель iiko-base</h1>
            <p class="lead">
                Управляйте меню, пользователями и интеграцией iiko в единой консоли. Войдите, чтобы открыть защищенный раздел администратора.
            </p>
            <div class="badges">
                <span class="badge">🔐 Безопасный доступ</span>
                <span class="badge">⚡ Быстрый старт</span>
                <span class="badge">🛠️ Управление iiko</span>
            </div>
        </section>

        <section class="panel">
            @if (session('status'))
                <div class="status success">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="errors">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @else
                <div class="status muted">Введите учетные данные администратора, чтобы продолжить.</div>
            @endif

            <form method="POST" action="{{ route('login.submit') }}">
                @csrf
                <div>
                    <label for="username">Имя пользователя</label>
                    <div class="field">
                        <span class="icon">👤</span>
                        <input type="text" id="username" name="username" placeholder="admin" value="{{ old('username') }}" required autocomplete="username">
                    </div>
                </div>
                <div>
                    <label for="password">Пароль</label>
                    <div class="field">
                        <span class="icon">🔒</span>
                        <input type="password" id="password" name="password" placeholder="Введите пароль" required autocomplete="current-password">
                    </div>
                </div>
                <button type="submit">Войти в админку</button>
            </form>
            @env('local')
                <p class="footer">API: {{ config('app.backend_api_url') }}</p>
            @endenv
        </section>
    </div>
</body>
</html>

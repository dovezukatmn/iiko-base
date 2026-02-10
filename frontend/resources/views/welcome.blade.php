<!DOCTYPE html>
<html lang="ru">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>iiko-base Admin</title>
        <meta http-equiv="refresh" content="0; url={{ route('login') }}">
    </head>
    <body>
        <main>
            <h1>Перенаправление в админку</h1>
            <p>Открываем страницу входа. Если перенаправление не сработало, воспользуйтесь кнопкой ниже.</p>
            <p>
                <a href="{{ route('login') }}">Перейти к форме входа</a>
            </p>
        </main>
    </body>
</html>

@extends('layouts.admin')

@section('title', 'Обзор')
@section('page-title', 'Обзор')

@section('content')
<section class="grid-3" style="margin-bottom:20px;">
    <div class="card stat-card">
        <span class="stat-label">Роль</span>
        <span class="stat-value" style="font-size:20px;">{{ $user['role'] ?? 'admin' }}</span>
        <span class="badge badge-muted" style="margin-top:4px;">Доступ к настройкам</span>
    </div>
    <div class="card stat-card">
        <span class="stat-label">Интеграции</span>
        <span class="stat-value" style="font-size:20px;">iiko</span>
        <span class="badge badge-success" style="margin-top:4px;">API готово</span>
    </div>
    <div class="card stat-card">
        <span class="stat-label">Сессия</span>
        <span class="stat-value" style="font-size:20px;">Активна</span>
        <span class="badge badge-success" style="margin-top:4px;">Токен сохранен</span>
    </div>
</section>

<div class="card">
    <div class="card-header">
        <div>
            <div class="card-title">Быстрые действия</div>
            <div class="card-subtitle">Перейдите к нужному разделу</div>
        </div>
    </div>
    <div class="grid-2">
        <a href="{{ route('admin.maintenance') }}" class="collapse-trigger" style="text-decoration:none;">
            <span>🔧 Обслуживание и настройки</span>
            <span class="arrow">→</span>
        </a>
        <a href="{{ route('admin.menu') }}" class="collapse-trigger" style="text-decoration:none;">
            <span>📋 Настройка меню iiko</span>
            <span class="arrow">→</span>
        </a>
        <a href="{{ route('admin.orders') }}" class="collapse-trigger" style="text-decoration:none;">
            <span>🛒 Управление заказами</span>
            <span class="arrow">→</span>
        </a>
        <a href="{{ route('admin.users') }}" class="collapse-trigger" style="text-decoration:none;">
            <span>👥 Управление пользователями</span>
            <span class="arrow">→</span>
        </a>
    </div>
</div>
@endsection

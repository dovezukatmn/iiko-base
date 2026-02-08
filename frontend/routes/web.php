<?php

use App\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::redirect('/', '/login');
Route::get('/login', [AdminController::class, 'showLogin'])->name('login');
Route::post('/login', [AdminController::class, 'login'])->name('login.submit');
Route::middleware('admin.session')->group(function () {
    Route::get('/admin', [AdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::get('/admin/maintenance', [AdminController::class, 'maintenance'])->name('admin.maintenance');

    // AJAX API proxy routes for maintenance page
    Route::get('/admin/api/status', [AdminController::class, 'apiStatus'])->name('admin.api.status');
    Route::get('/admin/api/iiko-settings', [AdminController::class, 'apiIikoSettings'])->name('admin.api.iiko_settings');
    Route::post('/admin/api/iiko-settings', [AdminController::class, 'apiCreateIikoSettings'])->name('admin.api.iiko_settings.create');
    Route::put('/admin/api/iiko-settings/{id}', [AdminController::class, 'apiUpdateIikoSettings'])->name('admin.api.iiko_settings.update');
    Route::post('/admin/api/iiko-test', [AdminController::class, 'apiTestConnection'])->name('admin.api.iiko_test');
    Route::post('/admin/api/iiko-organizations', [AdminController::class, 'apiOrganizations'])->name('admin.api.iiko_organizations');
    Route::post('/admin/api/iiko-terminal-groups', [AdminController::class, 'apiTerminalGroups'])->name('admin.api.iiko_terminal_groups');
    Route::post('/admin/api/iiko-payment-types', [AdminController::class, 'apiPaymentTypes'])->name('admin.api.iiko_payment_types');
    Route::post('/admin/api/iiko-couriers', [AdminController::class, 'apiCouriers'])->name('admin.api.iiko_couriers');
    Route::post('/admin/api/iiko-order-types', [AdminController::class, 'apiOrderTypes'])->name('admin.api.iiko_order_types');
    Route::post('/admin/api/iiko-discount-types', [AdminController::class, 'apiDiscountTypes'])->name('admin.api.iiko_discount_types');
    Route::post('/admin/api/iiko-stop-lists', [AdminController::class, 'apiStopLists'])->name('admin.api.iiko_stop_lists');
    Route::post('/admin/api/iiko-register-webhook', [AdminController::class, 'apiRegisterWebhook'])->name('admin.api.iiko_register_webhook');
    Route::get('/admin/api/webhook-events', [AdminController::class, 'apiWebhookEvents'])->name('admin.api.webhook_events');
    Route::get('/admin/api/logs', [AdminController::class, 'apiLogs'])->name('admin.api.logs');

    Route::post('/logout', [AdminController::class, 'logout'])->name('logout');
});

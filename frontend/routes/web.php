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
    Route::get('/admin/menu', [AdminController::class, 'menuPage'])->name('admin.menu');
    Route::get('/admin/orders', [AdminController::class, 'ordersPage'])->name('admin.orders');
    Route::get('/admin/users', [AdminController::class, 'usersPage'])->name('admin.users');

    // AJAX API proxy routes for maintenance page
    Route::get('/admin/api/status', [AdminController::class, 'apiStatus'])->name('admin.api.status');
    Route::get('/admin/api/iiko-settings', [AdminController::class, 'apiIikoSettings'])->name('admin.api.iiko_settings');
    Route::post('/admin/api/iiko-settings', [AdminController::class, 'apiCreateIikoSettings'])->name('admin.api.iiko_settings.create');
    Route::put('/admin/api/iiko-settings/{id}', [AdminController::class, 'apiUpdateIikoSettings'])->name('admin.api.iiko_settings.update');
    Route::delete('/admin/api/iiko-settings/{id}', [AdminController::class, 'apiDeleteIikoSettings'])->name('admin.api.iiko_settings.delete');
    Route::post('/admin/api/iiko-test', [AdminController::class, 'apiTestConnection'])->name('admin.api.iiko_test');
    Route::post('/admin/api/iiko-organizations', [AdminController::class, 'apiOrganizations'])->name('admin.api.iiko_organizations');
    Route::post('/admin/api/iiko-organizations-by-key', [AdminController::class, 'apiOrganizationsByKey'])->name('admin.api.iiko_organizations_by_key');
    Route::post('/admin/api/iiko-terminal-groups', [AdminController::class, 'apiTerminalGroups'])->name('admin.api.iiko_terminal_groups');
    Route::post('/admin/api/iiko-payment-types', [AdminController::class, 'apiPaymentTypes'])->name('admin.api.iiko_payment_types');
    Route::post('/admin/api/iiko-couriers', [AdminController::class, 'apiCouriers'])->name('admin.api.iiko_couriers');
    Route::post('/admin/api/iiko-order-types', [AdminController::class, 'apiOrderTypes'])->name('admin.api.iiko_order_types');
    Route::post('/admin/api/iiko-discount-types', [AdminController::class, 'apiDiscountTypes'])->name('admin.api.iiko_discount_types');
    Route::post('/admin/api/iiko-stop-lists', [AdminController::class, 'apiStopLists'])->name('admin.api.iiko_stop_lists');
    Route::post('/admin/api/iiko-cancel-causes', [AdminController::class, 'apiCancelCauses'])->name('admin.api.iiko_cancel_causes');
    Route::post('/admin/api/iiko-removal-types', [AdminController::class, 'apiRemovalTypes'])->name('admin.api.iiko_removal_types');
    Route::post('/admin/api/iiko-tips-types', [AdminController::class, 'apiTipsTypes'])->name('admin.api.iiko_tips_types');
    Route::post('/admin/api/iiko-delivery-restrictions', [AdminController::class, 'apiDeliveryRestrictions'])->name('admin.api.iiko_delivery_restrictions');
    Route::post('/admin/api/iiko-register-webhook', [AdminController::class, 'apiRegisterWebhook'])->name('admin.api.iiko_register_webhook');
    Route::post('/admin/api/iiko-webhook-settings', [AdminController::class, 'apiWebhookSettings'])->name('admin.api.iiko_webhook_settings');
    Route::get('/admin/api/webhook-events', [AdminController::class, 'apiWebhookEvents'])->name('admin.api.webhook_events');
    Route::get('/admin/api/logs', [AdminController::class, 'apiLogs'])->name('admin.api.logs');

    // Menu, Orders, Users API proxy routes
    Route::get('/admin/api/menu', [AdminController::class, 'apiMenu'])->name('admin.api.menu');
    Route::post('/admin/api/iiko-menu', [AdminController::class, 'apiIikoMenu'])->name('admin.api.iiko_menu');
    Route::post('/admin/api/iiko-sync-menu', [AdminController::class, 'apiSyncMenu'])->name('admin.api.iiko_sync_menu');
    Route::get('/admin/api/orders', [AdminController::class, 'apiOrders'])->name('admin.api.orders');
    Route::post('/admin/api/iiko-deliveries', [AdminController::class, 'apiIikoDeliveries'])->name('admin.api.iiko_deliveries');
    Route::get('/admin/api/users', [AdminController::class, 'apiUsers'])->name('admin.api.users');
    Route::post('/admin/api/users', [AdminController::class, 'apiCreateUser'])->name('admin.api.users.create');
    Route::put('/admin/api/users/{userId}/role', [AdminController::class, 'apiUpdateUserRole'])->name('admin.api.users.update_role');
    Route::delete('/admin/api/users/{userId}', [AdminController::class, 'apiDeleteUser'])->name('admin.api.users.delete');
    Route::put('/admin/api/users/{userId}/toggle-active', [AdminController::class, 'apiToggleUserActive'])->name('admin.api.users.toggle_active');

    // Loyalty / iikoCard API proxy routes
    Route::post('/admin/api/iiko-loyalty-programs', [AdminController::class, 'apiLoyaltyPrograms'])->name('admin.api.iiko_loyalty_programs');
    Route::post('/admin/api/iiko-loyalty-customer-info', [AdminController::class, 'apiLoyaltyCustomerInfo'])->name('admin.api.iiko_loyalty_customer_info');
    Route::post('/admin/api/iiko-loyalty-customer', [AdminController::class, 'apiLoyaltyCreateCustomer'])->name('admin.api.iiko_loyalty_customer');
    Route::post('/admin/api/iiko-loyalty-balance', [AdminController::class, 'apiLoyaltyBalance'])->name('admin.api.iiko_loyalty_balance');
    Route::post('/admin/api/iiko-loyalty-topup', [AdminController::class, 'apiLoyaltyTopup'])->name('admin.api.iiko_loyalty_topup');
    Route::post('/admin/api/iiko-loyalty-withdraw', [AdminController::class, 'apiLoyaltyWithdraw'])->name('admin.api.iiko_loyalty_withdraw');
    Route::post('/admin/api/iiko-loyalty-hold', [AdminController::class, 'apiLoyaltyHold'])->name('admin.api.iiko_loyalty_hold');
    Route::get('/admin/api/iiko-loyalty-transactions', [AdminController::class, 'apiLoyaltyTransactions'])->name('admin.api.iiko_loyalty_transactions');

    // Synchronization API proxy routes
    Route::post('/admin/api/sync/full', [AdminController::class, 'apiSyncFull'])->name('admin.api.sync.full');
    Route::post('/admin/api/sync/menu', [AdminController::class, 'apiSyncMenuEndpoint'])->name('admin.api.sync.menu');
    Route::post('/admin/api/sync/stoplist', [AdminController::class, 'apiSyncStoplist'])->name('admin.api.sync.stoplist');
    Route::post('/admin/api/sync/terminals', [AdminController::class, 'apiSyncTerminals'])->name('admin.api.sync.terminals');
    Route::post('/admin/api/sync/payments', [AdminController::class, 'apiSyncPayments'])->name('admin.api.sync.payments');
    Route::get('/admin/api/sync/history', [AdminController::class, 'apiSyncHistory'])->name('admin.api.sync.history');

    // Webhook Management API proxy routes
    Route::post('/admin/api/webhooks/register', [AdminController::class, 'apiWebhookRegister'])->name('admin.api.webhooks.register');
    Route::get('/admin/api/webhooks/settings', [AdminController::class, 'apiWebhookSettingsGet'])->name('admin.api.webhooks.settings');
    Route::post('/admin/api/webhooks/test', [AdminController::class, 'apiWebhookTest'])->name('admin.api.webhooks.test');

    // Data Retrieval API proxy routes
    Route::get('/admin/api/data/categories', [AdminController::class, 'apiDataCategories'])->name('admin.api.data.categories');
    Route::get('/admin/api/data/products', [AdminController::class, 'apiDataProducts'])->name('admin.api.data.products');
    Route::get('/admin/api/data/stop-lists', [AdminController::class, 'apiDataStopLists'])->name('admin.api.data.stop_lists');

    Route::post('/logout', [AdminController::class, 'logout'])->name('logout');
});

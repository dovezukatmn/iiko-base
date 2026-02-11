<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureAdminSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AdminController extends Controller
{
    public const COOKIE_TOKEN = 'admin_token';
    public const COOKIE_USERNAME = 'admin_username';
    private const DEFAULT_COOKIE_LIFETIME_MINUTES = 120;
    private string $apiBase;

    public function __construct()
    {
        $this->apiBase = rtrim(config('app.backend_api_url', 'http://localhost:8000/api/v1'), '/');
    }

    public function showLogin(Request $request): View|RedirectResponse
    {
        if ($request->session()->has('token')) {
            return redirect()->route('admin.dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        try {
            $response = Http::timeout(10)->post("{$this->apiBase}/auth/login", $validated);
        } catch (\Throwable $e) {
            return back()
                ->withErrors(['connection' => 'Не удалось связаться с API. Попробуйте позже.'])
                ->withInput();
        }

        if ($response->failed()) {
            $message = $response->json('detail') ?? 'Неверные учетные данные';
            return back()->withErrors(['auth' => $message])->withInput();
        }

        $token = $response->json('access_token');
        if (!self::isValidJwt($token)) {
            return back()->withErrors(['auth' => 'Получен некорректный токен от сервера'])->withInput();
        }

        $request->session()->regenerate();
        $request->session()->put('token', $token);
        $request->session()->put('username', $validated['username']);

        $this->queueAuthCookie($request, self::COOKIE_TOKEN, $token);
        $this->queueAuthCookie($request, self::COOKIE_USERNAME, $validated['username']);

        try {
            $meResponse = Http::withToken($token)
                ->timeout(EnsureAdminSession::TOKEN_CHECK_TIMEOUT)
                ->get("{$this->apiBase}/auth/me");
            if ($meResponse->ok()) {
                $request->session()->put('user', $meResponse->json());
            }
        } catch (\Throwable $e) {
            Log::warning('Не удалось получить профиль пользователя после входа', ['error' => $e->getMessage()]);
        }

        return redirect()->route('admin.dashboard');
    }

    public function dashboard(Request $request): View|RedirectResponse
    {
        return view('admin.dashboard', [
            'user' => $request->session()->get('user'),
            'username' => $request->session()->get('username'),
            'displayName' => $request->session()->get('user.username')
                ?? $request->session()->get('username')
                ?? 'администратор',
        ]);
    }

    public function maintenance(Request $request): View
    {
        return view('admin.maintenance', [
            'user' => $request->session()->get('user'),
            'username' => $request->session()->get('username'),
            'displayName' => $request->session()->get('user.username')
                ?? $request->session()->get('username')
                ?? 'администратор',
        ]);
    }

    public function menuPage(Request $request): View
    {
        return view('admin.menu', [
            'user' => $request->session()->get('user'),
            'username' => $request->session()->get('username'),
            'displayName' => $request->session()->get('user.username')
                ?? $request->session()->get('username')
                ?? 'администратор',
        ]);
    }

    public function ordersPage(Request $request): View
    {
        return view('admin.orders', [
            'user' => $request->session()->get('user'),
            'username' => $request->session()->get('username'),
            'displayName' => $request->session()->get('user.username')
                ?? $request->session()->get('username')
                ?? 'администратор',
        ]);
    }

    public function usersPage(Request $request): View
    {
        return view('admin.users', [
            'user' => $request->session()->get('user'),
            'username' => $request->session()->get('username'),
            'displayName' => $request->session()->get('user.username')
                ?? $request->session()->get('username')
                ?? 'администратор',
        ]);
    }

    public function webhooksPage(Request $request): View
    {
        return view('admin.webhooks', [
            'user' => $request->session()->get('user'),
            'username' => $request->session()->get('username'),
            'displayName' => $request->session()->get('user.username')
                ?? $request->session()->get('username')
                ?? 'администратор',
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget(['token', 'user', 'username']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        cookie()->queue(cookie()->forget(self::COOKIE_TOKEN));
        cookie()->queue(cookie()->forget(self::COOKIE_USERNAME));

        return redirect()->route('login')->with('status', 'Вы успешно вышли из системы');
    }

    // ─── API Proxy Methods ──────────────────────────────────────────────

    public function apiStatus(Request $request): JsonResponse
    {
        return $this->proxyGet($request, '/status');
    }

    public function apiIikoSettings(Request $request): JsonResponse
    {
        return $this->proxyGet($request, '/iiko/settings');
    }

    public function apiCreateIikoSettings(Request $request): JsonResponse
    {
        return $this->proxyPost($request, '/iiko/settings', $request->all());
    }

    public function apiUpdateIikoSettings(Request $request, int $id): JsonResponse
    {
        return $this->proxyPut($request, "/iiko/settings/{$id}", $request->all());
    }

    public function apiDeleteIikoSettings(Request $request, int $id): JsonResponse
    {
        return $this->proxyDelete($request, "/iiko/settings/{$id}");
    }

    public function apiTestConnection(Request $request): JsonResponse
    {
        $settingId = $request->input('setting_id');
        return $this->proxyPost($request, "/iiko/test-connection?setting_id={$settingId}");
    }

    public function apiOrganizations(Request $request): JsonResponse
    {
        $settingId = $request->input('setting_id');
        return $this->proxyPost($request, "/iiko/organizations?setting_id={$settingId}");
    }

    public function apiOrganizationsByKey(Request $request): JsonResponse
    {
        return $this->proxyPost($request, '/iiko/organizations-by-key', $request->only(['api_key', 'api_url']));
    }

    public function apiTerminalGroups(Request $request): JsonResponse
    {
        $settingId = $request->input('setting_id');
        $orgId = $request->input('organization_id');
        return $this->proxyPost($request, "/iiko/terminal-groups?setting_id={$settingId}&organization_id={$orgId}");
    }

    public function apiPaymentTypes(Request $request): JsonResponse
    {
        $settingId = $request->input('setting_id');
        $orgId = $request->input('organization_id');
        return $this->proxyPost($request, "/iiko/payment-types?setting_id={$settingId}&organization_id={$orgId}");
    }

    public function apiCouriers(Request $request): JsonResponse
    {
        $settingId = $request->input('setting_id');
        $orgId = $request->input('organization_id');
        return $this->proxyPost($request, "/iiko/couriers?setting_id={$settingId}&organization_id={$orgId}");
    }

    public function apiOrderTypes(Request $request): JsonResponse
    {
        $settingId = $request->input('setting_id');
        $orgId = $request->input('organization_id');
        return $this->proxyPost($request, "/iiko/order-types?setting_id={$settingId}&organization_id={$orgId}");
    }

    public function apiDiscountTypes(Request $request): JsonResponse
    {
        $settingId = $request->input('setting_id');
        $orgId = $request->input('organization_id');
        return $this->proxyPost($request, "/iiko/discount-types?setting_id={$settingId}&organization_id={$orgId}");
    }

    public function apiStopLists(Request $request): JsonResponse
    {
        $settingId = $request->input('setting_id');
        $orgId = $request->input('organization_id');
        return $this->proxyPost($request, "/iiko/stop-lists?setting_id={$settingId}&organization_id={$orgId}");
    }

    public function apiCancelCauses(Request $request): JsonResponse
    {
        $settingId = $request->input('setting_id');
        $orgId = $request->input('organization_id');
        return $this->proxyPost($request, "/iiko/cancel-causes?setting_id={$settingId}&organization_id={$orgId}");
    }

    public function apiRemovalTypes(Request $request): JsonResponse
    {
        $settingId = $request->input('setting_id');
        $orgId = $request->input('organization_id');
        return $this->proxyPost($request, "/iiko/removal-types?setting_id={$settingId}&organization_id={$orgId}");
    }

    public function apiTipsTypes(Request $request): JsonResponse
    {
        $settingId = $request->input('setting_id');
        $orgId = $request->input('organization_id');
        return $this->proxyPost($request, "/iiko/tips-types?setting_id={$settingId}&organization_id={$orgId}");
    }

    public function apiDeliveryRestrictions(Request $request): JsonResponse
    {
        $settingId = $request->input('setting_id');
        $orgId = $request->input('organization_id');
        return $this->proxyPost($request, "/iiko/delivery-restrictions?setting_id={$settingId}&organization_id={$orgId}");
    }

    public function apiRegisterWebhook(Request $request): JsonResponse
    {
        $settingId = $request->input('setting_id');
        $domain = $request->input('domain');
        return $this->proxyPost($request, "/iiko/register-webhook?setting_id={$settingId}&domain=" . urlencode($domain));
    }

    public function apiWebhookSettings(Request $request): JsonResponse
    {
        $settingId = $request->input('setting_id');
        $orgId = $request->input('organization_id');
        return $this->proxyPost($request, "/iiko/webhook-settings?setting_id={$settingId}&organization_id={$orgId}");
    }

    public function apiWebhookEvents(Request $request): JsonResponse
    {
        $query = http_build_query($request->only(['limit', 'skip', 'event_type', 'processed', 'search']));
        $path = '/webhooks/events' . ($query ? "?{$query}" : '');
        return $this->proxyGet($request, $path);
    }

    public function apiLogs(Request $request): JsonResponse
    {
        return $this->proxyGet($request, '/logs');
    }

    public function apiMenu(Request $request): JsonResponse
    {
        return $this->proxyGet($request, '/menu?' . http_build_query($request->only(['skip', 'limit'])));
    }

    public function apiIikoMenu(Request $request): JsonResponse
    {
        $settingId = $request->input('setting_id');
        $orgId = $request->input('organization_id');
        return $this->proxyPost($request, "/iiko/menu?setting_id={$settingId}&organization_id={$orgId}");
    }

    public function apiSyncMenu(Request $request): JsonResponse
    {
        $settingId = $request->input('setting_id');
        $orgId = $request->input('organization_id');
        return $this->proxyPost($request, "/iiko/sync-menu?setting_id={$settingId}&organization_id={$orgId}");
    }

    public function apiOrders(Request $request): JsonResponse
    {
        $query = http_build_query($request->only(['status_filter', 'skip', 'limit']));
        return $this->proxyGet($request, '/orders?' . $query);
    }

    public function apiIikoDeliveries(Request $request): JsonResponse
    {
        $settingId = $request->input('setting_id');
        $orgId = $request->input('organization_id');
        $statuses = $request->input('statuses', '');
        $days = $request->input('days', 1);
        $path = "/iiko/deliveries?setting_id={$settingId}&organization_id={$orgId}&days={$days}";
        if ($statuses) {
            $path .= "&statuses=" . urlencode($statuses);
        }
        return $this->proxyPost($request, $path);
    }

    public function apiUsers(Request $request): JsonResponse
    {
        return $this->proxyGet($request, '/users');
    }

    public function apiUpdateUserRole(Request $request, int $userId): JsonResponse
    {
        return $this->proxyPut($request, "/users/{$userId}/role", $request->only(['role']));
    }

    public function apiCreateUser(Request $request): JsonResponse
    {
        return $this->proxyPost($request, '/users', $request->only(['email', 'username', 'password', 'role', 'is_active']));
    }

    public function apiDeleteUser(Request $request, int $userId): JsonResponse
    {
        $token = $request->session()->get('token');
        try {
            $response = Http::withToken($token)->timeout(15)->delete("{$this->apiBase}/users/{$userId}");
            return response()->json($response->json(), $response->status());
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Ошибка подключения к API: ' . $e->getMessage()], 502);
        }
    }

    public function apiToggleUserActive(Request $request, int $userId): JsonResponse
    {
        return $this->proxyPut($request, "/users/{$userId}/toggle-active");
    }

    // ─── Loyalty / iikoCard API Proxy Methods ───────────────────────────

    public function apiLoyaltyPrograms(Request $request): JsonResponse
    {
        $settingId = $request->input('setting_id');
        $orgId = $request->input('organization_id');
        return $this->proxyPost($request, "/iiko/loyalty/programs?setting_id={$settingId}&organization_id={$orgId}");
    }

    public function apiLoyaltyCustomerInfo(Request $request): JsonResponse
    {
        $settingId = $request->input('setting_id');
        return $this->proxyPost($request, "/iiko/loyalty/customer-info?setting_id={$settingId}", $request->only([
            'organization_id', 'customer_id', 'phone', 'card_track', 'card_number', 'email',
        ]));
    }

    public function apiLoyaltyCreateCustomer(Request $request): JsonResponse
    {
        $settingId = $request->input('setting_id');
        return $this->proxyPost($request, "/iiko/loyalty/customer?setting_id={$settingId}", $request->only([
            'organization_id', 'name', 'phone', 'email', 'card_track', 'card_number', 'birthday',
        ]));
    }

    public function apiLoyaltyBalance(Request $request): JsonResponse
    {
        $settingId = $request->input('setting_id');
        $orgId = $request->input('organization_id');
        $customerId = $request->input('customer_id');
        return $this->proxyPost($request, "/iiko/loyalty/balance?setting_id={$settingId}&organization_id={$orgId}&customer_id={$customerId}");
    }

    public function apiLoyaltyTopup(Request $request): JsonResponse
    {
        $settingId = $request->input('setting_id');
        return $this->proxyPost($request, "/iiko/loyalty/topup?setting_id={$settingId}", $request->only([
            'organization_id', 'customer_id', 'wallet_id', 'amount', 'comment',
        ]));
    }

    public function apiLoyaltyWithdraw(Request $request): JsonResponse
    {
        $settingId = $request->input('setting_id');
        return $this->proxyPost($request, "/iiko/loyalty/withdraw?setting_id={$settingId}", $request->only([
            'organization_id', 'customer_id', 'wallet_id', 'amount', 'comment',
        ]));
    }

    public function apiLoyaltyHold(Request $request): JsonResponse
    {
        $settingId = $request->input('setting_id');
        return $this->proxyPost($request, "/iiko/loyalty/hold?setting_id={$settingId}", $request->only([
            'organization_id', 'customer_id', 'wallet_id', 'amount', 'comment',
        ]));
    }

    public function apiLoyaltyTransactions(Request $request): JsonResponse
    {
        $settingId = $request->input('setting_id');
        $orgId = $request->input('organization_id');
        $customerId = $request->input('customer_id', '');
        $limit = $request->input('limit', 50);
        $path = "/iiko/loyalty/transactions?setting_id={$settingId}&organization_id={$orgId}&limit={$limit}";
        if ($customerId) {
            $path .= "&customer_id={$customerId}";
        }
        return $this->proxyGet($request, $path);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    private function proxyGet(Request $request, string $path): JsonResponse
    {
        $token = $request->session()->get('token');
        try {
            $response = Http::withToken($token)->timeout(15)->get("{$this->apiBase}{$path}");
            if ($response->status() === 401) {
                $detail = $response->json('detail') ?? '';
                if (str_contains($detail, 'Сессия') || str_contains($detail, 'токен')) {
                    return response()->json(['error' => $detail, 'session_expired' => true], 401);
                }
            }
            return response()->json($response->json(), $response->status());
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Ошибка подключения к API: ' . $e->getMessage()], 502);
        }
    }

    private function proxyPost(Request $request, string $path, array $body = []): JsonResponse
    {
        $token = $request->session()->get('token');
        try {
            $response = Http::withToken($token)->timeout(15)->post("{$this->apiBase}{$path}", $body);
            if ($response->status() === 401) {
                $detail = $response->json('detail') ?? '';
                if (str_contains($detail, 'Сессия') || str_contains($detail, 'токен')) {
                    return response()->json(['error' => $detail, 'session_expired' => true], 401);
                }
            }
            return response()->json($response->json(), $response->status());
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Ошибка подключения к API: ' . $e->getMessage()], 502);
        }
    }

    private function proxyPut(Request $request, string $path, array $body = []): JsonResponse
    {
        $token = $request->session()->get('token');
        try {
            $response = Http::withToken($token)->timeout(15)->put("{$this->apiBase}{$path}", $body);
            if ($response->status() === 401) {
                $detail = $response->json('detail') ?? '';
                if (str_contains($detail, 'Сессия') || str_contains($detail, 'токен')) {
                    return response()->json(['error' => $detail, 'session_expired' => true], 401);
                }
            }
            return response()->json($response->json(), $response->status());
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Ошибка подключения к API: ' . $e->getMessage()], 502);
        }
    }

    private function proxyDelete(Request $request, string $path): JsonResponse
    {
        $token = $request->session()->get('token');
        try {
            $response = Http::withToken($token)->timeout(15)->delete("{$this->apiBase}{$path}");
            if ($response->status() === 401) {
                $detail = $response->json('detail') ?? '';
                if (str_contains($detail, 'Сессия') || str_contains($detail, 'токен')) {
                    return response()->json(['error' => $detail, 'session_expired' => true], 401);
                }
            }
            return response()->json($response->json(), $response->status());
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Ошибка подключения к API: ' . $e->getMessage()], 502);
        }
    }

    // ─── Synchronization Endpoints ──────────────────────────────────────────────
    
    public function apiSyncFull(Request $request): JsonResponse
    {
        $settingId = $request->input('setting_id');
        return $this->proxyPost($request, "/sync/full?setting_id={$settingId}");
    }

    public function apiSyncMenuEndpoint(Request $request): JsonResponse
    {
        $settingId = $request->input('setting_id');
        return $this->proxyPost($request, "/sync/menu?setting_id={$settingId}");
    }

    public function apiSyncStoplist(Request $request): JsonResponse
    {
        $settingId = $request->input('setting_id');
        return $this->proxyPost($request, "/sync/stoplist?setting_id={$settingId}");
    }

    public function apiSyncTerminals(Request $request): JsonResponse
    {
        $settingId = $request->input('setting_id');
        return $this->proxyPost($request, "/sync/terminals?setting_id={$settingId}");
    }

    public function apiSyncPayments(Request $request): JsonResponse
    {
        $settingId = $request->input('setting_id');
        return $this->proxyPost($request, "/sync/payments?setting_id={$settingId}");
    }

    public function apiSyncHistory(Request $request): JsonResponse
    {
        $orgId = $request->input('organization_id', '');
        $limit = $request->input('limit', 50);
        $path = "/sync/history?limit={$limit}";
        if ($orgId) {
            $path .= "&organization_id={$orgId}";
        }
        return $this->proxyGet($request, $path);
    }

    // ─── Webhook Management Endpoints ────────────────────────────────────────────
    
    public function apiWebhookRegister(Request $request): JsonResponse
    {
        $settingId = $request->input('setting_id');
        $webhookUrl = $request->input('webhook_url');
        $authToken = $request->input('auth_token');
        $path = "/webhooks/register?setting_id={$settingId}&webhook_url=" . urlencode($webhookUrl);
        if ($authToken) {
            $path .= "&auth_token=" . urlencode($authToken);
        }
        return $this->proxyPost($request, $path);
    }

    public function apiWebhookSettingsGet(Request $request): JsonResponse
    {
        $settingId = $request->input('setting_id');
        return $this->proxyGet($request, "/webhooks/settings?setting_id={$settingId}");
    }

    public function apiWebhookTest(Request $request): JsonResponse
    {
        $settingId = $request->input('setting_id');
        return $this->proxyPost($request, "/webhooks/test?setting_id={$settingId}");
    }

    // ─── Data Retrieval Endpoints ────────────────────────────────────────────────
    
    public function apiDataCategories(Request $request): JsonResponse
    {
        $orgId = $request->input('organization_id', '');
        $path = "/data/categories";
        if ($orgId) {
            $path .= "?organization_id={$orgId}";
        }
        return $this->proxyGet($request, $path);
    }

    public function apiDataProducts(Request $request): JsonResponse
    {
        $categoryId = $request->input('category_id', '');
        $isAvailable = $request->input('is_available', '');
        $limit = $request->input('limit', 100);
        $offset = $request->input('offset', 0);
        $path = "/data/products?limit={$limit}&offset={$offset}";
        if ($categoryId) {
            $path .= "&category_id={$categoryId}";
        }
        if ($isAvailable !== '') {
            $path .= "&is_available=" . ($isAvailable ? 'true' : 'false');
        }
        return $this->proxyGet($request, $path);
    }

    public function apiDataStopLists(Request $request): JsonResponse
    {
        $orgId = $request->input('organization_id', '');
        if (!$orgId) {
            return response()->json(['error' => 'organization_id is required'], 400);
        }
        return $this->proxyGet($request, "/data/stop-lists?organization_id={$orgId}");
    }

    public static function isValidJwt(?string $token): bool
    {
        return is_string($token) && substr_count($token, '.') === 2;
    }

    private function queueAuthCookie(Request $request, string $name, string $value): void
    {
        $configuredLifetime = config('session.lifetime');
        $cookieMinutes = is_numeric($configuredLifetime)
            ? (int) $configuredLifetime
            : self::DEFAULT_COOKIE_LIFETIME_MINUTES;
        if ($cookieMinutes <= 0) {
            Log::warning('Некорректное значение session.lifetime, используется значение по умолчанию', [
                'configured' => $configuredLifetime,
            ]);
            $cookieMinutes = self::DEFAULT_COOKIE_LIFETIME_MINUTES;
        }
        $cookieDomain = config('session.domain');
        $cookieSecure = config('session.secure');
        if (app()->environment('production')) {
            if ($cookieSecure === false) {
                Log::warning('SESSION_SECURE_COOKIE отключен, но в production устанавливается secure cookie для токена.');
            }
            $cookieSecure = true;
        } elseif ($cookieSecure === null) {
            $cookieSecure = $request->isSecure();
        }
        $cookieSameSite = config('session.same_site', 'lax');

        cookie()->queue(cookie(
            name: $name,
            value: Crypt::encryptString($value),
            minutes: $cookieMinutes,
            path: '/',
            domain: $cookieDomain,
            secure: $cookieSecure,
            httpOnly: true,
            sameSite: $cookieSameSite
        ));
    }

    // ─── Order Management API Methods ──────────────────────────────────────
    
    public function apiOrderDetails(Request $request, int $id): JsonResponse
    {
        $token = $request->session()->get('token');
        if (!$token) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        try {
            $response = Http::withToken($token)
                ->timeout(30)
                ->get("{$this->apiBase}/orders/{$id}");
            
            if ($response->successful()) {
                return response()->json($response->json());
            }
            
            return response()->json([
                'error' => $response->json('detail') ?? 'Failed to fetch order details'
            ], $response->status());
        } catch (\Throwable $e) {
            Log::error('Order details fetch failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function apiOrderUpdateStatus(Request $request, int $id): JsonResponse
    {
        $token = $request->session()->get('token');
        if (!$token) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        $validated = $request->validate([
            'status' => 'required|string',
        ]);

        try {
            $response = Http::withToken($token)
                ->timeout(30)
                ->post("{$this->apiBase}/orders/{$id}/update-status", [
                    'status' => $validated['status']
                ]);
            
            if ($response->successful()) {
                return response()->json($response->json());
            }
            
            return response()->json([
                'error' => $response->json('detail') ?? 'Failed to update order status'
            ], $response->status());
        } catch (\Throwable $e) {
            Log::error('Order status update failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function apiOrderAssignCourier(Request $request, int $id): JsonResponse
    {
        $token = $request->session()->get('token');
        if (!$token) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        $validated = $request->validate([
            'courier_id' => 'required|string',
            'courier_name' => 'nullable|string',
        ]);

        try {
            $response = Http::withToken($token)
                ->timeout(30)
                ->post("{$this->apiBase}/orders/{$id}/assign-courier", $validated);
            
            if ($response->successful()) {
                return response()->json($response->json());
            }
            
            return response()->json([
                'error' => $response->json('detail') ?? 'Failed to assign courier'
            ], $response->status());
        } catch (\Throwable $e) {
            Log::error('Courier assignment failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function apiOrderCancel(Request $request, int $id): JsonResponse
    {
        $token = $request->session()->get('token');
        if (!$token) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        $validated = $request->validate([
            'cancel_reason' => 'nullable|string',
        ]);

        try {
            $response = Http::withToken($token)
                ->timeout(30)
                ->post("{$this->apiBase}/orders/{$id}/cancel", [
                    'cancel_reason' => $validated['cancel_reason'] ?? 'Cancelled from admin panel'
                ]);
            
            if ($response->successful()) {
                return response()->json($response->json());
            }
            
            return response()->json([
                'error' => $response->json('detail') ?? 'Failed to cancel order'
            ], $response->status());
        } catch (\Throwable $e) {
            Log::error('Order cancellation failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ─── Outgoing Webhooks API Methods ────────────────────────────────────

    public function apiOutgoingWebhooks(Request $request): JsonResponse
    {
        $token = $request->session()->get('token');
        if (!$token) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        try {
            $response = Http::withToken($token)
                ->timeout(30)
                ->get("{$this->apiBase}/outgoing-webhooks");
            
            if ($response->successful()) {
                return response()->json($response->json());
            }
            
            return response()->json([
                'error' => $response->json('detail') ?? 'Failed to fetch webhooks'
            ], $response->status());
        } catch (\Throwable $e) {
            Log::error('Outgoing webhooks fetch failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function apiOutgoingWebhookDetails(Request $request, int $id): JsonResponse
    {
        $token = $request->session()->get('token');
        if (!$token) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        try {
            $response = Http::withToken($token)
                ->timeout(30)
                ->get("{$this->apiBase}/outgoing-webhooks/{$id}");
            
            if ($response->successful()) {
                return response()->json($response->json());
            }
            
            return response()->json([
                'error' => $response->json('detail') ?? 'Failed to fetch webhook details'
            ], $response->status());
        } catch (\Throwable $e) {
            Log::error('Outgoing webhook details fetch failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function apiCreateOutgoingWebhook(Request $request): JsonResponse
    {
        $token = $request->session()->get('token');
        if (!$token) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        try {
            $response = Http::withToken($token)
                ->timeout(30)
                ->post("{$this->apiBase}/outgoing-webhooks", $request->all());
            
            if ($response->successful()) {
                return response()->json($response->json());
            }
            
            return response()->json([
                'error' => $response->json('detail') ?? 'Failed to create webhook'
            ], $response->status());
        } catch (\Throwable $e) {
            Log::error('Outgoing webhook creation failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function apiUpdateOutgoingWebhook(Request $request, int $id): JsonResponse
    {
        $token = $request->session()->get('token');
        if (!$token) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        try {
            $response = Http::withToken($token)
                ->timeout(30)
                ->put("{$this->apiBase}/outgoing-webhooks/{$id}", $request->all());
            
            if ($response->successful()) {
                return response()->json($response->json());
            }
            
            return response()->json([
                'error' => $response->json('detail') ?? 'Failed to update webhook'
            ], $response->status());
        } catch (\Throwable $e) {
            Log::error('Outgoing webhook update failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function apiDeleteOutgoingWebhook(Request $request, int $id): JsonResponse
    {
        $token = $request->session()->get('token');
        if (!$token) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        try {
            $response = Http::withToken($token)
                ->timeout(30)
                ->delete("{$this->apiBase}/outgoing-webhooks/{$id}");
            
            if ($response->successful()) {
                return response()->json($response->json());
            }
            
            return response()->json([
                'error' => $response->json('detail') ?? 'Failed to delete webhook'
            ], $response->status());
        } catch (\Throwable $e) {
            Log::error('Outgoing webhook deletion failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function apiTestOutgoingWebhook(Request $request, int $id): JsonResponse
    {
        $token = $request->session()->get('token');
        if (!$token) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        try {
            $response = Http::withToken($token)
                ->timeout(60)
                ->post("{$this->apiBase}/outgoing-webhooks/{$id}/test", []);
            
            if ($response->successful()) {
                return response()->json($response->json());
            }
            
            return response()->json([
                'error' => $response->json('detail') ?? 'Failed to test webhook'
            ], $response->status());
        } catch (\Throwable $e) {
            Log::error('Outgoing webhook test failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function apiOutgoingWebhookLogs(Request $request): JsonResponse
    {
        $token = $request->session()->get('token');
        if (!$token) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        try {
            $queryParams = $request->only(['limit', 'success', 'webhook_id', 'order_id']);
            $queryString = http_build_query($queryParams);
            
            $response = Http::withToken($token)
                ->timeout(30)
                ->get("{$this->apiBase}/outgoing-webhook-logs?" . $queryString);
            
            if ($response->successful()) {
                return response()->json($response->json());
            }
            
            return response()->json([
                'error' => $response->json('detail') ?? 'Failed to fetch webhook logs'
            ], $response->status());
        } catch (\Throwable $e) {
            Log::error('Outgoing webhook logs fetch failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}


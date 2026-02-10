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
        return $this->proxyGet($request, '/webhooks/events');
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
        return $this->proxyPost($request, "/iiko/deliveries?setting_id={$settingId}&organization_id={$orgId}&statuses=" . urlencode($statuses));
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
}

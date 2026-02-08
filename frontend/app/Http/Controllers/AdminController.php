<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AdminController extends Controller
{
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
        if (!$this->isValidJwt($token)) {
            return back()->withErrors(['auth' => 'Получен некорректный токен от сервера'])->withInput();
        }

        $request->session()->put('token', $token);
        $request->session()->put('username', $validated['username']);

        try {
            $meResponse = Http::withToken($token)->timeout(10)->get("{$this->apiBase}/auth/me");
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

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget(['token', 'user', 'username']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

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
        $webhookUrl = $request->input('webhook_url');
        return $this->proxyPost($request, "/iiko/register-webhook?setting_id={$settingId}&webhook_url=" . urlencode($webhookUrl));
    }

    public function apiWebhookEvents(Request $request): JsonResponse
    {
        return $this->proxyGet($request, '/webhooks/events');
    }

    public function apiLogs(Request $request): JsonResponse
    {
        return $this->proxyGet($request, '/logs');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    private function proxyGet(Request $request, string $path): JsonResponse
    {
        $token = $request->session()->get('token');
        try {
            $response = Http::withToken($token)->timeout(15)->get("{$this->apiBase}{$path}");
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
            return response()->json($response->json(), $response->status());
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Ошибка подключения к API: ' . $e->getMessage()], 502);
        }
    }

    private function isValidJwt(?string $token): bool
    {
        return is_string($token) && substr_count($token, '.') === 2;
    }
}

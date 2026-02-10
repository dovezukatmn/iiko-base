<?php

namespace App\Http\Middleware;

use Closure;
use App\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EnsureAdminSession
{
    public const TOKEN_CHECK_TIMEOUT = 10;
    private string $apiBase;

    public function __construct()
    {
        $this->apiBase = rtrim(config('app.backend_api_url', 'http://localhost:8000/api/v1'), '/');
    }

    public function handle(Request $request, Closure $next)
    {
        if (!$request->session()->has('token')) {
            $token = $this->decryptCookie($request, AdminController::COOKIE_TOKEN);
            $username = $this->decryptCookie($request, AdminController::COOKIE_USERNAME);

            if ($token && $username && AdminController::isValidJwt($token)) {
                if ($this->validateToken($token)) {
                    $request->session()->regenerate();
                    $request->session()->put('token', $token);
                    $request->session()->put('username', $username);
                } else {
                    return $this->redirectToLoginWithClearedCookies();
                }
            } elseif ($token || $username) {
                // Partially restored cookies are treated as invalid
                return $this->redirectToLoginWithClearedCookies();
            }
        }

        if (!$request->session()->has('token')) {
            return $this->redirectToLoginWithClearedCookies();
        }

        return $next($request);
    }

    private function validateToken(string $token): bool
    {
        try {
            $response = Http::withToken($token)
                ->timeout(self::TOKEN_CHECK_TIMEOUT)
                ->get("{$this->apiBase}/auth/me");
            return $response->ok();
        } catch (\Throwable $e) {
            Log::warning('Не удалось проверить токен из cookie', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function clearAuthCookies(): array
    {
        return [
            cookie()->forget(AdminController::COOKIE_TOKEN),
            cookie()->forget(AdminController::COOKIE_USERNAME),
        ];
    }

    private function decryptCookie(Request $request, string $name): ?string
    {
        $value = $request->cookie($name);
        if ($value === null) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable $e) {
            Log::warning('Не удалось расшифровать cookie админа', ['cookie' => $name, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function redirectToLoginWithClearedCookies(): RedirectResponse
    {
        $cookies = $this->clearAuthCookies();
        $response = redirect()->route('login')->with('status', 'Пожалуйста, авторизуйтесь.');
        return $response->withCookies($cookies);
    }
}

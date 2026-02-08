<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class AdminController extends Controller
{
    private string $apiBase;

    public function __construct()
    {
        $this->apiBase = rtrim(env('BACKEND_API_URL', 'http://localhost:8000/api/v1'), '/');
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
            $response = Http::post("{$this->apiBase}/auth/login", $validated);
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
        $request->session()->put('token', $token);
        $request->session()->put('username', $validated['username']);

        try {
            $meResponse = Http::withToken($token)->get("{$this->apiBase}/auth/me");
            if ($meResponse->ok()) {
                $request->session()->put('user', $meResponse->json());
            }
        } catch (\Throwable $e) {
            // Игнорируем сбои получения профиля: токен уже сохранен
        }

        return redirect()->route('admin.dashboard');
    }

    public function dashboard(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('token')) {
            return redirect()->route('login')->with('status', 'Пожалуйста, авторизуйтесь.');
        }

        return view('admin.dashboard', [
            'user' => $request->session()->get('user'),
            'username' => $request->session()->get('username'),
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget(['token', 'user', 'username']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Вы успешно вышли');
    }
}

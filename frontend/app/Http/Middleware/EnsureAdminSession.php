<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAdminSession
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->session()->has('token')) {
            return redirect()->route('login')->with('status', 'Пожалуйста, авторизуйтесь.');
        }

        return $next($request);
    }
}

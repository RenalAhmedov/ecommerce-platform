<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\TokenLoginRequest;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $this->auth->login(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->boolean('remember'),
            $request->ip(),
        );

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function storeWithToken(TokenLoginRequest $request): RedirectResponse
    {
        $this->auth->loginWithToken($request->string('token')->toString(), $request->ip());

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->auth->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}

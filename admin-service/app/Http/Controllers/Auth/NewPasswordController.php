<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    public function create(Request $request): View
    {
        return view('auth.reset-password', ['request' => $request]);
    }

    public function store(ResetPasswordRequest $request): RedirectResponse
    {
        $this->auth->resetPassword($request->only('email', 'password', 'password_confirmation', 'token'));

        return redirect()->route('login')->with('status', __('Your password has been reset.'));
    }
}

<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        $this->auth->sendResetLink($request->string('email')->toString());

        return back()->with('status', __('If an admin account exists for this email, a reset link is on its way.'));
    }
}

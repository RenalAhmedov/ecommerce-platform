<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Only admins get a link, and the reply is the same whatever happened, so
     * the form cannot be used to discover which emails have an account.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        Password::sendResetLink([
            'email' => $request->string('email')->toString(),
            'role' => UserRole::Admin->value,
        ]);

        return back()->with('status', __('If an admin account exists for this email, a reset link is on its way.'));
    }
}

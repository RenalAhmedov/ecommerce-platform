<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;

class PasswordController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function update(UpdatePasswordRequest $request): RedirectResponse
    {
        $this->users->changePassword($request->user(), $request->string('password')->toString());

        return back()->with('status', 'password-updated');
    }
}

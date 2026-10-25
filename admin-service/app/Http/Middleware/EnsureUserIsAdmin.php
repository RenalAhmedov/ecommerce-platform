<?php

namespace App\Http\Middleware;

use App\Services\AuthService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function __construct(private readonly AuthService $auth) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isAdmin()) {
            Context::add('user_id', $request->user()->id);

            return $next($request);
        }

        Log::warning('Non-admin access refused', ['user_id' => $request->user()?->id, 'path' => $request->path()]);

        if (! $request->hasSession()) {
            abort(Response::HTTP_FORBIDDEN, 'Only administrators can use this API.');
        }

        $this->auth->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors([
            'email' => __('This panel is for administrators only.'),
        ]);
    }
}

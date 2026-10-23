<?php

namespace App\Http\Middleware;

use App\Services\AuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The role is read from the database, not from the JWT, so a demoted admin
 * loses access at once. Panel sessions are signed out; API calls get a 403.
 */
class EnsureUserIsAdmin
{
    public function __construct(private readonly AuthService $auth) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isAdmin()) {
            return $next($request);
        }

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

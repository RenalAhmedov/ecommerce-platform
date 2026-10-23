<?php

namespace App\Http\Middleware;

use App\Services\AuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A panel session opened with an api-gateway token ends as soon as that token
 * is logged out (in either service), revoked or expires. Sessions opened with
 * a password are not affected.
 */
class EnsureSessionTokenIsActive
{
    public function __construct(private readonly AuthService $auth) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->auth->endSessionIfTokenEnded()) {
            return $next($request);
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', __('Your session ended because its access token was logged out or has expired.'));
    }
}

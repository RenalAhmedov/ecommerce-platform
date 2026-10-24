<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\JwtAuthService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    public function __construct(private readonly JwtAuthService $jwt) {}

    public function logout(Request $request): Response
    {
        $this->jwt->logout($request->bearerToken());

        return response()->noContent();
    }
}

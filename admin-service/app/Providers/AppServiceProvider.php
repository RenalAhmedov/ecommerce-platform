<?php

namespace App\Providers;

use App\Services\JwtAuthService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Builder::defaultTimePrecision(null);
        Model::shouldBeStrict(! $this->app->isProduction());
        DB::prohibitDestructiveCommands(! $this->app->runningUnitTests());

        Auth::viaRequest('jwt', fn (Request $request) => $this->app->make(JwtAuthService::class)
            ->userFromToken($request->bearerToken()));
    }
}

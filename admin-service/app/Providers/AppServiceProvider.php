<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Builder::defaultTimePrecision(null);
        Model::shouldBeStrict(! $this->app->isProduction());
        DB::prohibitDestructiveCommands(! $this->app->runningUnitTests());
    }
}

<?php

namespace App\Services;

use App\Jobs\NotifyCatalogChanged;
use Illuminate\Support\Facades\Log;

/**
 * The one place that announces catalogue changes to other services.
 * Callers invoke it after their transaction has committed, so a rolled-back
 * change is never announced.
 */
class CatalogNotifier
{
    public function catalogChanged(string $reason): void
    {
        NotifyCatalogChanged::dispatch();

        Log::debug('Catalog change queued for the api-gateway', ['reason' => $reason]);
    }
}

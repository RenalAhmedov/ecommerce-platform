<?php

namespace App\Services;

use App\Jobs\NotifyCatalogChanged;
use Illuminate\Support\Facades\Log;

class CatalogNotifier
{
    public function catalogChanged(string $reason): void
    {
        NotifyCatalogChanged::dispatch();

        Log::debug('Catalog change queued for the api-gateway', ['reason' => $reason]);
    }
}

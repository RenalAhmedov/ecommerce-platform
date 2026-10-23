<?php

namespace App\Models\Concerns;

trait UsesTimestampTz
{
    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:s.uP';
    }
}

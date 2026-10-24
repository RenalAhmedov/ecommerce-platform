<?php

namespace App\Support;

final class LikePattern
{
    public static function contains(string $search): string
    {
        return '%'.addcslashes($search, '\\%_').'%';
    }
}

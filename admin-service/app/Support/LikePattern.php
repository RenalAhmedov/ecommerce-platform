<?php

namespace App\Support;

/** Search input is matched literally: "%" and "_" typed by a user are not SQL wildcards. */
final class LikePattern
{
    public static function contains(string $search): string
    {
        return '%'.addcslashes($search, '\\%_').'%';
    }
}

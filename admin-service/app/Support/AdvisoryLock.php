<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use LogicException;

final class AdvisoryLock
{
    public static function productSku(string $sku): string
    {
        return "products.sku:{$sku}";
    }

    public static function productSlug(string $slug): string
    {
        return "products.slug:{$slug}";
    }

    public static function userEmail(string $email): string
    {
        return 'users.email:'.mb_strtolower($email);
    }

    public static function adminRoster(): string
    {
        return 'users.admins';
    }

    public static function categoryTree(): string
    {
        return 'categories.tree';
    }

    /** @param  list<string>  $keys */
    public static function acquire(array $keys): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Advisory locks must be taken inside a database transaction.');
        }

        // Always taken in the same order, so two writers never wait on each other (deadlock).
        sort($keys, SORT_STRING);

        foreach ($keys as $key) {
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', [$key]);
        }
    }
}

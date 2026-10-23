<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Transaction-scoped Postgres locks shared with the Nest api-gateway
 * (api-gateway/src/database/advisory-locks.ts). The schema has no unique
 * indexes on sku/slug/email, so both services serialise "check then insert"
 * on the same keys; the key format must stay identical on both sides.
 */
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

    /**
     * Taken by every change that can remove an admin (demotion, deletion), so
     * two admins demoting each other at the same moment can't leave none.
     */
    public static function adminRoster(): string
    {
        return 'users.admins';
    }

    /**
     * Only the admin service writes categories, and those writes are rare, so
     * one lock serialises them all: slug checks and moves within the tree
     * (two concurrent moves could otherwise create a loop).
     */
    public static function categoryTree(): string
    {
        return 'categories.tree';
    }

    /**
     * Keys are taken in sorted order so two writers never wait on each other
     * in opposite order (deadlock). Released automatically at commit/rollback.
     *
     * @param  list<string>  $keys
     */
    public static function acquire(array $keys): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Advisory locks must be taken inside a database transaction.');
        }

        sort($keys, SORT_STRING);

        foreach ($keys as $key) {
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', [$key]);
        }
    }
}

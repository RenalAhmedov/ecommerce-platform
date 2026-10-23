<?php

namespace App\Repositories;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\AdvisoryLock;
use App\Support\LikePattern;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class UserRepository
{
    /**
     * @param  array{search?: ?string, role?: ?string, trashed?: bool|string|null}  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return User::query()
            ->withCount('orders')
            ->when(filter_var($filters['trashed'] ?? false, FILTER_VALIDATE_BOOLEAN), fn (Builder $query) => $query->onlyTrashed())
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $pattern = LikePattern::contains($search);
                $query->where(fn (Builder $query) => $query->whereLike('name', $pattern)->orWhereLike('email', $pattern));
            })
            ->when($filters['role'] ?? null, fn (Builder $query, string $role) => $query->where('role', UserRole::from($role)))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** Soft-deleted users are not found, so their tokens stop working. */
    public function findById(int $id): ?User
    {
        return User::find($id);
    }

    /** Re-reads the row with SELECT … FOR UPDATE; must run inside a transaction. */
    public function lockById(int $id): User
    {
        return User::whereKey($id)->lockForUpdate()->firstOrFail();
    }

    /** Same key as the Nest registration, so a sign-up and an admin can't create the same email at once. */
    public function lockEmail(string $email): void
    {
        AdvisoryLock::acquire([AdvisoryLock::userEmail($email)]);
    }

    public function lockAdminRoster(): void
    {
        AdvisoryLock::acquire([AdvisoryLock::adminRoster()]);
    }

    /** Case-insensitive and including deleted users, exactly like the Nest check. */
    public function existsByEmail(string $email, ?int $ignoreId = null): bool
    {
        return User::withTrashed()
            ->whereRaw('LOWER(email) = LOWER(?)', [$email])
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }

    public function countAdmins(): int
    {
        return User::where('role', UserRole::Admin)->count();
    }

    /** @param  array{name: string, email: string, password: string}  $attributes */
    public function create(array $attributes, UserRole $role): User
    {
        $user = new User($attributes);
        $user->role = $role;
        $user->save();

        return $user;
    }

    public function save(User $user): void
    {
        $user->save();
    }

    public function delete(User $user): void
    {
        $user->delete();
    }

    public function restore(User $user): void
    {
        $user->restore();
    }

    public function updatePasswordAndRememberToken(User $user, string $password, string $rememberToken): void
    {
        $user->forceFill([
            'password' => $password,
            'remember_token' => $rememberToken,
        ])->save();
    }
}

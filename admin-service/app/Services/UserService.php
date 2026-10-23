<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function __construct(private readonly UserRepository $users) {}

    /**
     * @param  array{search?: ?string, role?: ?string, trashed?: bool|string|null}  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->users->paginate($filters, $perPage);
    }

    /**
     * Same uniqueness rule as the Nest registration (same advisory lock,
     * case-insensitive, deleted users included), so a shop sign-up and an
     * admin can never create the same email at the same moment.
     *
     * @param  array{name: string, email: string, password: string, role: string}  $data
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $this->users->lockEmail($data['email']);
            $this->ensureEmailAvailable($data['email']);

            return $this->users->create(Arr::only($data, ['name', 'email', 'password']), UserRole::from($data['role']));
        });
    }

    /**
     * An empty password keeps the current one. A new password also renews the
     * remember token, which signs the user out of "remember me" sessions.
     *
     * @param  array{name?: string, email?: string, password?: ?string, role?: string}  $data
     */
    public function update(User $user, array $data, User $actor): User
    {
        return DB::transaction(function () use ($user, $data, $actor) {
            $user = $this->users->lockById($user->id);
            $user->fill(Arr::only($data, ['name', 'email']));

            if (filled($data['password'] ?? null)) {
                $user->password = $data['password'];
                $user->remember_token = Str::random(60);
            }

            if ($user->isDirty('email')) {
                $this->users->lockEmail($user->email);
                $this->ensureEmailAvailable($user->email, $user->id);
            }

            if (isset($data['role'])) {
                $role = UserRole::from($data['role']);
                if ($user->isAdmin() && $role !== UserRole::Admin) {
                    $this->ensureAdminCanBeRemoved($user, $actor, 'role', 'demote');
                }
                $user->role = $role;
            }

            $this->users->save($user);

            return $user;
        });
    }

    /** Orders keep pointing at a deleted user; restoring brings the account back with them. */
    public function delete(User $user, User $actor): void
    {
        DB::transaction(function () use ($user, $actor) {
            $user = $this->users->lockById($user->id);

            if ($user->isAdmin()) {
                $this->ensureAdminCanBeRemoved($user, $actor, 'user', 'delete');
            }

            $this->users->delete($user);
        });
    }

    /** Emails of deleted users stay reserved, so a restore never creates a duplicate. */
    public function restore(User $user): void
    {
        $this->users->restore($user);
    }

    public function changePassword(User $user, string $password): void
    {
        $this->users->updatePassword($user, $password);
    }

    /** A new remember token signs the user out of "remember me" sessions on other devices. */
    public function resetPassword(User $user, string $password): void
    {
        $this->users->updatePasswordAndRememberToken($user, $password, Str::random(60));

        event(new PasswordReset($user));
    }

    /** @throws ValidationException */
    private function ensureEmailAvailable(string $email, ?int $ignoreId = null): void
    {
        if ($this->users->existsByEmail($email, $ignoreId)) {
            throw ValidationException::withMessages(['email' => 'Email is already registered.']);
        }
    }

    /**
     * Admins can't remove their own access (no locking yourself out) and the
     * last admin can't be removed. The roster lock makes "count admins, then
     * remove one" atomic, so two admins demoting each other can't leave none.
     *
     * @throws ValidationException
     */
    private function ensureAdminCanBeRemoved(User $admin, User $actor, string $field, string $action): void
    {
        if ($admin->is($actor)) {
            throw ValidationException::withMessages([$field => "You cannot {$action} your own admin account."]);
        }

        $this->users->lockAdminRoster();

        if ($this->users->countAdmins() <= 1) {
            throw ValidationException::withMessages([$field => "You cannot {$action} the last admin."]);
        }
    }
}

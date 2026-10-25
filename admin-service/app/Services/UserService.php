<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly JwtAuthService $tokens,
    ) {}

    /**
     * @param  array{search?: ?string, role?: ?string, trashed?: bool|string|null}  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->users->paginate($filters, $perPage);
    }

    public function find(int $id): ?User
    {
        return $this->users->findById($id);
    }

    /** @param  array{name: string, email: string, password: string, role: string}  $data */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $this->users->lockEmail($data['email']);
            $this->ensureEmailAvailable($data['email']);

            $user = $this->users->create(Arr::only($data, ['name', 'email', 'password']), UserRole::from($data['role']));

            Log::info('User created', ['target_user_id' => $user->id, 'role' => $user->role->value]);

            return $user;
        });
    }

    /** @param  array{name?: string, email?: string, password?: ?string, role?: string}  $data */
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

            $changed = array_values(array_diff(array_keys($user->getDirty()), ['password', 'remember_token']));
            $passwordChanged = $user->isDirty('password');
            $roleChanged = $user->isDirty('role');
            $this->users->save($user);

            if ($passwordChanged || $roleChanged) {
                $this->tokens->revokeAllForUser($user->id);
            }

            if ($changed !== [] || $passwordChanged) {
                Log::info('User updated', ['target_user_id' => $user->id, 'changed' => $changed, 'password_changed' => $passwordChanged]);
            }

            return $user;
        });
    }

    public function delete(User $user, User $actor): void
    {
        DB::transaction(function () use ($user, $actor) {
            $user = $this->users->lockById($user->id);

            if ($user->isAdmin()) {
                $this->ensureAdminCanBeRemoved($user, $actor, 'user', 'delete');
            }

            $this->users->delete($user);
            $this->tokens->revokeAllForUser($user->id);

            Log::info('User deleted', ['target_user_id' => $user->id, 'role' => $user->role->value]);
        });
    }

    public function restore(User $user): void
    {
        $this->users->restore($user);

        Log::info('User restored', ['target_user_id' => $user->id]);
    }

    public function changePassword(User $user, string $password): void
    {
        $this->replacePassword($user, $password);

        Log::info('Password changed', ['target_user_id' => $user->id]);
    }

    public function resetPassword(User $user, string $password): void
    {
        $this->replacePassword($user, $password);
        event(new PasswordReset($user));

        Log::info('Password reset completed', ['target_user_id' => $user->id]);
    }

    private function replacePassword(User $user, string $password): void
    {
        DB::transaction(function () use ($user, $password) {
            $this->users->updatePasswordAndRememberToken($user, $password, Str::random(60));
            $this->tokens->revokeAllForUser($user->id);
        });
    }

    /** @throws ValidationException */
    private function ensureEmailAvailable(string $email, ?int $ignoreId = null): void
    {
        if ($this->users->existsByEmail($email, $ignoreId)) {
            throw ValidationException::withMessages(['email' => 'Email is already registered.']);
        }
    }

    /** @throws ValidationException */
    private function ensureAdminCanBeRemoved(User $admin, User $actor, string $field, string $action): void
    {
        if ($admin->is($actor)) {
            throw ValidationException::withMessages([$field => "You cannot {$action} your own admin account."]);
        }

        // Makes "count the admins, then remove one" atomic, so two admins can't remove each other.
        $this->users->lockAdminRoster();

        if ($this->users->countAdmins() <= 1) {
            throw ValidationException::withMessages([$field => "You cannot {$action} the last admin."]);
        }
    }
}

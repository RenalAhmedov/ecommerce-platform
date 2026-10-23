<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Requests\Users\UserIndexRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    private const PER_PAGE = 20;

    public function __construct(private readonly UserService $users) {}

    public function index(UserIndexRequest $request): View
    {
        return view('users.index', [
            'users' => $this->users->paginate($request->validated(), self::PER_PAGE),
            'roles' => UserRole::cases(),
        ]);
    }

    public function create(): View
    {
        return view('users.create', ['roles' => UserRole::cases()]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = $this->users->create($request->validated());

        return redirect()->route('users.index')->with('success', "User {$user->email} created.");
    }

    public function edit(User $user): View
    {
        return view('users.edit', ['user' => $user, 'roles' => UserRole::cases()]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user = $this->users->update($user, $request->validated(), $request->user());

        return redirect()->route('users.edit', $user)->with('success', 'User saved.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->users->delete($user, $request->user());

        return redirect()->route('users.index')->with('success', "User {$user->email} deleted.");
    }

    public function restore(User $user): RedirectResponse
    {
        $this->users->restore($user);

        return redirect()->route('users.index')->with('success', "User {$user->email} restored.");
    }
}

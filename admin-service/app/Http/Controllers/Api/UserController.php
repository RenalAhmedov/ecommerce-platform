<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Requests\Users\UserIndexRequest;
use App\Http\Resources\UserCollection;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UserController extends Controller
{
    private const DEFAULT_LIMIT = 20;

    public function __construct(private readonly UserService $users) {}

    public function index(UserIndexRequest $request): UserCollection
    {
        return new UserCollection(
            $this->users->paginate($request->validated(), $request->integer('limit', self::DEFAULT_LIMIT)),
        );
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        return (new UserResource($this->users->create($request->validated())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        return new UserResource($this->users->update($user, $request->validated(), $request->user()));
    }

    public function destroy(Request $request, User $user): Response
    {
        $this->users->delete($user, $request->user());

        return response()->noContent();
    }

    public function restore(User $user): UserResource
    {
        $this->users->restore($user);

        return new UserResource($user);
    }
}

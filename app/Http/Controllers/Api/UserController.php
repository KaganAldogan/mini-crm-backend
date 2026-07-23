<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreUserRequest;
use App\Http\Requests\Api\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class UserController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $users = User::query()
            ->with('maintenanceCategories')
            ->latest()
            ->get();

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->safe()->only(['name', 'email', 'role', 'password']);
        $user = User::create($data);
        $this->syncMaintenanceCategories($user, $request);

        $user->load('maintenanceCategories');

        return (new UserResource($user))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $data = $request->safe()->only(['name', 'email', 'role']);

        if ($request->filled('password')) {
            $data['password'] = $request->validated('password');
        }

        $user->update($data);
        $this->syncMaintenanceCategories($user, $request);
        $user->load('maintenanceCategories');

        return new UserResource($user);
    }

    private function syncMaintenanceCategories(
        User $user,
        StoreUserRequest|UpdateUserRequest $request
    ): void {
        if ($user->isTechnician()) {
            $user->maintenanceCategories()->sync(
                $request->validated('maintenance_category_ids', [])
            );

            return;
        }

        $user->maintenanceCategories()->sync([]);
    }

    public function destroy(Request $request, User $user): Response|JsonResponse
    {
        if ($request->user()->id === $user->id) {
            return response()->json([
                'message' => 'Kendi hesabınızı silemezsiniz.',
            ], 422);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->noContent();
    }
}

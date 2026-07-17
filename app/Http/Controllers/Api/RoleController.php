<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreRoleRequest;
use App\Http\Requests\Api\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $roles = Role::query()
            ->with(['permissions' => fn ($q) => $q->orderBy('sort_order')])
            ->withCount('users')
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();

        return RoleResource::collection($roles);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = DB::transaction(function () use ($request) {
            $role = Role::query()->create([
                'name' => $request->validated('name'),
                'slug' => $request->validated('slug'),
                'description' => $request->validated('description'),
                'is_system' => false,
            ]);

            $role->permissions()->sync($request->validated('permission_ids', []));

            return $role->load(['permissions' => fn ($q) => $q->orderBy('sort_order')])
                ->loadCount('users');
        });

        return (new RoleResource($role))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Role $role): RoleResource
    {
        $role->load(['permissions' => fn ($q) => $q->orderBy('sort_order')])
            ->loadCount('users');

        return new RoleResource($role);
    }

    public function update(UpdateRoleRequest $request, Role $role): RoleResource|JsonResponse
    {
        if ($role->is_system && $request->validated('slug') !== $role->slug) {
            return response()->json([
                'message' => 'Sistem rollerinin anahtarı değiştirilemez.',
            ], 422);
        }

        $oldSlug = $role->slug;

        DB::transaction(function () use ($request, $role, $oldSlug) {
            $newSlug = $role->is_system
                ? $role->slug
                : $request->validated('slug');

            $role->update([
                'name' => $request->validated('name'),
                'slug' => $newSlug,
                'description' => $request->validated('description'),
            ]);

            if ($oldSlug !== $role->slug) {
                User::query()
                    ->where('role', $oldSlug)
                    ->update(['role' => $role->slug]);
            }

            $role->permissions()->sync($request->validated('permission_ids', []));
        });

        $role->load(['permissions' => fn ($q) => $q->orderBy('sort_order')])
            ->loadCount('users');

        return new RoleResource($role);
    }

    public function destroy(Role $role): Response|JsonResponse
    {
        if ($role->is_system) {
            return response()->json([
                'message' => 'Sistem rolleri silinemez.',
            ], 422);
        }

        if ($role->users()->exists()) {
            return response()->json([
                'message' => 'Bu role bağlı kullanıcılar varken silinemez.',
            ], 422);
        }

        $role->delete();

        return response()->noContent();
    }
}

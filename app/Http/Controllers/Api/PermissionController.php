<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PermissionResource;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        $permissions = Permission::query()
            ->orderBy('sort_order')
            ->get();

        $groups = $permissions
            ->groupBy('group')
            ->map(fn ($items, $group) => [
                'group' => $group,
                'permissions' => PermissionResource::collection($items)->resolve(),
            ])
            ->values();

        return response()->json([
            'data' => [
                'groups' => $groups,
                'permissions' => PermissionResource::collection($permissions),
            ],
        ]);
    }
}

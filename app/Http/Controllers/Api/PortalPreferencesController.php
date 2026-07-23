<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PortalPreferencesController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'preferences' => $user->reminderPreferences(),
            'allowed_days' => User::LEASE_END_REMINDER_DAYS,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lease_end_reminder_enabled' => ['sometimes', 'boolean'],
            'lease_end_reminder_days' => [
                'sometimes',
                'integer',
                Rule::in(User::LEASE_END_REMINDER_DAYS),
            ],
            'lease_end_reminder_email' => ['sometimes', 'boolean'],
        ]);

        $request->user()->update($data);

        return response()->json([
            'message' => 'Tercihler güncellendi.',
            'preferences' => $request->user()->fresh()->reminderPreferences(),
            'allowed_days' => User::LEASE_END_REMINDER_DAYS,
        ]);
    }
}

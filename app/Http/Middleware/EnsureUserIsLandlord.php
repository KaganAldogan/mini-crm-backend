<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsLandlord
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isLandlord()) {
            return response()->json([
                'message' => 'Bu alana yalnızca ev sahipleri erişebilir.',
            ], 403);
        }

        return $next($request);
    }
}

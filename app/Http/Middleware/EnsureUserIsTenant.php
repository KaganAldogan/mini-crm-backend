<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isTenant()) {
            return response()->json([
                'message' => 'Bu alana yalnızca kiracılar erişebilir.',
            ], 403);
        }

        return $next($request);
    }
}

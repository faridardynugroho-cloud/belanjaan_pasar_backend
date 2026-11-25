<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isAdmin()) {
            return response()->json([
                'error_code' => 'UNAUTHORIZED_ROLE',
                'message' => 'Hanya admin yang dapat mengakses endpoint ini.',
            ], 403);
        }

        return $next($request);
    }
}

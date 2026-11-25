<?php

namespace App\Http\Middleware;

use App\Enums\ApprovalStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApprovedDriver
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isDriver()) {
            return response()->json([
                'error_code' => 'UNAUTHORIZED_ROLE',
                'message' => 'Hanya driver yang dapat mengakses endpoint ini.',
            ], 403);
        }

        $profile = $user->driverProfile;

        if (! $profile || $profile->approval_status !== ApprovalStatus::Approved->value) {
            return response()->json([
                'error_code' => 'DRIVER_NOT_APPROVED',
                'message' => 'Akun driver belum disetujui admin.',
                'approval_status' => $profile?->approval_status,
            ], 403);
        }

        return $next($request);
    }
}

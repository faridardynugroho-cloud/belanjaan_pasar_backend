<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\RejectDriverRequest;
use App\Http\Resources\DriverProfileResource;
use App\Models\DriverDocumentCheck;
use App\Models\DriverProfile;
use App\Services\DriverVerificationService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class DriverVerificationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = DriverProfile::query()
            ->with(['user', 'document', 'vehicle', 'documentChecks'])
            ->when(
                $request->filled('approval_status'),
                fn ($q) => $q->where('approval_status', $request->string('approval_status')->toString()),
            )
            ->orderByDesc('driver_profile_id');

        return DriverProfileResource::collection($query->paginate(15));
    }

    public function show(DriverProfile $driverProfile): DriverProfileResource
    {
        $driverProfile->load(['user', 'document', 'vehicle', 'documentChecks']);

        return DriverProfileResource::make($driverProfile);
    }

    public function approve(Request $request, DriverProfile $driverProfile): JsonResponse
    {
        try {
            $admin = $request->user();

            $approved = DB::transaction(function () use ($admin, $driverProfile) {
                DriverVerificationService::pastikanPemeriksaan($driverProfile);

                $checks = DriverDocumentCheck::query()
                    ->where('driver_profile_id', $driverProfile->driver_profile_id)
                    ->where('status', DriverDocumentCheck::STATUS_PENDING)
                    ->get();

                foreach ($checks as $check) {
                    if ($check->pathFoto() !== null) {
                        DriverVerificationService::nilaiDokumen($admin, $check, DriverDocumentCheck::STATUS_VALID);
                    }
                }

                return DriverVerificationService::setujui($admin, $driverProfile);
            });

            $approved->load(['user', 'document', 'vehicle', 'documentChecks']);

            return response()->json([
                'message' => 'Driver approved successfully',
                'driver' => DriverProfileResource::make($approved),
            ]);
        } catch (DomainException $e) {
            return response()->json([
                'error_code' => 'VERIFICATION_FAILED',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function reject(RejectDriverRequest $request, DriverProfile $driverProfile): JsonResponse
    {
        try {
            $rejected = DriverVerificationService::tolak(
                $request->user(),
                $driverProfile,
                $request->validated('rejection_reason'),
            );

            $rejected->load(['user', 'document', 'vehicle', 'documentChecks']);

            return response()->json([
                'message' => 'Driver rejected successfully',
                'driver' => DriverProfileResource::make($rejected),
            ]);
        } catch (DomainException $e) {
            return response()->json([
                'error_code' => 'VERIFICATION_FAILED',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}

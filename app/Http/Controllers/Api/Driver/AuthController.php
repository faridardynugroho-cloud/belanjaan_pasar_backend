<?php

namespace App\Http\Controllers\Api\Driver;

use App\Enums\ApprovalStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Driver\LoginRequest;
use App\Http\Requests\Api\Driver\RegisterRequest;
use App\Http\Resources\DriverProfileResource;
use App\Models\DriverDocument;
use App\Models\DriverProfile;
use App\Models\DriverVehicle;
use App\Models\User;
use App\Services\DriverVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $disk = Storage::disk('public');
        $storedPaths = [];

        try {
            $ktpPath = $request->file('ktp_photo')->store('driver-documents/ktp', 'public');
            $storedPaths[] = $ktpPath;

            $selfiePath = $request->file('selfie_photo')->store('driver-documents/selfie', 'public');
            $storedPaths[] = $selfiePath;

            $simPath = $request->file('sim_photo')->store('driver-documents/sim', 'public');
            $storedPaths[] = $simPath;

            $vehiclePath = $request->file('vehicle_photo')->store('driver-vehicles/vehicle', 'public');
            $storedPaths[] = $vehiclePath;

            $stnkPath = $request->file('stnk_photo')->store('driver-vehicles/stnk', 'public');
            $storedPaths[] = $stnkPath;

            $result = DB::transaction(function () use (
                $request,
                $ktpPath,
                $selfiePath,
                $simPath,
                $vehiclePath,
                $stnkPath,
            ) {
                $user = User::create([
                    'name' => $request->string('name')->toString(),
                    'email' => $request->string('email')->toString(),
                    'password' => $request->string('password')->toString(),
                    'phone' => $request->string('phone')->toString(),
                    'gender' => $request->string('gender')->toString(),
                    'birth_date' => $request->date('birth_date')->toDateString(),
                    'role' => UserRole::Driver->value,
                ]);

                $profile = DriverProfile::create([
                    'user_id' => $user->user_id,
                    'domicile_address' => $request->string('domicile_address')->toString(),
                    'is_available' => false,
                    'approval_status' => ApprovalStatus::Pending->value,
                ]);

                DriverDocument::create([
                    'driver_profile_id' => $profile->driver_profile_id,
                    'nik' => $request->string('nik')->toString(),
                    'ktp_photo' => $ktpPath,
                    'selfie_photo' => $selfiePath,
                    'sim_number' => $request->string('sim_number')->toString(),
                    'sim_photo' => $simPath,
                    'sim_expired_at' => $request->date('sim_expired_at')->toDateString(),
                ]);

                DriverVehicle::create([
                    'driver_profile_id' => $profile->driver_profile_id,
                    'vehicle_type' => $request->string('vehicle_type')->toString(),
                    'vehicle_brand' => $request->string('vehicle_brand')->toString(),
                    'vehicle_model' => $request->string('vehicle_model')->toString(),
                    'vehicle_plate' => strtoupper($request->string('vehicle_plate')->toString()),
                    'vehicle_color' => $request->string('vehicle_color')->toString(),
                    'production_year' => $request->integer('production_year'),
                    'vehicle_photo' => $vehiclePath,
                    'stnk_photo' => $stnkPath,
                ]);

                DriverVerificationService::pastikanPemeriksaan($profile);

                $token = $user->createToken($request->input('device_name', 'driver-app'))->plainTextToken;

                $profile->load(['user', 'document', 'vehicle', 'documentChecks']);

                return [
                    'token' => $token,
                    'profile' => $profile,
                ];
            });

            return response()->json([
                'message' => 'Driver registered successfully',
                'token' => $result['token'],
                'driver' => DriverProfileResource::make($result['profile']),
            ], 201);
        } catch (Throwable $e) {
            if ($storedPaths !== []) {
                $disk->delete($storedPaths);
            }

            throw $e;
        }
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->string('email')->toString())->first();

        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials'],
            ]);
        }

        if (! $user->isDriver()) {
            return response()->json([
                'error_code' => 'UNAUTHORIZED_ROLE',
                'message' => 'User is not authorized as a driver',
            ], 403);
        }

        $token = $user->createToken($request->input('device_name', 'driver-app'))->plainTextToken;

        $profile = $user->driverProfile?->load(['user', 'document', 'vehicle', 'documentChecks']);

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'driver' => $profile ? DriverProfileResource::make($profile) : null,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isDriver()) {
            return response()->json([
                'error_code' => 'UNAUTHORIZED_ROLE',
                'message' => 'Unauthorized',
            ], 403);
        }

        $profile = $user->driverProfile?->load(['user', 'document', 'vehicle', 'documentChecks']);

        return response()->json([
            'driver' => $profile ? DriverProfileResource::make($profile) : null,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }
}

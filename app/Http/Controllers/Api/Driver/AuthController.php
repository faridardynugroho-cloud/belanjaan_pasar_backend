<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Driver\CompleteProfileRequest;
use App\Http\Requests\Api\Driver\LoginRequest;
use App\Http\Requests\Api\Driver\RegisterRequest;
use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password,
                'phone' => $request->phone,
                'gender' => $request->gender,
                'birth_date' => $request->birth_date,
                'address' => $request->address,
                'role' => User::ROLE_DRIVER,
            ]);

            DriverProfile::create([
                'user_id' => $user->user_id,
                'vehicle_type' => $request->vehicle_type,
                'vehicle_plate' => $request->vehicle_plate,
                'is_available' => false,
                'approval_status' => 'pending',
            ]);

            $token = $user->createToken($request->device_name ?? 'driver-app')->plainTextToken;

            return response()->json([
                'message' => 'Driver registered successfully',
                'token' => $token,
                'user' => $user->load('driverProfile'),
            ], 201);
        });
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials'],
            ]);
        }

        if (! $user->isDriver()) {
            return response()->json(['message' => 'User is not authorized as a driver'], 403);
        }

        $token = $user->createToken($request->device_name ?? 'driver-app')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user->load('driverProfile'),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        if (! $request->user()->isDriver()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'user' => $request->user()->load('driverProfile'),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    public function completeProfile(CompleteProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isDriver()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        DB::transaction(function () use ($user, $request) {
            $user->update($request->only(['name', 'phone', 'gender', 'birth_date', 'address']));

            DriverProfile::updateOrCreate(
                ['user_id' => $user->user_id],
                [
                    'vehicle_type' => $request->vehicle_type,
                    'vehicle_plate' => $request->vehicle_plate,
                ]
            );
        });

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user->fresh()->load('driverProfile'),
        ]);
    }
}

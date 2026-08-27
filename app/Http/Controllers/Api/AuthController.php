<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * POST /api/auth/register
     * NOTE: Ab signup ka main raasta OTP hai (FirebaseAuthController@verify).
     * Ye endpoint sirf backward-compatibility ke liye rakha hai.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Account created successfully.',
            'data' => [
                'user'  => $user,
                'token' => $token,
            ],
        ], 201);
    }

    /**
     * POST /api/auth/login
     * NOTE: Ab login ka main raasta OTP hai. Ye password-based login un users ke liye
     * hai jinka password set hai (OTP-only users ka password null hota hai).
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $login = $request->input('login');
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        $user = User::where($field, $login)->first();

        // FIX: OTP-only users ka password null hota hai — Hash::check(..., null) se pehle guard
        if (! $user || ! $user->password || ! Hash::check($request->input('password'), $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials. Ya to email/phone/password ghalat hai, ya ye account OTP se bana tha — OTP se login karein.',
            ], 401);
        }

        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Logged in successfully.',
            'data' => [
                'user'  => $user,
                'token' => $token,
            ],
        ]);
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['success' => true, 'message' => 'Logged out successfully.']);
    }

    /**
     * GET /api/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => ['user' => $request->user()]]);
    }
}

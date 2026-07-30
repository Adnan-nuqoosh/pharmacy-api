<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SocialAuthController extends Controller
{
    /**
     * POST /api/auth/google
     * Design ka "Sign up with Google" button.
     *
     * Body: { "id_token": "<Google ka ID token>" }
     *
     * Flow: mobile app / frontend Google SDK se login karwata hai, phir wahan se
     * mila hua id_token yahan bhejta hai. Hum Google se verify kar ke apna token dete hain.
     *
     * ⚠️ SETUP: .env mein GOOGLE_CLIENT_ID daalna zaroori hai:
     *    GOOGLE_CLIENT_ID=xxxxx.apps.googleusercontent.com
     */
    public function google(Request $request): JsonResponse
    {
        $request->validate([
            'id_token' => ['required', 'string'],
        ]);

        $clientId = config('services.google.client_id');

        if (! $clientId) {
            return response()->json([
                'success' => false,
                'message' => 'Google login configure nahi hai (GOOGLE_CLIENT_ID missing).',
            ], 500);
        }

        // Google se token verify karwate hain
        $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
            'id_token' => $request->input('id_token'),
        ]);

        if ($response->failed()) {
            return response()->json([
                'success' => false,
                'message' => 'Google token invalid hai.',
            ], 401);
        }

        $payload = $response->json();

        // SECURITY: token hamari hi app ke liye bana hona chahiye
        if (($payload['aud'] ?? null) !== $clientId) {
            return response()->json([
                'success' => false,
                'message' => 'Ye token is app ke liye nahi hai.',
            ], 401);
        }

        // SECURITY: email verified hona chahiye
        if (($payload['email_verified'] ?? 'false') !== 'true' && ($payload['email_verified'] ?? false) !== true) {
            return response()->json([
                'success' => false,
                'message' => 'Google account ka email verified nahi hai.',
            ], 401);
        }

        $email = $payload['email'] ?? null;
        if (! $email) {
            return response()->json(['success' => false, 'message' => 'Google se email nahi mila.'], 422);
        }

        // User pehle se hai to login, warna naya banao
        $user = User::where('email', $email)->first();
        $isNew = false;

        if (! $user) {
            $user = User::create([
                'name'              => $payload['name'] ?? 'User',
                'email'             => $email,
                'phone'             => null, // Google phone nahi deta — user baad mein profile se daalega
                'password'          => Str::random(32), // random, kyunke login Google se hoga
                'email_verified_at' => now(),
            ]);
            $isNew = true;
        }

        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => $isNew ? 'Account ban gaya (Google).' : 'Logged in with Google.',
            'data' => [
                'user'          => $user,
                'token'         => $token,
                'is_new_user'   => $isNew,
                'needs_phone'   => empty($user->phone), // frontend phone maange
            ],
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class FirebaseAuthController extends Controller
{
    /**
     * POST /api/auth/otp/verify
     *
     * Sign Up + Log In dono isi endpoint se hote hain — Firebase khud tay karta hai
     * ke phone verified hai ya nahi, hum sirf uska token check karte hain.
     *
     * Flow:
     * 1. Mobile app Firebase SDK se phone par OTP bhejta hai (backend involved nahi)
     * 2. User OTP dalta hai, Firebase verify karta hai aur ek "idToken" deta hai
     * 3. App wo idToken yahan bhejta hai
     * 4. Hum Firebase se confirm karte hain token asli hai, phone number nikaalte hain
     * 5. Phone number se user milta hai to login, warna naya account (signup)
     *
     * Body:
     *   id_token : Firebase se mila hua ID token (required)
     *   name     : naya user ho to zaroori (Sign Up screen ka Name field)
     *
     * ⚠️ SETUP: .env mein FIREBASE_API_KEY chahiye (Firebase Console > Project Settings > Web API Key)
     */
    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id_token' => ['required', 'string'],
            'name'     => ['nullable', 'string', 'max:100'],
            'email'    => ['nullable', 'email', 'max:150'],
        ]);

        $apiKey = config('services.firebase.api_key');

        if (! $apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'Firebase configure nahi hai (FIREBASE_API_KEY missing).',
            ], 500);
        }

        // Firebase se token verify karwate hain — ye phone number bhi confirm karta hai
        $response = Http::post(
            "https://identitytoolkit.googleapis.com/v1/accounts:lookup?key={$apiKey}",
            ['idToken' => $data['id_token']]
        );

        if ($response->failed()) {
            return response()->json([
                'success' => false,
                'message' => 'OTP token invalid ya expire ho chuka hai.',
            ], 401);
        }

        $firebaseUser = $response->json('users.0');
        $phone = $firebaseUser['phoneNumber'] ?? null;

        if (! $phone) {
            return response()->json([
                'success' => false,
                'message' => 'Is token mein verified phone number nahi mila.',
            ], 422);
        }

        $user = User::where('phone', $phone)->first();
        $isNew = false;

        if (! $user) {
            // Naya user — Sign Up flow. Name zaroori hai (design ka required field).
            if (empty($data['name'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Naya account banane ke liye name zaroori hai.',
                    'data'    => ['is_new_user' => true, 'phone' => $phone],
                ], 422);
            }

            $user = User::create([
                'name'              => $data['name'],
                'phone'             => $phone,
                'email'             => $data['email'] ?? null,
                'password'          => null, // OTP-only user — password ki zaroorat nahi
                'phone_verified_at' => now(),
            ]);
            $isNew = true;
        } elseif (! $user->phone_verified_at) {
            $user->update(['phone_verified_at' => now()]);
        }

        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => $isNew ? 'Account ban gaya.' : 'Logged in successfully.',
            'data' => [
                'user'        => $user,
                'token'       => $token,
                'is_new_user' => $isNew,
            ],
        ], $isNew ? 201 : 200);
    }
}

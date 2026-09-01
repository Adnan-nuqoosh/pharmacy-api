<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ForgotPasswordController extends Controller
{
    /**
     * POST /api/auth/forgot-password
     * Body: { "email": "user@example.com" }
     *
     * 6-digit code generate kar ke email par bhejta hai.
     * Security: chahe email exist kare ya na kare, hamesha same success message —
     * taake koi ye pata na laga sake ke kaunsi email registered hai (user enumeration se bachao).
     */
    public function sendCode(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = $request->input('email');
        $user  = User::where('email', $email)->first();

        if ($user) {
            $code = (string) random_int(100000, 999999);

            // Purana code (agar ho) hata kar naya save karein
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            DB::table('password_reset_tokens')->insert([
                'email'      => $email,
                'token'      => Hash::make($code),
                'created_at' => now(),
            ]);

            // MAIL_MAILER=log ho to code storage/logs/laravel.log mein dikhega (local testing).
            // Production mein real mailer (smtp/mailgun/ses) .env mein set karein.
            Mail::raw("Aapka Abwab Al Kheir Pharmacy password reset code: {$code}\nYe code 15 minute tak valid hai.", function ($message) use ($email) {
                $message->to($email)->subject('Password Reset Code');
            });
        }

        return response()->json([
            'success' => true,
            'message' => 'Agar ye email registered hai, to usay 6-digit code bhej diya gaya hai.',
        ]);
    }

    /**
     * POST /api/auth/reset-password
     * Body: { "email": "...", "code": "123456", "password": "...", "password_confirmation": "..." }
     */
    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'code'     => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $record = DB::table('password_reset_tokens')->where('email', $data['email'])->first();

        if (! $record) {
            return response()->json([
                'success' => false,
                'message' => 'Koi code request nahi ki gayi ya wo istemaal ho chuki hai.',
            ], 422);
        }

        // 15 minute expiry
        if (now()->diffInMinutes($record->created_at) > 15) {
            DB::table('password_reset_tokens')->where('email', $data['email'])->delete();

            return response()->json([
                'success' => false,
                'message' => 'Code expire ho chuka hai. Dobara request karein.',
            ], 422);
        }

        if (! Hash::check($data['code'], $record->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Code ghalat hai.',
            ], 422);
        }

        $user = User::where('email', $data['email'])->first();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'User nahi mila.'], 404);
        }

        $user->update(['password' => $data['password']]); // 'hashed' cast se khud hash ho jata hai

        // Code istemaal ho gaya — hata dein
        DB::table('password_reset_tokens')->where('email', $data['email'])->delete();

        // Security: password change hote hi purane saare tokens/sessions revoke
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password reset ho gaya. Ab naye password se login karein.',
        ]);
    }
}

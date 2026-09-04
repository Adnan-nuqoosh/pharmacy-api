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
     * Step 1 — POST /api/auth/forgot-password
     * Body: { "email": "user@example.com" }
     *
     * Generates a 6-digit code and sends it to the user's email address.
     * Security: The same success message is always returned, regardless of
     * whether the email address is registered.
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

            DB::table('password_reset_tokens')->where('email', $email)->delete();
            DB::table('password_reset_tokens')->insert([
                'email'      => $email,
                'token'      => Hash::make($code),
                'created_at' => now(),
            ]);

            // When MAIL_MAILER=log, the code will be available in
            // storage/logs/laravel.log for local testing.
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
     * Step 2 — POST /api/auth/verify-otp
     * Body: { "email": "...", "code": "483920" }
     *
     * Verifies the code without requesting a new password.
     * If the code is valid, a "reset_token" is returned for use in the next
     * reset-password step.
     *
     * The original 6-digit code is invalidated after successful verification,
     * preventing it from being reused or replayed.
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code'  => ['required', 'string'],
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $data['email'])
            ->first();

        if (! $record) {
            return response()->json([
                'success' => false,
                'message' => 'Koi code request nahi ki gayi ya wo istemaal ho chuki hai.',
            ], 422);
        }

        if (now()->diffInMinutes($record->created_at) > 15) {
            DB::table('password_reset_tokens')
                ->where('email', $data['email'])
                ->delete();

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

        // The code is valid. Replace it with a new and separate reset token.
        // The original 6-digit code can no longer be used.
        $resetToken = Str::random(60);

        DB::table('password_reset_tokens')
            ->where('email', $data['email'])
            ->update([
                'token'      => Hash::make($resetToken),
                'created_at' => now(), // Starts a new 10-minute window for Step 3.
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Code verified.',
            'data'    => ['reset_token' => $resetToken],
        ]);
    }

    /**
     * Step 3 — POST /api/auth/reset-password
     * Body: {
     *   "email": "...",
     *   "reset_token": "...",
     *   "password": "...",
     *   "password_confirmation": "..."
     * }
     *
     * This step requires the reset_token received from Step 2 instead of the
     * 6-digit code.
     *
     * The reset token remains valid for 10 minutes and is invalidated after
     * being used successfully.
     */
    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'       => ['required', 'email'],
            'reset_token' => ['required', 'string'],
            'password'    => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $data['email'])
            ->first();

        if (! $record) {
            return response()->json([
                'success' => false,
                'message' => 'Pehle OTP verify karein.',
            ], 422);
        }

        if (now()->diffInMinutes($record->created_at) > 10) {
            DB::table('password_reset_tokens')
                ->where('email', $data['email'])
                ->delete();

            return response()->json([
                'success' => false,
                'message' => 'Session expire ho gaya. Dobara shuru se try karein.',
            ], 422);
        }

        if (! Hash::check($data['reset_token'], $record->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid reset session. Dobara OTP verify karein.',
            ], 422);
        }

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User nahi mila.',
            ], 404);
        }

        // The password is automatically hashed through the model's "hashed" cast.
        $user->update([
            'password' => $data['password'],
        ]);

        DB::table('password_reset_tokens')
            ->where('email', $data['email'])
            ->delete();

        // Revoke all existing authentication tokens and sessions after the
        // password has been changed.
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password reset ho gaya. Ab naye password se login karein.',
        ]);
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * PATCH /api/profile — Profile screen: name/email/phone update.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name'  => ['sometimes', 'string', 'max:100'],
            'email' => ['sometimes', 'email', 'max:150', Rule::unique('users')->ignore($user->id)],
            'phone' => ['sometimes', 'string', 'max:20', 'regex:/^\+?[0-9]{9,15}$/', Rule::unique('users')->ignore($user->id)],
        ]);

        $user->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated.',
            'data'    => ['user' => $user->fresh()],
        ]);
    }

    /**
     * POST /api/profile/change-password
     * Body: { current_password, password, password_confirmation }
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($request->input('current_password'), $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password ghalat hai.',
            ], 422);
        }

        $user->update(['password' => $request->input('password')]); // hashed cast se hash ho jayega

        // Security: password change ke baad baqi devices ke tokens delete
        $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();

        return response()->json(['success' => true, 'message' => 'Password changed successfully.']);
    }
}

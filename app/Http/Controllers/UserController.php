<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use App\Jobs\SendVerificationCodeJob;

class UserController extends Controller
{

    public function user(Request $request)
    {
        $user = auth()->user();
        return response()->json([
            'success' => true,
            'data' => [
                'firstname'  => $user->firstname,
                'lastname'   => $user->lastname,
                'middlename' => $user->middlename,
                'email'      => $user->email,
                'phone'      => $user->phone,
                'business'   => $user->businesses->map(fn($business) => [
                    'name'   => $business->name,
                    'alt_id' => $business->alt_id,
                ]),
            ]
        ], 200);
    }

    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'firstname' => 'sometimes|required|string|max:255',
            'lastname' => 'sometimes|required|string|max:255',
            'middlename' => 'nullable|string|max:255',
            'phone' => 'sometimes|required|string|regex:/^\+234[0-9]{10}$/|unique:users,phone,' . $user->id,
        ], [
            'phone.regex' => 'The phone number must start with +234 and be followed by exactly 10 digits (e.g., +2348012345678)',
        ]);

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'middlename' => $user->middlename,
                'phone' => $user->phone,
            ],
        ], 200);
    }

    public function updatePassword(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed|max:255',
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect',
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully',
        ], 200);
    }
}

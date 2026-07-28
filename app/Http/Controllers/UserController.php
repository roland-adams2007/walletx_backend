<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use App\Jobs\SendVerificationCodeJob;

class UserController extends Controller
{

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);
        $user = User::where('email', $request->email)->first();
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid login information'
            ], 200);
        }
        if ($user->role != 'user') {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to access this page.'
            ], 200);
        }
        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'You are not active, Contact support!',
            ], 200);
        }

        if ($user->email_verified_at == null) {
            $response = $this->sendVerificationEmail($user);
            if ($response) {
                return $response;
            }
            return response()->json([
                'success'        => true,
                'verified'       => false,
                'email_verified' => false,
                'message'        => 'Please verify your account. A verification link has been sent to your email.',
            ], 200);
        }

        return $this->loginSuccess($user, $request);
    }

    public function register(Request $request)
    {
        $request->validate([
            "firstname" => "required|string|max:255",
            "lastname" => "required|string|max:255",
            "middlename" => "nullable|string|max:255",
            "email" => "required|email|unique:users,email|max:255",
            "password" => "required|string|min:8|confirmed|max:255",
            "phone" => "required|string|regex:/^\+234[0-9]{10}$/"
        ], [
            'phone.regex' => 'The phone number must start with +234 and be followed by exactly 10 digits (e.g., +2348012345678)'
        ]);

        $user = new User([
            "firstname" => $request->firstname,
            "lastname" => $request->lastname,
            "middlename" => $request->middlename,
            "email" => $request->email,
            "password" => Hash::make($request->password),
            "phone" => $request->phone,
            "role" => "user",
            "is_active" => 1,
            "email_verified_at" => null,
        ]);
        $user->save();

        $response = $this->sendVerificationEmail($user);

        if ($response) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'email_verified' => false,
            'message' => 'Please verify your account. A verification code has been sent to your email.',
            'email' => $user->email
        ], 200);
    }

    public function verify_email(Request $request)
    {
        $request->validate([
            "code" => "required|string|size:6",
            "email" => "required|email"
        ]);

        $cachedData = Cache::get('email_verify_' . $request->code);

        if (!$cachedData) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired verification code. Please request a new one.',
            ], 200);
        }

        if ($cachedData['email'] !== $request->email) {
            return response()->json([
                'success' => false,
                'message' => 'This verification code belongs to a different email address.'
            ], 200);
        }

        $user = User::find($cachedData['user_id']);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 200);
        }

        if ($user->email !== $request->email) {
            return response()->json([
                'success' => false,
                'message' => 'Email mismatch.'
            ], 200);
        }

        if ($user->email_verified_at) {
            return response()->json([
                'success' => false,
                'message' => 'Email is already verified. Please log in.',
            ], 200);
        }

        $user->email_verified_at = now();
        $user->save();

        Cache::forget('email_verify_' . $request->code);
        Cache::forget('email_verify_user_' . $user->id);
        Cache::forget('email_verify_sent_' . $user->id);
        Cache::forget('email_verify_sent_' . $user->id . '_expiry');

        return response()->json([
            'success' => true,
            'email_verified' => true,
            'message' => 'Email verified successfully! You can now log in.'
        ], 200);
    }

    public function resendVerification(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email'
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user->email_verified_at) {
            return response()->json([
                'success' => false,
                'message' => 'Email is already verified.'
            ], 200);
        }

        $response = $this->sendVerificationEmail($user);

        if ($response) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'message' => 'A new verification code has been sent to your email. It will expire in 15 minutes.'
        ]);
    }

    private function sendVerificationEmail(User $user)
    {
        $cacheKey = 'email_verify_sent_' . $user->id;
        $cooldown = 30;

        if (Cache::has($cacheKey)) {
            $expiryKey = $cacheKey . '_expiry';
            $secondsLeft = Cache::get($expiryKey) - now()->timestamp;
            $secondsLeft = max(1, $secondsLeft);

            return response()->json([
                'success' => false,
                'email_verified' => false,
                'message' => "A verification link was already sent. Please try again in a few seconds.",
            ], 429);
        }

        $existingToken = Cache::get('email_verify_user_' . $user->id);
        if (!$existingToken) {
            $code = $this->generateEmailVerifyCode($user);
            dispatch(new SendVerificationCodeJob($user, $code));
        }

        $expiryKey = $cacheKey . '_expiry';
        Cache::put($cacheKey, true, now()->addSeconds($cooldown));
        Cache::put($expiryKey, now()->addSeconds($cooldown)->timestamp, now()->addSeconds($cooldown));

        return null;
    }

    private function generateEmailVerifyCode(User $user): string
    {
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $userCacheKey = 'email_verify_user_' . $user->id;
        $oldCode = Cache::get($userCacheKey);
        if ($oldCode) {
            Cache::forget('email_verify_' . $oldCode);
        }

        $ttl = now()->addMinutes(15);
        Cache::put('email_verify_' . $code, [
            'user_id' => $user->id,
            'email' => $user->email
        ], $ttl);
        Cache::put($userCacheKey, $code, $ttl);

        return $code;
    }

    protected function loginSuccess(User $user, Request $request)
    {
        $token = $user->createToken('auth_token', ['*'], now()->addHours(24))->plainTextToken;

        return response()->json([
            'success'      => true,
            'token_type'   => 'Bearer',
            'verified'     => (bool) $user->email_verified_at,
            'access_token' => $token,
            'expires_at'   => now()->addHours(24)->toDateTimeString(),
            'user' => [
                'firstname'  => $user->firstname,
                'lastname'   => $user->lastname,
                'middlename' => $user->middlename,
                'email'      => $user->email,
                'phone'      => $user->phone,
                'business'   => $user->businesses->map(fn($business) => [
                    'name'   => $business->name,
                    'alt_id' => $business->alt_id,
                ]),
            ],
            'message' => 'Successfully logged in'
        ], 200);
    }

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

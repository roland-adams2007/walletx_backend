<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use App\Jobs\SendVerificationCodeJob;
use App\Models\AuthSession;
use App\Models\EmailToken;
use App\Models\RefreshToken;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->merge([
            'device_id' => $request->header('X-Device-Id'),
        ]);

        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_id' => 'required|string|max:255',
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
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

        if (is_null($user->email_verified_at)) {
            $response = $this->sendVerificationEmail($user);

            if ($response) {
                return $response;
            }

            return response()->json([
                'success' => true,
                'verified' => false,
                'email_verified' => false,
                'message' => 'Please verify your account. A verification link has been sent to your email.',
            ], 200);
        }

        return $this->loginSuccess($user, $request);
    }

    public function register(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            "firstname" => "required|string|max:255",
            "lastname" => "required|string|max:255",
            "middlename" => "nullable|string|max:255",
            "email" => "required|email|unique:users,email|max:255",
            "password" => "required|string|min:8|confirmed|max:255",
            "phone" => "required|string|regex:/^\+234[0-9]{10}$/"
        ], [
            'phone.regex' => 'The phone number must start with +234 and be followed by exactly 10 digits (e.g., +2348012345678)'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 200);
        }

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

        $token = EmailToken::findValid($request->code, 'verify_email');

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired verification code. Please request a new one.',
            ], 200);
        }

        if ($token->email !== $request->email) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired verification code. Please request a new one.'
            ], 200);
        }

        $user = User::find($token->user_id);

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

        $token->markUsed();
        $user->email_verified_at = now();
        $user->save();

        Cache::forget('email_verify_sent_' . $user->email);
        Cache::forget('email_verify_sent_' . $user->email . '_expiry');

        return response()->json([
            'success' => true,
            'email_verified' => true,
            'message' => 'Email verified successfully! You can now log in.'
        ], 200);
    }

    public function refresh(Request $request)
    {
        $plainToken = $request->cookie('refresh_token');

        if (!$plainToken) {
            return response()->json([
                'success' => false,
                'message' => 'Refresh token missing.'
            ], 401);
        }

        $refreshToken = RefreshToken::where('token', hash('sha256', $plainToken))
            ->whereNull('revoked_at')
            ->first();

        if (!$refreshToken || !$refreshToken->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid refresh token.'
            ], 401)->withoutCookie('refresh_token');
        }

        $session = $refreshToken->session;

        if (!$session || !$session->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Session expired.'
            ], 401)->withoutCookie('refresh_token');
        }

        return DB::transaction(function () use ($session, $refreshToken) {
            $user = $session->user;

            $refreshToken->update([
                'revoked_at' => now(),
                'last_used_at' => now(),
            ]);

            $plainRefreshToken = Str::random(64);
            RefreshToken::create([
                'session_id' => $session->id,
                'token' => hash('sha256', $plainRefreshToken),
                'expires_at' => now()->addDays(30),
            ]);

            $session->update([
                'last_activity' => now(),
            ]);

            $accessToken = $user->createToken(
                "session-{$session->id}",
                ['*'],
                now()->addDay()
            );

            return response()->json([
                'success' => true,
                'token_type' => 'Bearer',
                'access_token' => $accessToken->plainTextToken,
                'expires_in' =>  now()->addDay()->toIso8601String(),
            ])->cookie(
                'refresh_token',
                $plainRefreshToken,
                60 * 24 * 30,
                '/',
                null,
                app()->environment('production'),
                true,
                false,
                'Strict'
            );
        });
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
        $cacheKey = 'email_verify_sent_' . $user->email;
        $cooldown = 30;

        if (! Cache::add($cacheKey, true, now()->addSeconds($cooldown))) {
            $secondsLeft = max(1, Cache::get($cacheKey . '_expiry') - now()->timestamp);

            return response()->json([
                'success' => false,
                'email_verified' => false,
                'message' => "A verification link was already sent. Please try again in a few seconds.",
            ], 429);
        }

        Cache::put($cacheKey . '_expiry', now()->addSeconds($cooldown)->timestamp, now()->addSeconds($cooldown));

        $code = $this->generateEmailVerifyCode($user);
        dispatch(new SendVerificationCodeJob($user, $code));

        return null;
    }

    private function generateEmailVerifyCode(User $user): string
    {
        EmailToken::where('email', $user->email)
            ->where('type', 'verify_email')
            ->where('is_used', false)
            ->update(['is_used' => true]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        EmailToken::create([
            'email' => $user->email,
            'user_id' => $user->id,
            'type' => 'verify_email',
            'token_hash' => hash('sha256', $code),
            'expires_at' => now()->addMinutes(15),
        ]);

        return $code;
    }

    protected function loginSuccess(User $user, Request $request)
    {
        return DB::transaction(function () use ($user, $request) {
            $browser = $request->header('X-Browser', 'Unknown Browser');
            $platform = $request->header('X-Platform', 'Unknown OS');
            $deviceType = $request->header('X-Device-Type', 'Unknown Device');
            $deviceName = "{$browser} on {$platform}";

            $session = AuthSession::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'device_id' => $request->device_id,
                ],
                [
                    'device_name' => $deviceName,
                    'device_type' => $deviceType,
                    'platform' => $platform,
                    'browser' => $browser,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'last_activity' => now(),
                    'expires_at' => now()->addDays(30),
                    'revoked_at' => null,
                ]
            );

            $session->refreshTokens()->update([
                'revoked_at' => now(),
            ]);

            $newToken = $user->createToken(
                "session-{$session->id}",
                ['*'],
                now()->addDay()
            );

            $session->update([
                'access_token_id' => $newToken->accessToken->id,
            ]);

            $accessToken = $newToken->plainTextToken;

            $plainRefreshToken = Str::random(64);
            RefreshToken::create([
                'session_id' => $session->id,
                'token' => hash('sha256', $plainRefreshToken),
                'expires_at' => now()->addDays(30),
            ]);

            return response()->json([
                'success' => true,
                'token_type' => 'Bearer',
                'verified' => (bool) $user->email_verified_at,
                'access_token' => $accessToken,
                'expires_at' => now()->addDay()->toIso8601String(),
                // 'user' => [
                //     'firstname' => $user->firstname,
                //     'lastname' => $user->lastname,
                //     'middlename' => $user->middlename,
                //     'email' => $user->email,
                //     'phone' => $user->phone,
                //     'business' => $user->businesses->map(fn($business) => [
                //         'name' => $business->name,
                //         'alt_id' => $business->alt_id,
                //     ]),
                // ],
                'message' => 'Successfully logged in',
            ])->cookie(
                'refresh_token',
                $plainRefreshToken,
                60 * 24 * 30,
                '/',
                null,
                app()->environment('production'),
                true,
                false,
                'Strict'
            );
        });
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        $currentToken = $user->currentAccessToken();

        return DB::transaction(function () use ($user, $currentToken) {
            $session = AuthSession::where('user_id', $user->id)
                ->where('access_token_id', $currentToken->id)
                ->first();

            if ($session) {
                $session->refreshTokens()->update(['revoked_at' => now()]);
                $session->update(['revoked_at' => now()]);
            }

            $currentToken->delete();

            return response()->json([
                'success' => true,
                'message' => 'Successfully logged out',
            ])->withoutCookie('refresh_token');
        });
    }
}

<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

class RouteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(
                sha1(
                    $request->ip() . '|' .
                        $request->header('X-Device-Id') . '|' .
                        $request->userAgent()
                )
            );
        });

        RateLimiter::for('login', function (Request $request) {
            $deviceId = $request->header('X-Device-Id');

            return [
                Limit::perMinute(5)->by(
                    'email:' . strtolower($request->input('email'))
                ),

                Limit::perMinute(10)->by(
                    'ip:' . $request->ip()
                ),

                Limit::perMinute(10)->by(
                    'device:' . sha1($deviceId . '|' . $request->userAgent())
                ),

                Limit::perMinute(20)->by(
                    'combo:' . sha1(
                        strtolower($request->input('email')) . '|' .
                            $request->ip() . '|' .
                            $deviceId . '|' .
                            $request->userAgent()
                    )
                ),
            ];
        });

        RateLimiter::for('register', function (Request $request) {
            $deviceId = $request->header('X-Device-Id');

            return [
                Limit::perMinute(3)->by('ip:' . $request->ip()),
                Limit::perHour(10)->by('ip:' . $request->ip()),
                Limit::perHour(5)->by(
                    'device:' . sha1($deviceId . '|' . $request->userAgent())
                ),
            ];
        });

        RateLimiter::for('verify_email', function (Request $request) {
            $deviceId = $request->header('X-Device-Id');

            return [
                Limit::perMinute(5)->by(
                    'email:' . strtolower($request->input('email'))
                ),

                Limit::perMinute(10)->by(
                    'ip:' . $request->ip()
                ),

                Limit::perMinute(10)->by(
                    'device:' . sha1($deviceId . '|' . $request->userAgent())
                ),
            ];
        });

        RateLimiter::for('resend_verification', function (Request $request) {
            $deviceId = $request->header('X-Device-Id');

            return [
                Limit::perMinute(3)->by(
                    'email:' . strtolower($request->input('email'))
                ),

                Limit::perHour(5)->by(
                    'email:' . strtolower($request->input('email'))
                ),

                Limit::perMinute(5)->by(
                    'ip:' . $request->ip()
                ),

                Limit::perHour(5)->by(
                    'device:' . sha1($deviceId . '|' . $request->userAgent())
                ),
            ];
        });

        RateLimiter::for('refresh', function (Request $request) {
            return [
                Limit::perMinute(30)->by(
                    sha1(
                        $request->ip() . '|' .
                            $request->header('X-Device-Id') . '|' .
                            $request->userAgent()
                    )
                ),
            ];
        });
    }
}

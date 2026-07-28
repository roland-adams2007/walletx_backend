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
            return Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            return [
                Limit::perMinute(5)->by('email:' . $request->input('email')),
                Limit::perMinute(10)->by('ip:' . $request->ip()),
            ];
        });

        RateLimiter::for('register', function (Request $request) {
            return [
                Limit::perMinute(3)->by('ip:' . $request->ip()),
                Limit::perHour(10)->by('ip:' . $request->ip()),
            ];
        });

        RateLimiter::for('verify_email', function (Request $request) {
            return [
                Limit::perMinute(5)->by('email:' . $request->input('email')),
                Limit::perMinute(10)->by('ip:' . $request->ip()),
            ];
        });

        RateLimiter::for('resend_verification', function (Request $request) {
            return [
                Limit::perMinute(3)->by('email:' . $request->input('email')),
                Limit::perHour(5)->by('email:' . $request->input('email')),
                Limit::perMinute(5)->by('ip:' . $request->ip()),
            ];
        });
    }
}

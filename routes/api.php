<?php

use App\Http\Controllers\api\InlineController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WalletController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [UserController::class, 'login'])
    ->middleware('throttle:login');
Route::post('/register', [UserController::class, 'register'])
    ->middleware('throttle:register');
Route::post('/verify-email', [UserController::class, 'verify_email'])
    ->middleware('throttle:verify_email');
Route::post('/resend-verification', [UserController::class, 'resendVerification'])
    ->middleware('throttle:resend_verification');
Route::post('/transaction/initialize/inline', [InlineController::class, 'initialise'])->middleware('throttle:api');
Route::post('/payments/charge', [InlineController::class, 'charge'])->middleware('throttle:api');
Route::get('/payments/verify/{reference}', [InlineController::class, 'verify'])->middleware('throttle:api');

Route::middleware([
    'auth:sanctum',
    'throttle:api',
    'account.verified',
    'account.active'
])->group(function () {
    Route::get('/user', [UserController::class, 'user']);
    Route::put('/user/profile', [UserController::class, 'updateProfile']);
    Route::put('/user/password', [UserController::class, 'updatePassword']);
    Route::post('/business', [BusinessController::class, 'create']);
    Route::get('/business', [BusinessController::class, 'getBusinessDetails']);
    Route::get('/business/balance', [BusinessController::class, 'getBusinessBalance']);
    Route::put('/business', [BusinessController::class, 'updateBusinessDetails']);
    Route::put('/business/preference', [BusinessController::class, 'updatePreference']);
    Route::post('/business/upgrade', [BusinessController::class, 'upgradeToRegistered']);
    Route::get('/business/api-keys', [BusinessController::class, 'getApiKeys']);
    Route::post('/business/api-keys/rotate', [BusinessController::class, 'rotateApiKeys']);
});

Route::fallback(fn() => response()->json([
    'data'    => [],
    'success' => false,
    'message' => 'Invalid Route',
], 404));
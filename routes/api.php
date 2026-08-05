<?php

use App\Http\Controllers\api\InlineController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:login');

Route::post('/auth/register', [AuthController::class, 'register'])
    ->middleware('throttle:register');

Route::post('/auth/verify-email', [AuthController::class, 'verify_email'])
    ->middleware('throttle:verify_email');

Route::post('/auth/resend-verification', [AuthController::class, 'resendVerification'])
    ->middleware('throttle:resend_verification');

Route::post('/auth/refresh', [AuthController::class, 'refresh'])
    ->middleware('throttle:refresh');

Route::post('/auth/logout', [AuthController::class, 'logout']);

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
    Route::patch('/user/profile', [UserController::class, 'updateProfile']);
    Route::put('/user/password', [UserController::class, 'updatePassword']);

    Route::post('/business', [BusinessController::class, 'create']);
    Route::get('/business', [BusinessController::class, 'getBusinessDetails']);
    Route::get('/business/balance', [BusinessController::class, 'getBusinessBalance']);
    Route::put('/business', [BusinessController::class, 'updateBusinessDetails']);
    Route::put('/business/settlement-bank', [BusinessController::class, 'updateSettlementBank']);
    Route::put('/business/preference', [BusinessController::class, 'updatePreference']);
    Route::post('/business/upgrade', [BusinessController::class, 'upgradeToRegistered']);
    Route::get('/business/api-keys', [BusinessController::class, 'getApiKeys']);
    Route::post('/business/api-keys/rotate', [BusinessController::class, 'rotateApiKeys']);
    Route::put('/business/api-keys/webhook', [BusinessController::class, 'updateWebhook']);
    Route::put('/business/api-keys/ip-whitelist', [BusinessController::class, 'updateIpWhitelist']);
    Route::post('/business/deactivate', [BusinessController::class, 'deactivateBusiness']);

    Route::get('/customers', [CustomerController::class, 'index']);
    Route::get('/customers/{cus_id}', [CustomerController::class, 'show']);
    Route::post('/customers', [CustomerController::class, 'store']);
    Route::put('/customers/{cus_id}', [CustomerController::class, 'update']);
    Route::patch('/customers/{cus_id}/blacklist', [CustomerController::class, 'updateBlacklist']);

    Route::post('/uploads', [UploadController::class, 'store']);
    Route::get('/banks', [BankController::class, 'fetchAll']);
    Route::post('/bank/verify', [BankController::class, 'verifyBankAccount']);
});

Route::fallback(fn() => response()->json([
    'data'    => [],
    'success' => false,
    'message' => 'Invalid Route',
], 404));

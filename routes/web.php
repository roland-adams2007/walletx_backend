<?php

use App\Http\Controllers\api\CheckoutController;
use Illuminate\Support\Facades\Route;

Route::get('/checkout/{accessCode}', [CheckoutController::class, 'index'])->name('checkout.show');

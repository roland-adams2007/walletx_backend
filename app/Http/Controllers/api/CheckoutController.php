<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;

class CheckoutController extends Controller
{
    public function index($accessCode)
    {
        if (!$accessCode) {
            return view('checkout.index', [
                'transaction' => null,
                'error' => 'Invalid transaction.',
            ]);
        }

        $transaction = Transaction::where('access_code', $accessCode)->first();

        if (!$transaction) {
            return view('checkout.index', [
                'transaction' => null,
                'error' => 'Invalid transaction.',
            ]);
        }

        if ($transaction->status !== 'pending') {
            $message = match ($transaction->status) {
                'success' => 'This transaction has already been completed.',
                'failed' => 'This transaction has failed.',
                'abandoned' => 'This transaction has been abandoned.',
                'reversed' => 'This transaction has been reversed.',
                default => 'This transaction is no longer available for payment.',
            };

            return view('checkout.index', [
                'transaction' => null,
                'error' => $message,
            ]);
        }

        if ($transaction->expires_at && $transaction->expires_at->isPast()) {
            return view('checkout.index', [
                'transaction' => null,
                'error' => 'This transaction has expired.',
            ]);
        }

        $business = $transaction->business;

        $transactionData = [
            'customer_email' => $transaction->customer?->email,
            'reference' => $transaction->reference,
            'sub_amount' => $transaction->sub_amount,
            'fee' => $transaction->fee,
            'amount' => $transaction->amount,
            'status' => $transaction->status,
            'access_code' => $transaction->access_code,
            'expires_at' => $transaction->expires_at?->toIso8601String(),
            'callback_url' => $transaction->meta['callback_url'] ?? null,
            'merchant' => [
                'name' => $business->name,
                'logo' => $business->logo_url,
            ],
        ];

        return view('checkout.index', [
            'transaction' => $transactionData,
            'error' => null,
        ]);
    }

    public function cancel($accessCode)
    {
        $transaction = Transaction::where('access_code', $accessCode)->first();

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid transaction.',
            ], 404);
        }

        if ($transaction->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'This transaction can no longer be cancelled.',
            ], 422);
        }

        $transaction->update(['status' => 'abandoned']);

        return response()->json([
            'success' => true,
            'message' => 'Transaction cancelled.',
            'redirect_url' => $transaction->meta['callback_url'] ?? null,
        ]);
    }
}

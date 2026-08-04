<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;


class BankController extends Controller
{
    public function fetchAll(): JsonResponse
    {
        return response()->json(Bank::allCached());
    }


    public function verifyBankAccount(Request $request): JsonResponse
    {
        $request->validate([
            'account_number' => 'required|string',
            'bank_code' => 'required|string',
        ]);

        $response = Http::withToken(config('services.paystack.secret'))
            ->get('https://api.paystack.co/bank/resolve', [
                'account_number' => $request->account_number,
                'bank_code' => $request->bank_code,
            ]);

        if ($response->failed()) {
            return response()->json([
                'success' => false,
                'message' => $response->json('message', 'Unable to verify bank account'),
            ], $response->status());
        }

        $data = $response->json('data');

        return response()->json([
            'success' => true,
            'message' => 'Bank account verified successfully',
            'data' => [
                'account_number' => $data['account_number'],
                'account_name' => $data['account_name'],
            ],
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'business_id' => 'required|exists:businesses,alt_id',
            'cus_id' => 'nullable|string|exists:customers,cus_id',
            'reference' => 'nullable|string|max:255',
            'customer' => 'nullable|string|max:255',
            'min_amount' => 'nullable|integer|min:0',
            'max_amount' => 'nullable|integer|min:0',
            'status' => 'nullable|string|in:success,pending,failed,reversed',
            'channel' => 'nullable|string|max:50',
            'transaction_type' => 'nullable|string|max:50',
            'date_type' => 'nullable|string|in:custom,today,this_week,this_month,this_year',
            'start_date' => 'nullable|required_if:date_type,custom|date',
            'end_date' => 'nullable|required_if:date_type,custom|date|after_or_equal:start_date',
        ]);

        if (
            !empty($validated['min_amount']) &&
            !empty($validated['max_amount']) &&
            $validated['max_amount'] < $validated['min_amount']
        ) {
            return response()->json([
                'success' => false,
                'message' => 'max_amount must be greater than or equal to min_amount',
            ], 422);
        }

        $business = Business::where('alt_id', $validated['business_id'])->first();
        if (!$business) {
            return response()->json([
                'success' => false,
                'message' => 'Business not found',
            ], 404);
        }

        $query = Transaction::query()
            ->leftJoin('customers', 'customers.id', '=', 'transactions.customer_id')
            ->select(
                'transactions.status',
                'customers.email as customer_email',
                'transactions.reference',
                'transactions.channel',
                'transactions.created_at as date',
                'transactions.amount',
            );

        if (!empty($validated['cus_id'])) {
            $query->where('customers.cus_id', $validated['cus_id']);
        }

        if (!empty($validated['reference'])) {
            $query->where('transactions.reference', 'like', '%' . $validated['reference'] . '%');
        }

        if (!empty($validated['customer'])) {
            $query->where(function ($q) use ($validated) {
                $q->where('customers.email', 'like', '%' . $validated['customer'] . '%')
                    ->orWhere('customers.cus_id', $validated['customer']);
            });
        }

        if (!empty($validated['min_amount'])) {
            $query->where('transactions.amount', '>=', (int) round($validated['min_amount'] * 100));
        }

        if (!empty($validated['max_amount'])) {
            $query->where('transactions.amount', '<=', (int) round($validated['max_amount'] * 100));
        }

        if (!empty($validated['date_type'])) {
            if ($validated['date_type'] === 'custom') {
                $query->whereBetween('transactions.created_at', [$validated['start_date'], $validated['end_date']]);
            } else if ($validated['date_type'] === 'today') {
                $query->whereDate('transactions.created_at', now()->toDateString());
            } else if ($validated['date_type'] === 'this_week') {
                $query->whereBetween('transactions.created_at', [now()->startOfWeek(), now()->endOfWeek()]);
            } else if ($validated['date_type'] === 'this_month') {
                $query->whereMonth('transactions.created_at', now()->month);
            } else if ($validated['date_type'] === 'this_year') {
                $query->whereYear('transactions.created_at', now()->year);
            }
        }

        if (!empty($validated['status'])) {
            $query->where('transactions.status', $validated['status']);
        }

        if (!empty($validated['channel'])) {
            $query->where('transactions.channel', $validated['channel']);
        }

        if (!empty($validated['transaction_type'])) {
            $query->where('transactions.transaction_type', $validated['transaction_type']);
        }

        $transactions = $query
            ->where('transactions.business_id', $business->id)
            ->orderByDesc('transactions.id')
            ->paginate(20);

        $transactions->getCollection()->transform(function ($item) {
            if ($item->date) {
                $item->date = Carbon::parse($item->date)->toIso8601String();
            }
            if (isset($item->amount)) {
                $item->amount = $item->amount / 100;
            }
            return $item;
        });

        return response()->json([
            'success' => true,
            'message' => 'Transactions retrieved successfully',
            'data' => $transactions->items(),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'last_page' => $transactions->lastPage(),
            ],
        ]);
    }

    /**
     * Display a specific transaction by reference
     *
     * @param string $reference
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($reference)
    {
        $transaction = Transaction::with(['customer', 'business'])
            ->where('reference', $reference)
            ->first();

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found',
            ], 404);
        }

        $data = [
            'reference' => $transaction->reference,
            'amount' => $transaction->amount / 100, // Convert from kobo to naira
            'fee' => $transaction->fee / 100, // Convert from kobo to naira
            'net_amount' => $transaction->net_amount / 100, // Convert from kobo to naira
            'channel' => $transaction->channel,
            'transaction_type' => $transaction->transaction_type,
            'status' => $transaction->status,
            'date' => $transaction->created_at ? $transaction->created_at?->toIso8601String() : null,
            'paid_at' => $transaction->paid_at ? $transaction->paid_at?->toIso8601String() : null,
            'narration' => $transaction->description,
            'customer' => $transaction->customer ? [
                'name' => $transaction->customer->name,
                'email' => $transaction->customer->email,
                'cus_id' => $transaction->customer->cus_id,
            ] : null,
            'authorization' => $transaction->authorization,
            'ip_address' => $transaction->ip_address,
            'device' => $transaction->device,
            'user_agent' => $transaction->user_agent,
            'balance_before' => $transaction->balance_before ? $transaction->balance_before / 100 : null, // Convert from kobo to naira
            'balance_after' => $transaction->balance_after ? $transaction->balance_after / 100 : null, // Convert from kobo to naira
            'gateway_response' => $transaction->gateway_response,
            'meta' => $transaction->meta,
        ];

        return response()->json([
            'success' => true,
            'message' => 'Transaction retrieved successfully',
            'data' => $data,
        ]);
    }
}
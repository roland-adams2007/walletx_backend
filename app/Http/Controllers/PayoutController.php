<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Payout;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PayoutController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'business_id' => 'required|exists:businesses,alt_id',
            'reference' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:pending,processing,success,failed,reversed',
            'source' => 'nullable|string|in:manual,automatic',
            'min_amount' => 'nullable|integer|min:0',
            'max_amount' => 'nullable|integer|min:0',
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

        $business = $user->businesses()->where('alt_id', $validated['business_id'])->first();

        if (!$business) {
            return response()->json([
                'success' => false,
                'message' => 'Business not found',
            ], 404);
        }

        $query = Payout::query()
            ->where('business_id', $business->id)
            ->select(
                'reference',
                'status',
                'source',
                'amount',
                'fee',
                'bank_code',
                'account_number',
                'account_name',
                'created_at',
                'processed_at',
            );

        if (!empty($validated['reference'])) {
            $query->where('reference', 'like', '%' . $validated['reference'] . '%');
        }

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['source'])) {
            $query->where('source', $validated['source']);
        }

        if (!empty($validated['min_amount'])) {
            $query->where('amount', '>=', (int) round($validated['min_amount'] * 100));
        }

        if (!empty($validated['max_amount'])) {
            $query->where('amount', '<=', (int) round($validated['max_amount'] * 100));
        }

        if (!empty($validated['date_type'])) {
            if ($validated['date_type'] === 'custom') {
                $query->whereBetween('created_at', [$validated['start_date'], $validated['end_date']]);
            } else if ($validated['date_type'] === 'today') {
                $query->whereDate('created_at', now()->toDateString());
            } else if ($validated['date_type'] === 'this_week') {
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
            } else if ($validated['date_type'] === 'this_month') {
                $query->whereMonth('created_at', now()->month);
            } else if ($validated['date_type'] === 'this_year') {
                $query->whereYear('created_at', now()->year);
            }
        }

        $payouts = $query
            ->orderByDesc('id')
            ->paginate(20);

        $payouts->getCollection()->transform(function ($item) {
            $item->amount = $item->amount / 100;
            $item->fee = $item->fee / 100;
            $item->created_at = $item->created_at ? Carbon::parse($item->created_at)->toIso8601String() : null;
            $item->processed_at = $item->processed_at ? Carbon::parse($item->processed_at)->toIso8601String() : null;
            return $item;
        });

        return response()->json([
            'success' => true,
            'message' => 'Payouts retrieved successfully',
            'data' => $payouts->items(),
            'meta' => [
                'current_page' => $payouts->currentPage(),
                'per_page' => $payouts->perPage(),
                'total' => $payouts->total(),
                'last_page' => $payouts->lastPage(),
            ],
        ]);
    }

    public function show($reference)
    {
        $user = auth()->user();

        $payout = Payout::where('reference', $reference)->first();

        if (!$payout) {
            return response()->json([
                'success' => false,
                'message' => 'Payout not found',
            ], 404);
        }

        $business = $user->businesses()->where('id', $payout->business_id)->first();

        if (!$business) {
            return response()->json([
                'success' => false,
                'message' => 'Payout not found',
            ], 404);
        }

        $data = [
            'reference' => $payout->reference,
            'status' => $payout->status,
            'source' => $payout->source,
            'amount' => $payout->amount / 100,
            'fee' => $payout->fee / 100,
            'bank_code' => $payout->bank_code,
            'account_number' => $payout->account_number,
            'account_name' => $payout->account_name,
            'narration' => $payout->narration,
            'gateway_reference' => $payout->gateway_reference,
            'gateway_response' => $payout->gateway_response,
            'failure_reason' => $payout->failure_reason,
            'retry_count' => $payout->retry_count,
            'ip_address' => $payout->ip_address,
            'device' => $payout->device,
            'user_agent' => $payout->user_agent,
            'created_at' => $payout->created_at ? $payout->created_at->toIso8601String() : null,
            'processed_at' => $payout->processed_at ? $payout->processed_at->toIso8601String() : null,
            'meta' => $payout->meta,
        ];

        return response()->json([
            'success' => true,
            'message' => 'Payout retrieved successfully',
            'data' => $data,
        ]);
    }
}

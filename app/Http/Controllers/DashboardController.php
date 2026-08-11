<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function revenue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'business_id' => 'required|string',
            'date_type' => 'nullable|string|in:all,today,this_week,this_month,this_year',
        ]);

        $business = auth()->user()->businesses()->where('alt_id', $validated['business_id'])->first();

        if (!$business) {
            return response()->json([
                'success' => false,
                'message' => 'Business not found',
            ], 404);
        }

        $query = Transaction::query()
            ->where('business_id', $business->id)
            ->where('type', 'credit') // adjust to your actual "money in" value
            ->where('status', 'success');

        $this->applyDateFilter($query, $validated);

        $revenue = $query->sum('amount');

        return response()->json([
            'success' => true,
            'data' => [
                'revenue' => $revenue / 100,
            ],
        ]);
    }

    public function rate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'business_id' => 'required|string',
        ]);

        $business = auth()->user()->businesses()->where('alt_id', $validated['business_id'])->first();

        if (!$business) {
            return response()->json([
                'success' => false,
                'message' => 'Business not found',
            ], 404);
        }

        $successCount = Transaction::query()
            ->where('business_id', $business->id)
            ->where('status', 'success')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $failedCount = Transaction::query()
            ->where('business_id', $business->id)
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $total = $successCount + $failedCount;

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $total,
                'success_count' => $successCount,
                'failed_count' => $failedCount,
                'success_rate' => $total > 0 ? round(($successCount / $total) * 100, 2) : 0,
                'failed_rate' => $total > 0 ? round(($failedCount / $total) * 100, 2) : 0,
            ],
        ]);
    }

    private function applyDateFilter($query, array $validated): void
    {
        if (empty($validated['date_type']) || $validated['date_type'] === 'all') {
            return;
        }

        switch ($validated['date_type']) {
            case 'today':
                $query->whereDate('transactions.created_at', now()->toDateString());
                break;
            case 'this_week':
                $query->whereBetween('transactions.created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'this_month':
                $query->whereMonth('transactions.created_at', now()->month);
                break;
            case 'this_year':
                $query->whereYear('transactions.created_at', now()->year);
                break;
        }
    }
}

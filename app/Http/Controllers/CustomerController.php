<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Customer;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'email' => 'nullable|email|max:255',
            'business_id' => 'required|exists:businesses,alt_id',
        ]);

        if (!empty($validated['business_id'])) {
            $business = Business::where('alt_id', $validated['business_id'])->first();
            if (!$business) {
                return response()->json([
                    'success' => false,
                    'message' => 'Business not found',
                ], 404);
            }
        }

        $query = Customer::query();

        if (!empty($validated['email'])) {
            $query->where('email', 'like', '%' . $validated['email'] . '%');
        }

        $customers = $query->where('business_id', $business->id)->orderByDesc('id')->paginate(20);

        $data = $customers->getCollection()->map(function ($customer) {
            return [
                'cus_id'       => $customer->cus_id,
                'name'         => $customer->name,
                'phone'        => $customer->phone,
                'email'        => $customer->email,
                'is_blacklist' => $customer->is_blacklist,
                'date_added'   => $customer->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Customers retrieved successfully',
            'data'    => $data,
            'meta'    => [
                'current_page' => $customers->currentPage(),
                'per_page'     => $customers->perPage(),
                'total'        => $customers->total(),
                'last_page'    => $customers->lastPage(),
            ],
        ], 200);
    }

    public function show($cusId)
    {
        $customer = Customer::with([
            'transactions' => function ($query) {
                $query->latest()->limit(5);
            }
        ])->where('cus_id', $cusId)->first();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found',
            ], 404);
        }

        $successfulTransactions = $customer->transactions()->where('status', 'success');
        $totalTransactions = $customer->transactions();

        return response()->json([
            'success' => true,
            'message' => 'Customer retrieved successfully',
            'data' => [
                'cus_id' => $customer->cus_id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'is_blacklist' => $customer->is_blacklist,
                'transactions' => [
                    'successful_transactions' => $successfulTransactions->count(),
                    'total_transactions' => $totalTransactions->count(),
                    'total_spent' => $successfulTransactions->sum('amount') / 100,
                    'recent_transactions' => $customer->transactions->map(function ($transaction) {
                        return [
                            'amount' => $transaction->amount / 100,
                            'channel' => $transaction->channel,
                            'status' => $transaction->status,
                            'created_at' => $transaction->created_at?->toIso8601String(),
                        ];
                    })->values(),
                ],
            ],
        ], 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'business_id' => 'required|exists:businesses,alt_id',
            'first_name'  => 'required|string|max:255',
            'last_name'   => 'required|string|max:255',
            'phone'       => 'required|string|max:20',
            'email'       => 'required|email|max:255',
        ]);

        $business = Business::where('alt_id', $validated['business_id'])->first();

        if (!$business) {
            return response()->json([
                'success' => false,
                'message' => 'Business not found',
            ], 404);
        }

        $fullName = preg_replace('/\s+/', ' ', trim($validated['first_name'] . ' ' . $validated['last_name']));
        $cusId = 'cus_' . Str::random(12);

        try {
            $customer = Customer::create([
                'business_id'  => $business->id,
                'cus_id'       => $cusId,
                'name'         => $fullName,
                'phone'        => $validated['phone'],
                'email'        => $validated['email'],
                'is_blacklist' => false,
            ]);
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                $existingCustomer = Customer::where('business_id', $business->id)
                    ->where('email', $validated['email'])
                    ->first();

                if ($existingCustomer) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Customer already exists',
                        'data'    => [
                            'cus_id'     => $existingCustomer->cus_id,
                            'name'       => $existingCustomer->name,
                            'phone'      => $existingCustomer->phone,
                            'email'      => $existingCustomer->email,
                            'created_at' => $existingCustomer->created_at?->toIso8601String(),
                            'updated_at' => $existingCustomer->updated_at?->toIso8601String(),
                        ],
                    ], 409);
                }
            }
            throw $e;
        }

        return response()->json([
            'success' => true,
            'message' => 'Customer created successfully',
            'data'    => [
                'cus_id'     => $customer->cus_id,
                'name'       => $customer->name,
                'phone'      => $customer->phone,
                'email'      => $customer->email,
                'created_at' => $customer->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function update(Request $request, $cusId)
    {
        $customer = Customer::where('cus_id', $cusId)->first();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found',
            ], 404);
        }

        $validated = $request->validate([
            'firstname' => 'required|string|max:255',
            'lastname'  => 'required|string|max:255',
            'phone'     => 'required|string|max:20',
        ]);

        $fullName = preg_replace('/\s+/', ' ', trim($validated['firstname'] . ' ' . $validated['lastname']));

        $customer->update([
            'name'  => $fullName,
            'phone' => $validated['phone'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Customer updated successfully',
            'data'    => [
                'cus_id'       => $customer->cus_id,
                'name'         => $customer->name,
                'email'        => $customer->email,
                'phone'        => $customer->phone,
                'is_blacklist' => $customer->is_blacklist,
            ],
        ], 200);
    }

    public function updateBlacklist(Request $request, $cusId)
    {
        $customer = Customer::where('cus_id', $cusId)->first();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found',
            ], 404);
        }

        $validated = $request->validate([
            'is_blacklist' => 'required|boolean',
        ]);

        $customer->update([
            'is_blacklist' => $validated['is_blacklist'],
        ]);

        return response()->json([
            'success' => true,
            'message' => $validated['is_blacklist']
                ? 'Customer added to blacklist'
                : 'Customer removed from blacklist',
            'data' => [
                'cus_id'       => $customer->cus_id,
                'is_blacklist' => $customer->is_blacklist,
            ],
        ], 200);
    }
}

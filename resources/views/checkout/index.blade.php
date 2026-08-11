<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $error ? 'Invalid Transaction' : $transaction['merchant']['name'] . ' — Checkout' }}</title>
    <link rel="stylesheet" href="{{ asset('css/checkout.css') }}">
</head>

<body>

    <div class="wx-page">

        @if ($error)
            <div class="wx-error-card">
                <div class="wx-error-card-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round">
                        <circle cx="12" cy="12" r="9" />
                        <path d="M12 8v5M12 16h.01" />
                    </svg>
                </div>
                <h2 class="wx-error-card-title">Invalid Transaction</h2>
                <p class="wx-error-card-message">{{ $error }}</p>
            </div>
        @else
            <div class="wx-modal" id="wx-card" data-reference="{{ $transaction['reference'] }}"
                data-access-code="{{ $transaction['access_code'] }}" data-amount="{{ $transaction['amount'] }}"
                data-expires-at="{{ $transaction['expires_at'] }}"
                data-cancel-url="{{ route('checkout.cancel', $transaction['access_code']) }}"
                data-charge-url="{{ url('/api/payments/charge') }}"
                data-callback-url="{{ $transaction['callback_url'] }}">
                <div class="wx-grabber"></div>

                <button class="wx-close" id="wx-cancel-btn" aria-label="Cancel transaction">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round">
                        <path d="M18 6 6 18M6 6l12 12" />
                    </svg>
                </button>

                <div class="wx-sidebar" id="wx-sidebar">
                    <p class="wx-sidebar-label">PAY WITH</p>
                    <div class="wx-method active" data-tab="card">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                            stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="5" width="20" height="14" rx="2.5" />
                            <path d="M2 10h20" />
                        </svg>
                        <span>Card</span>
                    </div>
                    <div class="wx-method" data-tab="transfer">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="M7 10 3 6l4-4M3 6h13a4 4 0 0 1 4 4v1M17 14l4 4-4 4M21 18H8a4 4 0 0 1-4-4v-1" />
                        </svg>
                        <span>Transfer</span>
                    </div>
                </div>

                <div class="wx-content">
                    <div id="wx-form-wrap">

                        <div class="wx-topbar">
                            <div class="wx-brandmark-row">
                                @if ($transaction['merchant']['logo'])
                                    <img class="wx-brandmark" id="wx-brandmark"
                                        src="{{ $transaction['merchant']['logo'] }}"
                                        alt="{{ $transaction['merchant']['name'] }}">
                                @endif
                            </div>
                            <div class="wx-topbar-right">
                                @if (!empty($transaction['customer_email']))
                                    <p class="wx-email">{{ $transaction['customer_email'] }}</p>
                                @endif
                                <p class="wx-pay-line">Pay
                                    <strong>₦{{ number_format($transaction['amount'] / 100, 2) }}</strong>
                                </p>
                                @if ($transaction['expires_at'])
                                    <p class="wx-timer" id="wx-timer">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                            stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="12" cy="12" r="9" />
                                            <path d="M12 7v5l3.5 2" />
                                        </svg>
                                        <span id="wx-timer-text">Expires in --:--</span>
                                    </p>
                                @endif
                            </div>
                        </div>

                        <div class="wx-panel" data-panel="card">
                            <p class="wx-panel-title">Pay with card</p>
                            <p class="wx-transfer-note">Test mode: a demo card is prefilled, no real card is charged.
                            </p>

                            <div class="wx-field">
                                <label for="wx-card-number">Card number</label>
                                <div class="wx-input-wrap">
                                    <input id="wx-card-number" placeholder="0000 0000 0000 0000" maxlength="19"
                                        inputmode="numeric" autocomplete="cc-number" value="4242 4242 4242 4242">
                                    <span class="wx-card-brand" id="wx-card-brand">VISA</span>
                                </div>
                            </div>
                            <div class="wx-row">
                                <div class="wx-field">
                                    <label for="wx-exp-m">Expiry</label>
                                    <div style="display:flex; gap:6px;">
                                        <input id="wx-exp-m" placeholder="MM" maxlength="2" inputmode="numeric"
                                            autocomplete="cc-exp-month" value="12">
                                        <input id="wx-exp-y" placeholder="YY" maxlength="2" inputmode="numeric"
                                            autocomplete="cc-exp-year" value="30">
                                    </div>
                                </div>
                                <div class="wx-field">
                                    <label for="wx-cvv">CVV</label>
                                    <input id="wx-cvv" placeholder="123" maxlength="3" inputmode="numeric"
                                        autocomplete="cc-csc" value="123">
                                </div>
                            </div>
                            <p class="wx-error" id="wx-card-error"></p>

                            <div id="wx-card-form-actions">
                                <button class="wx-btn" id="wx-pay-btn"><span id="wx-pay-btn-text">Pay <span
                                            class="wx-amount">₦{{ number_format($transaction['amount'] / 100, 2) }}</span></span></button>
                            </div>

                            <div id="wx-card-outcome" style="display:none;">
                                <p class="wx-panel-title" style="font-size:14px;">Choose an outcome to simulate</p>
                                <div style="display:flex; gap:8px; margin:10px 0;">
                                    <button class="wx-outcome-btn" data-outcome="success"
                                        style="flex:1;padding:10px;border-radius:8px;border:1.5px solid #16a34a;color:#16a34a;background:transparent;font-weight:600;cursor:pointer;">Success</button>
                                    <button class="wx-outcome-btn" data-outcome="pending"
                                        style="flex:1;padding:10px;border-radius:8px;border:1.5px solid #d97706;color:#d97706;background:transparent;font-weight:600;cursor:pointer;">Pending</button>
                                    <button class="wx-outcome-btn" data-outcome="failed"
                                        style="flex:1;padding:10px;border-radius:8px;border:1.5px solid #dc2626;color:#dc2626;background:transparent;font-weight:600;cursor:pointer;">Failed</button>
                                </div>
                            </div>
                        </div>

                        <div class="wx-panel" data-panel="transfer" style="display:none;">
                            <p class="wx-panel-title">Transfer ₦{{ number_format($transaction['amount'] / 100, 2) }}
                                to {{ $transaction['merchant']['name'] }}</p>
                            <p class="wx-transfer-note">Test mode: no real account number is generated. Choose an
                                outcome to simulate the transfer.</p>

                            <div id="wx-transfer-outcome" style="display:flex; gap:8px; margin:16px 0;">
                                <button class="wx-outcome-btn" data-outcome="success"
                                    style="flex:1;padding:10px;border-radius:8px;border:1.5px solid #16a34a;color:#16a34a;background:transparent;font-weight:600;cursor:pointer;">Success</button>
                                <button class="wx-outcome-btn" data-outcome="pending"
                                    style="flex:1;padding:10px;border-radius:8px;border:1.5px solid #d97706;color:#d97706;background:transparent;font-weight:600;cursor:pointer;">Pending</button>
                                <button class="wx-outcome-btn" data-outcome="failed"
                                    style="flex:1;padding:10px;border-radius:8px;border:1.5px solid #dc2626;color:#dc2626;background:transparent;font-weight:600;cursor:pointer;">Failed</button>
                            </div>

                            <p class="wx-error" id="wx-transfer-error"></p>
                        </div>

                    </div>

                    <div id="wx-result-wrap" style="display:none;"></div>

                    <p class="wx-secure">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                            stroke-linecap="round" stroke-linejoin="round">
                            <rect x="4" y="10" width="16" height="11" rx="2" />
                            <path d="M8 10V7a4 4 0 0 1 8 0v3" />
                        </svg>
                        Secured by <strong>WalletX</strong>
                    </p>
                </div>
            </div>

        @endif

    </div>

    <script src="{{ asset('js/checkout.js') }}"></script>
</body>

</html>

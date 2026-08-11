@extends('emails.receipts.layout')

@section('content')
<table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
    <tr>
        <td align="center" style="padding-bottom:28px;">
            <p style="margin:0 0 6px;color:#5c6660;font-size:13.5px;line-height:1.6;">
                {{ $customerEmail }} just paid
            </p>
            <p class="font-display"
                style="margin:0 0 20px;color:#172420;font-size:20px;font-style:italic;font-weight:500;">
                {{ $business->name }}
            </p>
            <p class="font-mono amount-text"
                style="margin:0;color:#172420;font-size:36px;font-weight:700;letter-spacing:-0.5px;">
                NGN {{ $amount }}
            </p>
        </td>
    </tr>
</table>

<table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation"
    style="border-top:1px solid #ece6d6;padding-top:24px;">
    <tr>
        <td style="padding-bottom:14px;">
            <p class="font-mono"
                style="margin:0;color:#172420;font-size:13px;font-weight:700;letter-spacing:0.5px;">
                TRANSACTION DETAILS
            </p>
        </td>
    </tr>
    <tr>
        <td style="padding:10px 0;border-top:1px solid #f0ebdb;">
            <table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
                <tr>
                    <td style="color:#8c978f;font-size:13px;">Business</td>
                    <td align="right" style="color:#172420;font-size:13px;font-weight:500;">
                        {{ $business->name }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td style="padding:10px 0;border-top:1px solid #f0ebdb;">
            <table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
                <tr>
                    <td style="color:#8c978f;font-size:13px;">Reference</td>
                    <td align="right" class="font-mono" style="color:#172420;font-size:12.5px;">
                        {{ $reference }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td style="padding:10px 0;border-top:1px solid #f0ebdb;">
            <table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
                <tr>
                    <td style="color:#8c978f;font-size:13px;">Date</td>
                    <td align="right" style="color:#172420;font-size:13px;font-weight:500;">
                        {{ $date }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    @if($channel === 'bank_transfer')
    <tr>
        <td style="padding:10px 0;border-top:1px solid #f0ebdb;">
            <table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
                <tr>
                    <td style="color:#8c978f;font-size:13px;">Bank</td>
                    <td align="right" style="color:#172420;font-size:13px;font-weight:500;">
                        {{ $authorization['bank_name'] ?? 'Bank Transfer' }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    @endif
</table>
@endsection
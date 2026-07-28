@extends('emails.layout')

@section('content')
    <table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:26px;">
        <tr>
            <td>
                <p
                    style="margin:0 0 6px;font-size:11px;font-weight:600;text-transform:uppercase;
                letter-spacing:1.6px;color:#c89b3c;">
                    Account Security
                </p>
                <h2 class="font-display"
                    style="margin:0;font-size:24px;font-weight:500;color:#172420;
                 letter-spacing:-0.3px;line-height:1.3;">
                    Verify your login
                </h2>
            </td>
        </tr>
    </table>

    <table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:26px;">
        <tr>
            <td>
                <p style="margin:0;font-size:14px;color:#5c665f;line-height:1.65;">
                    Hi <strong style="color:#172420;">{{ $user->first_name }}</strong>, we received a login attempt for your
                    account. Enter the code below to verify it's you.
                </p>
            </td>
        </tr>
    </table>

    <table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:26px;">
        <tr>
            <td class="code-card"
                style="border-radius:20px;padding:28px;
                background-color:#1f5c43;
                background-image:linear-gradient(145deg, #123527, #1f5c43);">
                <p
                    style="margin:0 0 8px;font-size:11px;text-transform:uppercase;letter-spacing:2px;
                color:rgba(255,255,255,0.6);">
                    Verification code
                </p>
                <p class="font-mono code-digits"
                    style="margin:0 0 20px;font-size:34px;font-weight:700;
                color:#ffffff;letter-spacing:10px;">
                    {{ $code }}
                </p>
                <table border="0" cellpadding="0" cellspacing="0" role="presentation">
                    <tr>
                        <td style="border:1px solid rgba(255,255,255,0.3);border-radius:20px;padding:5px 14px;">
                            <span class="font-mono" style="font-size:11px;color:rgba(255,255,255,0.8);">
                                Expires in 15mins
                            </span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
            <td style="border-top:1px solid #dcd4c0;padding-top:18px;">
                <p style="margin:0;font-size:12px;color:#9ca3af;line-height:1.6;">
                    If you didn't attempt to log in, you can safely ignore this email or contact support immediately.
                </p>
            </td>
        </tr>
    </table>
@endsection

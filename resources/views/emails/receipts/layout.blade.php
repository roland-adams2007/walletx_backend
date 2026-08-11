@php
    $appName = config('app.name', 'WalletX');
@endphp
<!DOCTYPE html
    PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $appName }}</title>
    <style type="text/css">
        @import url('https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,400;0,500;0,600;1,400;1,500&family=Work+Sans:wght@400;500;600&family=Space+Mono:wght@400;700&display=swap');

        body,
        table,
        td,
        a {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }

        table,
        td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }

        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            outline: none;
            text-decoration: none;
            display: block;
        }

        body {
            margin: 0 !important;
            padding: 0 !important;
            background-color: #f6f2e7;
            font-family: 'Work Sans', Arial, sans-serif;
        }

        .font-display {
            font-family: 'Fraunces', Georgia, serif;
        }

        .font-mono {
            font-family: 'Space Mono', Consolas, monospace;
        }

        @media only screen and (max-width:620px) {
            .email-wrap {
                width: 100% !important;
            }

            .body-pad {
                padding: 28px 22px 24px !important;
            }

            .head-pad {
                padding: 18px 22px !important;
            }

            .foot-pad {
                padding: 18px 22px !important;
            }

            .amount-text {
                font-size: 28px !important;
            }
        }
    </style>
</head>

<body style="margin:0;padding:0;background-color:#f6f2e7;">

    <table width="100%" border="0" cellpadding="0" cellspacing="0" bgcolor="#f6f2e7" role="presentation">
        <tr>
            <td align="center" style="padding:40px 16px;">

                <table class="email-wrap" width="580" border="0" cellpadding="0" cellspacing="0"
                    role="presentation"
                    style="background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #dcd4c0;">

                    <tr>
                        <td class="head-pad" align="left" style="background-color:#172420;padding:24px 40px;">
                            <table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
                                <tr>
                                    <td valign="middle">
                                        <img src="{{ asset('logo.png') }}" alt="{{ $appName }}" height="28"
                                            style="height:28px;display:block;" />
                                    </td>
                                    <td align="right" valign="middle">
                                        <span class="font-mono"
                                            style="display:inline-block;border:1px solid rgba(255,255,255,0.3);
                               border-radius:20px;padding:4px 12px;font-size:10.5px;font-weight:400;
                               color:rgba(255,255,255,0.8);letter-spacing:1px;">
                                            RECEIPT
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td class="body-pad" style="padding:36px 40px 32px;background:#ffffff;">
                            @yield('content')
                        </td>
                    </tr>

                    <tr>
                        <td class="foot-pad" align="center"
                            style="background-color:#f6f2e7;border-top:1px solid #dcd4c0;padding:18px 40px;">
                            <p style="margin:0;color:#8c978f;font-size:11.5px;line-height:1.6;">
                                © {{ date('Y') }} {{ $appName }}. All rights reserved.
                            </p>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>
</body>

</html>
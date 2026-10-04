@props(['heading', 'actionText' => null, 'actionUrl' => null, 'showFallback' => false])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PAIRfect Paws</title>
</head>
<body style="margin:0;padding:0;background-color:#f8f5f5;color:#1f2937;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.6;">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;background-color:#f8f5f5;">
        <tr>
            <td align="center" style="padding:24px 12px;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="border-collapse:collapse;width:100%;max-width:600px;background-color:#ffffff;border:1px solid #eadfdf;">
                    <tr><td style="background-color:#7f1d1d;padding:20px 28px;color:#ffffff;font-size:22px;font-weight:700;">PAIRfect Paws</td></tr>
                    <tr>
                        <td style="padding:28px;">
                            <h1 style="margin:0 0 20px;color:#251b1b;font-family:Arial,Helvetica,sans-serif;font-size:24px;line-height:1.3;">{{ $heading }}</h1>
                            {{ $slot }}
                            @if($actionText && $actionUrl)
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;margin:24px 0;">
                                    <tr><td style="background-color:#7f1d1d;border-radius:8px;">
                                        <a href="{{ $actionUrl }}" style="display:inline-block;padding:11px 18px;color:#ffffff;font-size:16px;font-weight:700;text-decoration:none;">{{ $actionText }}</a>
                                    </td></tr>
                                </table>
                                @if($showFallback)
                                    <p style="margin:0 0 12px;color:#4b5563;font-size:13px;">If the button does not work, copy and paste this link into your browser:</p>
                                    <p style="margin:0 0 20px;overflow-wrap:anywhere;word-break:break-all;font-size:13px;"><a href="{{ $actionUrl }}" style="color:#7f1d1d;text-decoration:underline;">{{ $actionUrl }}</a></p>
                                @endif
                            @endif
                        </td>
                    </tr>
                    <tr><td style="padding:18px 28px;border-top:1px solid #eadfdf;color:#6b7280;font-size:12px;">PAIRfect Paws<br>This is an automated message from the PAIRfect Paws shelter management system.</td></tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

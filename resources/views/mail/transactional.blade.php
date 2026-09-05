<!DOCTYPE html>
<html lang="en">
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:32px;color:#1f2937;line-height:1.55">
    <div style="border-top:6px solid #7f1d1d;border-radius:10px;background:#fff;padding:28px;box-shadow:0 2px 12px rgba(0,0,0,.08)">
        <p style="margin:0 0 8px;color:#7f1d1d;font-size:13px;font-weight:700;letter-spacing:.08em;text-transform:uppercase">PAIRfect Paws</p>
        <h1 style="margin:0 0 22px;font-size:24px;color:#111827">{{ $heading }}</h1>

        @foreach ($lines as $line)
            <p style="margin:0 0 14px">{{ $line }}</p>
        @endforeach

        @if ($actionText && $actionUrl)
            <p style="margin:26px 0">
                <a href="{{ $actionUrl }}" style="display:inline-block;border-radius:8px;background:#7f1d1d;color:#fff;padding:11px 18px;text-decoration:none;font-weight:700">
                    {{ $actionText }}
                </a>
            </p>
        @endif

        <hr style="margin:28px 0 18px;border:0;border-top:1px solid #e5e7eb">
        <p style="margin:0;color:#6b7280;font-size:12px">PAIRfect Paws Animal Shelter &bull; This is an automated message.</p>
    </div>
</body>
</html>

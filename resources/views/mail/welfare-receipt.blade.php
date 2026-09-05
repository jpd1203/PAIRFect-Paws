<!DOCTYPE html><html><body style="font-family:sans-serif;max-width:580px;margin:0 auto;padding:2rem">
<h2 style="color:#5b6af0">🐾 PAIRfect Paws — Welfare Report Received</h2>
<p>Hello {{ $log->adoptionApplication->user->first_name }},</p>
<p>Thank you for submitting your <strong>{{ $log->milestone->shortLabel() }} welfare report</strong> for <strong>{{ $log->adoptionApplication->pet->name }}</strong>!</p>
<p>Your report was received on <strong>{{ \App\Support\ManilaTime::format($log->submitted_date, 'F j, Y \a\t g:i A') }}</strong>. Our team may review your submission and reach out if additional information is needed.</p>
<p style="margin-top:1.5rem"><a href="{{ url('/monitoring/my-checkins') }}" style="background:#10b981;color:#fff;padding:0.6rem 1.2rem;border-radius:8px;text-decoration:none;font-weight:600">View My Check-ins</a></p>
<hr style="margin:2rem 0;border:none;border-top:1px solid #eee">
<p style="color:#9ca3af;font-size:0.8rem">PAIRfect Paws Animal Shelter &bull; This is an automated message.</p>
</body></html>

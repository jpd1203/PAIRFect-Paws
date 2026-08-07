<!DOCTYPE html><html><body style="font-family:sans-serif;max-width:580px;margin:0 auto;padding:2rem">
<h2 style="color:#5b6af0">🐾 PAIRfect Paws — Application Update</h2>
<p>Hello {{ $application->user->first_name }},</p>
<p>Your adoption application for <strong>{{ $application->pet->name }}</strong> has been updated.</p>
<table style="margin:1.5rem 0;background:#f8f9ff;border-radius:8px;padding:1rem;width:100%">
    <tr><td style="color:#6b7280;font-size:0.9rem">New Status</td><td><strong>{{ $application->status->value }}</strong></td></tr>
    @if($application->interview_date)
    <tr><td style="color:#6b7280;font-size:0.9rem">Interview Date</td><td>{{ $application->interview_date->format('F j, Y g:i A') }}</td></tr>
    @endif
</table>
@if($application->status->value === 'Approved')
<p>🎉 Congratulations! Your application has been approved. You will be contacted shortly with next steps.</p>
@elseif($application->status->value === 'Rejected')
<p>We regret to inform you that your application was not approved at this time. Please visit our shelter to speak with staff if you have questions.</p>
@endif
<p style="margin-top:1.5rem"><a href="{{ url('/applications/mine') }}" style="background:#5b6af0;color:#fff;padding:0.6rem 1.2rem;border-radius:8px;text-decoration:none;font-weight:600">View My Applications</a></p>
<hr style="margin:2rem 0;border:none;border-top:1px solid #eee">
<p style="color:#9ca3af;font-size:0.8rem">PAIRfect Paws Animal Shelter &bull; This is an automated message.</p>
</body></html>

<!DOCTYPE html>
<html lang="en">
<body style="font-family:Arial,sans-serif;max-width:580px;margin:0 auto;padding:32px;color:#1f2937;line-height:1.55">
    <h2 style="color:#7f1d1d">PAIRfect Paws - Application Update</h2>
    <p>Hello {{ $adopterName }},</p>
    <p>Your adoption application for <strong>{{ $petName }}</strong> has been updated.</p>

    @if($event === 'interview_rescheduled' && $previousInterviewDate)
        <p>Your previous interview time was {{ $previousInterviewDate }}. Please use the new schedule below.</p>
    @endif

    <table style="margin:24px 0;background:#f8f9ff;border-radius:8px;padding:16px;width:100%">
        <tr><td style="color:#6b7280;font-size:14px">New Status</td><td><strong>{{ $statusDisplay }}</strong></td></tr>
        @if($interviewDate)
            <tr><td style="color:#6b7280;font-size:14px">Interview Date</td><td>{{ $interviewDate }} (Asia/Manila)</td></tr>
        @endif
    </table>

    @if($status === 'Approved')
        <p>Congratulations! Your application has been approved. Shelter staff will contact you with the next steps.</p>
    @elseif($status === 'Rejected')
        <p>Your application was not approved at this time. Please contact the shelter if you have questions.</p>
    @elseif($status === 'Waitlisted')
        <p>Your application remains active on the first-come, first-served waitlist. We will notify you if it is promoted.</p>
    @elseif($status === 'PrimaryCandidate')
        <p>Your application has been promoted to primary candidate. Shelter staff will contact you to schedule an interview.</p>
    @elseif($status === 'NoShow')
        <p>Your application was closed as a no-show. Contact the shelter if you believe this was recorded in error.</p>
    @elseif($status === 'Withdrawn')
        <p>Your application has been recorded as withdrawn.</p>
    @elseif($status === 'Closed')
        <p>This adoption queue has closed because the pet is no longer available. Thank you for your interest.</p>
    @endif

    <p style="margin-top:24px"><a href="{{ $actionUrl }}" style="background:#7f1d1d;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none;font-weight:600">View My Applications</a></p>
    <hr style="margin:32px 0;border:none;border-top:1px solid #eee">
    <p style="color:#6b7280;font-size:12px">PAIRfect Paws Animal Shelter &bull; This is an automated message.</p>
</body>
</html>

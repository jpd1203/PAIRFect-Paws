<x-mail.layout heading="Application Update" action-text="View My Applications" :action-url="$actionUrl">
    <p style="margin:0 0 14px;">Hello {{ $adopterName }},</p>
    <p style="margin:0 0 14px;">Your adoption application for <strong>{{ $petName }}</strong> has been updated.</p>

    @if($event === 'interview_rescheduled' && $previousInterviewDate)
        <p style="margin:0 0 14px;">Your previous interview time was {{ $previousInterviewDate }}. Please use the new schedule below.</p>
    @endif

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;margin:20px 0;background-color:#faf5f5;">
        <tr><td style="padding:12px;color:#6b7280;font-size:14px;">New Status</td><td style="padding:12px;"><strong>{{ $statusDisplay }}</strong></td></tr>
        @if($interviewDate)
            <tr><td style="padding:12px;color:#6b7280;font-size:14px;">Interview Date</td><td style="padding:12px;">{{ $interviewDate }} (Asia/Manila)</td></tr>
        @endif
    </table>

    @if($status === 'InterviewScheduled')
        @if($event === 'interview_rescheduled')
            <p style="margin:0 0 14px;">Your interview has been rescheduled. Please use the updated date and time above.</p>
        @else
            <p style="margin:0 0 14px;">Great news! An interview has been scheduled for your application. Please ensure you are available at the time listed above.</p>
        @endif
        <p style="margin:0 0 14px;">If you are unavailable at the scheduled date or time, you may request a reschedule. Provide your available dates and times so shelter staff can review your request. Your current schedule stays in place until staff confirms a change.</p>
        <p style="margin:0 0 14px;"><a href="{{ $rescheduleUrl }}" style="color:#7f1d1d;font-weight:700;text-decoration:underline;">Request Reschedule</a></p>
    @elseif($status === 'UnderReview')
        <p style="margin:0 0 14px;">Your application is currently under final review by our shelter staff. We are carefully considering your application and will notify you of our decision soon.</p>
    @elseif($status === 'Pending')
        <p style="margin:0 0 14px;">Your application is currently in the queue and pending review by our shelter staff.</p>
    @elseif($status === 'Approved')
        <p style="margin:0 0 14px;">Congratulations! Your application has been approved. Shelter staff will contact you with the next steps.</p>
    @elseif($status === 'Rejected')
        <p style="margin:0 0 14px;">Your application was not approved at this time. Please contact the shelter if you have questions.</p>
    @elseif($status === 'Waitlisted')
        <p style="margin:0 0 14px;">Your application remains active on the priority waitlist. We will notify you if it is promoted.</p>
    @elseif($status === 'PrimaryCandidate')
        <p style="margin:0 0 14px;">Your application has been promoted to primary candidate. Shelter staff will contact you to schedule an interview.</p>
    @elseif($status === 'NoShow')
        <p style="margin:0 0 14px;">Your application was closed as a no-show. Contact the shelter if you believe this was recorded in error.</p>
    @elseif($status === 'Withdrawn')
        <p style="margin:0 0 14px;">Your application has been recorded as withdrawn.</p>
    @elseif($status === 'Closed')
        <p style="margin:0 0 14px;">This adoption queue has closed because the pet is no longer available. Thank you for your interest.</p>
    @endif
</x-mail.layout>

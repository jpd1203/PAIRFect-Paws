<x-mail.layout heading="Welfare Report Received" action-text="View My Check-ins" :action-url="url('/monitoring/my-checkins')">
    <p style="margin:0 0 14px;">Hello {{ $log->adoptionApplication->user->first_name }},</p>
    <p style="margin:0 0 14px;">Thank you for submitting your <strong>{{ $log->milestone->shortLabel() }} welfare report</strong> for <strong>{{ $log->adoptionApplication->pet->name }}</strong>!</p>
    <p style="margin:0 0 14px;">Your report was received on <strong>{{ \App\Support\ManilaTime::format($log->submitted_date, 'F j, Y \a\t g:i A') }}</strong>. Our team may review your submission and reach out if additional information is needed.</p>
</x-mail.layout>

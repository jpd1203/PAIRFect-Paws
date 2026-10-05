<x-mail.layout heading="Check-in Reminder" action-text="Submit Report" :action-url="url('/monitoring/my-checkins')">
    @if($isPresentationDemo)
        <p style="margin:0 0 14px;padding:12px;background:#fff3cd;border:1px solid #e8c86b;"><strong>Presentation demo:</strong> This message demonstrates the post-adoption reminder workflow. It does not change your official reminder history.</p>
    @endif
    <p style="margin:0 0 14px;">Hello {{ $log->adoptionApplication->user->first_name }},</p>
    <p style="margin:0 0 14px;">This is a reminder that your <strong>{{ $log->milestone->shortLabel() }} post-adoption welfare report</strong> for <strong>{{ $log->adoptionApplication->pet->name }}</strong> is due on <strong>{{ $log->scheduled_date->format('F j, Y') }}</strong>.</p>
    @if(filled($customMessage))
        <div style="margin:18px 0;padding:14px;border-left:4px solid #7f1d1d;background-color:#faf5f5;">
            <strong>Message from the shelter:</strong><br>
            {{ $customMessage }}
        </div>
    @endif
    <p style="margin:0 0 14px;">Please log in and submit your welfare report to keep your adoption case in good standing.</p>
</x-mail.layout>

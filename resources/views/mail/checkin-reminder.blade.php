<!DOCTYPE html><html><body style="font-family:sans-serif;max-width:580px;margin:0 auto;padding:2rem">
<h2 style="color:#5b6af0">🐾 PAIRfect Paws — Check-in Reminder</h2>
<p>Hello {{ $log->adoptionApplication->user->first_name }},</p>
<p>This is a reminder that your <strong>{{ match($log->milestone->value) { 'ThreeDays' => '3-Day', 'ThreeWeeks' => '3-Week', 'ThreeMonths' => '3-Month' } }} post-adoption welfare report</strong> for <strong>{{ $log->adoptionApplication->pet->name }}</strong> is due on <strong>{{ $log->scheduled_date->format('F j, Y') }}</strong>.</p>
<p>Please log in and submit your welfare report to keep your adoption case in good standing.</p>
<p style="margin-top:1.5rem"><a href="{{ url('/monitoring/my-checkins') }}" style="background:#5b6af0;color:#fff;padding:0.6rem 1.2rem;border-radius:8px;text-decoration:none;font-weight:600">Submit Report</a></p>
<hr style="margin:2rem 0;border:none;border-top:1px solid #eee">
<p style="color:#9ca3af;font-size:0.8rem">PAIRfect Paws Animal Shelter &bull; This is an automated message.</p>
</body></html>

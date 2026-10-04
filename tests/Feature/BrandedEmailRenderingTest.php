<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\Milestone;
use App\Mail\CheckInReminderMail;
use App\Mail\StatusUpdateMail;
use App\Mail\TransactionalMail;
use App\Mail\WelfareReportReceiptMail;
use App\Models\AdoptionApplication;
use App\Models\Pet;
use App\Models\PostAdoptionLog;
use App\Models\User;
use App\Notifications\QueuedResetPassword;
use App\Notifications\QueuedVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class BrandedEmailRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_verification_email_uses_branded_html_and_text_with_a_working_signed_url(): void
    {
        $this->useArrayMail();
        $this->travelTo(now()->startOfSecond());
        $user = User::factory()->create(['email_verified_at' => null]);
        $notification = new QueuedVerifyEmail;

        app(MailChannel::class)->send($user, $notification);

        $message = $this->lastMessage();
        $url = $notification->toMail($user)->viewData['actionUrl'];
        $this->assertSame('Verify your PAIRfect Paws email address', $message->getSubject());
        $this->assertBranded($message->getHtmlBody(), ['Welcome to PAIRfect Paws!', 'Verify Email Address', '60 minutes']);
        $this->assertStringContainsString(e($url), $message->getHtmlBody());
        $this->assertStringContainsString($url, $message->getTextBody());
        $this->assertStringNotContainsString('&amp;', $message->getTextBody());
        $this->assertSame('https', parse_url($url, PHP_URL_SCHEME));
        $this->assertSame('PAIRfect Paws', $message->getFrom()[0]->getName());
        $this->assertSame('notifications@example.test', $message->getFrom()[0]->getAddress());

        $this->get($url)->assertRedirect(route('login'));
        $this->assertTrue($user->refresh()->hasVerifiedEmail());
    }

    public function test_password_reset_email_uses_branded_html_and_text_with_configured_expiry_and_working_url(): void
    {
        $this->useArrayMail();
        config()->set('auth.passwords.users.expire', 42);
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);
        $notification = new QueuedResetPassword($token);

        app(MailChannel::class)->send($user, $notification);

        $message = $this->lastMessage();
        $url = $notification->toMail($user)->viewData['actionUrl'];
        $this->assertSame('Reset your PAIRfect Paws password', $message->getSubject());
        $this->assertBranded($message->getHtmlBody(), ['Password reset requested', 'Reset Password', '42 minutes']);
        $this->assertStringContainsString(e($url), $message->getHtmlBody());
        $this->assertStringContainsString($url, $message->getTextBody());
        $this->assertStringNotContainsString('&amp;', $message->getTextBody());
        $this->assertSame('https', parse_url($url, PHP_URL_SCHEME));
        $this->assertSame('PAIRfect Paws', $message->getFrom()[0]->getName());

        $this->get($url)->assertOk()->assertSee('Choose a new password');
    }

    public function test_transactional_email_renders_heading_lines_action_and_plain_text_without_default_branding(): void
    {
        $this->useArrayMail();
        $url = route('application.index', ['source' => 'email', 'kind' => 'receipt']);
        $mail = new TransactionalMail(
            'Application received',
            'Your adoption application was received',
            ['Hello Jessa,', 'We received your application for Mimi.', '<script>alert(1)</script>'],
            'View My Applications',
            $url,
        );

        Mail::mailer('array')->send($mail->to('adopter@example.test'));

        $message = $this->lastMessage();
        $this->assertBranded($message->getHtmlBody(), [
            'Your adoption application was received', 'Hello Jessa,', 'Mimi', 'View My Applications',
        ]);
        $this->assertStringContainsString(e($url), $message->getHtmlBody());
        $this->assertStringContainsString($url, $message->getTextBody());
        $this->assertStringContainsString('&lt;script&gt;', $message->getHtmlBody());
        $this->assertStringNotContainsString('<script>', $message->getHtmlBody());
        $this->assertSame('PAIRfect Paws', $message->getFrom()[0]->getName());
        $this->assertSame('notifications@example.test', $message->getFrom()[0]->getAddress());
    }

    public function test_status_update_preserves_applicant_pet_status_dates_and_action_urls(): void
    {
        [$application] = $this->placement();
        $application->status = ApplicationStatus::InterviewScheduled;
        $application->interview_date = '2026-10-10 01:00:00';
        $mail = new StatusUpdateMail($application, 'interview_rescheduled', 'October 9, 2026 at 9:00 AM');

        $html = $mail->render();
        $this->assertBranded($html, [
            'Jessa', 'Mimi', 'Scheduled', 'Interview Date', 'October 9, 2026 at 9:00 AM',
            'Request Reschedule', 'View My Applications',
        ]);
        $this->assertStringContainsString(e($mail->rescheduleUrl), $html);
        $this->assertStringContainsString(e($mail->actionUrl), $html);
        $this->assertSame('https', parse_url($mail->actionUrl, PHP_URL_SCHEME));
    }

    public function test_checkin_reminder_preserves_milestone_date_custom_message_and_monitoring_url(): void
    {
        [, $log] = $this->placement();
        $mail = new CheckInReminderMail($log, 'Please tell us how Mimi is eating.');

        $html = $mail->render();
        $this->assertBranded($html, [
            'Jessa', 'Mimi', '3-Day', 'October 12, 2026',
            'Please tell us how Mimi is eating.', 'Submit Report',
        ]);
        $this->assertStringContainsString(e(url('/monitoring/my-checkins')), $html);
    }

    public function test_welfare_receipt_preserves_milestone_submission_date_and_monitoring_url(): void
    {
        [, $log] = $this->placement();
        $mail = new WelfareReportReceiptMail($log);

        $html = $mail->render();
        $this->assertBranded($html, ['Jessa', 'Mimi', '3-Day', 'October 12, 2026', 'View My Check-ins']);
        $this->assertStringContainsString(e(url('/monitoring/my-checkins')), $html);
    }

    private function useArrayMail(): void
    {
        config()->set('mail.default', 'array');
        config()->set('mail.from.address', 'notifications@example.test');
        config()->set('mail.from.name', 'PAIRfect Paws');
        Mail::forgetMailers();
    }

    private function lastMessage(): Email
    {
        return Mail::mailer('array')->getSymfonyTransport()->messages()->last()->getOriginalMessage();
    }

    /** @param list<string> $expected */
    private function assertBranded(string $html, array $expected): void
    {
        $this->assertStringContainsString('PAIRfect Paws', $html);
        $this->assertStringContainsString('#7f1d1d', $html);
        $this->assertStringNotContainsStringIgnoringCase('Laravel', $html);
        $this->assertStringNotContainsString('laravel.com', $html);
        foreach ($expected as $text) {
            $this->assertStringContainsString($text, $html);
        }
    }

    /** @return array{AdoptionApplication, PostAdoptionLog} */
    private function placement(): array
    {
        $user = new User(['first_name' => 'Jessa']);
        $pet = new Pet(['name' => 'Mimi']);
        $application = new AdoptionApplication(['status' => ApplicationStatus::Approved->value]);
        $application->id = 42;
        $application->setRelation('user', $user);
        $application->setRelation('pet', $pet);
        $log = new PostAdoptionLog([
            'milestone' => Milestone::ThreeDays->value,
            'scheduled_date' => '2026-10-12',
            'submitted_date' => '2026-10-12 01:00:00',
        ]);
        $log->setRelation('adoptionApplication', $application);

        return [$application, $log];
    }
}

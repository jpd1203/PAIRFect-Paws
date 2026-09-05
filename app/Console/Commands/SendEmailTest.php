<?php

namespace App\Console\Commands;

use App\Mail\TransactionalMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendEmailTest extends Command
{
    protected $signature = 'email:test {email : Recipient email address} {--audience=adopter : adopter or staff}';

    protected $description = 'Queue a PAIRfect Paws SMTP delivery test email.';

    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));
        $audience = strtolower(trim((string) $this->option('audience')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Provide a valid recipient email address.');

            return self::FAILURE;
        }

        if (! in_array($audience, ['adopter', 'staff'], true)) {
            $this->error('The --audience option must be adopter or staff.');

            return self::FAILURE;
        }

        if (config('mail.default') !== 'smtp'
            || blank(config('mail.mailers.smtp.host'))
            || blank(config('mail.mailers.smtp.username'))
            || blank(config('mail.mailers.smtp.password'))
            || blank(config('mail.from.address'))) {
            $this->error('SMTP is not fully configured. Add the provider credential to MAIL_PASSWORD and clear the configuration cache.');

            return self::FAILURE;
        }

        Mail::to($email)->queue(new TransactionalMail(
            'PAIRfect Paws email workflow test',
            'Production email delivery test',
            [
                "This is the {$audience} delivery test requested for PAIRfect Paws.",
                'Receiving this message confirms that the application queued it and the configured mail worker handed it to the SMTP relay.',
            ],
            'Open PAIRfect Paws',
            config('app.url'),
        ));

        $this->info("Queued the {$audience} delivery test on the emails queue.");

        return self::SUCCESS;
    }
}

# Production email delivery

PAIRfect Paws uses Laravel's database queue. Email jobs are encrypted, sent on
the `emails` queue, retried three times, and retained in `failed_jobs` after the
last failure.

## Required environment

Copy the mail and queue keys from `.env.example`. Put the SMTP credential only
in the server's untracked `.env`. `APP_URL` must be the stable public HTTPS
origin before sending verification links.

## Gmail SMTP for testing

For a personal Gmail sender, enable 2-Step Verification and generate a dedicated
Google App Password. Use the Gmail address for both `MAIL_USERNAME` and
`MAIL_FROM_ADDRESS`, and place the App Password in `MAIL_PASSWORD`. Never use the
normal Google Account password or commit the App Password.

Set `STAFF_ALERT_EMAIL` to a real monitored shelter inbox. The built-in
`admin@pairfectpaws.com` and `volunteer@pairfectpaws.com` accounts remain usable
for local sign-in, but are deliberately excluded from email delivery because
their placeholder domain does not provide mailboxes.

Gmail SMTP is appropriate for development and low-volume testing. Before a
public production launch, move to a transactional provider using a domain owned
by the shelter so sender identity, deliverability, and account ownership are not
tied to one person's Gmail account.

## Persistent processes

Install `deploy/supervisor/pairfect-paws-worker.conf.example`, correct its PHP
binary and deployment path, then reload Supervisor. During each deployment run:

```text
php artisan optimize:clear
php artisan queue:restart
```

The existing reminder schedule also requires this cron entry:

```text
* * * * * cd /var/www/pairfect-paws && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Inspect failures with `php artisan queue:failed` and retry a resolved failure
with `php artisan queue:retry <id>`.

## Local Windows background services

The queue worker delivers queued mail, while the Laravel scheduler dispatches
post-adoption reminders and the other scheduled maintenance commands. Start
both project-local background processes from PowerShell:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\start-background-services.ps1
```

They use a project-bundled PHP runtime first, then Herd, XAMPP, or a compatible
PHP executable on `PATH`. Every candidate is checked for PHP 8.4.1 or newer. To
choose one explicitly, pass `-PhpPath C:\path\to\php.exe`.

Both processes run in hidden windows and write their PID and separate output and
error logs under `storage/framework` and `storage/logs`. The queue worker is
persistent; it no longer exits after the previous one-hour `--max-time` limit.
Check or stop the exact recorded project processes with:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\status-background-services.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\stop-background-services.ps1
```

The scripts validate the process command before stopping it and clean stale PID
files without terminating an unrelated process. They are idempotent, so running
the start command again will not create a duplicate recorded worker or
scheduler. They do not install a Windows Scheduled Task or configure automatic
startup; run the start command again after a Windows restart.

The queue worker and scheduler can also be managed individually with
`start-queue-worker.ps1`, `stop-queue-worker.ps1`, `start-scheduler.ps1`, and
`stop-scheduler.ps1`.

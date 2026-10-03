# Azure App Service F1 pilot (not a production runbook)

This repository can be prepared for a low-traffic, synthetic-data pilot on the
built-in Azure App Service Linux PHP image. No Azure resources are provisioned
by this repository. Confirm that PHP 8.4 and F1 are offered in the chosen
subscription/region before creating anything. MySQL Flexible Server billing and
free-offer eligibility must be checked in the Azure portal before creation.

## Build and deployment boundaries

The repository root must be deployed to `/home/site/wwwroot`, but Nginx must
serve only `/home/site/wwwroot/public`. In App Service's **Startup Command** use:

```sh
cp /home/site/wwwroot/azure/nginx-default.conf /etc/nginx/sites-available/default && nginx -t && service nginx reload
```

The checked-in Nginx file follows Microsoft's built-in PHP/Laravel example.
Confirm its PHP-FPM upstream on the actual image before admitting testers; it
cannot be validated from a Windows checkout. Never expose the repository root
or use an `.htaccess` rewrite as a substitute for the public document root.
Set the App Service setting `PHP_INI_SCAN_DIR=:/home/site/wwwroot/azure` so the
checked-in `azure/uploads.ini` raises PHP's upload/post limits to match the
existing 100 MiB pet-photo validation. Confirm `php -i` in the actual container;
the Nginx file sets the matching request-body limit. Use small files on F1.

After the Web App exists, use Deployment Center to connect GitHub Actions to the
selected branch. Audit Azure's generated workflow before enabling it; do not
add a second competing deployment workflow. The build job should use PHP 8.4,
Composer 2, and Node 22.12 or later (as required by Vite 7), then run:

```sh
composer validate
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
npm ci
npm run build
```

Deploy `app/`, `bootstrap/`, `config/`, `database/`, `public/` (including the
built `public/build/` directory), `resources/`, `routes/`, `storage/`, `vendor/`,
`artisan`, and `composer.json`/`composer.lock`. Do not deploy `.env`, test
databases, `node_modules`, or private credential files. If deploying a prepared
artifact, disable duplicate server-side Oryx builds. The generated workflow
must use GitHub encrypted secrets or OIDC, never inline credentials. The Web
App name and GitHub authentication are not known until the Azure resource exists.

After environment settings and database connectivity are present, run these
**once** from App Service SSH:

```sh
cd /home/site/wwwroot
php artisan migrate --force
php artisan storage:link
php artisan optimize
php artisan release:check
```

Never automate `migrate:fresh` against Azure. Re-run `migrate --force` only as a
reviewed migration step. Keep writable `storage/` and `bootstrap/cache/` owned
by the application user; do not use `chmod 777`.

## App settings, not a committed `.env`

Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` to the actual HTTPS
`azurewebsites.net` URL, and a stable `APP_KEY` generated securely outside the
repository. Set `DB_CONNECTION=mysql` and the five `DB_*` connection fields to
the dedicated MySQL Flexible Server database. Confirm TLS is active; set
`MYSQL_ATTR_SSL_CA` if the chosen connection configuration needs a CA file.
Set `SESSION_DRIVER=database`, `CACHE_STORE=database`,
`SESSION_SECURE_COOKIE=true`, `SESSION_SAME_SITE=lax`, and leave
`SESSION_DOMAIN` unset for the temporary hostname. Enable App Service **HTTPS
Only**. `TRUSTED_PROXIES` defaults to loopback; if forwarded HTTPS URLs are
wrong, set it to the verified immediate App Service proxy IPs/ranges and test
signed links, redirects, and cookies. `*` trusts any forwarded host/IP/scheme
and should not be selected without checking the ingress header behavior.

`PRIVACY_CONTACT_EMAIL` must be a real shelter-controlled address. Configure
the existing SMTP settings and `STAFF_ALERT_EMAIL`; Laravel 13 here reads
`MAIL_SCHEME` and `MAIL_REQUIRE_TLS`, not `MAIL_ENCRYPTION`. Keep the Google
Vision service-account JSON outside the deployed/public tree and point
`GOOGLE_APPLICATION_CREDENTIALS` to its private absolute path. OCR failures
fall back to staff review, but cannot prove automatic verification without
working credentials and the Vision API. Do not put these values into GitHub.

For a controlled F1 pilot, `QUEUE_CONNECTION=sync` is an **explicit choice**:
transactional mail then runs during requests, so slow SMTP can delay them.
Verification mail already uses the sync connection. `QUEUE_CONNECTION=database`
requires a separate supervised worker on `emails,default`; without it, queued
notifications are not delivered. F1 does not supply reliable Always On, so do
not present queued email or scheduled reminders as automated on F1.

## Features limited by F1

The scheduler in `routes/console.php` runs check-in reminders daily, reservation
timeouts hourly, matching recomputation hourly, and capture-challenge cleanup
daily. `php artisan schedule:run` must be invoked every minute by reliable
external scheduling to preserve those guarantees. It is not automatic on F1.
Manual command execution can demonstrate logic but is not a substitute for
production automation.

Post-adoption video verification requires an absolute executable `FFPROBE_PATH`.
The code rejects check-ins when the binary is missing; it must not trust the
browser's reported duration. The built-in App Service image has not been
verified to include `ffprobe`. If unavailable, leave this feature out of the
F1 pilot and use a later supported binary/container deployment for full tests.
The strict `release:check` will report this as a failure until a working binary
is configured; do not treat a partial pilot as a passed production check.
The legacy C2PA verifier needs `c2patool` only for signed legacy image paths;
the current live-video path uses server-side duration probing and SHA-256.

Pet photos and handover proof under the `public` disk, and private adoption
documents, handover receipts, and welfare videos under `local`, can be used
only with disposable pilot data. These uploads must move to durable private or
public Azure Blob-backed storage (as appropriate) before real use. Do not
assume a GitHub deployment preserves user-uploaded files in `wwwroot`.

## Acceptance gate

1. Confirm PHP 8.4 and required extensions (`pdo_mysql`, `curl`, `openssl`,
   `fileinfo`, `mbstring`, `dom`/`xml`, `intl`, `gd`, `zip`) in the actual App
   Service container; local Composer checks are not proof of Azure's image.
2. A disposable MySQL 8.0.46 database passed clean migrations,
   `migrate:fresh --seed`, and 264 tests locally on 2026-10-04. Never point
   `migrate:fresh` at project or live data. Repeat the compatibility gate if
   the Azure server uses MySQL 8.4 or a materially different configuration.
3. Confirm `/up` and the homepage respond over HTTPS, while `/composer.json`,
   `/.env`, `/storage/logs/laravel.log`, and `/database/database.sqlite` do not.
4. Test registration, email verification, login/logout, CSRF, roles, profile,
   BFI, recommendations, applications, interviews, ranking, and handover with
   synthetic accounts. Check generated URLs and secure session cookies.
5. Test document OCR and SMTP with provisioned credentials; test the queue and
   scheduler independently. Treat post-adoption video as unavailable until
   `ffprobe` is installed and a live capture passes end to end.

Do not call the pilot complete on local build/tests alone. Keep
`POST_ADOPTION_TIME_TRAVEL_ENABLED=false`; its route is also environment-guarded.
Do not run demo seeders in Azure. Upgrade hosting/background infrastructure and
durable storage before real adopter data or a B1/domain production launch.

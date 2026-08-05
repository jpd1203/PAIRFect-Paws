# PAIRfect Paws — Adopter + Admin Web App (Laravel / Blade / Tailwind)

Converted from the original C# ASP.NET Core MVC (Razor `.cshtml`) front end.
Two panels in one Laravel app:

- **Adopter side** (`/`) — browse pets, Pet Recommendation, apply, track applications, post-adoption check-ins.
- **Admin side** (`/admin`) — Dashboard, Animal Records, Pet Assessment, Applications, Compatibility, Adoption Profile, Assessment Record, Monitoring, Flagged Cases, Volunteers, Audit Logs, Manage Funds.

## Stack (pinned to what you're already running)

- **Laravel 12** (`^12.0`), PHP 8.2+
- **Tailwind CSS 3.4.19**, **Vite 7.3.6**, **laravel-vite-plugin 2.1.0**
- MySQL via Eloquent

This delivery is a **complete, self-contained Laravel 12 project** — `artisan`,
`public/index.php`, `bootstrap/app.php`, every `config/*.php` file, and the full
`storage/` skeleton are all included. You do **not** need to merge this into a
separate `laravel new` skeleton this time — that mismatch was the friction
last time. Just `composer install` and go.

## Getting it running

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate

# create a MySQL database named pairfect_paws (or edit .env to match yours), then:
php artisan migrate --seed
php artisan storage:link

npm run build      # or: npm run dev (while actively editing)
php artisan serve
```

Demo logins (from the seeder — **rotate immediately in any real deployment**):

| Panel | URL | Email | Password |
|---|---|---|---|
| Adopter | `/login` | `jessa@example.com` | `ChangeMe123!` |
| Admin | `/admin/login` | `admin@pairfectpaws.test` | `ChangeMe123!` |
| Volunteer | `/admin/login` | `volunteer@pairfectpaws.test` | `ChangeMe123!` |

The seeder also creates 5 pets (one already fully adopted with a complete
3-3-3 monitoring history, one mid-assessment, one pending application, and
sample fund records) so every screen has something to look at immediately.

## What's in the admin side

**File naming convention** — same as before: `Folder_View.cshtml` →
`resources/views/folder/view.blade.php`. E.g. `Animal_Index.cshtml` →
`resources/views/admin/animal/index.blade.php`.

- **Shared sidebar** — reuses the same visual language as the adopter side
  (same color tokens, same component patterns) so both panels feel like one
  product. Added **Pet Assessment**, **Assessment Record**, and
  **Compatibility** under ADOPTION, and **Manage Funds** under SYSTEM, per
  your notes.
- **Animal Records** — species / age / health / status filter bars above the
  table (all four combine, not just the last one clicked); Add Animal modal
  changed to a 3-column grid; the View/Edit modal now has a **Pet Assessment**
  status row with an **Edit Pet Assessment** button under the photo upload
  field, linking straight to that pet's next assessment form.
- **Pet Assessment** — one shared, data-driven Blade template
  (`admin/assessment/form.blade.php`) drives both the dog and cat
  questionnaires from `App\Support\AssessmentQuestions` (the exact question
  banks from `PetAssessment_dogAssessmentForm.html` /
  `catAssessmentForm.html`), rather than two near-duplicate templates —
  easier to maintain, same output. Every pet can be assessed up to 3 times
  (`PetAssessment::MAX_ASSESSMENTS_PER_PET`); the 3rd assessment
  auto-promotes a pet from "Assessing" to "Available".
- **Assessment Record** — same layout as Volunteers/Audit Logs, with the
  requested columns (Pet, Assessed By, Date Last Assessed, Status, Summary,
  Actions). The **Summary** modal averages *across all* of a pet's
  assessments per category (Energy, Trainability, Independence, Temperament)
  with the qualitative labels from your screenshot (Moderate, Low,
  Independent, Somewhat fearful, etc.) — see `PetAssessment::energyLabel()`
  and friends for the exact bands.
- **Applications** — new pipeline: **Pending → Scheduled → Under Review →
  Approved/Rejected**, matching your description exactly
  (`AdoptionApplication::STATUS_*`). The Review modal adds **Adoption Record
  History** (shown only if this applicant has a prior completed adoption —
  their 3-3-3 monitoring is pulled live) and **Compatibility Result** (shown
  only if they applied via Pet Recommendation) as conditional rows, plus an
  **Interview Notes** section that appears once an interview has happened.
  Approving an application automatically creates the pet's 3-3-3 monitoring
  schedule and marks the pet Adopted.
- **Compatibility** — an admin-facing review list (not the adopter's live
  slider screen) of every applicant who used Pet Recommendation, with match-
  tier filters (High/Good/Fair/Low) and a **View Breakdown** modal showing
  the same 6-row bar breakdown from the reference HTML, restyled to match
  the rest of the admin panel.
- **Adoption Profile** — adopter lifestyle profiles as cards, each with
  **Adoption Record History** (when applicable) and **Document Upload**
  View buttons.
- **Monitoring / Flagged Cases / Volunteers / Audit Logs** — same
  records-container + filter-bar + modal patterns as everywhere else, per
  your "still the same layout" note.
- **Manage Funds** — donation/expense ledger with running totals and a
  Record Funds modal (Date, Entry Type, Activity, Amount).

## Security measures (admin side, in addition to what the adopter side already has)

- **Separate staff guard boundary**: `EnsureUserIsStaff` middleware (aliased
  `staff`) blocks any account whose `role` isn't `Admin`/`Volunteer` from
  every `/admin/*` route, and force-logs-out + blocks deactivated staff
  accounts on their next request.
- **Separate login**: `/admin/login` is entirely separate from the adopter
  `/login` — an adopter account can never authenticate into the admin panel
  even if they guess the URL.
- **Audit trail**: every meaningful admin action (approvals, rejections,
  animal record changes, assessments, flags, escalations, fund entries)
  writes an immutable `AuditLog` row via `AuditLog::record()` — see Audit
  Logs for the full trail, exportable to CSV.
- **Authorization on document access**: uploaded applicant documents live on
  the `private` disk (not web-accessible) and are only ever served through
  `ApplicationController::document()` / `AdopterProfileController::document()`,
  both gated by the `staff` middleware.
- **Self-lockout guard**: `VolunteerController::update()` refuses to let the
  last active Admin account deactivate or demote themselves, so staff can't
  accidentally lock everyone out.
- **Same baseline hardening as the adopter side**: bcrypt hashing, CSRF on
  every form, whitelisted `Rule::in(...)` validation everywhere (species,
  status, ratings 0–4, decisions, etc.), parameter-bound Eloquent queries,
  Blade auto-escaping, rate limiting, and the global `SecurityHeaders`
  middleware.

## Folder map (new/changed since the adopter-side delivery)

```
app/Http/Controllers/Admin/    Dashboard, Animal, Assessment, Application, Compatibility,
                                AdopterProfile, Monitoring, FlaggedCases, Volunteer, AuditLog, ManageFunds
app/Http/Controllers/Auth/StaffLoginController.php
app/Http/Middleware/EnsureUserIsStaff.php
app/Models/                    PetAssessment, FlaggedCase, AuditLog, FundRecord (new)
                                Pet, User, CheckIn, AdoptionApplication (extended)
app/Services/PetMatchService.php   unchanged — same service now also feeds the admin Compatibility page
app/Support/AssessmentQuestions.php   dog + cat question banks (data-driven form)
app/Support/AdminOptions.php   species/age/health/status/intervention dropdown lists
resources/views/admin/         layouts/, partials/sidebar, dashboard/, animal/, assessment/,
                                application/, compatibility/, adopter-profile/, monitoring/,
                                flagged-cases/, volunteer/, audit-logs/, funds/, auth/
resources/css/admin.css        Tailwind port of admin.css + PetAssessment_updated.css
resources/js/admin.js          global admin JS (sidebar, modals, toast, search, filter-bar)
public/js/admin/               per-page JS (animal, application)
database/migrations/           all admin-side tables + the columns added to existing adopter tables
routes/admin.php               every /admin/* route, separate from routes/web.php
```

## Notes

- `routes/admin.php` is loaded from `bootstrap/app.php`'s `then:` callback,
  kept deliberately separate from `routes/web.php` so the adopter and admin
  route files never collide.
- The Pet Recommendation `PetMatchService` your adopter side already uses is
  the same service behind the admin Compatibility page — one scoring engine,
  two views of it.
- If you rename any Blade file or route, the JSON payloads embedded in
  `animal/index.blade.php` and `application/index.blade.php` (used to drive
  the modals without extra round-trips) reference route names, not raw URLs
  — renaming a route via `routes/admin.php` is enough, nothing else to touch.

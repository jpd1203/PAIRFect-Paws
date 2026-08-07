# PAIRfect Paws — System Documentation

**PAIRfect Paws** is a PHP/Laravel web application for managing animal shelter adoptions and post-adoption welfare monitoring. Built with Laravel, Laravel's native authentication, Eloquent ORM (MySQL), and Laravel Mail.

---

## Table of Contents
1. [Architecture Overview](#architecture-overview)
2. [User Roles & Access Control](#user-roles--access-control)
3. [Module: Account Management](#module-account-management)
4. [Module: Animal Catalog (Public)](#module-animal-catalog-public)
5. [Module: Adoption Applications](#module-adoption-applications)
6. [Module: Admin Dashboard](#module-admin-dashboard)
7. [Module: Animal Management (Admin)](#module-animal-management-admin)
8. [Module: Application Management (Admin)](#module-application-management-admin)
9. [Module: Adoption Profiles (Admin)](#module-adoption-profiles-admin)
10. [Module: Post-Adoption Monitoring (Adopter)](#module-post-adoption-monitoring-adopter)
11. [Module: Monitoring Dashboard (Staff)](#module-monitoring-dashboard-staff)
12. [Module: Flagged Cases (Staff)](#module-flagged-cases-staff)
13. [Module: Audit Logs](#module-audit-logs)
14. [Module: Volunteer & Staff Management](#module-volunteer--staff-management)
15. [Scheduled Tasks](#scheduled-tasks)
16. [Core Application Services](#core-application-services)
17. [Data Models](#data-models)

---

## Architecture Overview

```
pairfect-paws/
├── app/
│   ├── Http/
│   │   ├── Controllers/    — HTTP route handlers, business logic coordination
│   │   │   └── Admin/      — Staff/admin-facing controllers
│   │   └── Middleware/     — Role-based route guards (AdminOnly, StaffOnly, AdopterOnly)
│   ├── Models/             — Eloquent models
│   ├── Enums/              — Backed PHP enums (Role, Species, ApplicationStatus, etc.)
│   ├── Services/           — Injectable service classes (flag evaluation, audit logging)
│   └── Mail/               — Mailable classes for transactional email
├── database/
│   ├── migrations/         — Schema definitions (replaces EF Core migrations)
│   └── seeders/            — Database seeder
├── resources/
│   └── views/               — Blade templates (per role/feature)
├── routes/
│   ├── web.php              — Route definitions + middleware group assignment
│   └── console.php          — Scheduled task definitions (replaces the hosted background service)
├── public/                  — Static assets (CSS, JS, images); uploaded files are served via the
│                               `storage:link` symlink into `storage/app/public`
└── bootstrap/app.php        — App composition root: middleware registration, exception handling
```

The application uses:
- **Laravel's native authentication** (session guard, `Auth` facade) for authentication and role management (Bcrypt password hashing)
- **Eloquent ORM + MySQL** as the relational database backend (migrated via `php artisan migrate`)
- **Cookie-based sessions** using Laravel's default session driver, with a configurable lifetime (`config/session.php`) and HTTPS enforcement (`secure` cookie flag + forced HTTPS in production)
- **Laravel Mail** for HTML email delivery via the SMTP mailer, configured against **Mailtrap** in development

---

## User Roles & Access Control

Three role types are enforced through custom **Middleware** classes (registered in `bootstrap/app.php` and applied as route-group middleware in `routes/web.php`):

| Role | Middleware | Permissions |
|------|--------|-------------|
| **Administrator** | `admin`, `staff` | Full access to all admin modules including archiving and volunteer management |
| **Volunteer** | `staff` | Read/write access to application, monitoring, and flagged case modules — but cannot archive pets or manage accounts |
| **Adopter** | `adopter` | Can browse pets, submit adoption applications, and submit post-adoption welfare reports |

Each middleware checks `auth()->user()->role` and calls `abort(403)` (routed to the access-denied page) if the requirement isn't met. Users are redirected to their role-appropriate home page after a successful login.

---

## Module: Account Management

**Controller:** `AuthController`
**Views:** `resources/views/auth/`

This module handles all user authentication and registration for public-facing adopter accounts.

### Functions

#### `GET /login`
Renders the login form. If a user is already authenticated (`Auth::check()`), they are immediately redirected to their role-specific home page (Admin/Volunteer → Dashboard; Adopter → My Check-ins).

#### `POST /login`
Authenticates the user using email and password via **Laravel's `Auth::attempt()`**.
- Validates the CSRF token (Laravel's built-in `VerifyCsrfToken` middleware + `@csrf` Blade directive)
- Checks if the account is active (`user.is_active`) — deactivated accounts are denied even with correct credentials, by rejecting the attempt and returning a validation error before session regeneration
- On success, regenerates the session and redirects to the appropriate dashboard based on role

#### `GET /register`
Renders the registration form for new adopter accounts.

#### `POST /register`
Creates a new Adopter account.
- Validates that the email is not already registered (`unique:users,email` rule)
- Creates a `User` model instance with the `Adopter` role
- Hashes the password using **Bcrypt** (`Hash::make()`, Laravel's default)
- Automatically logs the user in (`Auth::login()`) and redirects to My Check-ins

#### `POST /logout`
Logs the user out via `Auth::logout()`, invalidates the session, regenerates the CSRF token, and redirects to the Login page.

#### `GET /access-denied`
Renders a friendly "You don't have access" page, shown when the role middleware aborts a request with a 403.

---

## Module: Animal Catalog (Public)

**Controller:** `PetController`
**Views:** `resources/views/pets/`

This module is **publicly accessible** — no login required — allowing anyone to browse available pets.

### Functions

#### `GET /pets`
Displays a browsable catalog of all non-archived pets.
- Supports optional filters: `species` (Cat/Dog) and `status` (Available/Processing/Adopted)
- Uses an **Eloquent Global Scope** (e.g. `NotArchivedScope`, registered in the `Pet` model's `booted()` method) to automatically exclude `is_archived = true` records from every query
- Passes filter state to the view via `compact()`/`with()` for UI toggling

#### `GET /pets/{pet}`
Displays the full detail page for a single pet (via route-model binding), including its branch, health status, behavioral notes, and vaccination records.

#### `GET /pets/create` *(Staff Only)*
Renders the form to add a new pet. Populates the branch dropdown from `Branch::all()`.

#### `POST /pets` *(Staff Only)*
Creates a new pet record in the database.
- Accepts an optional photo upload, stored via the **`Storage` facade** (e.g. `Storage::disk('public')->putFile('pets', $request->file('photo'))`) instead of a custom upload service
- Sets `intake_date` and relies on Eloquent's automatic `created_at` timestamp
- Falls back to a default placeholder image if no photo is provided

#### `GET /pets/{pet}/edit` *(Staff Only)*
Renders the edit form pre-populated with the existing pet's data.

#### `PUT /pets/{pet}` *(Staff Only)*
Updates an existing pet's details.
- Retains the original `intake_date` and `created_at`
- Retains the existing photo if no new one is uploaded
- Handles **optimistic concurrency** via a manually managed integer `version` column (Laravel has no built-in `RowVersion` equivalent). The form submits the version it was loaded with; the update runs as `Pet::where('id', $id)->where('version', $submittedVersion)->update([...'version' => $submittedVersion + 1])`. If the affected row count is `0`, a stale-record condition is assumed and a user-friendly "this record was changed by someone else" error is shown instead of saving

#### `POST /pets/{pet}/archive` *(Admin Only)*
Performs a **soft delete** by setting `is_archived = true`. The pet is hidden from all catalog queries (via the Global Scope above) but its data is preserved in the database.

---

## Module: Adoption Applications

**Controller:** `ApplicationController`
**Views:** `resources/views/applications/`

This module enables adopters to submit applications and staff to review them through a structured workflow.

### Functions (Adopter)

#### `GET /applications/create/{pet}`
Renders the adoption application form for a specific pet.
- Verifies the pet exists and has `availability_status = 'available'` before showing the form
- Displays pet info (name, breed, branch) on the form for context

#### `POST /applications`
Submits an adoption application.
- **Duplicate prevention:** Checks if the current user already has an active (non-Approved, non-Rejected) application for the same pet
- **Document upload:** Requires a supporting document (government ID or Proof of Address), stored via the `Storage` facade
- Sets the initial status to `Pending`
- Logs the submission via the **Audit Log service**

#### `GET /applications/mine`
Displays a personal history of all adoption applications submitted by the currently logged-in adopter, ordered from most recent.

### Functions (Staff)

#### `GET /applications/queue`
Displays all adoption applications grouped by status: Pending, Under Review, Interview Scheduled, Approved, Rejected.

#### `PATCH /applications/{application}/status`
Updates an application's status through the **defined state machine**:

```
Pending → UnderReview → InterviewScheduled → Approved
       ↘              ↘                    ↘
        Rejected        Rejected             Rejected
```

- **Illegal transitions are blocked directly in the controller** (e.g., jumping from Pending to Approved directly is rejected with a validation/redirect error)
- When **Approved**: wrapped in `DB::transaction(function () { ... })` — the associated pet's `availability_status` is set to `Adopted`, and 3 `PostAdoptionLog` milestone records (3-Day, 3-Week, 3-Month) are created atomically in the same transaction
- When moved to **UnderReview**: the pet status becomes `Processing`
- An **email notification** (Mailable + `Mail::to($user)->send(...)`) is sent to the adopter informing them of the status change
- The status change is written to the **Audit Log**

#### `POST /applications/{application}/interview`
Schedules an interview date/time for an application. Validates the state machine (must be in Pending or UnderReview). Logs to the Audit Log and sends an email notification.

---

## Module: Admin Dashboard

**Controller:** `Admin\DashboardController`
**Views:** `resources/views/admin/dashboard/`

The central summary view for all staff (Administrators and Volunteers).

### Functions

#### `GET /admin/dashboard`
Aggregates real-time statistics via Eloquent query builder calls and displays them on the dashboard:
- **Total Animals** — count of all non-archived pets
- **Available Animals** — count of pets with `Available` status
- **Pending Applications** — count of applications in `UnderReview` status
- **Active Monitoring Cases** — total number of `PostAdoptionLog` records
- **Flagged Cases Count** — count of logs with `flagged_for_review = true`
- **Recent Applications** — the 5 most recently submitted applications
- **Post-Adoption Alerts** — the 5 most recent flagged monitoring logs

---

## Module: Animal Management (Admin)

**Controller:** `Admin\AnimalController`
**Views:** `resources/views/admin/animal/`

The administrative interface for viewing all animals in the system, including those in various adoption statuses.

### Functions

#### `GET /admin/animals` *(Staff Only)*
Lists all pets with full details including health status, availability status, and vaccination records. Maps raw `Pet` Eloquent models to a lightweight view-model array/DTO for display consistency.

> **Note:** Animal creation, editing, and archiving are still handled by the `PetController` module but displayed within the same admin layout/area.

---

## Module: Application Management (Admin)

**Controller:** `Admin\ApplicationController`
**Views:** `resources/views/admin/application/`

A detailed admin view of all applications with staff-side controls.

### Functions

#### `GET /admin/applications` *(Staff Only)*
Lists all adoption applications, eager-loaded with applicant and pet details (`with('user', 'pet')`). Displays applicant contact info, housing type, income range, interview date, and uploaded document path.

#### `POST /admin/applications/{application}/interview` *(Staff Only)*
Schedules an interview for an application by updating the `interview_date` field and setting the status to `InterviewScheduled`.

#### `POST /admin/applications/{application}/decision` *(Staff Only)*
Approves or rejects an application, updating its status and logging the decision in the Audit Log.

---

## Module: Adoption Profiles (Admin)

**Controller:** `Admin\AdoptionProfileController`
**Views:** `resources/views/admin/adoption-profile/`

A read-only view of all **approved** adoption cases, providing a contact directory for adopted pet owners.

### Functions

#### `GET /admin/adoption-profiles` *(Staff Only)*
Lists all applications with `Approved` status. Shows adopter name, contact email, phone number, and pet information.

#### `GET /admin/adoption-profiles/{application}/document` *(Staff Only)*
Redirects to the stored document's URL (`Storage::disk('public')->url($path)`). Allows staff to quickly access uploaded ID or proof of address documents.

---

## Module: Post-Adoption Monitoring (Adopter)

**Controller:** `MonitoringController`
**Views:** `resources/views/monitoring/`

The adopter-facing portal for viewing and submitting required post-adoption welfare reports.

### Functions

#### `GET /monitoring/my-checkins`
Lists all post-adoption milestones (3-Day, 3-Week, 3-Month) for the current adopter, showing:
- The pet's name
- The milestone type
- Scheduled date
- Submission status (Submitted / Pending / Overdue)

#### `GET /monitoring/reports/{log}/create`
Renders the welfare report form for a specific milestone.
- Validates the log belongs to the currently logged-in user (prevents accessing other users' forms — via a policy check or manual `abort_if`)
- Prevents re-submission if a report has already been submitted for that milestone

#### `POST /monitoring/reports/{log}`
Submits the welfare report for a specific milestone.
- Accepts: Pet current status, behavioral observations, living conditions, eating habits, vet visit details, any concerns, and an optional photo (via `Storage`)
- **Automatically triggers flag evaluation** via the `FlagEvaluationService` after saving
- Sends a **receipt email** (Mailable) to the adopter confirming their submission
- Logs the submission in the **Audit Log**

---

## Module: Monitoring Dashboard (Staff)

**Controller:** `Admin\MonitoringController`
**Views:** `resources/views/admin/monitoring/`

The staff-side view of all post-adoption check-in statuses across all adopters.

### Functions

#### `GET /admin/monitoring` *(Staff Only)*
Groups all post-adoption logs into three categories:
- **Compliant** — reports that have been submitted on time
- **Due Soon** — reports scheduled within the next 3 days that haven't been submitted
- **Overdue** — reports past their due date with no submission

#### `GET /admin/monitoring/flagged` *(Staff Only)*
Lists all post-adoption logs that are `flagged_for_review = true` and not yet resolved.

#### `POST /admin/monitoring/flagged/{log}/resolve` *(Staff Only)*
Resolves a flagged case by:
- Setting a `resolution_note`
- Recording a `resolved_at` timestamp
- Recording `resolved_by_user_id`
- Writing an entry to the **Audit Log**

---

## Module: Flagged Cases (Staff)

**Controller:** `FlaggedCaseController`, `Admin\FlaggedCaseController`
**Views:** `resources/views/flagged-cases/`, `resources/views/admin/flagged-cases/`

Manages welfare concerns that have been escalated for staff attention.

### Flagging Triggers (Automatic)
A case is automatically flagged when:
1. **Bad welfare report submitted:** The adopter reports `pet_current_status = Poor`; OR reports `Fair` with non-empty behavioral observations or concerns
2. **Missed check-in:** The scheduled task sends 2+ reminders for an overdue log with no response → the log is automatically flagged

### Functions

#### `GET /flagged-cases/reports/create` *(Adopter)*
Shows the overdue report submission form for flagged cases.

#### `GET /flagged-cases/overdue-notice` / `flagged-notice` *(Adopter)*
Informational pages shown to adopters when their check-in is overdue or their case has been flagged.

---

## Module: Audit Logs

**Controller:** `Admin\AuditLogController`
**Views:** `resources/views/admin/audit-logs/`

A chronological, immutable record of significant system actions performed by all users. No update or delete routes are exposed for this resource — it is append-only by design.

### Actions Tracked
| Action | Trigger |
|--------|---------|
| `Adoption Application Submitted` | Adopter submits an application |
| `Application Status Updated` | Staff changes application status |
| `Interview Scheduled` | Staff schedules an interview |
| `Post-Adoption Welfare Report Submitted` | Adopter submits check-in |
| `Post-Adoption Log Flagged` | Auto-flagging due to bad welfare report |
| `Post-Adoption Log Flagged (Missed Submission)` | Auto-flagging due to repeated reminders |
| `Post-Adoption Flag Resolved` | Staff resolves a flagged case |

Each log entry records: **Timestamp, UserId, Action, EntityName, EntityId, and Notes**.

### Functions

#### `GET /admin/audit-logs` *(Staff Only)*
Displays the full audit log table, viewable only by staff.

---

## Module: Volunteer & Staff Management

**Controller:** `Admin\VolunteerController`
**Views:** `resources/views/admin/volunteer/`

Allows administrators to manage the internal staff accounts (Volunteers and Admins).

### Functions

#### `GET /admin/volunteers` *(Admin Only)*
Lists all users with the `Administrator` or `Volunteer` role, showing name, email, role, and active status.

---

## Scheduled Tasks

### `SendCheckinReminders` (Artisan Command)

Replaces the .NET `BackgroundService`. Implemented as an Artisan console command (`app/Console/Commands/SendCheckinReminders.php`) and scheduled in **`routes/console.php`**:

```php
Schedule::command('checkins:send-reminders')->dailyAt('08:00');
```

The run time is configurable (e.g. via an environment variable read inside the command, or by adjusting the `dailyAt()` argument), mirroring the old `NotificationWorker:RunAtHour` setting.

**Daily Process:**
1. Queries all `PostAdoptionLog` records where no report has been submitted and the case is not already flagged
2. Sends **check-in reminder emails** (Mailable) to adopters whose milestone date is within the next 3 days or already past
3. Increments the `reminders_sent` counter for each log where a reminder is sent
4. After sending reminders, calls `FlagEvaluationService::flagMissedSubmissions()` to auto-flag logs that have accumulated 2 or more reminders without a submission

The server's own cron needs a single entry pointing at Laravel's scheduler runner, e.g. `* * * * * php artisan schedule:run`, which then dispatches this command at its configured time.

---

## Core Application Services

### `EmailService` (or plain Mailable classes + `Mail` facade)
Handles all transactional email delivery via Laravel Mail's SMTP driver. Reads connection settings from `config/mail.php`, sourced from environment variables (`MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`), pointed at **Mailtrap** in development.

| Method / Mailable | Purpose |
|--------|---------|
| `Mail::to($user)->send(...)` | Base mechanism — sends a Mailable-rendered HTML email |
| `CheckInReminderMail` | Reminder email for an upcoming/overdue milestone |
| `StatusUpdateMail` | Notifies adopter of application status change |
| `WelfareReportReceiptMail` | Confirms an adopter's submitted welfare report |

### `FlagEvaluationService`
Evaluates welfare report submissions and overdue logs to determine if they should be flagged for staff review.

| Method | Purpose |
|--------|---------|
| `evaluate($logId)` | Called after every welfare report submission. Flags the log if `pet_current_status = Poor`, or if `Fair` with behavioral concerns noted |
| `flagMissedSubmissions()` | Called by the scheduled task. Scans overdue logs and flags any that have received 2+ reminders without a submission |

### `AuditLogService`
A simple write-only service for creating audit log entries.

| Method | Purpose |
|--------|---------|
| `log($userId, $action, $entityName, $entityId, $notes)` | Creates an `AuditLog` Eloquent record |

### File Uploads (via `Storage` facade)
No custom upload service is needed — Laravel's `Storage` facade handles secure file uploads for pet photos, adopter ID documents, and welfare report photos directly in the relevant controllers (or a thin shared trait, if reuse is desired).
- File type/size validation is done through Laravel's `FormRequest` validation rules (e.g. `mimes:jpg,png,pdf`, `max:2048`)
- Files are stored on the `public` disk under `storage/app/public/{subfolder}/`, with Laravel auto-generating a unique filename
- The relative path returned by `Storage::disk('public')->putFile(...)` is what's saved in the database

---

## Data Models

| Model | Key Fields | Purpose |
|-------|-----------|---------|
| `User` | `id`, `first_name`, `last_name`, `role`, `is_active`, `branch_id` | Extends Laravel's default users table. Represents all system users (Admins, Volunteers, Adopters) |
| `Pet` | `id`, `name`, `species`, `breed`, `age`, `sex`, `health_status`, `availability_status`, `branch_id`, `is_archived`, `version` | A shelter animal. Soft-hidden via `is_archived` + Global Scope. Uses a manual `version` column for optimistic concurrency |
| `Branch` | `id`, `name`, `address`, `contact_number` | A physical shelter branch location |
| `AdoptionApplication` | `id`, `user_id`, `pet_id`, `status`, `motivation_statement`, `document_path`, `version` | An adopter's formal request to adopt a specific pet. Tracks the full application lifecycle |
| `PostAdoptionLog` | `id`, `application_id`, `milestone`, `scheduled_date`, `submitted_date`, `pet_current_status`, `flagged_for_review`, `reminders_sent`, `version` | A single post-adoption check-in record. Three are created per approved application (3-Day, 3-Week, 3-Month) |
| `AuditLog` | `id`, `user_id`, `action`, `entity_name`, `entity_id`, `notes`, `created_at` | An immutable record of a significant system action |

### Key Enums

Implemented as PHP 8.1 **backed enums** in `app/Enums/`, cast on the Eloquent model via `protected $casts = ['status' => ApplicationStatus::class]` (or the equivalent per-field cast).

| Enum | Values |
|------|--------|
| `Role` | `Administrator`, `Volunteer`, `Adopter` |
| `Species` | `Cat`, `Dog` |
| `AvailabilityStatus` | `Available`, `Processing`, `Adopted` |
| `ApplicationStatus` | `Pending`, `UnderReview`, `InterviewScheduled`, `Approved`, `Rejected` |
| `Milestone` | `ThreeDays`, `ThreeWeeks`, `ThreeMonths` |
| `PetCurrentStatus` | `Good`, `Fair`, `Poor` |

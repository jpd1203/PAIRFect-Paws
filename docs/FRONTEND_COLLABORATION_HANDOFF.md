# Frontend collaboration handoff

Prepared November 13, 2026 (Asia/Singapore) for the PAIRfect Paws frontend team.

## Start here

**Shared branch: collab/frontend-ui-fixes**
**Exact application base: ab670a9e37207a712c44c68754fa45220f224c66**

This is the full latest validated development application, NOT the older Azure/master
tree. The handoff commit adds only this document. Controllers, services, models,
50 migrations, all existing Blade/CSS/JavaScript, composer.json/composer.lock,
package.json/package-lock.json, tests and previous handoff documents are retained
unchanged. Dependencies, local .env, uploads and QA artifacts are NOT shipped in Git.

This branch is prepared locally and has NOT been published, merged or deployed by
this task. Commands referring to origin/collab/frontend-ui-fixes work only AFTER
the repository owner explicitly authorizes and publishes it.

You are free to redesign layouts, typography, navigation, cards, forms and modals,
improve accessibility and fix mobile/tablet/desktop presentation. You do NOT need to
preserve the present visual design. Preserve the request contracts/security/lifecycle
rules below; coordinate semantic changes with the backend owner.

Read these supporting documents:

- [Consolidated functional UI handoff](FRONTEND_HANDOFF_PHASE_16.md).
- [Manila time and accurate document status](FRONTEND_HANDOFF_PHASE_16_1.md).
- [Species-first size filtering](SPECIES_FIRST_SIZE_FILTER.md).
- [Weight-based size authority](WEIGHT_BASED_PET_SIZE.md).
- [Historical classification safety](HISTORICAL_PET_CLASSIFICATION_SAFETY.md).
- [Fictional fixture setup](PROTOTYPE_PET_NORMALIZATION.md).
- [Handover readiness/log clarity](HANDOVER_READINESS_AND_LOG_CLARITY.md),
  [clearance](HANDOVER_DELIVERY_CLEARANCE.md),
  [identity/scheduling](HANDOVER_IDENTITY_SCHEDULING.md),
  [transport cases](HANDOVER_TRANSPORT_INVESTIGATIONS.md).
- [Notification reliability](NOTIFICATION_RELIABILITY_PHASE_15D.md),
  [handover outbox](HANDOVER_EMAIL_RELIABILITY.md),
  [in-app links](IN_APP_NOTIFICATIONS.md).

The older Phase 16 document records timezone/OCR defects that Phase 16.1 subsequently
fixed. Do not treat those two defects as still open or revert those corrections.
The mobile/duplicate-ID issues below are still open.

## A. Where the UI lives

Paths below are real files in this branch. A complete file inventory follows in
Appendix A. Some templates are partials or alternative/legacy entry views, not
separate navigation pages; check route/controller usage before deleting them.

| Area | Current views/components | Main routes and client dependencies |
| --- | --- | --- |
| Public landing, QR donation | resources/views/landing.blade.php; resources/views/donate.blade.php; components/service-card.blade.php and donation-channel-card.blade.php | GET / (landing), /home (home), /donate (donate). Donate displays bank QR information, not a payment processor/public fund ledger. |
| Login AND registration | resources/views/auth/login.blade.php | GET/POST /login: login / login.store. GET /register redirects to /login?tab=register; POST /register: register.store. There is no separate register.blade.php. Shared tabs and legal dialogs live here. |
| Password/verification/access | resources/views/auth/forgot-password.blade.php, reset-password.blade.php, verify-email.blade.php, access-denied.blade.php | password.request, password.email, password.reset, password.update; verification.notice/send/verify. Keep signed verification and reset-token contracts. |
| Adopter starting page/shell | resources/views/animal/index.blade.php; layouts/app.blade.php; partials/sidebar.blade.php, profile-card.blade.php, email-verification-banner.blade.php | Signed-in adopter start is GET /animal (animal.index). No standalone adopter dashboard template/route exists; admin has its own dashboard. |
| Browse and pet details | resources/views/animal/_pet-grid.blade.php, _pet-modal-content.blade.php; resources/views/pets/index.blade.php, show.blade.php | Public pets.index/show/modal: GET /pets, /pets/{pet}, /pets/{pet}/modal. Adopter /animal uses the shared catalog/modal behavior. resources/js/app.js and public/js/browse-pets.js. |
| Personality/onboarding | resources/views/recommendation/intake.blade.php; partials/bfi-questionnaire.blade.php, matching-household-fields.blade.php | recommendation.intake/start; recommendation.onboarding / onboarding.skip. Preserve segmented controls and synchronized named inputs, not necessarily their styling. |
| Recommendation results | resources/views/recommendation/results.blade.php, _match-card.blade.php, _pagination.blade.php | GET /recommendation/results; POST /recommendation/recompute. public/js/recommendation-size-filters.js; shared pet modal from resources/js/app.js. Bottom pagination preserves filters. |
| Formal application/history | resources/views/application/apply.blade.php, index.blade.php | GET /apply/{pet}; POST /application/submit; GET /application. Aliases applications.create/store/mine also exist. public/js/my-application.js. |
| Account settings | resources/views/account/settings.blade.php | account.settings; PATCH account.profile.update and account.password.update. |
| Staff dashboard/animal intake | resources/views/admin/dashboard/index.blade.php; admin/animal/index.blade.php, _add-modal.blade.php, _view-modal.blade.php; pets/create.blade.php, edit.blade.php | admin.dashboard; admin.animals.* and admin.pets.* routes. public/js/admin/animal.js; partials/pet-measurement-inputs.blade.php, pet-calculated-size.blade.php, pet-size-guidance.blade.php. |
| Behavioral assessment | resources/views/admin/assessment/record.blade.php, form.blade.php, _question_row.blade.php, _summary-modal-content.blade.php | admin.assessments.record/create/store/summary. Server questionnaire is config/matching.php; distinct observers are enforced server-side. |
| Staff application review/interviews | resources/views/admin/application/index.blade.php; _review-modal, _schedule-modal, _note-modal, _decision-modal, _history-modal, _compatibility-modal.blade.php | admin.applications.index/export/schedule/interview/notes/decide/queue-outcome. public/js/admin/application.js and inline interview-mode bindings. |
| Staff documents/identity | resources/views/admin/application/document-verification.blade.php, identity-verification.blade.php, _identity-form.blade.php | admin.applications.document/document-verification/document-ocr-retry/document-decision/identity/identity.store. Authorized private routes, never public file links. |
| Compatibility/adopter account histories | resources/views/admin/compatibility/index.blade.php; admin/adopter-profile/index.blade.php and _history-modal-content.blade.php | admin.compatibility.index, admin.pets.ranked-applicants, admin.adopter-profiles.index/history; adoption-profiles aliases remain. Histories group by adopter account, then placement. |
| Staff handover | resources/views/admin/handover/index.blade.php, show.blade.php, _pipeline.blade.php, _schedule.blade.php, _release_form.blade.php, _recorded_release.blade.php, _adopter_confirmation.blade.php | admin.handover.index/show/schedule/reschedule.review/release/reminder/reopen; release-proof is protected. |
| Delivery readiness/cases | resources/views/admin/handover/_delivery-clearance.blade.php, _transport-investigations.blade.php | admin.handover.delivery.address/readiness; admin.handover.incidents.store/update/evidence. |
| Adopter handover/receipt | resources/views/handover/status.blade.php, confirm.blade.php, notifications.blade.php, _schedule-actions.blade.php, _schedule-summary.blade.php, _live_tracking.blade.php | adopter.handover.my/status/schedule.method/schedule.confirm/reschedule; adopter.confirm/confirm.submit; handover.receipt-proof. |
| Adopter check-ins | resources/views/monitoring/my-checkins.blade.php, report-due.blade.php, create.blade.php, overdue-notice.blade.php, flagged-notice.blade.php, _report-view-modal-content.blade.php | monitoring.my-checkins/submit-report/create/submit/capture-challenge/follow-up/modal. resources/js/post-adoption-camera.js; public/js/my-check-ins.js. |
| Staff monitoring/welfare resolution | resources/views/admin/monitoring/index.blade.php, flagged.blade.php, _resolution-fields.blade.php, _return-confirmation.blade.php | admin.monitoring.index/flagged/photo/video/reminder/flag; admin.monitoring.resolve is administrator-only. |
| Admin-only management/demo | resources/views/admin/volunteer/index.blade.php, create.blade.php; admin/audit-logs/index.blade.php; admin/post-adoption-demo/index.blade.php; admin/time-travel/index.blade.php | admin.volunteers.*, admin.audit-logs.*, admin.post-adoption-demo.*; time-travel.* is a separate guarded legacy testing tool. Do not expose admin/demo controls to volunteers/adopters. |
| Notifications | resources/views/notifications/index.blade.php; partials/notification-bell.blade.php | notifications.index/open/read/read-all. Handover-specific notices also have protected adopter routes. Keep deep links/highlight targets. |
| Legal content | resources/views/legal/terms.blade.php, terms-content.blade.php, privacy.blade.php, privacy-content.blade.php | Registration opens dialogs with shared content; standalone legal routes remain fallbacks. Do not remove consent fields or weaken content/consent requirements. |
| Outgoing email UI | resources/views/mail/*.blade.php; components/mail/layout.blade.php | Verification/reset/transactional/check-in/welfare templates. Styling must preserve signed URLs, escaping, recipient privacy and accurate intent/delivery wording. |

Shared foundations:

- resources/views/layouts/app.blade.php, components/public-layout.blade.php,
  admin/layouts/app.blade.php; components/navbar.blade.php, footer.blade.php,
  nav-link.blade.php; admin/partials/sidebar.blade.php.
- resources/css/app.css and admin.css; resources/js/app.js and admin.js are Vite
  entries alongside the CSS entries (vite.config.js). app.js imports philippine-address.js
  and post-adoption-camera.js. Some pages also have inline scripts and public/js
  scripts; do not assume every handler comes from the bundle.
- Preserve modal IDs/data attributes/window callbacks such as PAIRfectPaws, openModal
  and openPetModal, or update every caller/test together. Avoid duplicate event binding.
- partials/favicons.blade.php and open-graph.blade.php control common branding metadata.
  Keep PAIRfect Paws browser/email branding; visible marketing copy is a separate concern.

## B. UI issue backlog — document now, fix on frontend branches

| Status | Reproduce/review | Files and cautions |
| --- | --- | --- |
| Confirmed in earlier bounded browser QA; still deferred | Staff delivery-readiness unavailable/disabled panel clips at360px. Reproduce that state, not only completed pickup. | admin/handover/_delivery-clearance.blade.php, admin/handover/show.blade.php, resources/css/admin.css. Check long status/help text, min-content sizing and horizontal scroll. Do not remove prerequisite guidance. |
| Confirmed earlier; still deferred | At390px, navigate/redirect to #deliveryReadiness: fixed header obscures its heading. | Same partial, admin layout/topbar/CSS. Adjust scroll offset/focus while retaining fragment target and nearby errors. |
| Confirmed earlier and source still contains it | Duplicate releaseFormSection IDs can render for identity/schedule/release sections. | admin/handover/_schedule.blade.php has two conditional occurrences; _release_form.blade.php has another. show.blade.php composes them. Make IDs unique with coordinated deep-link/selector review; #handoverForm is the actual release form. |
| Reproduced in THIS read-only guest browser pass | Registration first_name, last_name, email, password and password_confirmation have no associated label (input.labels.length=0); email/password controls have blank accessible names in the snapshot. Sign-in email/password labels are associated correctly. | auth/login.blade.php. Add unique IDs/for associations or equivalent accessible names without changing submitted names, tabs or validation. First/Last placeholders are not a replacement for persistent labels. No fix was made here. |
| Earlier accessibility-review recommendation, not a certification result | Decorative icon glyphs can contribute to accessible control names. | Review Font Awesome markup, semantic buttons, aria-hidden on decoration, focus rings and keyboard operation. Preserve meaningful text. |

This pass used real headless Chromium at 360x800, 390x844 and 1280x800 against a NEW
loopback-only, read-only guest harness. No migrations, seeders, account creation,
login POSTs, database record changes, mail or jobs occurred. Mutating methods and
database-backed catalog routes were blocked by the ignored QA router.

Observed: desktop sign-in renders; mobile registration document width equals viewport
at 360/390; Terms dialog opens and Escape returns focus to its triggering link;
Region I -> Ilocos Norte -> Adams populates province/city/barangay from local JSON.
These are guest UI checks, not submitted-address persistence or authentication proof.
No newly reproduced horizontal overflow in these particular guest states.

This pass did NOT reproduce the authenticated staff readiness states because that
would require fixture database setup/writes, outside this preparation's restriction.
Do not label the three inherited issues fixed or retested. No physical-phone,
screen-reader, broad accessibility, private-document or production QA certification.

Ignored local evidence in the preparation worktree (NOT committed):
output/playwright/collab-login-desktop.png, collab-registration-360.png,
collab-registration-390.png, collab-terms-360.png; CLI snapshots under .playwright-cli/.
Previous authenticated evidence locations are documented in the Phase16/16.1 handoffs.
Arrange a separately approved evidence handoff; screenshots do not travel in this branch.

## C. Backend contracts — restyle controls, do not bypass these

Authoritative routes are routes/web.php; validators/controllers/services remain the
source of truth if a description needs clarification. Use Blade route() helpers and
model-bound IDs, not hand-assembled URLs or hardcoded account/pet names.

### Common request, error and security behavior

- Keep @csrf on state-changing forms, correct GET/POST/PUT/PATCH/DELETE methods,
  @method overrides, multipart encoding for files and the csrf-token meta/header
  used by fetch. No state-changing action should become a plain GET link.
- Keep old() input, field-level @error messages, error summaries, loading/disabled
  states and422 JSON handling. A network error must not show a success state.
  Disable double submission as feedback, but keep server transaction/idempotency rules.
- Keep auth/adopter/staff/admin/verified restrictions, active-account checks and
  record ownership. Hiding a button is not authorization. Do not remove a server
  guard to make a redesigned button work.
- Use escaped Blade output/textContent for user/pet names, notes, OCR text and history.
  Do not interpolate untrusted text into executable inline JavaScript. The receipt
  not-received confirmation intentionally uses a STATIC message (Phase1 security fix).
- Update script selectors/IDs/data attributes in all consumers and tests together.
  Notification deep links/highlight query parameters and readiness anchors must
  continue landing on the correct visible record/section, not an empty modal.

### Authentication, location and matching inputs

| Form | Contract |
| --- | --- |
| Sign-in | email,password; optional remember,redirect. Preserve safe intended-action return and inactive-account feedback. |
| Register | first_name,last_name,email,password,password_confirmation, terms_accepted=1,privacy_consent=1, structured address below. Role is assigned by backend; do not add a public role/admin selector. |
| Address | region_code,province_code,city_municipality_code,barangay_code,street_address,zip_code. Submit PSGC CODE strings, not display names; server resolves/validates parents and persists canonical names. ZIP is required four digits by backend. The direct-region province sentinel is __direct__, not an invented province. Keep data-address-* bindings and dependent resets in components/philippine-address-fields.blade.php and resources/js/philippine-address.js. |
| Password reset | password.email uses email; password.update uses token,email,password,password_confirmation. Preserve throttles/token binding; never put reset tokens into unrelated UI logs. |
| Verification | Signed verification.verify URL with id/hash can work while logged out; preserve signature/expiry. Resend is POST verification.send with throttling. Do not automatically mark users verified client-side. |
| BFI matching intake | bfi_responses[EX1]..[EX6], [C1]..[C6], [N1]..[N4], [O1]..[O4]; exactly20 configured keys, each1–5. Also housing_type,monthly_income_range (existing ApplicationOptions values), has_existing_pets/has_children as0/1. Optional intended-pet flow must return to its original action. |
| Profile/formal application distinction | Formal apply still includes physical_activity_level,time_availability,prior_pet_experience,household_composition as descriptive submitted strings, plus contact/address/motivation/document/consent. Do NOT silently remove these because matching intake uses BFI; changing their validation is backend work. |

Skipping onboarding may create/use an account and browse pets, but a COMPLETE reusable
BFI/matching profile is required before personalized results or a formal application.
Anonymous matching uses the existing session-based guest profile. Unverified adopters
may browse/recommend under current policy, but formal applications/sensitive actions
remain blocked. Preserve the verification notice and intended-pet return.

Keep one synchronized named control per BFI key. The existing segmented UI updates
hidden selects; cosmetic changes must not omit N1, submit duplicates, change key
case, replace blanks with zero or send client-calculated personality scores.
config/matching.php owns prompts/dimensions/reverse keys. Current O2 is “Has little
interest in abstract ideas”; do not resurrect old wording/mappings from frontend-v2.
Changing wording/scoring/required answers is not a styling-only decision.

### Pet intake, assessment and recommendation authority

- New pet measurements use age_years and age_months (0–11 additional months);
  persisted age is canonical total months. weight_kg is positive decimal kg, height_cm
  optional decimal cm. Preserve pet version bindings/optimistic conflict feedback.
  Do not restore ambiguous old age input or editable authoritative physical_size.
- PetSizeClassifier is the single backend authority (weight-v1, species/known adult
  age/measured weight). Render server size_display and filter labels. No independent
  threshold table in JavaScript, fake adult sizes or healthy-weight/vet claims.
  Historical legacy_unclassified does not become trusted via ordinary form edits.
- Behavioral assessment uses responses[group][item] from config/matching.php,
  nullable integer0–4 where allowed, not BFI's1–5 scale. Unobserved is not zero.
  Respect server coverage/validity and three DISTINCT staff observers; the same
  administrator/volunteer cannot assess the same pet repeatedly.
- GET recommendation.results accepts species empty/Dog/Cat; size empty or Extra Small,
  Small,Medium,Large,Extra Large; include_ineligible=1 when requested; page.
  Explanatory kg ranges are LABELS, not submitted sizes. All Species allows only
  All Sizes. Reset size on species change, keyboard and restored/back-forward states.
- Preserve IDs recommendationFilters, recommendationSpecies, recommendationSize,
  recommendationSizeHelp, recommendationSizeOptions and the server-generated
  application/json options, or coordinate every binding/test change.
- Current default is eligible-only. Explicit include_ineligible=1 retains excluded
  cases with reasons and disabled adoption; preserve that flag across filters/pages.
  A size-specific result must pass hasClassifiedPhysicalSize() and current metadata.
- Keep current KNN formula/six features/weights, safety/availability filters, distance
  ordering, server score/eligibility/explanations and page size. Do not turn an
  ineligible item into “low match” with an enabled Apply button or fabricated score.
- Browse visibility requires the existing completed-assessment/availability/archive
  policy. A pet visible in an ineligible recommendation is not automatically adoptable.

### Applications, interviews and private verification

- Formal application: POST application.submit, bound pet_id; first_name,last_name,email,
  phone_number, address codes, the four descriptive profile fields listed above,
  motivation_statement, document (one PDF/JPG/JPEG/PNG, current limit10MiB),
  agreed_to_terms. Server checks verified account/profile, availability and computes
  authoritative KNN score before entry into ranking. Never trust a submitted score.
- Document replacement: applications.document.replace, bound application; keep a
  SINGLE document input and current follow-up limits. Do not use multiple/gallery
  uploads or expose storage paths. OCR/review/extracted text remain staff-only.
- Staff scheduling: admin.applications.schedule includes application_id (selected
  actual account/application, not free-text identity), interview_date,interview_time,
  interview_mode=Online or InPerson, staff_id. Online needs interview_meeting_url;
  InPerson needs interview_location. Preserve conditional hidden/disabled field
  handling and validation. Other forms use bound admin.applications.interview.
- Notes/decision/queue-outcome/document-decision forms must preserve their existing
  fields and controller status/role rules. Administrator-only final decisions,
  queue overrides (reason required) and audit pages must not become volunteer actions.
- Identity form: stage,verification_method,status,discrepancy_note and configured
  checks government_id_presented,applicant_matches_id_photo,name_matches_application,
  submitted_document_consistent,no_material_discrepancy. Interview permits
  in_person/video_interview; pickup is in_person. Status values verified/needs_review/
  failed. Preserve per-attempt identity and protected document review requirements.
- Phase16.1 document messaging is evidence-aware: Verified alone does not prove OCR
  ran, Google succeeded, identity/authenticity or biometric verification. No-text
  manual/unknown states remain neutral; extracted text/reasons stay escaped.

### Handover forms, agreement versions and delivery readiness

| Action | Route/method | Submitted contract |
| --- | --- | --- |
| Select initial method | POST adopter.handover.schedule.method, {handover} | method=pickup/delivery, schedule_version |
| Staff proposal | POST admin.handover.schedule, {handover} | method,date(Y-m-d),start_time/end_time(H:i),schedule_version |
| Confirm agreement | POST adopter.handover.schedule.confirm, {handover} | schedule_version |
| Request reschedule | POST adopter.handover.reschedule, {handover} | schedule_version,reason,options[0..2][date/start_time/end_time] |
| Review request | POST admin.handover.reschedule.review, {handover} | schedule_version,decision=approved/declined, option_index when approved |
| Verify delivery destination | POST admin.handover.delivery.address, {handover} | schedule_version,clearance_version,destination_fingerprint,status=verified/needs_review |
| Record contact/availability | POST admin.handover.delivery.readiness, {handover} | same revision/fingerprint fields;recipient=adopter;contact_method=phone/email/other;available=0/1;notes required for other |
| Release | POST admin.handover.release, {handover} | release_method=pickup/delivery,release_date,release_time,staff_id; delivery courier_provider,tracking_number,tracking_url; optional proof image |
| Receipt | POST adopter.confirm.submit, {handover}, multipart | outcome=received/not_received,note,receipt_proof; CSRF |
| Reopen/remind/case actions | existing admin.handover.reopen/reminder/incidents.* | Keep current eligibility/attempt/version/reason/evidence controls; do not reconstruct state in JavaScript. |

Never discard/version-refresh hidden agreement data automatically to force a stale
form through. A method/window change requires the real proposal/rescheduling workflow
and renewed adopter agreement. Initial method selection does not permit mutating an
already proposed/confirmed agreement in place.

Readiness requires active verified authorized staff, approved/unreleased delivery,
current verified snapshot address, confirmed NONEXPIRED agreement, and actual direct
adopter contact. No third-party recipient authorization. Unavailability is a saved
outcome but blocks dispatch. Keep disabled explanations, status feedback, old input,
#deliveryReadiness, #deliveryReadinessErrors and #deliveryReadinessPrerequisites.
An outgoing email by itself is not a completed availability conversation.

Release checks occur server-side under locks: confirmation, identity, clearance,
courier/provider/reference/HTTPS tracking URL, active staff and no duplicate transition.
Only EXPIRED windows are blocked; existing early release for pickup AND delivery
remains allowed. Do not introduce an earliest-start policy in HTML/JavaScript.
Release_date/release_time are Manila wall-clock inputs; canonical released_at is UTC.
Release proof is optional under current validator, image JPG/PNG/WEBP up to5MiB;
do not invent a mandatory new business requirement.

Positive receipt requires receipt_proof JPG/PNG/WEBP up to5MiB. Negative outcome still
requires confirmation: Cancel must not submit; Accept submits outcome=not_received.
Do not turn both buttons into received, lose the clicked submitter's value, or add
a dynamic executable pet-name prompt. Receipt proof routes remain private.
Reopening preserves history and existing eligibility, increments attempts and clears
the prior agreement/release. Missing reason still uses backend default “Handover redone”;
making it mandatory server-side is a separate policy decision.

History labels go through HandoverHistoryLabel::forStaff(), with actor/order/evidence
preserved. Technical revisions/fingerprints stay in audit storage; do not rewrite
historical labels or expose technical noise merely to simplify rendering.
External courier links are share/tracking references, not integrated booking/GPS APIs.

### Time, monitoring and notifications

- Use App\Support\ManilaTime for canonical instant displays. Do not add eight hours
  manually, globally change app timezone or double-convert saved release_date/time.
  Date/time defaults use one captured instant; old/saved form input wins over defaults.
- Monitoring starts ONLY after staff release plus protected positive adopter receipt.
  An approval, schedule, delivery-ready flag, investigation closure or negative receipt
  is not completion. Retain3-Day ->3-Week ->3-Month order and due/overdue/flagged states.
- Check-in form fields: pet_current_status,living_conditions,eating_habits,
  behavioral_observations,vet_visit_details,concerns; keep existing option VALUES
  and backend validation lists. Camera fields include video,capture_challenge_id,
  capture_challenge_token,camera_captured_at,recording_duration_ms.
- resources/js/post-adoption-camera.js owns MediaRecorder/live3-second capture,
  five-minute session-bound challenge flow, FormData, validation, cleanup and retake.
  Preserve data-post-adoption-camera-form/data-camera-* bindings/challenge URL/CSRF.
  No standard gallery uploader or client “verified” flag may bypass backend ffprobe,
  SHA-256 replay checks, token consumption, ownership and due/submission guards.
  HTTPS/localhost camera access is required. Browser capture is not unspoofable proof.
- Structured welfare resolution/physical return uses admin-only controls,
  resolution_outcome,resolution_note and conditional return_date,return_reason,
  return_condition,return_handled_by_user_id,confirm_return. Derive values from enum/
  controller, not renamed labels. Return-date validation uses Philippine calendar time.
- In-app intent, queued email, transport acceptance and inbox delivery are different.
  Keep accurate wording and deep-link behavior. Do not advertise SMS/push delivery or
  change reminders/retry timings as part of a redesign. Use fake/log mail locally;
  never process a copied live queue to test a toast.

## D. Local setup — future developer commands, not run on existing databases

Prerequisites: PHP 8.4.1+ (match repository 8.4 CI), Composer 2, Node 26.x/npm matching
the current CI, Git; required PHP extensions/platform requirements. XAMPP PHP 8.2/8.3
is too old. SQLite needs pdo_sqlite/sqlite3; MySQL uses pdo_mysql. Use a separate
disposable DB either way. No XAMPP server is needed for the SQLite + PHP-server path.

After the owner publishes this branch, in a NEW clean clone:

```sh
git clone --branch collab/frontend-ui-fixes --single-branch https://github.com/jpd1203/PAIRFect-Paws.git PAIRfect-Paws-ui
cd PAIRfect-Paws-ui
git status --short
php --version
node --version
composer install --prefer-dist --no-interaction
composer check-platform-reqs
npm ci
npm run build
```

Use locked installs, not composer update/npm update/audit fix or global dependency
upgrades during UI work. npm currently reports inherited build-tool advisories; do
not silently update lockfiles to make that warning disappear. Discuss separately.
Vite build precedes layout-rendering tests so public/build/manifest.json exists.

Example Windows PowerShell preparation, ONLY in that fresh clone:

```powershell
if (Test-Path -LiteralPath .env) { throw 'Review existing local environment; do not overwrite it.' }
Copy-Item -LiteralPath .env.example -Destination .env
if (Test-Path -LiteralPath database/frontend-ui.sqlite) { throw 'Choose a NEW disposable DB file.' }
New-Item -ItemType File -Path database/frontend-ui.sqlite
(Resolve-Path -LiteralPath database/frontend-ui.sqlite).Path
```

Edit the NEW untracked .env locally: APP_ENV local, APP_DEBUG true, loopback APP_URL,
DB_CONNECTION sqlite and DB_DATABASE set to that new file's absolute path; clear
DB_URL so it cannot override the target. MAIL_MAILER array, QUEUE_CONNECTION sync,
SESSION_DRIVER file, CACHE_STORE file. Leave real SMTP/OCR/cloud credentials unset.
Never copy Azure/development .env, credentials, live uploads, database exports or queues.
Existing .env.example defaults are NOT permission to connect to an existing MySQL DB.

Before continuing, privately confirm the effective target is the newly created
disposable file. Then, for this NEW local DB ONLY:

```sh
php artisan key:generate
php artisan migrate
php artisan db:seed --class=PrototypePetSeeder
php artisan serve --host=127.0.0.1 --port=8000
```

These are setup instructions, not actions performed in this preparation task.
Use another free port if frontend-v2 already uses 8000; never stop its server blindly.
Use npm run dev in a second terminal for HMR, OR npm run build for compiled assets.
Do not leave a stale public/hot pointing to a stopped Vite server; stop HMR normally
and rebuild when testing compiled assets.

PrototypePetSeeder is opt-in/local-testing only: 15 fictional pets, 44 raw assessments,
three distinct fictional volunteer observers and one matching-complete adopter.
Ten adult fixtures are recommendation-eligible for that profile; juvenile,
unknown-age, missing-weight, incomplete-assessment and unavailable examples remain.
Accounts/passwords are explicitly fictional and defined in that seeder; never reuse
them in a hosted environment. Array mail does not deliver verification/reset emails.
Use the seeder's synthetic verified accounts or approved automated tests, not a real
recipient or weakening verification guards to get past a demo screen.

For broader staff/adoption/handover UI scenarios, DefenseDemoSeeder is an OPTIONAL
local-only deterministic scenario on a SEPARATE fresh disposable DB. It creates
fictional records/evidence and refuses reseeding its existing demo branch. Review
its source and credentials before use; it is not a repair/reset tool. MatchingDemoSeeder
tests are destructive and must run separately on disposable databases. Never run
AssessmentSeeder/DemoPetsSeeder/reset/migrate:fresh on an established/shared DB.
No seed/migration/database-write command above was executed by this handoff task.

### Checks before a UI PR

```sh
npm run test:frontend
npm run build
php artisan view:cache
php artisan view:clear
git diff --check
git status --short
```

For changed forms/interactions, also run the relevant feature regressions using ONLY
a separately configured disposable test DB and fake mail. Examples: HandoverReceiptSecurityTest,
HandoverScheduleIntegrityTest, HandoverReadinessFeedbackTest, HandoverDeliveryClearanceTest,
HandoverReleaseAtomicityTest, HandoverReopeningAtomicityTest, HandoverTimezonePresentationTest,
DocumentVerificationPresentationTest, SpeciesFirstSizeFilterTest and matching/monitoring
suites. SQLite-only tests do not prove MySQL concurrency. Full MySQL/SQLite and the
destructive MatchingDemoSeederTest must be separate/serial processes because fake
uploads share storage. Coordinate full backend validation with the backend owner.

## E. Existing frontend-v2 is NOT the branch source

Read-only inspection of the existing PAIRfect-Paws-frontend-v2 worktree found:

- HEAD 655e95bba8b4c6340df9890dfd5733077f0ca72a,
  branch feature/interview-type-2026-10-05.
- 63 modified tracked files and 39 untracked files total; 27 modified UI files and 6
  untracked UI files. Many backend/services/migrations/tests are mixed into this
  dirty checkout. Some are earlier copies of already integrated phases.
- Actual sampled UI work includes a new untracked components/pet-image.blade.php,
  replacement image usage and CSS [data-pet-image] styling. This component and its
  dependent references/tests must be reviewed together, not copied individually
  into views where the component does not exist.
- The sampled BFI diff is whitespace-only; do not assume every dirty path contains a
  new design or replace the newer BFI policy. The complete dirty UI path list is in
  Appendix B. Untracked get()) is an unexplained artifact for its owner to inspect,
  not something this task deletes or publishes.

No file was reset, overwritten, stashed, cleaned, staged, committed or automatically
merged in that existing worktree. Do not publish its entire dirty tree as UI work.

Safe owner-led port:

1. Leave frontend-v2 unchanged; optionally make an owner-controlled backup outside
   Git before work. Do not include .env/uploads/dependencies/QA in a patch.
2. Create a NEW porting worktree based on the shared branch (after publication):

```sh
git fetch origin
git worktree add -b ui/selected-frontend-v2-port ../PAIRfect-Paws-ui-port origin/collab/frontend-ui-fixes
```

3. In the NEW porting worktree, compare selected files against the dirty original
   using an editor/diff tool. Example (adjust paths):

```sh
git diff --no-index -- ../PAIRfect-Paws-frontend-v2/resources/css/app.css resources/css/app.css
```

Exit 1 here means differences, not a failed merge. A normal git diff in frontend-v2
compares to its OLD HEAD and includes functionality already present/newer elsewhere.
Do not blindly git apply that patch or replace whole folders/files from the old tree.
Port visual hunks manually into the new base; review new components/dependencies
as a unit; keep current security, time/OCR, classification and hidden-field contracts.
Ask the backend owner about semantic conflicts rather than selecting ours/theirs.
Test, explicitly stage chosen paths and submit a PR to collab/frontend-ui-fixes.

## F. Publication, individual branches and integration

### Publication risk inspection

At preparation time, GitHub reported one active workflow,
.github/workflows/master_pairfectpaws.yml, and ZERO repository webhooks.
Current remote main/master were c1011b0e6357f33f9f3f655f78ea75304bd292de /
8b6ba70dd155ab43f2330c773edcb69de563eda7. The proposed shared branch was absent remotely.

The collaboration branch contains the newer Phase 18A workflow:

- Push trigger: master ONLY. Publishing collab/frontend-ui-fixes does not match it.
- PR trigger: main/master ONLY. A PR INTO collab/frontend-ui-fixes has NO automatic
  validation from this workflow. Run the checks above manually; no checks is not PASS.
- Manual dispatch from non-master cannot enter runtime/deploy due to exact guards.
  Do not dispatch workflows in this task.
- The existing remote master still has the older automatic deployment workflow.
  Do not target/merge/push master or main during frontend collaboration.

No deploy is triggered by this branch push in the inspected YAML, and no repository
webhook was configured. Installed GitHub Apps, external systems/Azure Deployment
Center or integrations outside those observations were NOT exhaustively verified.
The owner must recheck these before authorizing publication; do not claim a universal
no-side-effects guarantee. No workflow/webhook/protection was changed here.

### Owner publication — ONLY AFTER explicit authorization

From the isolated collaboration worktree, verify branch, clean status and the exact
handoff commit SHA supplied with this document:

```sh
git branch --show-current
git status --short
git log -1 --oneline
git ls-remote --heads origin refs/heads/collab/frontend-ui-fixes
git push --set-upstream origin HEAD:refs/heads/collab/frontend-ui-fixes
```

If the remote branch already exists unexpectedly, STOP and compare; never overwrite
or force-push. The push command is prepared, NOT executed by this task.

### Groupmate checkout and personal UI branch

Safest for someone with an existing dirty clone: use a NEW clone, or add a worktree
without switching/resetting the old one:

```sh
git fetch origin
git worktree add -b ui/your-name-responsive ../PAIRfect-Paws-ui-your-name origin/collab/frontend-ui-fixes
cd ../PAIRfect-Paws-ui-your-name
```

Choose a unique branch/path; if either exists, inspect it instead of overwriting.
An already CLEAN clone may instead use:

```sh
git fetch origin
git switch --create ui/your-name-responsive origin/collab/frontend-ui-fixes
```

After edits and local checks, stage EXPLICIT selected UI paths (not git add .),
review staged diff for secrets/unrelated backend changes, commit and publish only
the personal UI branch:

```sh
git add -- resources/css/app.css
git diff --cached --check
git diff --cached
git commit -m "ui: improve responsive layout"
git push --set-upstream origin HEAD
gh pr create --base collab/frontend-ui-fixes --head ui/your-name-responsive --title "UI: responsive layout" --body "UI scope, tested viewports/states, checks run, and backend contracts preserved."
```

The staged CSS file is an EXAMPLE; stage the actual reviewed paths for your change.
Never add .env, uploads, DB files, node_modules/vendor, public/build, screenshots/
browser state or QA output. Do not upgrade dependencies or edit workflows incidentally.
No PR/push above was executed by this preparation task.

For updates, first commit your OWN reviewed UI changes on your UI branch, fetch and
merge origin/collab/frontend-ui-fixes INTO that branch if needed. Do not integrate
main/master just because they are default/hosting branches. Resolve contract conflicts
with the backend owner, retest and request review; do not force overwrite teammates.
Backend owner reviews semantic changes, runs appropriate integration regressions and
accepts PRs into the shared collaboration branch. Production promotion is a separate,
explicitly approved release after schema/storage/backup/operational verification.

## G. What was validated for this handoff

New isolated branch starts exactly at ab670a9; 21 checked security/backend/integration
commits through Phase 16.1 and both Phase 18A commits are ancestors. The application
tree is unchanged; only this document is added. All existing frontend handoffs,
50 migrations, frontend source and dependency manifests/locks are included.

Executed in this NEW worktree: Composer validate/install/platform checks; npm ci;
17 frontend tests; Vite build/manifest (2.80s); 31 deployment-tool tests; route
enumeration and Blade compilation/clear; bounded guest browser checks described
above; document/path/route and Git tree/preservation checks.

No full Laravel database suite was rerun here: the task prohibits database record
changes. Previous Phase 18A results (not new tests): MySQL 929 passed including isolated
seeder, SQLite 921 passed plus 8 MySQL-only skips. Those are local regression evidence,
NOT proof of Azure/background delivery. Existing npm advisories remain unchanged.

All 26 preexisting worktrees and 120 original changed-file hashes were verified unchanged.
Generated dependencies/builds/screenshots/logs/router remain ignored locally. Only
the handoff document is committed. No UI defect, backend code, real DB, live service,
Azure/GitHub setting, remote branch or deployment is changed.

## H. Copy-ready message to frontend groupmates

> Please use collab/frontend-ui-fixes AFTER the owner publishes it, not Azure master
> or the old dirty frontend-v2 tree. It contains the complete latest backend and UI
> source. Create your own ui/... branch/worktree, install locked dependencies and use
> a fresh disposable local database with fake mail. Read docs/FRONTEND_COLLABORATION_HANDOFF.md.
> You may redesign freely, preserving form names/values, routes/CSRF, permissions,
> schedule/clearance revisions, private evidence, server matching/classification,
> receipt/monitoring gates and Manila display contracts. Prioritize the 360px readiness
> clipping, 390px anchor overlap, duplicate releaseFormSection IDs and registration
> label accessibility. PRs must target collab/frontend-ui-fixes, NOT master/main.
> Run frontend tests/build/Blade and relevant form regressions; shared-branch PRs
> currently have no automatic workflow checks. Ask the backend owner about semantic
> conflicts. Do not push/deploy production or copy real credentials/data.

## Appendix A. Complete current frontend source inventory

Paths below are existing source, not newly created pages. This handoff changes NONE
of them. Their route/partial relationships are summarized above.

```text
public/js/admin/animal.js
public/js/admin/application.js
public/js/browse-pets.js
public/js/my-application.js
public/js/my-check-ins.js
public/js/recommendation-size-filters.js
resources/css/admin.css
resources/css/app.css
resources/js/admin.js
resources/js/app.js
resources/js/philippine-address.js
resources/js/post-adoption-camera.js
resources/views/account/settings.blade.php
resources/views/admin/adopter-profile/_history-modal-content.blade.php
resources/views/admin/adopter-profile/index.blade.php
resources/views/admin/animal/_add-modal.blade.php
resources/views/admin/animal/_view-modal.blade.php
resources/views/admin/animal/index.blade.php
resources/views/admin/application/_compatibility-modal.blade.php
resources/views/admin/application/_decision-modal.blade.php
resources/views/admin/application/_history-modal.blade.php
resources/views/admin/application/_identity-form.blade.php
resources/views/admin/application/_note-modal.blade.php
resources/views/admin/application/_review-modal.blade.php
resources/views/admin/application/_schedule-modal.blade.php
resources/views/admin/application/document-verification.blade.php
resources/views/admin/application/identity-verification.blade.php
resources/views/admin/application/index.blade.php
resources/views/admin/assessment/_question_row.blade.php
resources/views/admin/assessment/_summary-modal-content.blade.php
resources/views/admin/assessment/form.blade.php
resources/views/admin/assessment/record.blade.php
resources/views/admin/audit-logs/index.blade.php
resources/views/admin/compatibility/index.blade.php
resources/views/admin/dashboard/index.blade.php
resources/views/admin/handover/_adopter_confirmation.blade.php
resources/views/admin/handover/_delivery-clearance.blade.php
resources/views/admin/handover/_pipeline.blade.php
resources/views/admin/handover/_recorded_release.blade.php
resources/views/admin/handover/_release_form.blade.php
resources/views/admin/handover/_schedule.blade.php
resources/views/admin/handover/_transport-investigations.blade.php
resources/views/admin/handover/index.blade.php
resources/views/admin/handover/show.blade.php
resources/views/admin/layouts/app.blade.php
resources/views/admin/monitoring/_resolution-fields.blade.php
resources/views/admin/monitoring/_return-confirmation.blade.php
resources/views/admin/monitoring/flagged.blade.php
resources/views/admin/monitoring/index.blade.php
resources/views/admin/partials/sidebar.blade.php
resources/views/admin/post-adoption-demo/index.blade.php
resources/views/admin/time-travel/index.blade.php
resources/views/admin/volunteer/create.blade.php
resources/views/admin/volunteer/index.blade.php
resources/views/animal/_pet-grid.blade.php
resources/views/animal/_pet-modal-content.blade.php
resources/views/animal/index.blade.php
resources/views/application/apply.blade.php
resources/views/application/index.blade.php
resources/views/auth/access-denied.blade.php
resources/views/auth/forgot-password.blade.php
resources/views/auth/login.blade.php
resources/views/auth/reset-password.blade.php
resources/views/auth/verify-email.blade.php
resources/views/components/donation-channel-card.blade.php
resources/views/components/footer.blade.php
resources/views/components/mail/layout.blade.php
resources/views/components/nav-link.blade.php
resources/views/components/navbar.blade.php
resources/views/components/philippine-address-fields.blade.php
resources/views/components/public-layout.blade.php
resources/views/components/service-card.blade.php
resources/views/donate.blade.php
resources/views/handover/_live_tracking.blade.php
resources/views/handover/_schedule-actions.blade.php
resources/views/handover/_schedule-summary.blade.php
resources/views/handover/confirm.blade.php
resources/views/handover/notifications.blade.php
resources/views/handover/status.blade.php
resources/views/landing.blade.php
resources/views/layouts/app.blade.php
resources/views/legal/privacy-content.blade.php
resources/views/legal/privacy.blade.php
resources/views/legal/terms-content.blade.php
resources/views/legal/terms.blade.php
resources/views/mail/checkin-reminder.blade.php
resources/views/mail/password-reset-text.blade.php
resources/views/mail/password-reset.blade.php
resources/views/mail/status-update.blade.php
resources/views/mail/transactional-text.blade.php
resources/views/mail/transactional.blade.php
resources/views/mail/verification-text.blade.php
resources/views/mail/verification.blade.php
resources/views/mail/welfare-receipt.blade.php
resources/views/monitoring/_report-view-modal-content.blade.php
resources/views/monitoring/create.blade.php
resources/views/monitoring/flagged-notice.blade.php
resources/views/monitoring/my-checkins.blade.php
resources/views/monitoring/overdue-notice.blade.php
resources/views/monitoring/report-due.blade.php
resources/views/notifications/index.blade.php
resources/views/partials/bfi-questionnaire.blade.php
resources/views/partials/email-verification-banner.blade.php
resources/views/partials/favicons.blade.php
resources/views/partials/matching-household-fields.blade.php
resources/views/partials/notification-bell.blade.php
resources/views/partials/open-graph.blade.php
resources/views/partials/pet-calculated-size.blade.php
resources/views/partials/pet-measurement-inputs.blade.php
resources/views/partials/pet-size-guidance.blade.php
resources/views/partials/post-adoption-demo-notice.blade.php
resources/views/partials/profile-card.blade.php
resources/views/partials/sidebar.blade.php
resources/views/partials/time-travel-banner.blade.php
resources/views/pets/create.blade.php
resources/views/pets/edit.blade.php
resources/views/pets/index.blade.php
resources/views/pets/show.blade.php
resources/views/recommendation/_match-card.blade.php
resources/views/recommendation/_pagination.blade.php
resources/views/recommendation/intake.blade.php
resources/views/recommendation/results.blade.php
```

## Appendix B. Dirty frontend-v2 UI paths (NOT imported)

M = modified tracked file; ?? = untracked. Snapshot only; owner must recheck before porting.

```text
 M public/js/admin/animal.js
 M resources/css/app.css
 M resources/views/admin/animal/_add-modal.blade.php
 M resources/views/admin/animal/_view-modal.blade.php
 M resources/views/admin/animal/index.blade.php
 M resources/views/admin/application/identity-verification.blade.php
 M resources/views/admin/assessment/record.blade.php
 M resources/views/admin/compatibility/index.blade.php
 M resources/views/admin/handover/_adopter_confirmation.blade.php
 M resources/views/admin/handover/_schedule.blade.php
 M resources/views/admin/handover/index.blade.php
 M resources/views/admin/handover/show.blade.php
 M resources/views/animal/_pet-modal-content.blade.php
 M resources/views/application/index.blade.php
 M resources/views/auth/login.blade.php
 M resources/views/handover/_schedule-actions.blade.php
 M resources/views/handover/_schedule-summary.blade.php
 M resources/views/handover/confirm.blade.php
 M resources/views/handover/status.blade.php
 M resources/views/landing.blade.php
 M resources/views/partials/bfi-questionnaire.blade.php
 M resources/views/pets/create.blade.php
 M resources/views/pets/edit.blade.php
 M resources/views/pets/index.blade.php
 M resources/views/pets/show.blade.php
 M resources/views/recommendation/_match-card.blade.php
 M resources/views/recommendation/results.blade.php
?? resources/views/admin/handover/_delivery-clearance.blade.php
?? resources/views/admin/handover/_transport-investigations.blade.php
?? resources/views/components/pet-image.blade.php
?? resources/views/partials/pet-measurement-inputs.blade.php
?? resources/views/partials/pet-size-guidance.blade.php
?? resources/views/recommendation/_pagination.blade.php
```

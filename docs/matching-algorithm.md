# PAIRfect Paws matching: implementation and frontend handoff

Algorithm version: **2.0**. Configuration: `config/matching.php`.

## What remains, and what was replaced

`adopter_profiles` was **not removed**. It is the reusable, account-level record containing the adopter's BFI answers, four personality means, household constraints, and existing lifestyle details. `adoption_applications` remains the per-pet application/history record, including its submitted lifestyle/address snapshot and calculated compatibility breakdown. The admin Adopter Profiles page and adoption history remain intact.

The old lifestyle-to-pet distance formula and editable recommendation sliders were replaced. Physical activity, time availability, prior experience, and general household composition remain on the formal application for staff review; they are **not Euclidean-vector inputs** and no longer appear in the recommendation questionnaire. Housing and income affect constraints/penalties; explicit children/existing-pets answers are used instead of guessing from descriptive text.

This is deterministic, distance-based nearest-neighbor ranking. There is no trained predictive model, classification label, Python service, or model-fitting step. The matcher provides compatibility information and ranking for decision support. It does not automatically approve or reject adoptions.

## End-to-end flow

```text
Verified adopter -> saved household profile + 20 BFI responses
                                    |
                                    v
                         E, C, N, O means -> ideal six-feature vector
                                    |
Staff observations -> pet vector -> eligibility filters
                                    |
                           weighted Euclidean distance
                                    |
                           optional housing penalty
                                    |
                            compatibility percentage
                                    |
                          ranked top five available pets
                                    |
Application submission -> same server-side calculation -> stored explanation
                                    |
Staff compatibility ranking -> highest eligible primary -> interview
                                    |
                            Soft-Reserved pet
                                    |
                         next-ranked promotion if needed
                                    |
                         human adoption decision
```

1. Register an adopter account and verify its email. The one-time onboarding page offers the same 20-item assessment, with **Skip for now**. Skipping stores no invented BFI answers and allows pet browsing; it does not enable recommendations or formal applications. Existing adopter accounts are not retroactively forced through onboarding.
2. Open **Pet Recommendation** (`GET /recommendation`) later if onboarding was skipped, or select a pet's **Apply** action to return to the questionnaire for that pet. The one-time prompt is retained across sign-ins until skipped or completed.
3. Complete the reusable household/safety information and 20-item questionnaire. `POST /recommendation/start` validates and saves these to the current account, then redirects to results or the intended pet application.
4. `GET /recommendation/results` builds the profile from saved raw answers. Missing/invalid BFI data redirects to intake; no neutral/default score is invented.
5. Eligible assessed pets are scored with the shared `KnnRecommendationService::calculateMatch()` adapter and pure `Matching\KnnMatcher` core. Species and size are optional filters, not personality sliders.
6. Results are sorted before applying the top-five limit. View and Adopt buttons point to the real pet/application routes.
7. Clicking Apply with an incomplete profile redirects to the questionnaire for that pet. After completion, the adopter returns to the same application. A 30-minute session draft restores entered text/address fields if submission was attempted before profile completion; documents must be reattached.
8. The application page shows assessment completion and an update link; it does not repeat the BFI questions. Server-side submission rechecks the saved profile and pet, applies safety rules, and calculates the authoritative score in the existing application transaction. A new formal application is created only with a valid compatibility result.
9. Staff can inspect all rankings at `admin.compatibility.index`, or one pet at `admin.pets.ranked-applicants`, with eligibility, exclusions, distances, penalty, version, and six-feature explanation. The application list shows per-pet rank badges for eligible applications.

## Questionnaire scoring and feature semantics

The user-confirmed adapted Table 12 questionnaire has 20 integer responses on 1–5:

- E: EX1–EX6; C: C1–C6; N: N1–N4; O: O1–O4.
- Reverse keys: EX2, EX3, EX6, C1, C4, C5, N1, N4, O2, O4.
- Reverse score = `6 - response`. Each dimension is the mean of its scored items.
- No Agreeableness items or scores are invented.
- All item wording, dimension mapping, and reverse keys live in one config file.
- O2 and O4 both say “Has few artistic interests.” O4 is explicitly provisional and configurable, as instructed by the project team.

The ordered, continuous 1–5 vectors are:

| Feature | Ideal adopter target | Pet meaning at higher values |
| --- | --- | --- |
| energy | E | More energetic |
| trainability | C | More trainable |
| independence | (C + (6 − E)) / 2 | More independent |
| temperament | 6 − N | More fearful/reactive, **not calmer** |
| physical_size | (O + E) / 2 | Larger |
| medical_needs | (C + (6 − N)) / 2 | Greater care needs |

This implements the supplied capstone transformation exactly: `6 − N` is interpreted as tolerance for a reactive pet, not a direct answer to “I want a calm pet.” It is a project hypothesis, not a validated guarantee of compatibility. Staff must interpret this distinction correctly.

### Pet assessment construction

Staff submit species-specific raw integer answers on **0–4**, with unknown/not-observed answers left null. Out-of-range and fractional inputs are rejected. A subscale needs at least 80% of its configured items; otherwise its score is null, never zero.

- Dogs: mean energy; mean trainability with T5/T6/T7 reversed using `4 − raw`; independence = `4 − attachment mean`; temperament = mean of the stranger-fear and non-social-fear subscale means.
- Cats: mean energy and trainability; independence = `4 − mean(sociability, attention-seeking)`; temperament is the direct fear/reactivity mean.
- The `independence.reverse_attachment` flag controls the documented capstone interpretation issue for both species.
- Group-scoped keys avoid collisions between cat question categories. The capstone's duplicate sociability wording is preserved and labelled, not silently corrected.
- Use the latest raw assessment from **each distinct observer**, ordered by timestamp then record ID. Repeated submissions from one person do not count as three observers.
- Average each feature across non-null observer scores. Normalize with **raw mean + 1**, preserving floating-point precision. At least three distinct observers and non-null final features are required.
- Add/Edit Animal's existing **Health & Status** step owns the canonical pet-profile fields: veterinary-assessed physical size and medical-needs level, plus life stage, documented aggression history, and high vocalization. Authorized shelter staff may enter veterinary findings from the veterinary record; there is no separate veterinarian role. Size uses the existing Extra Small–Extra Large labels mapped to 1–5, and medical needs use 1–5. Both may remain unknown when the animal is first added.
- Life stage, documented aggression history, and high-vocalization status are supporting shelter profile/safety information entered or verified from records and observations. Health descriptions and ages are not parsed into guessed values.
- Pet Assessment owns only raw C-BARQ/Fe-BARQ observations and the derived energy, trainability, independence, and temperament scores. It never writes the five pet-profile fields.
- Staff may save behavioral observations while these shelter safety fields remain unknown. Matching stays incomplete until they have been verified; an empty field is never interpreted as No, Adult, or low needs.
- Legacy summary-only assessments lack recoverable raw responses. They stay in history but do not qualify as new questionnaire observations.

### Matching inputs by purpose

| Purpose | Inputs |
| --- | --- |
| Six-feature Euclidean vector | Energy, Trainability, Independence, Temperament, Physical Size, Medical Needs |
| Eligibility and safety | Life Stage, Documented Aggression History, Existing Pets, Children, Financial Readiness |
| Housing penalty | High Vocalization, Housing Type, Energy |

Life stage, aggression history, and high vocalization are never added as seventh distance features. The two veterinary-assessed fields are direct vector inputs; life stage and medical needs also support the financial-readiness rule. A pet with missing physical size, medical needs, life stage, aggression history, or vocalization remains ineligible for matching. Direct match requests return `PET_SIZE_NOT_ASSESSED`, `MEDICAL_NEEDS_NOT_ASSESSED`, or `SAFETY_INFORMATION_INCOMPLETE` as appropriate, with no fabricated score.

The nullable aggression-history schema defaults to `null` for new pets (`null` = unverified, `false` = recorded No, `true` = recorded Yes). High vocalization and life stage were already nullable. A separate nullable `aggression_history_verified_at` records an explicit staff confirmation; matching requires both a Yes/No value and this timestamp. The migration preserves every historical `true` and `false`. It recognizes historical Yes (which could not come from the former No default) and pets with raw staff assessments, while leaving other historical No values unverified and ineligible until staff confirms them. The source of every historical No cannot be reconstructed automatically. The Edit Animal form displays those unverified No values as “Unknown,” without rewriting their stored answer.

## Eligibility, penalty, and math

Exclude new recommendations/applications before calculating distance if the adopter profile is incomplete, the pet profile is incomplete, or the pet is archived/not `Available` (including `Soft-Reserved`, `On Hold`, and `Adopted`). Existing submitted applications are recalculated while the pet is `Soft-Reserved` so their active waitlist retains compatibility scores. Archived, Adopted, and On Hold pets still invalidate active matching. Further exclusions:

- Existing pets at home + pet temperament ≥ 4.
- Children in the household + documented aggression history.
- Financial readiness ≤ 2, unless the pet is an adult with medical needs ≤ 2.

Income categories map explicitly to financial readiness 1–5. `Apartment / Condo` maps to apartment, Townhouse to house without yard, and the two existing house options retain their yard semantics. Null safety data does not silently mean “safe.”

For eligible pairs:

```text
base distance = sqrt(sum(weight_i * (ideal_i - pet_i)^2))
housing penalty = 1 once, if apartment/condo AND (energy >= 4 OR high vocalization)
adjusted distance = base distance + housing penalty
maximum distance = sqrt(sum(weights) * 16)
compatibility = clamp(100 * (1 - adjusted distance / maximum distance), 0, 100)
```

All six weights default to 1, so the maximum is `sqrt(96)`. Weights must contain exactly the six feature keys and be finite and positive. The score is not a probability of adoption success. Per-feature bars show closeness only; they are not averaged to obtain the final percentage.

Recommendations: full-precision score descending, adjusted distance ascending, pet ID ascending; then top K (default 5). Eligible applicants for the same pet: full-precision score descending, submission timestamp ascending, application ID ascending. Legacy/unscored records remain visible for historical review but receive no active rank. Display/storage rounding of `knn_score` to two decimals does not determine ranking.

The active reservation waitlist uses the same eligibility and compatibility order. Staff may initially schedule only the highest-ranked eligible applicant. Interview scheduling sets the pet `Soft-Reserved`; it does not happen at application submission. Rejection, withdrawal, and no-show promote the next highest-ranked eligible waitlisted applicant. The pet row and application rows are locked during these transitions, allowing one primary candidate at a time. An administrator may override the primary with a required reason recorded in the audit trail. Staff still make final approval/rejection decisions.

## Persistence, refresh, and privacy

- Profiles: raw `bfi_responses`, E/C/N/O means, completion timestamp, explicit existing-pets/children flags, and financial readiness.
- Assessments: raw grouped `responses` with species and `scoring_version`; numeric summaries are widened to doubles.
- Pets: care fields, raw-derived summary values, distinct observer count, scoring version.
- Applications: `knn_score` percentage, `knn_distance` adjusted distance, `knn_penalty`, full `compatibility_result`, `knn_computed_at`, `knn_algorithm_version`, and source/config fingerprint.
- The migration preserves the old `knn_score` distance in `knn_distance`, takes an old percentage only if one actually existed in `compatibility_result.overall`, and labels it `legacy-lifestyle`. It does not pretend legacy records used version 2.0.
- Relevant profile/pet/assessment changes refresh nonterminal applications. Staff lists also refresh them; `matching:recompute` runs hourly when Laravel's scheduler is running. Unchanged fingerprints avoid needless writes. Bulk SQL bypasses model events, so call the refresh service/command after external imports.
- Existing pending/waitlisted scores remain usable during soft reservation; the same pet is hidden from new recommendations/applications. Archived/adopted/on-hold pets become unavailable for active matching. Terminal Approved/Rejected/Withdrawn/NoShow/Closed records keep their existing snapshot. On approval, remaining waitlist rows are closed before the pet becomes Adopted, preserving their final compatibility snapshots.
- Config changes are part of the fingerprint. Runtime matching always rescores raw questionnaires under current config. Stored profile means/pet summary display fields are snapshots; re-saving profiles/reassessing pets refreshes those summary fields too.
- Pure math performs no database calls. Candidate/observer/profile relations are eagerly loaded, and all candidates are scored before limiting results.
- Raw BFI answers are hidden from model serialization. Adopters can edit only their own profile; staff-only explanation routes use the existing role/verification middleware. Debug logs contain IDs, eligibility/reason, version, distance, penalty, and score—not questionnaire answers, addresses, income, or feature vectors.

## Operations and QA

After starting the configured database, from the project directory:

```powershell
php artisan migrate
php artisan matching:recompute
npm.cmd run build
php artisan test --compact
```

Use the project's PHP binary if `php` is not on PATH. Never run `migrate:fresh` against the real database.

`MatchingDemoSeeder` is opt-in and rejects environments other than local/testing. On a **scratch database only**, run `php artisan db:seed --class=MatchingDemoSeeder -v` to create 12 synthetic pets, three observers, and four adopter profiles; verbose output lists each adopter's top five. Re-running it updates only named matching-demo records. Demo emails use `example.test`; the local-only login password is `MatchingDemo123!`. It is not invoked by the default production seeder. Do not use the older random/summary-only seeders to validate this algorithm.

The test suite covers math, reverse scoring, precision, incomplete inputs, safety exclusions, distinct observers, top-K/ties, tampered scores, role access, refresh/invalidation, form rendering, and repeatable demo seeding. The scratch migration test explicitly asserts SQLite `:memory:` before running `migrate:fresh --seed`.

### Remaining limitations

- O4 duplicate wording and cat sociability duplicate wording require the capstone team's final confirmation.
- Independence reversal and the personality-to-care transformations are configurable project assumptions, not psychometric or animal-welfare certification.
- Three observations can still contain observer bias. Safety screening and human judgment remain essential.
- Old records need actual new questionnaire data; historical decimals/raw responses cannot be reconstructed.
- Local MySQL migration is pending while `127.0.0.1:3307` is unavailable. SQLite QA is not a substitute for a successful MySQL deployment check.
- Browser visual QA was not completed because the temporary server launch was blocked. Laravel HTTP/Blade tests and the production asset build were checked instead.
- Historical unscored applications remain stored for audit; only a valid profile and pet assessment can bring an active legacy application into compatibility ranking. New applications must pass both prerequisites before persistence.

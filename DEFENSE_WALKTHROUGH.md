# PAIRfect Paws defense walkthrough

This is a **local-only fictional demo**. The `DefenseDemoSeeder` is opt-in, uses fixed names and answers, and does not run from `DatabaseSeeder`. All `.test` addresses are reserved fictional addresses; they cannot receive email. Historical supporting documents are deliberately labeled fictional and recorded as `LegacyReview`, **not** as Google OCR-verified. Historical check-in records are snapshots, not live camera submissions.

## Prepare and reset safely

1. Use a separate local database or make a restorable backup of your current development database first. Do not run this against production or a teammate's shared database.
2. Apply migrations, then seed explicitly:

   ```powershell
   C:\Users\jpdol\Desktop\Capstone-PAIRfect_Paws\tools\php85\php.exe artisan migrate
   C:\Users\jpdol\Desktop\Capstone-PAIRfect_Paws\tools\php85\php.exe artisan db:seed --class=DefenseDemoSeeder
   ```

3. Start the app using your normal local server. For email demonstrations, configure a controlled real mailbox and run the queue worker separately; the fictional `.test` demo accounts will not receive mail.
   Before presenting, set `APP_URL` in `.env` to the actual reachable local or ngrok URL, run `php artisan config:clear`, and restart the queue worker so generated links use the current host. The present local `.env` still points at an older ngrok URL.
4. For another rehearsal, restore the **pre-demo database backup** (or recreate a dedicated disposable demo database), then run the seeder again. The seeder refuses a second run while its demonstration branch exists; it never deletes records. There is no web-accessible reset route. The generated fictional PDF is at `storage/app/private/defense-demo/fictional-supporting-document.pdf` or the configured local disk root; it can be overwritten on a fresh run. Before any restore, make a new backup of work done since the prior snapshot.

For this inspected local MySQL database only, `php scripts/audit-defense-data.php --summary` performs a read-only consistency audit. `php scripts/cleanup-defense-data.php` conservatively removes the one unused default sample account and repairs three unsupported legacy pet statuses; `php scripts/reconcile-defense-timeline.php` repairs the older version of this fictional defense dataset if present. Both repair scripts are local-only, validate their exact targets, and are safe to rerun. A private pre-cleanup SQL backup is stored under `storage/app/private/defense-backups/` (gitignored). These scripts are not a substitute for checking the audit before acting on a different database.

Password for every account below: `DefenseDemo2026!` (local demo only). All are pre-verified so the walkthrough does not depend on fictional email delivery.

| Role | Account | Purpose |
| --- | --- | --- |
| Administrator | `evelyn.cruz@defense.pairfectpaws.test` | Animal management, decision, dashboard, report |
| Volunteer | `rafael.dizon@defense.pairfectpaws.test` | Interviewer and third pet-assessment observer |
| Volunteer | `nina.bautista@defense.pairfectpaws.test` | Assessment observer and interview history |
| Adopter | `angela.reyes@defense.pairfectpaws.test` | Luna approved; Coco under review |
| Adopter | `miguel.santos@defense.pairfectpaws.test` | Bruno ranked applicant |
| Adopter | `patricia.mendoza@defense.pairfectpaws.test` | Bruno applicant; Pepper scheduled |
| Adopter | `carlo.villanueva@defense.pairfectpaws.test` | Bruno applicant; prior Nala monitoring concern |
| Adopter | `bea.navarro@defense.pairfectpaws.test` | Children + Tala aggression safety exclusion |
| Adopter | `dina.flores@defense.pairfectpaws.test` | Milo pending; live adopter flow |

## Recommended 15–20 minute presentation

1. **Maintenance (admin):** Open Animal Records. Edit a harmless field on Bruno (for example, his descriptive text) and save. Open Assessment Records: Bruno has three separate staff observations and a complete scored assessment. Mochi has only two (Evelyn and Rafael); record Mochi's third observation live **as Nina** to demonstrate scoring completion. Avoid changing Bruno's matching inputs once the ranking demonstration begins.
2. **Profile and processing (adopter):** Log in as Miguel. Open the matching/adopter profile and show all 20 BFI responses plus household information. Select Recommend. Explain: BFI answers and three-observer behavior data enter the existing matching service; its eligibility rules, distance, and compatibility score determine the recommended pets. Pepper is absent because it is Soft-Reserved; Mochi is absent until the third assessment is completed.
3. **Three-applicant transaction (admin):** Open Compatibility or Applications and select Bruno. Miguel, Patricia, and Carlo already have formal applications with complete profiles and **server-calculated** scores. Show that the existing ranking service orders eligible applicants by score, then submission time. Schedule the *highest-ranked eligible* applicant for an interview, assigning Rafael. Bruno becomes Soft-Reserved, the two others become Waitlisted, and no new applications can be sent for Bruno. This is a real state transition, not a preloaded screenshot.
4. **Safety rule:** Bea's rejected Tala application retains its computed `eligible=false` result. Bea has children; Tala has verified aggression history. Log in as Bea and demonstrate that Tala is excluded from her recommendations. The rejection reason is a fictional historical staff decision.
5. **Success and monitoring:** Show Angela–Luna's high calculated match (92.78% with the current scoring configuration), Approved application, Luna Adopted, handover Received, and the generated 3-day, 3-week, and 3-month monitoring schedule. Angela has rescue/special-needs experience and Luna has a stable managed condition. All three milestones have compliant historical snapshots. Show Carlo–Nala: one flagged welfare report and a missed 3-month check-in; the staff-only adopter-history warning reflects those records. Do not describe these preloaded records as camera-verified video.
6. **Status variety:** Pepper is Interview Scheduled and Soft-Reserved; Coco is Under Review; Milo and Bruno initially have Pending applications; Luna/Nala are Approved; Tala's safety case is Rejected. These statuses make dashboard counts and report rows meaningful.
7. **Report:** In Admin → Applications, use the new **Export Applications CSV** form. Download all rows, then filter `Approved` to show Luna and Nala only. The report reads current database data and includes applicant, pet, calculated compatibility, application date, status, interview date, and approval date. Date boundaries use Philippine time. An adopter cannot access this staff report.

For a **new** application live, use an eligible *Available* pet without an active application from that adopter (for example, a newly completed Mochi assessment and Dina's account). A real supporting document is still mandatory. OCR may require configured Google credentials and may flag fictional documents; do not promise automatic verification. Authorized staff can use the existing document-review action with a recorded reason after inspecting the document. A live 3-second video check-in also requires a real browser camera and valid challenge token; the seeder does not bypass this security control.

## What the seeded data proves

- The seeder supplies fixed fictional inputs, **not fixed KNN outputs**. It invokes the production BFI/behavior scoring and application matching services. Bruno's three scores are distinct and the real ranking order is tested.
- A scheduled primary applicant causes the real queue service to Soft-Reserve Bruno and waitlist the other two in the automated test.
- Approved adoption, handover, three monitoring milestones, flagged monitoring, and the adopter-history warning have consistent linked records.
- The CSV is a live query, not a static file. Existing dashboard calculations are unchanged.

## Quick validation

```powershell
C:\Users\jpdol\Desktop\Capstone-PAIRfect_Paws\tools\php85\php.exe artisan test --filter=DefenseDemoWalkthroughTest
```

The automated test performs the critical seeded-data, RBAC, ranking, reservation, dashboard, page, and export checks. Full browser camera capture and external OCR/email delivery remain environment-dependent and should be checked once on the actual defense laptop with the intended hardware and credentials.

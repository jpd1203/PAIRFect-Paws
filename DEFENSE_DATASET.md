# Defense dataset and consistency audit

Audit date: 2026-10-01. Scope: inspected **local** `pairfect_paws` MySQL database. The read-only auditor is `scripts/audit-defense-data.php`; the opt-in canonical fictional dataset is `database/seeders/DefenseDemoSeeder.php`. Do not use these results to justify cleanup on another database without a new audit.

| Record | Before | After |
| --- | ---: | ---: |
| Pets | 31 | 31 |
| Users (adopters) | 27 (18) | 26 (17) |
| Adopter profiles | 12 | 12 |
| Applications | 44 | 44 |
| Current matching snapshots | 25 | 25 |
| Interviews with dates | 18 | 18 |
| Behavioral assessment rows | 89 | 89 |
| Handovers | 10 | 10 |
| Post-adoption milestones (submitted) | 30 (15) | 30 (16) |
| Capture challenges | 11 | 11 |
| Handover notifications | 33 | 33 |
| Audit logs | 244 | 249 |

## Exact changes made

- Removed only the unverified, unused default `Jane Doe` / `adopter@example.com` account (#3). It had no profile, application, assessment, handover, notification, session, reset token, or audit activity. No other user was removed or merged.
- Pets #38 Lily G, #39 Lucy Z, and #40 Lily D had no applications or handovers, yet were Soft-Reserved, Soft-Reserved, and Adopted. All three now show **Assessing** and zero verified assessment observers. Their photos, legacy score columns, and three old same-observer assessment rows each were preserved rather than fabricated or deleted. A factual pending-assessment description was added where blank.
- Normalized only the fictional defense branch's dates. Its accounts and profiles now precede its applications; pet intake/assessment dates precede applications and adoptions. Angela–Luna has compliant 3-day, 3-week, and 3-month monitoring snapshots. Carlo–Nala retains a flagged welfare report and missed 3-month milestone. No formula-derived score was edited manually.
- Each change was recorded in the audit log. Both local repair scripts are guarded, validate exact targets, run transactionally, and are idempotent on the cleaned database.

## Fictional presentation records

Demo staff: Evelyn Cruz (administrator), Rafael Dizon and Nina Bautista (volunteers). Demo adopters: Angela Reyes, Miguel Santos, Patricia Mendoza, Carlo Villanueva, Bea Navarro, and Dina Flores. All demo account addresses and the local-only password are in [DEFENSE_WALKTHROUGH.md](DEFENSE_WALKTHROUGH.md).

| Pet | Purpose |
| --- | --- |
| Luna | Angela's high-match approved adoption, received handover, all three completed normal monitoring milestones |
| Nala | Carlo's approved adoption with a flagged welfare report and missed milestone; staff history warning |
| Bruno | Three eligible applicants (Miguel, Patricia, Carlo) with distinct server-calculated scores; ranking and live Soft-Reserve demonstration |
| Tala | Documented aggression history; excluded for Bea's household with children |
| Milo | Available pet with Dina's pending application |
| Mochi | Two distinct observers; complete the third assessment live as Nina |
| Pepper | Upcoming scheduled interview and Soft-Reserved state |
| Coco | Primary candidate Under Review |

The Applications CSV report includes Pending, Under Review, Interview Scheduled, Approved, and Rejected examples from the real database query. It is not a static report.

## Intentionally untouched findings

- No foreign-key orphans, duplicate email addresses, duplicate monitoring milestones, unsupported adopted statuses, or unsupported Soft-Reserved statuses remain in the audited database. Four repeated user–pet application pairs are distinct historical attempts with different dates/statuses, not records safe to merge.
- Twenty-one older pets have three legacy assessment rows from the **same** assessor, generally without raw questionnaire responses. They are not three independent observers under the current matching method. Most are linked to existing accounts, applications, or media, so those rows were retained. The three unlinked pets above were marked Assessing instead of being presented as match-ready.
- Fifteen older nonterminal applications have a current 2.0 exclusion snapshot but no compatibility percentage because the adopter BFI or pet raw assessment is incomplete. Their scores cannot be truthfully filled in without new real questionnaire data. An older primary candidate for pet #43 is among them and warrants staff review before a live transaction; it was not auto-rejected.
- On the currently configured disks, one older pet photo, ten older application documents, and four older check-in photos are not found. These references may be from a previous storage layout or contain sensitive user history. None was silently cleared or deleted. Defense-branch document references are present; external OCR and live video still require their actual services/hardware.
- The QA-labeled adopter account has an application, audit events, and a historical document reference tied to a personal email alias. It was **not** deleted without a separate decision. Personal accounts and historical approved adoptions were preserved.

## Rehearsal and recovery

The private pre-cleanup MySQL snapshot is under `storage/app/private/defense-backups/` and is gitignored. Protect it as sensitive data. Restore a backup only to an isolated local database, then run the explicit defense seeder; never run `migrate:fresh` or a public reset route on the shared application. For the inspected database, run `php scripts/audit-defense-data.php --summary` before and after the guarded local repair scripts. See the walkthrough guide for the complete order.

# Phase 15B: historical classification safety

Phase 15C adds a narrowly allowlisted **fictional prototype fixture** normalization/rehearsal tool, not a measurement certification workflow or a general legacy-save bypass. Ordinary editing protections below remain unchanged. See [the prototype normalization runbook](PROTOTYPE_PET_NORMALIZATION.md) for identities, dry-run/hash guards, disposable-only apply and separately authorized deployment requirements.

## Enforced backend contract

Persisted `legacy_unclassified` is a review boundary, not a calculation result. `PetSizeClassifier::requiresHistoricalReview()` reads the model's original persisted status rather than editable attributes. `changes()` preserves the exact original `physical_size`, `legacy_physical_size`, status and rule version, including nulls. Name, description, photo, availability, species, age and weight edits are not review attestations; even entering both measurements cannot promote history.

The Pet saving event invokes this shared guard for every legacy save, including metadata-only changes. The version-checked staff HTTP query update already merges the shared classification result last. Existing intentional direct classification writes (including the opt-in synthetic matching seeder) use the same operation. `isCurrent()`, exposed by `Pet::hasClassifiedPhysicalSize()`, also rejects a legacy model with unsaved forged metadata.

New pets and nonlegacy current-policy pets retain automatic calculation. Integer-gram thresholds, adult ages, rule version, KNN features/formula, exclusions, pagination and terminal application history are unchanged. Historical sizes never supply a numeric KNN fallback. All Sizes with ineligible pets retains the existing `PET_SIZE_NOT_ASSESSED` reason; size-specific results exclude these records. Behavioral-summary quiet saves write behavioral fields only and do not classify history. The impact command remains a read-only proposal, not permission to classify.

No migration, new review endpoint, command, attestation, bypass flag or frontend change is introduced. Saving the existing form is never an explicit measurement review.

## Future review workflow: approval required, not implemented

A separately authorized phase must establish who may review, how known age and freshly measured weight are evidenced, and how uncertain age is handled without inference. A safe backend contract should require explicit review intent distinct from ordinary editing; authorize the active verified reviewer; validate species, canonical age and measured weight; check/lock the current revision; preserve both historical snapshots; and atomically record the actor, evidence, reviewed values, time, prior/new provenance, audit and resulting classification. Recompute nonterminal matching results only through established services, never rewrite terminal outcomes. Missing/uncertain information must not fabricate adult sizes. Shelter policy must decide evidence, corrections, revocation and any additional schema/UI before implementation.

## Limits and operations

This is application-path protection, not a database privilege boundary: raw SQL, event-disabled writes, manual original-attribute manipulation or an operator with database access can bypass model safeguards. Existing classified synthetic test fixtures intentionally use quiet writes. No new such production write path is introduced. Future import/review code must not bypass the shared guard accidentally; review changes require their own explicit audited design.

Already-promoted records from before this fix cannot be reliably distinguished from legitimate current records using existing metadata. This change does not retroactively relabel them or inspect real data. A separately approved audit/reconciliation is needed if such records exist. Existing legacy snapshot nulls remain null; no historical evidence is invented. Historical animals remain unavailable for trusted matching until an approved review workflow exists, even if staff edits apparently complete age/weight values. This intentional restriction may reduce eligible recommendations.

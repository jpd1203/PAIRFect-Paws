# Phase 11B: species-first recommendation size filtering

Base: Phase 11A fd772669bb7ed3b6e39ab37b3adb1db6fb44f63a. origin/main was fetched and still pointed to c1011b0; there were no newer groupmate changes to integrate.

## Filter contract

Keep species values empty (All Species), Dog, Cat. Size is empty (All Sizes) or one of the existing five category names. Explanatory range text is never a submitted value or a new KNN feature.

Examples: `/recommendation/results?species=Dog&size=Medium`, or `/recommendation/results?species=Cat&include_ineligible=1`. Invalid species, unknown category, explanatory labels as values, arrays, wrong types and a nonempty size without a specific species are rejected server-side. The existing prohibited score/vector fields remain prohibited. Controller and direct service calls share the same validation.

No new category thresholds are maintained by the browser. PetSizeClassifier exposes bands and labels derived from its existing integer-gram policy; the same bands supply SQL bounds and minimum ages. The classifier's approved cutoffs, adult ages and classification outcomes are unchanged.

## Query and trust

Size-specific queries require exact species/category/status/current rule, a known adult age, positive weight and the requested weight band. Exact string guards prevent MySQL case-insensitive collations from accepting metadata or category spelling that PHP rejects. All range values are parameter-bound and derived from classifier policy.

Every resulting row must additionally pass Pet::hasClassifiedPhysicalSize() and exact species/category checks before scoring, counting or pagination. SQL is a narrowing step, not permission to trust malformed legacy data. Counts and page boundaries therefore cannot include false-positive historical, stale or inconsistent records. No pre-filter SQL count is used to construct the paginator.

All Sizes deliberately does not add this size-specific restriction. Existing eligible-only default behavior is retained. With include_ineligible=1, juvenile, unknown-age, historical and other ineligible pets retain their existing reasons and no adult size is invented. The flag is preserved on form submission and pagination. Public catalog visibility remains unchanged. A trusted size does not bypass behavioral, safety, availability or other eligibility checks.

KNN's formula, six features, numeric mapping, weights, safety filters, scores, sorting, explanations and pagination size are unchanged. There are no schema changes or data writes in this feature.

## UI and navigation

Existing dropdown wrappers/classes, icons, button styles, recommendation cards and bottom pagination are preserved. Native selects gain explicit accessible labels and a polite hidden help region. With All Species selected, size contains only All Sizes and is disabled. Dog/Cat selections show the corresponding server-generated kilogram labels.

The native change event resets size to All Sizes whenever species changes, even if a category name is valid for both species. No custom keyboard handler replaces native select interaction. Initial load, pageshow (including back/forward cache), and popstate rebuild controls from canonical GET parameters rather than stale browser-restored unsent edits. Valid bookmarked category values are preserved; invalid client-restored values are cleared, not trusted.

Without JavaScript, server-rendered options and server validation still apply; apply a species selection first to obtain its size options. Automatic reset on an unsent species change requires JavaScript. No form value can bypass the backend trust checks.

Pet intake, questionnaires, handover, delivery, unrelated components and deployment are outside this change. Historical classification requires a separately approved explicit staff measurement-review workflow, which has not been implemented. Ordinary edits cannot provide that attestation (Phase 15B); this filter does not reclassify or backfill records.

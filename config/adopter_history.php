<?php

return [
    // Provisional screening thresholds; these are not KNN inputs or automatic
    // eligibility rules. Shelter policy owners should confirm these values.
    'needs_review_flagged_reports' => 1,
    'administrative_review_flagged_reports' => 2,
    'needs_review_missed_checkins' => 1,
    'administrative_review_missed_checkins' => 2,
    'needs_review_late_checkins' => 2,
    'needs_review_unresolved_flags' => 1,
];

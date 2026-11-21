# Frontend handoff: research testing

- `resources/views/layouts/app.blade.php`: authenticated research-mode banner and adopter shortcut. Off by default.
- `resources/views/components/navbar.blade.php`: public navigation identifies the fictional catalog while the study is active.
- `resources/views/recommendation/results.blade.php`: explains automatic simulated approval instead of promising a real staff review in research mode.
- `resources/views/partials/sidebar.blade.php`: Handover Status points to the research dashboard in research mode and bypasses only the normal **client-side** no-handover toast. Server-side enrollment and ownership remain mandatory.
- `resources/views/research/apply.blade.php`: fictional pet, real server KNN result, explicit simulated-application POST. No personal documents or images requested.
- `resources/views/research/scenarios.blade.php`: per-participant placements, simulated approval/release, manual receipt confirmation and ordered accelerated reports. Controls use existing button, border and spacing classes and native labeled selects; no CSS, JS, or unrelated layout changes.

Keep CSRF, role/verification/enrollment gates, route bindings, eligibility and ownership server-side. Keep research labels even if restyling. Preserve the POSTs and their input names: receipt has no client-controlled owner/status; check-ins submit milestone, appetite, adjustment. All three milestones are immediately testable in sequence after receipt, not scheduled real appointments. No real incident or staff notification is generated. Groupmates can adjust spacing, headings and responsive layout; verify long names and keyboard navigation on phones before the study. Feature tests render both pages; no device screenshots are claimed.

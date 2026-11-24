# Frontend handoff — Admin sidebar

`resources/views/admin/partials/sidebar.blade.php`: removed the **Post-Adoption Demo** link from **System**. The shared sidebar no longer lists it, even when the feature is enabled. This applies to both desktop and mobile navigation. No styles or layout changed.

Monitoring, Flagged Cases, and Test Time Travel keep their existing behavior. The demo URL, access rules, and contextual demo controls in Monitoring and Flagged Cases are unchanged. Keep the link out of the global sidebar if the menu is restyled.

The Laravel regression test checks the rendered admin dashboard with the demo enabled and confirms Monitoring and Flagged Cases remain linked. No browser screenshots were taken.

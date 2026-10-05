# Post-Adoption Web Presentation Demo

The admin-only **Post-Adoption Demo** page is intended for a presentation on a web-hosted installation. It is disabled by default and does not require ngrok, a changed server clock, or a database migration.

## Enable on the hosted pilot

1. Deploy the tested code through the normal deployment branch. The current Azure workflow deploys pushes to `master`, not `main`.
2. Set the Azure App Service setting `POST_ADOPTION_WEB_DEMO_ENABLED=true`. Keep `APP_ENV=production` and `APP_DEBUG=false`; do not enable `POST_ADOPTION_TIME_TRAVEL_ENABLED`.
3. Use a shared/persistent cache store (the repository's `CACHE_STORE=database` setting is suitable). Run `php artisan optimize:clear` if the app has cached its previous configuration.
4. Sign in as a verified administrator and open `/admin/post-adoption-demo`.

Only approved applications with a handover confirmed **received** appear in the selector. Select the adopter/pet placement, then choose **Show due**, **Show overdue**, **Next milestone**, **All overdue**, or an exact presentation date. The temporary state affects only that placement and expires six hours after first activation. The adopter must use their normal verified account to see the matching due/overdue/flagged views. A camera report submitted during the demo is a **real report**, not a simulated one.

## Reminder and flag scenario

1. Choose **Show due** for an incomplete milestone.
2. Confirm the selected verified adopter email on the admin page and press **Send demo reminder**. This sends a real, clearly labeled presentation email synchronously. The demo counter advances only if the mail relay accepts the message.
3. Choose the next presentation day (for example, **Show overdue** or enter the next date). The first milestone appears overdue to this adopter, including the overdue sidebar badge and in-app demo notice.
4. Confirm and send the second reminder. It creates a **demo-only** welfare flag in staff monitoring, the flagged queue, the adopter flagged notice, and admin/adopter in-app notifications. Use **Resolve demo flag** to demonstrate resolution.
5. Use **Reset this adoption's demo** when finished. It removes the temporary date/counters/flag and marks that session's in-app demo notices read. Previously delivered demo emails and audit entries remain as evidence of the presentation.

Official `scheduled_date`, `reminders_sent`, `last_reminder_sent_at`, `is_flagged`, and resolution fields are never rewritten by the demo controls. The normal daily scheduler keeps using real Asia/Manila time and can still send a genuine reminder if a selected adoption is actually due; avoid selecting a real adopter without coordinating the additional demo email. Demo emails go to the **selected adopter's verified email**, not to a fixed inbox.

The demo page is restricted to verified administrators, requires an explicit email-send confirmation, is throttled, and audits changes. Disabling `POST_ADOPTION_WEB_DEMO_ENABLED` immediately hides and deactivates all presentation overrides; clear cached configuration after changing the setting.

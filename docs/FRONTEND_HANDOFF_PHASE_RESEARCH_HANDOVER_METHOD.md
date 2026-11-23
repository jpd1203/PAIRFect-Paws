# Frontend handoff — Research handover method selection

`resources/views/handover/_schedule-actions.blade.php` now uses the same server-calculated eligibility as the research method endpoint. Pickup and Delivery still use the existing radio controls, layout, and save button. A saved preference is editable only while the handover is unscheduled and does not have a window or a recorded receipt.

Review the chooser and the schedule confirmation panel on Handover Status. When a browser submits an outdated or ineligible method change, the server returns to that handover and displays the reason in its existing alert. No styling or responsive rules changed. Automated checks exercise the rendered form and alert; no new browser screenshots were taken.

Keep the `method` values (`pickup` and `delivery`), CSRF input and `schedule_version` hidden field. In Research Mode, render the picker using `can_select_schedule_method`, not schedule status alone. Keep the existing normal-mode permission predicate. Editing the styling must not let an old form overwrite a proposed or confirmed schedule.

# In-app notifications

The existing `handover_notifications` table is the single in-app notification feed. Its `handover_id` is nullable for non-handover events; historical handover rows remain in place. `HandoverNotification` retains the legacy class name. Email delivery and audit logs are separate systems.

| Event | Adopter | Volunteer | Administrator |
| --- | --- | --- | --- |
| Application submitted/document updated | Own application | No | Intake/review alert |
| Interview scheduled/rescheduled | Own application | Assigned interviewer | Scheduling alert |
| Application reviewed/approved/rejected/waitlisted/promoted | Own application | No | Existing queue view (separate scheduling alert) |
| Handover prepared/released/received/reopened | Own handover | No | Existing protected handover view |
| Check-in due/overdue/report received | Own placement | No | Flagged cases only |
| Welfare report flagged or missed check-in | No sensitive detail | No | Review alert |
| Email verified | Own account | Own account | Own account |

The bell reads only five recent records and an unread count. The full page paginates at 15. All notification routes require authentication, record actions verify `user_id`, and following a notice still passes through the destination's normal authorization. No OCR text, government ID data, welfare answers, passwords, or tokens are stored in the notice body.

The daily `checkins:send-reminders` command creates idempotent due/overdue in-app notices, independent of email success. Run Laravel's scheduler in production with a cron entry every minute:

```text
* * * * * php /path/to/artisan schedule:run
```

Existing email reminders still require the database queue worker:

```text
php artisan queue:work database --queue=emails,default --tries=3
```

Use a process supervisor for the worker. On the local Windows setup, `scripts/start-scheduler.ps1` and `scripts/start-queue-worker.ps1` start background processes, but they must be restarted after a machine reboot.

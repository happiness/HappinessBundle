# HappinessBundle

Kimai plugin that customizes various aspects of Kimai (weekday-grouped timesheet view, Happiness report, ...).

## Monthly retainer hour

Books a fixed number of hours (default 1) on every project that has the checkbox
**"Planeringsarbete för månaden (N h)"**, where N is the configured number of hours, enabled, once per month.

### Setup

1. Go to **System → Settings → Monthly retainer hour** and configure:

   | Setting | Default | Description |
   |---|---|---|
   | Enabled | yes | Switch the automatic booking on or off |
   | Day of month | 1 | Day to book on (1-28) |
   | Hours | 1 | Hours to book |
   | Description | Projektledning: Planeringsarbete för månaden, ... | Text of the timesheet |
   | Username | happiness | Username (not display name) of the user that makes the booking |
   | Activity ID | 10 | ID of the activity to book on (must exist and be usable on the project) |

2. Enable the checkbox on each project that should get the retainer hour.

The activity must be visible and either belong to the project or be a global activity
(and global activities must be allowed on the project); other projects are skipped.

### Cron job

Run the command **daily**. It only books when today is the configured day of the month,
so the day can be changed in the settings without touching the crontab.

```cron
0 6 * * * cd /path/to/kimai && php bin/console happiness:retainer:book --env=prod --no-interaction >> var/log/retainer.log 2>&1
```

With DDEV: `ddev exec bin/console happiness:retainer:book`.

### Command

```bash
bin/console happiness:retainer:book [--month=YYYY-MM] [--dry-run] [--force]
```

- `--month` – month to book, defaults to the current month (no automatic backfill).
- `--dry-run` – only show what would be booked.
- `--force` – ignore the enabled flag and the configured day (e.g. for manual runs).

Running the command more than once is safe: a project that already has the booking
(same user, project, activity, date and description) is skipped.

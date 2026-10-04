# MentoClock Laravel 12 backend

A separate attendance product derived from a review of the uploaded Tully Smiths codebase. Retains Laravel's bootstrap/configuration foundation and replaces construction-specific controllers, schema and routes. No uploaded customer files, database content, passwords, Firebase credentials or signing keys are bundled.

## Install in a NEW database
Requires PHP 8.2+, Composer and MySQL 8/InnoDB. Do not point this project at the Tully Smiths database. Existing customer data is not automatically migrated.

```bash
composer install
cp .env.example .env
php artisan key:generate
# Configure the new MySQL database credentials in .env
php artisan migrate
php artisan mento:business
php artisan serve
```

Repeat `php artisan mento:business` for each new subscribing business. Each business administrator accesses only their own console. This release does not include a platform-wide billing or super-admin screen.

Open the web console, create real branches with correct coordinates/geofence radii, add employees and assign branches. Mobile API base URL is `https://YOUR_HOST/api/v1/`. The app and API contracts match.

## Included
- Per-business branch and employee management, branch reassignment and employee activation/deactivation.
- Employee token login/logout; hashed bearer tokens expire after 30 days.
- Assigned branch authorization; fresh foreground GPS/geofence validation on clock-in/out.
- Server timestamps, employee-row locking for serialized punches, repeat clock-out preserves the original completion time.
- Business attendance dashboard, CSV export and corrections with before/after/reason audit records.
- Composite foreign keys prevent cross-business employee/branch relationships.

For deployment, use HTTPS, set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` and `SESSION_SECURE_COOKIE=true`. Serve only `/public`, never the project root. Configure backups, least-privilege database access, retention, monitoring and an employee privacy notice before rollout. GPS is not proof against spoofing. The clock-out policy requires returning to the original branch geofence; change the policy if your client needs offsite clock-outs.

## Tests and limitations
```bash
php artisan test
```
Tests cover tenant boundaries, duplicate clock-in, repeated clock-out, geofence rejection and admin role restriction. These tests have NOT run in this environment: PHP/Composer are unavailable. SQLite tests do not prove MySQL concurrency behavior; test simultaneous punch requests against MySQL before pilot. No syntax/runtime validation or deployment is claimed.

The UI lists the latest 200 records; CSV export includes all records. API history is currently unpaginated. Business timezone controls console display; mobile times use device timezone, and mobile daily totals group records by start date. Overnight/DST reporting needs server reporting rules before payroll use. Subscription billing, password recovery, selfie evidence, mobile manager screens and app-store release assets are not implemented. Setup regenerates native mobile projects; store-ready icons and signing still require configuration.

## Data structure
businesses → branches/users → employee_branches → attendance_records → audit_logs.
Every employee belongs to one business and may be assigned to multiple branches. One business is onboarded per command invocation. Email is globally unique. Active account and business status are checked on every token-authenticated API request. Jobs, expenses, checklists, construction clients, announcements and related permissions have been removed.

# MentoClock V38 — Release candidate and Sakib's deployment guide

**SOURCE**: Built from `MentoClock_Client_Manager_Backend_V37_Enhanced_Staging.zip` (not a live production export). 22 November 2026.
**STATUS**: STAGING CANDIDATE. Do not deploy to the live MentoClock host until integration, access-control and database migration tests pass on a staging clone.
**SAFETY**: V38 does not include a migration changing `attendance_records`, clock-in/out APIs, users' IDs or existing assignments. There are no changes to Flutter and no mobile rebuild is required for these web-only V38 features.

## What V38 implements
1. **Client Assistance Centre** under `/platform/assist`: scoped actions to create a workplace, create an employee, update workplace assignments. Super Admin does **not** assume a client user's identity.
2. **Audit trail** in `client_assistance_events`, plus linked entries in existing `platform_audit_logs`; saves actor ID, client, action, reason, and before/after assignment IDs, not passwords.
3. **Dedicated Change Workplace** page `/employees/{id}/workplaces`. Both client and assisted branch changes block reassignment while the employee has an active open shift, avoiding forced sign-out during work.
4. **Client employee search** `/people`: name, login email, employee code, workplace and active/inactive status; paginated results of 25. Client/business scoped.
5. **Guided trial onboarding** at `/assistance`: first workplace, employee, assignment and trial clock-in checklist with links to existing forms (not a full automatic provisioning wizard).
6. **Restricted Support Operator** role via `/platform/assist` for existing platform-admin identities. Platform middleware denies operators other platform routes including clients, billing, admins and monitoring. Only full Super Admin can change operator roles. Role changes invalidate previous platform sessions.
7. **Client-authorised assistance**: client admin requests with a reason; platform accepts for an assigned operator for up to 24 hours; client may revoke at any time. The server validates the assignment and expiry for every write. No client passwords used.
8. **Support notifications**: stored and visible in client's `/assistance` page for session activation and each successful assisted change. **In-portal only**: no emails are sent (existing email delivery issue should be fixed separately).

## Critical prerequisite from V37
The previously reported V37 HTTP 500 missing `attendance_attempt_events` indicates migrations may be pending on production. Do **not** treat a successful V38 deployment as proof V37 is healthy. Before starting, ensure all V37 monitoring tables exist, old attendance endpoints work and no pending unexpected migrations exist.

## Safe STAGING procedure
1. Clone **current production database and uploaded files** into an isolated staging environment, protecting private staff data (prefer anonymised data). Restrict staging email/SMS and production API endpoints.
2. Confirm current live version and differences from this V37 source; Sakib must review any changes already deployed that are not in this candidate. Do not overwrite newer live code blindly.
3. Back up production database and `.env`, `APP_KEY`, `storage`, uploads, user sessions if applicable and code; verify backup can restore.
4. On staging, install Composer dependencies using the lock file, ensure PHP extensions, copy environment settings for staging, and validate `APP_ENV`, DB connection and permissions.
5. Run `php artisan migrate:status`. Review pending migrations (especially V37 tables) before running **any** migration. Confirm V38 migration creates four *new* tables only.
6. Run `php artisan migrate --force` **on staging** only after reviewing pending list. Confirm tables: `client_assistance_sessions`, `client_assistance_events`, `client_assistance_notices`, `platform_support_operators`. Verify `attendance_records` count and sample open-shift IDs unchanged.
7. Run `php artisan test --filter=V38AssistanceTest` and full `php artisan test`; run `php artisan route:list` to ensure routes are registered. Run end-to-end browser tests: trial admin requests help, support activates, adds branch/employee, audits appear, client revokes, expired/revoked assistance cannot write; operator cannot open billing/admin URLs.
8. Test client search with two different businesses including same/similar names; verify no cross-business leakage and pagination.
9. Test employee branch reassignment while **clocked in**: must reject and leave record/token/branch intact. Once clocked out: transfer must preserve historical attendance and require employee re-login.
10. Test GPS enabled/disabled, clock-in/out, V37 monitoring, attendance corrections, reports, and existing mobile API against staging.

## PRODUCTION release plan — only after staging pass
**Avoid direct overwriting while staff are working.** V38 is additive, but the code and database need to be deployed together.

A. Confirm no pending unknown migrations and capture a full verified backup. Capture baseline `SELECT COUNT(*) FROM attendance_records` and active-shift IDs; keep these in a protected deployment record.
B. Prefer an atomic code release: prepare a new release directory (do not overwrite live code), upload source and dependencies, copy the live `.env`, preserve `APP_KEY` and shared storage. Do not upload a new `.env` or overwrite user files. Ensure new migration file is present.
C. During a short coordinated maintenance window, stop **new write requests** and background mutation jobs (but do not clock out employees); wait for in-flight writes to finish. Review and run only the migrations approved above. The migration adds tables and does not change attendance records.
D. Switch to prepared release with a single symlink/document-root change or your hosting-supported atomic release process. If shared hosting does not support atomic releases, prepare staging and execute an agreed low-traffic cutover rather than uploading individual PHP files over active workers.
E. Refresh caches in a safe order (`php artisan optimize:clear`; `php artisan config:cache` if appropriate; `php artisan route:cache` only if successful). Keep `APP_DEBUG=false` on production. Restart PHP-FPM/queue workers if available.
F. Confirm: existing employees can clock out, new employees can clock in, monitoring dashboards no longer 500, authenticated V37 endpoints work, `/people` search works, `/platform/assist` loads, and audit writes are logged. Compare active-shift IDs and historical attendance counts with baseline.
G. If any issue, **roll back code** to the previous release and suspend assistance features; leave newly created V38 tables intact until carefully reviewed. Never call `php artisan migrate:rollback` blindly and never truncate attendance or employee tables. If the migration itself fails, stop before switching code and investigate.

## Commands (run from project root on the appropriate environment)
```bash
php -v
php artisan migrate:status
php artisan route:list
php artisan test --filter=V38AssistanceTest
php artisan test
php artisan migrate --force     # ONLY after pending migrations have been reviewed and backups verified
php artisan optimize:clear
```

## Known limitations / follow-ups
- This package has been checked for PHP syntax and ZIP integrity, **not** run against Laravel, MySQL, live hosting or browser sessions in this environment. Full Composer `vendor/` is not included.
- The onboarding wizard is a guided checklist/deep links, not a multi-screen transactional wizard or bulk CSV importer.
- Notifications are in-client-portal only, not SMTP/WhatsApp.
- Role-management permits a full Super Admin to re-grant full access. Limit this privilege to trusted admins; consider a formal two-approver workflow later.
- Existing V37 Super Admin support tools remain available to full Super Admin identities; V38's consent sessions are required for **new** Client Assistance Centre actions, not retroactively for every historic platform support endpoint.
- Don't introduce support accounts before testing guard restrictions and operator conversion in staging.
- Client list still does some legacy overview calculations on the `/people` route; pagination limits the display, but deeper lazy-loading/performance refactoring may help very large tenants.

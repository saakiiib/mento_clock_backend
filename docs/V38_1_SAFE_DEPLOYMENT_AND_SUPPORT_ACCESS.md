# MentoClock V38.1 — Client Assistance Improvements

**Release type:** STAGING CANDIDATE — **not production tested**
**Source baseline:** `MentoClock_Client_Manager_Backend_V38_STAGING.zip` (22 November 2026), **not a fresh export from live hosting**.
**Date:** 23 November 2026.

## Why this release exists
The Client Assistance screen previously required an individual, expiring request to assist every business. V38.1 adds a prominent pending-request card to the Platform Overview and Assistance Centre, a list of businesses with ongoing client-authorised support, an improved session status workflow, and a clearer Support Operator section.

## Meaning of ongoing (standing) authorisation
A business administrator may opt in once, from `/assistance`, to authorise **limited account setup support** until they revoke it. This does NOT automatically grant access to all clients. The permission defaults to OFF. The only new operations covered are creating a workplace, creating an employee and modifying an employee's workplace assignments. Changes require an explanation, are tied to the true platform administrator, and appear in business activity history. The business administrator may revoke permission at any time. Existing short-lived assistance sessions still work.

Do not enable this setting for clients without their authorisation. Review your terms, Data Processing Agreement and customer privacy disclosures with a qualified UK adviser before relying on standing instructions as a contractual basis.

## New routes and features
- `GET /platform/assist` — support inbox, pending count, Manage Clients list, temporary requests, operator roles.
- `GET /platform/assist/manage/{business}` — Super Admin view of that authorised client's limited support workspace.
- `POST /platform/assist/manage/{business}/branch` — create a business-scoped workplace.
- `POST /platform/assist/manage/{business}/employee` — create a business-scoped employee with a unique password.
- `POST /platform/assist/manage/{business}/assign/{employee}` — change assignments, blocking changes during an open shift.
- `POST /assistance/standing-access` — client grant/revoke with explicit acknowledgement.
- Existing temporary session routes stay unchanged.
- A new request-card near the top of `/platform` shows pending requests.
- Existing `Activate` action is now idempotent if the same operator has already activated an unexpired session, otherwise returns a form error instead of an unhandled 422 exception page.

## New migration
`database/migrations/2026_11_23_000001_add_standing_support_authorization.php` creates **only**:
- `client_support_authorizations` (per-business opt-in and revocation)
- `client_standing_support_events` (append-only operational support changes and entry log)
- `client_support_authorization_history` (grant/revoke history)

No changes to `attendance_records`, `users`, `employee_branches`, the live Flutter API, or password hashes. No migration is required for the Flutter app.

## Access boundaries
- Only a current business administrator with workspace access can grant/revoke support for **their business**.
- Only a **full Super Admin** may use direct ongoing support. Restricted support operators continue to use time-limited assigned sessions.
- Every supported operation is scoped to the authorised business; branches belong to the selected business.
- Employee reassignment during an active shift remains blocked, and no shift is closed or changed automatically.
- Each support edit requires a reason and creates an event and a platform audit record. Passwords and tokens are not written to support logs.
- Ongoing access is revoked immediately for new operations. A revocation and an in-progress write must be serialised through database locks.
- The support workspace visit itself is recorded. Sensitive HR, payroll, exports and deletion are outside the new support UI.

## Mandatory checks before deployment
1. Confirm **current production HEAD**. Sakib must compare all modified files with the actual current live code and merge unrelated fixes. **Do not blindly replace** the current production application with this archive.
2. Confirm V37/V38 migrations succeeded on live, especially `attendance_attempt_events` and the four V38 assistance tables. If any expected table is missing, fix the prerequisites safely first.
3. Confirm `APP_ENV=production`, `APP_DEBUG=false` in the production `.env` and refresh the configuration cache. Production must not display Laravel debug traces.
4. Create **and verify restoration** of database and code backups, `.env` and `APP_KEY`, uploads and persistent storage. Record count and IDs of currently open attendance shifts.
5. Clone an anonymised production database to isolated staging. Use a separate staging URL and disable outgoing notifications to real employees.
6. Review pending migrations with `php artisan migrate:status`. Run only known/additive approved migrations, on **staging first**.
7. Run Composer install based on `composer.lock`, `php artisan test --filter=V38`, then the full Laravel test suite. Do not deploy if tests fail.
8. Confirm no changes to employee attendance, GPS settings, existing sessions or historical branch assignments.

## Staging regression checklist
- A trial business with no authorisation cannot be opened from `Manage Clients` (HTTP 403), and a POST to the direct support route is also denied.
- Client administrator grants permission: only their business becomes manageable; another client's data is inaccessible.
- Super Admin opens a client support workspace, creates a branch, creates an employee, changes an assignment, and checks the event and notification for **each** change.
- Client revokes permission: subsequent reads and writes fail, no new branch is created, and prior audit entries remain.
- Restricted Support Operator cannot use direct standing access or access billing/platform admin tools.
- Old time-limited requests continue: pending -> activated -> open -> revoke/expire.
- Double activation of an already active request does not cause a server exception, and non-pending requests are not offered an Activate control.
- Support Operator role section does not show a blank unusable selection when no other platform administrator exists.
- Active employee is blocked from changing workplace assignment; clock-in/out and state APIs still function normally.
- Support pages display only correctly scoped employees, branches and audits for the selected business.
- Pending assistance card shows correct count after request and activation.

## Deployment plan for active customers
1. Prefer an atomic/reversible code release (new release directory with symlink swap). Do not upload files one by one over the running application.
2. Take a final verified backup. Preserve the current `.env`, `APP_KEY`, upload storage and database.
3. Review migration list; new migration adds three tables only. Confirm all older migrations are present before migration.
4. During a controlled, brief deployment window pause **new write requests** and background modifying jobs (never auto clock-out an employee). Allow in-flight requests to finish.
5. Apply the approved migration and switch the prepared code release; then clear/rebuild route/config/view caches as appropriate.
6. Smoke-test a live employee with an open shift, a normal clock-out, a new clock-in, GPS restriction behaviour, attendance monitor, client search and the new direct-support permission flow. Check logs and support audit trails.
7. Verify all previously open attendance IDs remain intact except shifts normally closed by their employees; review new monitoring exceptions. Reopen traffic once checks pass.
8. On failure, return to the previous code release and disable new support routes; do not blindly roll back database migrations, truncate attendance or reset employee IDs. Leave additive tables in place until they can be reviewed.

## Useful commands (correct project directory only)
```bash
php -v
php artisan migrate:status
php artisan route:list --path=platform/assist
php artisan test --filter=V38StandingSupportTest
php artisan test --filter=V38AssistanceTest
php artisan test
php artisan optimize:clear
php artisan migrate --force  # only after reviewing ALL pending migrations and verifying backups
```

## Validation limitations
- PHP syntax checks and ZIP integrity have been verified in the preparation environment.
- No Composer vendor directory or configured MySQL test database was supplied, so **Laravel integration tests, database migrations, browser tests and live load tests have NOT run** here.
- This is a staging candidate. Sakib is responsible for completing the tests and reconciling with live fixes before any production deployment.
- The new limited support workflow does not retroactively restrict all legacy Super Admin operations elsewhere in the platform. A broader permission and data-access audit remains recommended.
- Client-facing copy describes an optional authorisation workflow; it is not a replacement for a legally reviewed support agreement and DPA.

# MentoClock backend V36 — release and safe deployment

## Source and scope
Built from supplied `MentoClock_Client_Manager_Backend_v35(1).zip`. This is **a candidate release**, not a production deployment. No live database was opened or modified. This package includes the existing entire Laravel project; it does not include `vendor/`, an `.env`, the live database or private uploaded documents.

## Implemented changes

1. **GPS checkbox now enforced for NEW clock-ins.** If `branches.location_required` is false, clock-in remains location-optional; if true, a valid location and configured workplace radius are required. An out-of-radius request returns HTTP 422 with `errors.location` rather than recording attendance. This uses the existing checkbox; no new database column or migration is needed.
2. **Existing open shifts protected on rollout.** Clock-outs continue with the original V35 behaviour by default: location coordinates are required where the checkbox is checked, but an outside-radius clock-out is permitted and flagged. After on-site testing, set `ATTENDANCE_GPS_STRICT_CLOCK_OUT=true` to enforce the radius also for clock-outs. WARNING: enabling this can prevent existing employees outside the radius from clocking out; use manager corrections when needed.
3. **Mobile login request limiting.** Up to 10 requests/minute per account+IP and 120 requests/minute per IP. Responses may be 429 when exceeded; confirm compatible with mobile error messaging.
4. **Attendance history database optimisation.** Batch-fetches branch names for recent records rather than retrieving a branch per record.
5. **Attendance reports optimisation.** Batch-fetches relevant rota shifts rather than querying separately for every attendance row. Existing overlap/break calculation is retained.
6. **Regression tests added/updated** for enabled/disabled GPS, out-of-range attempts, misconfigured coordinates, strict opt-in clock-outs, and safe clock-outs during rollout.

## Intentionally NOT changed (to minimise risk for active employees)

- No database migration, table changes, dropping or rewriting attendance rows.
- No changes to existing employee login names, hashes, tokens, IDs, branch memberships or business assignments.
- No API endpoint name, request field, or success-response contract changes.
- No forced password change; existing mobile app may not have a first-login enforcement screen.
- No mandatory pagination on admin employee lists (would break existing app/client expectations without UI changes).
- No database open-record uniqueness index (requires production duplicate-data audit and engine-specific safe migration).
- No queue driver switch (requires supervisor/queue worker setup and testing).
- No change to employee profile email/login identity (requires coordinated Flutter/API migration).

## Mandatory staging checks BEFORE replacing production files

1. Do not deploy during active shifts if avoidable. If necessary, use an atomic release-directory/symlink swap so requests keep working; avoid `php artisan down` and database maintenance while people are clocked in.
2. Backup the database, uploaded storage, `.env`, and current V35 code. Confirm backup restoration to an isolated environment; preserve `APP_KEY` and domain.
3. On a staging clone, run `composer install --no-interaction`, configure staging `.env`, `php artisan migrate:status`, `php artisan test --filter=AttendanceTest`, then the full `php artisan test` suite. Source-only PHP syntax checks are NOT equivalent to integration tests.
4. Verify with real device coordinates: GPS unticked -> clock in/out without coordinates; GPS ticked -> inside radius clock-in succeeds; outside radius clock-in returns 422; missing GPS returns 422; prior open shift clock-out still succeeds under default rollout setting.
5. Verify both business portal and mobile login, profile editing, reports, PDF exports and supervisor corrections, including two distinct businesses in the same client account.
6. Inspect production branch latitude/longitude/radius carefully: if a GPS-enabled branch has incorrect or absent coordinates, new clock-ins will fail until the branch is corrected or GPS is unticked. Recheck supermarket counter positions and device accuracy.
7. Check PHP runtime compatibility (PHP >=8.2), MySQL transaction isolation and PHP-FPM workers. Run 100/500/1000 concurrent request tests against staging only, never the live attendance database.

## Suggested controlled production rollout

- Deploy code and assets through a versioned release path, keep the exact previous V35 release ready to restore.
- Retain all live environment settings; add `ATTENDANCE_GPS_STRICT_CLOCK_OUT=false` to the live `.env`.
- On the new release run `php artisan config:cache` and `php artisan route:cache` (only if routes support it); do **not** run any unnecessary migrations or `migrate:fresh`.
- Switch web root atomically (or schedule a very brief quiet cutover if shared hosting does not support atomic switching).
- Immediately check sign-in, inside clock-in, clock-out for a preexisting active shift, live active-shifts dashboard, and server logs.
- If failures occur, revert to the old code release. Do NOT revert live database to a pre-cutover backup while employees have been clocking in, since that would lose attendance records.
- When all active shifts have ended and sites have validated GPS positions, consider changing `ATTENDANCE_GPS_STRICT_CLOCK_OUT=true`, then `php artisan config:cache`; monitor failures closely.

## Known limitations and follow-up V37 backlog

- Cross-worker concurrency and duplicate record uniqueness have not been stress-tested here; the user-row `lockForUpdate()` protects normal clock-in races when database transactions and connection configuration are correct.
- Reporting still loads all matching rows into memory. Consider a separate paginated report/export strategy for very large datasets; the V36 change reduces SQL round-trips but may use more PHP memory for shift collections.
- Login usernames and personal contact emails share a field. Separating them requires a deliberate schema/API/mobile migration.
- Queue processing is still synchronous by default on shared hosting; moving to VPS permits supervised workers and `database`/`redis` queues.
- Configured `accuracy_m` from the device is used in distance check and can be spoofed; server-side GPS alone cannot prove physical presence.
- API-level throttling is account+IP and IP based; consider WAF/CDN defence against distributed attacks.
- Full tests have not been run in this sandbox, because the uploaded source has no Composer vendor folder and a configured application test database is not present.

## Hosting move to VPS later

Use versioned deployments, MySQL full backup+restore, same `APP_KEY`, copy storage, preserve DNS/API hostname and HTTPS, verify scheduled tasks, mail and API token/session tables. The existing app does not need republishing if it keeps the same API contract and host. Test rollback with database data retained.

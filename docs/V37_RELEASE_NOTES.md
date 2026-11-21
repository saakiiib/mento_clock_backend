# V37 Attendance Monitoring – Release Candidate

**Source:** V36 candidate. **No production deployment performed.**

## Delivered
1. Attendance clock-in/out HTTP attempt logs (accepted, rejected, error). Records request UUID, business, employee, branch where known, response code, time, duration, app version headers and GPS accuracy (not coordinates). Logging failures do not interrupt attendance. Exceptions are rethrown.
2. Mobile/server state API: `GET /api/attendance/state` returns the server-authoritative active attendance. Existing `GET /api/attendance` remains unchanged.
3. Super Admin `/platform/attendance-monitor` dashboard with paginated events, filters, errors, alerts, diagnostics and existing attendance audit history.
4. Existing manager audit trail surfaced; platform support's independent `platform_audit_logs` are not duplicated here.
5. `php artisan attendance:detect-incidents` creates alerts for three or more rejected/failed attempts per business/branch in five minutes. Schedule every 5 minutes. Alerts are in dashboard, not yet email/SMS notifications.
6. `POST /api/attendance/diagnostics` accepts an employee-submitted issue report; the endpoint reports mismatch based on client/server active-state. Flutter UI/client-side persistent offline diagnostics **not delivered** because Flutter source wasn't in the supplied ZIP.
7. Admin-protected DB health check `/platform/attendance-monitor/health`, request timing and daily monitoring data retention.

## Existing-shift compatibility
- No modification of `attendance_records` schema or clock-in/out transactional code.
- New tables only, no backfill/cleanup of existing attendance.
- Existing API responses left intact; new `X-Attendance-Request-ID` header added on handled attendance requests.
- No auto-clock-out, no auto-correction.

## Staging acceptance checklist
- Install Composer dependencies and perform `php artisan migrate --force` on staging copy.
- Execute full test suite and manual cases: GPS enabled/disabled, location errors, already clocked in, off-rota confirmation, already clocked out, and simultaneous shift attempts.
- Check successful and rejected attempts appear on dashboard, are associated with correct business and employee, and do not expose coordinates or secrets.
- Verify active shift remains open after migration and can be clocked out.
- Test manager correction and finalisation, including audit history; verify platform support audit separately.
- Validate state endpoint matches database, and diagnostic mismatch reporting never changes attendance.
- Test alert command with test failures and dashboard acknowledgement.
- Verify PHP workers/DB can access monitoring tables; review latency impact under load.
- Confirm `/platform/attendance-monitor` is inaccessible without platform admin session.
- Confirm cron runs `php artisan schedule:run` each minute.

## Release plan
1. Backup live application, DB, `.env` and storage; retain encryption key.
2. Run migrations during an agreed short deployment window. Additive tables are designed for online migration, but provider-specific locking must be checked.
3. Deploy code atomically and clear config/route/view caches. Never overwrite live `.env`.
4. Test a real clock-in and clock-out, monitor database and error logs.
5. Roll back code on failure; **leave new monitoring tables in place** until incident resolved (dropping tables removes logs). Existing attendance data remains unchanged.

## Remaining to deliver for all seven phases
- Flutter source integration: diagnostic screen, device-side request tracking, resilient local logs, human-readable report button, new app release.
- Additional automatic alerts for short shifts, stale client states, anomalously long shifts, and external email/WhatsApp notifications.
- Dedicated full-system health telemetry (PHP-FPM, queue workers, MySQL metrics, error-rate dashboards).
- Unified platform support correction history in the monitoring dashboard (currently separate platform audit log).
- Real concurrency/load testing and integration suite verification on a representative server.

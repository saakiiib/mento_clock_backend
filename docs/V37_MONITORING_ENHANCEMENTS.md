# V37 additional monitoring enhancements (staging only)

These changes are additive/read-only with respect to existing attendance records.

## Added
- Monitor filters: exact request UUID, action and reason code.
- 24-hour summary counts and failure reason breakdown respect the chosen business.
- Read-only list of active shifts older than 16 hours (does not close shifts).
- Diagnostic reports detect both state mismatches and wrong active attendance IDs.
- Scheduled alert if a business generates two or more mobile/server state mismatch reports within 15 minutes. Requires Laravel scheduler running.
- Employee diagnostic report IDs displayed for customer support correlation.

## Known limitations and outstanding work
- Middleware runs only after authentication and module entitlement; auth failures and requests blocked before the monitor middleware are not captured by the attempt log.
- A network interruption before the request reaches Laravel cannot be seen without Flutter-side local diagnostics (separate app release needed).
- No evidence of actual delivered alerts without running scheduler in the deployed environment; alerts are stored for Super Admin view only.
- Current alert thresholds are fixed; tune them using real traffic measurements.
- Very long shifts can be legitimate, especially overnight. Do not close shifts automatically.
- Front-end/mobile cached attendance state still requires Flutter implementation and tests.

## Deployment
1. **Do not deploy directly to production.** Rebuild/test on staging with restored anonymised data.
2. Confirm V37 monitoring migrations are applied. These additions require no new migrations beyond V37.
3. Verify read-only monitor for filtered business, correct authorization and no tenant leaks.
4. Verify incident scheduler invocation and that existing attendance and open shifts remain unchanged.
5. Check logs for monitoring insert failures and monitor table growth.
6. Confirm backup and rollback of code and database before cutover.

## Test cases to run
- A user with a stored open shift sends client_state=clocked_out: mismatch true.
- A user reports a different client_attendance_id from the server's active record: mismatch true.
- A normal matching record produces mismatch false.
- Business filter changes totals, long shifts and failure breakdown.
- Request UUID search returns one specific event.
- Trigger two diagnostic mismatches, run scheduler, confirm one alert; run again and confirm no duplicate.
- Verify no writes to attendance_records during monitoring or alert detection.

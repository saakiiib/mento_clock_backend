# V37 Flutter integration contract (not compiled)

The source ZIP is backend-only. Integrate these endpoints in the Flutter project and publish a mobile update after device testing.

## 1. Attendance state synchronisation
`GET /api/attendance/state` with the employee's existing Sanctum token.

Example response:
```json
{
  "server_time": "2026-11-20T10:00:00+00:00",
  "is_clocked_in": true,
  "active_attendance": { "id": 123, "branch_id": 8, "clock_in": "2026-11-20T09:00:00+00:00" },
  "state_authority": "server"
}
```

Call after app login, foreground resume, successful clock-in/out and a timed-out clock request. Display server state over any optimistic local state; never silently auto-clock-out. If network unavailable, show 'Unable to confirm attendance state' instead of pretending success.

## 2. Correlation headers
Send `X-App-Version` and `X-Device-Platform: ios|android`. Attendance endpoints reply with `X-Attendance-Request-ID` for logged results. Save recent request IDs locally with UTC timestamp and HTTP status. Do not store passwords, bearer tokens or precise GPS in local diagnostics.

## 3. Manual problem report
`POST /api/attendance/diagnostics` (employee token, JSON):
```json
{
  "issue_type": "state_mismatch",
  "client_state": "clocked_in",
  "client_attendance_id": 123,
  "description": "App shows clocked in but attendance card disappeared",
  "app_version": "1.2.0",
  "platform": "ios"
}
```
Valid `issue_type`: `clock_in_failed`, `clock_out_failed`, `state_mismatch`, `missing_record`, `other`.
Server returns `report_id`, `server_is_clocked_in`, `state_mismatch`, and a confirmation message. This endpoint NEVER edits attendance.

## 4. UI requirement
Add Settings > Report attendance problem with issue type, optional description and permission to include app version, and a 'Check my current attendance state' action. Allow reports to be queued securely if offline, with clear indication that it is pending; ensure retries do not duplicate reports (a future extension should accept client-generated idempotency keys).

## 5. Test cases
Clock-in/out success; GPS outside-radius rejection; invalid location; stale state on app resume; interrupted network after accepted clock-in; delayed 409 response; wrong logged-in account; retry; app update; no GPS permission; no connectivity.

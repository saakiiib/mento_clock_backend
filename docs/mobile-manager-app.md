# MentoClock mobile manager workspace

Business administrators can sign in to the Flutter app and use a mobile manager workspace. Employee accounts continue to use the employee clocking, holiday, rota, and profile screens. Platform super-admin operations remain in the web platform portal.

## Manager API

Every endpoint below requires a bearer token for an active `admin` user and is scoped to the authenticated user's business. The API does not accept a client-supplied business ID.

| Method and path | Purpose |
| --- | --- |
| `GET /api/manager/dashboard` | Business name, employee and branch counts, current clock-ins |
| `GET /api/manager/attendance?date=YYYY-MM-DD` | Attendance records for a business-local date |
| `POST /api/manager/attendance/{id}/correct` | Audited attendance correction with a required reason |
| `GET /api/manager/leave` | Pending employee holiday requests |
| `POST /api/manager/leave/requests/{id}/review` | Approve or reject a pending request; preserves leave and rota checks |
| `GET /api/manager/rota?week=YYYY-MM-DD` | Shifts, active employees, workplaces and business timezone for a week |
| `POST /api/manager/rota/shifts` | Create one validated draft shift |
| `POST /api/manager/rota/publish-week` | Publish checked draft shifts for the selected week |

The manager routes are behind Sanctum authentication, active-business validation, the business administrator role gate, and the relevant module entitlement. Rota creation uses the existing conflict planner. Leave review uses existing overlap, entitlement, and rota checks. Attendance changes are recorded in the audit log.

## Deployment

This update changes Laravel routes and controllers but adds no database migration. Deploy the backend code with the existing Laravel release process, then build the Flutter app with the matching `lib` folder. Confirm the business has attendance, leave management, and rota modules enabled before inviting managers to use those screens.

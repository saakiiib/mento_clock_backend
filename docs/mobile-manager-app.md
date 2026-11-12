# MentoClock mobile manager workspace

Business administrators can sign in to the Flutter app and use a mobile manager workspace. Employee accounts continue to use employee clocking, holiday, rota, and profile screens. Platform super-admin operations remain in the web platform portal.

## Manager API

Every endpoint requires a bearer token for an active `admin` user and is scoped to that user's business. The API does not accept a client-supplied business ID.

| Method and path | Purpose |
| --- | --- |
| `GET /api/manager/dashboard` | Business name, employee and branch counts, current clock-ins |
| `GET /api/manager/attendance?date=YYYY-MM-DD` | Attendance records for a business-local date |
| `POST /api/manager/attendance/{id}/correct` | Correct a clock record with a required reason; records an audit entry |
| `POST /api/manager/attendance/{id}/finalize` | Mark a completed attendance record reviewed for payroll; records an audit entry |
| `GET /api/manager/leave` | Employee holiday requests |
| `POST /api/manager/leave/requests/{id}/review` | Approve or reject a pending request; preserves leave and rota checks |
| `GET /api/manager/rota?week=YYYY-MM-DD` | Shifts, active employees, workplaces and business timezone for a week |
| `POST /api/manager/rota/shifts` | Create one validated draft shift |
| `PUT /api/manager/rota/shifts/{id}` | Edit a draft, or revise a future published shift while retaining its earlier version in rota history |
| `POST /api/manager/rota/shifts/{id}/cancel` | Cancel a future shift and record the change in rota history |
| `POST /api/manager/rota/publish-week` | Publish checked draft shifts for the selected week |
| `GET /api/manager/reports?period=daily\|weekly\|monthly\|custom&date=YYYY-MM-DD&employee_id={id}&deduct_breaks=0\|1` | Attendance, employee, approved annual leave and payroll-reference totals |
| `GET /api/manager/reports?period=custom&start_date=YYYY-MM-DD&end_date=YYYY-MM-DD` | Custom date range up to 366 days; employee and break filters also apply |
| `GET /api/manager/reports/employees` | Active employee list and paid annual-leave hours-per-day setting |
| `PUT /api/manager/reports/employees/{id}/holiday-hours` | Set paid annual-leave hours per normal working day; audited |
| `GET /api/manager/reports/download?...&format=pdf\|csv` | Download the selected report in PDF or CSV format |
| `POST /api/manager/reports/email` | Email PDF and CSV copies to the signed-in manager |

Manager routes use Sanctum authentication, active-business validation, the business administrator role gate, and the relevant module entitlement. Rota changes use the conflict planner. Revising a future published shift cancels the prior version and creates a replacement in one database transaction; started or completed shifts are read-only. Leave review uses overlap, entitlement, and rota checks.

Managers can amend employee clock-in/out records in the app by entering a reason; the correction is saved in the audit log. They can finalize completed attendance records for payroll review. Any later correction clears the finalization, so that record needs review again. This is an attendance sign-off, not a payroll-period lock.

## Hours and payroll reference reports

Reports can include one employee or the whole team for today/a selected date, a week, a month, or a custom range of up to 366 days. Attendance reports show gross clocked hours, unpaid breaks, and net worked hours. `deduct_breaks=0` uses gross hours for the selected total; `deduct_breaks=1` uses net hours. Break minutes are estimated from the matching rota shift and capped at elapsed clock time. MentoClock does not record break start/end punches, so this is a scheduled estimate, not proof the employee took a break. Without a matching rota shift, zero minutes are deducted. For example, 10:00–16:00 with a 30-minute rota break is 6 gross hours, 0.5 break hours, and 5.5 net hours.

Approved annual leave is converted to paid hours using each employee's configured paid hours per normal working day. Set this from the employee's contract or agreed schedule. If it is missing, the report marks the combined payroll-reference total incomplete. Other leave types are not counted as paid annual leave. Payroll-reference hours are net worked hours plus approved annual-leave hours; MentoClock does not calculate wages, overtime, tax, or statutory pay. The Flutter PDF action opens the device share sheet for Mail, WhatsApp, and other installed apps.

Rota break minutes are manager-entered for each shift (0, 15, 30, 45, 60, or another value up to 600). New shifts default to 0, and a break cannot equal or exceed the planned shift duration. The report never deducts more time than the clocked duration.

Approved leave cannot be cancelled from the employee app or manager web portal once its business-local start date has arrived. The employee UI replaces Cancel with an already-taken/started label; the web portal shows “Already taken”. Both API/web handlers enforce the rule server-side.

## Deployment

Run the new Laravel migration before using paid holiday-hours reporting. Deploy the backend code with the existing Laravel release process, then build the Flutter app with the matching `lib` folder. Confirm the business has attendance, leave management, rota, and reports modules enabled before inviting managers to use those screens.

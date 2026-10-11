# MentoClock enterprise HR roadmap

## Product direction

Keep one multi-tenant product and one Super Admin console. Each client business receives its own workspace, users, locations, policies, records, and enabled module entitlements. Commercial agreements determine which live modules and capacity limits are enabled for each client.

Build the enterprise web portal first and use it to establish the complete HR workflows, permissions and client settings. Then extend the existing MentoClock Flutter app against the same backend and APIs, preserving its current clock-in/out features. Employees and business owners/managers sign in to the same tenant-aware system and see role-appropriate screens. The web portal remains available in a browser with the same account; the app can provide a secure link to it for full administration when a workflow is better suited to web. A separate replacement app is not required.

The mobile app should support two role-aware experiences rather than two separate products:

- **Employee:** clock in/out, view attendance, shifts and leave balance, request leave, read notices, maintain permitted personal details, and access assigned documents.
- **Business owner/manager:** review workforce summaries, approve leave and shift changes, view attendance exceptions, manage the tasks allowed by their role, and open the web portal for full administration.

Use one authentication and permission model across web and the existing mobile app. Keep the Super Admin account separate from client accounts. A business owner, HR administrator and branch manager must have distinct permissions; the current backend has only client `admin` and `employee` roles, so expand authorization before adding sensitive HR workflows. Do not grant owner permissions merely because a user has signed in through the owner-facing app screens.

The first implementation step is the SaaS Module Engine: catalogue, client entitlements, route/API enforcement, assignment history, and Super Admin selection. The engine is in place. This update expands Employee Management with a full employee profile form while preserving existing attendance behavior. Employee Management and Attendance Tracking remain required foundations; Dashboard & Reports is currently available as an optional module. Future modules appear as planned and cannot be assigned until their workflows have been built and verified.

## Module catalogue

| Module | Current state | Scope |
| --- | --- | --- |
| Employee Management | Available, profile expansion underway | Employee account and workplace assignment plus personal, home address, emergency contact, banking, passport/visa/CoS, department, designation, employment terms and reporting-authority fields. NI, bank, address, passport, visa/BRP and CoS reference data are encrypted at rest. Next: private documents and profile image, onboarding, contract records, role history and offboarding. |
| Attendance Tracking | Available | Mobile clock-in/out, geofence validation, attendance history, manager corrections and audit trail. Expand with break rules, timesheets and approval. |
| Dashboard & Reports | Available | Attendance reports and exports. Expand with labour cost and absence analytics when the required data and permissions exist. |
| Leave Management | Implemented for pilot | Workspace leave year, contractual annual entitlement defaults and employee overrides, weekly work patterns, annual/sick/unpaid/compassionate/other requests, overlap checks, pending holds, admin approval/decline, cancellation and audit events. Accrual, carry-over, part-year proration and statutory calculations remain future work. Employee API is ready for the existing app's next UI update. |
| Rota & Shifts | Pilot MVP implemented with Leave/Holiday integration | Weekly draft shifts with publish/cancel, employee and branch assignment, overlap/leave/company-closure checks, audit events and a published-shifts API for the existing app. Next: templates, recurring schedules, availability, swaps, coverage warnings, working-time/rest-rule enforcement and rota-to-attendance variance. |
| Compliance & Training | Pilot MVP implemented | Private, tenant-scoped employee document register with categories, expiry dates, review/rejection notes, authorized downloads and audit events; training/certification records with provider, status and renewal dates; client dashboard due-soon indicators. Automated email reminders, retention/export/deletion workflows and official legal checks remain future work. |
| Company Notices | Pilot MVP implemented | Client administrators draft, publish to all active employees, a workplace or one employee, and archive; optional per-recipient acknowledgements are recorded. Employee APIs are ready for later mobile UI work. Delivery reporting for scheduled notices and employee web self-service remain future work. |
| Holiday Calendar | Implemented for pilot | Tenant-scoped manual regional holidays and local closures, calendar management, change history and exclusion from working-day leave counts. Public-holiday dates and substitute days must be entered and verified by each workspace; automated official-date import remains future work. |

Potential later modules: recruitment/onboarding workflow, training and certification, performance reviews, equipment register, employee self-service, payroll export/integrations, and HR consultant multi-client access. Payroll calculations should remain an integration or separately scoped product until tax, pension, and statutory rules are maintained and tested.

## Suggested delivery order

1. Module entitlements and per-client capacity controls (current stage).
2. Complete the responsive enterprise web portal, including role-based access for business owners, HR administrators, location managers and employees.
3. Pilot Leave Management, Holiday Calendar and the first Rota & Shifts workflow together. Verify tenant entitlements, employee work patterns, closure flags, leave/rota conflict handling and mobile API access.
4. Expand Rota & Shifts with templates, availability, swaps, coverage and attendance variance once the integrated pilot workflows are stable.
5. Pilot private employee evidence, training/certification records and company notices; verify tenant boundaries and review/acknowledgement audit.
6. Add a dedicated employee self-service web portal, automated reminders, document retention/export controls and more granular permissions.
7. Stabilise and document the API contracts, then extend the existing role-aware mobile app for leave, shifts, notices and employee self-service while preserving clock-in/out.
8. Add cross-module reporting, integrations and consultant access after the base workflows have stable audit and permission models.

## Enterprise foundations to preserve

- Keep every tenant-owned table scoped by `business_id`; verify tenant scope on every read and write, including file downloads and background jobs.
- Use roles and per-action permissions for platform administrators, client owners, HR administrators, location managers and employees. Avoid relying on hidden UI controls as security.
- Keep an immutable audit trail for administrative changes, approvals, corrections, document access and entitlement changes.
- Store employment documents on private storage, use short-lived authorized downloads, and define configurable retention/export/deletion flows.
- Support business timezone, holiday region, leave year, approval policy and document retention as explicit client settings.
- Keep the same business, user, module and permission rules across the web portal and mobile APIs. Use role-specific navigation and enforce access in backend policies/middleware as well as in the interface.
- Separate module availability from client entitlement. A module must be released and supportable before the Super Admin can enable it for a customer.
- Keep module entitlements, contract/package notes and capacity limits in the Super Admin workspace. Add billing automation only when the commercial model and payment provider are decided.
- Add migration, tenant-isolation, permission and module-gating tests for each new workflow before release.

## Reference review

The supplied reference image groups the offer into employee management, attendance, leave, rota/shifts, compliance, reports, company notices and a holiday calendar. BritHR's public product description also presents employee lifecycle/document records, leave approvals and calendars, attendance/rota, sponsor compliance, analytics, security/audit, and advisor access. These are useful product categories; MentoClock should build its own workflow and interface around its current Laravel backend and client requirements.

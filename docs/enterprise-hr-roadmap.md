# MentoClock enterprise HR roadmap

## Product direction

Keep one multi-tenant product and one Super Admin console. Each client business receives its own workspace, users, locations, policies, records, and enabled module entitlements. Commercial agreements determine which live modules and capacity limits are enabled for each client.

Build the enterprise web portal first and use it to establish the complete HR workflows, permissions and client settings. Then extend the existing MentoClock Flutter app against the same backend and APIs, preserving its current clock-in/out features. Employees and business owners/managers sign in to the same tenant-aware system and see role-appropriate screens. The web portal remains available in a browser with the same account; the app can provide a secure link to it for full administration when a workflow is better suited to web. A separate replacement app is not required.

The mobile app should support two role-aware experiences rather than two separate products:

- **Employee:** clock in/out, view attendance, shifts and leave balance, request leave, read notices, maintain permitted personal details, and access assigned documents.
- **Business owner/manager:** review workforce summaries, approve leave and shift changes, view attendance exceptions, manage the tasks allowed by their role, and open the web portal for full administration.

Keep the Super Admin account separate from client accounts. Client web access now has permission-based roles for HR managers, rota managers, supervisors, report viewers and custom roles. The existing app keeps its current `admin` and `employee` API behavior during this web milestone; extend manager and employee API permissions when the agreed app and self-service work begins. Do not grant owner permissions merely because a user has signed in through the owner-facing app screens.

The SaaS Module Engine is in place with a module catalogue, client entitlements, route/API enforcement and Super Admin selection. The web portal now includes expanded employee profiles, leave and holiday management, rota, compliance, training, notices, lifecycle checklists, reminders, HR reporting and client-managed web roles. Employee Management and Attendance Tracking remain core foundations; optional modules are enabled per client.

## Module catalogue

| Module | Current state | Scope |
| --- | --- | --- |
| Employee Management | Available; expanded profile implemented | Employee account and workplace assignment plus personal, home address, emergency contact, banking, passport/visa/CoS, department, designation, employment terms and reporting-authority fields. NI, bank, address, passport, visa/BRP and CoS reference data are encrypted at rest. Private document records and onboarding/offboarding workflows are implemented; profile photo, richer role history and retention/export controls remain future work. |
| Attendance Tracking | Available | Mobile clock-in/out, geofence validation, attendance history, manager corrections and audit trail. Expand with break rules, timesheets and approval. |
| Dashboard & Reports | Available | Attendance reports and exports. Expand with labour cost and absence analytics when the required data and permissions exist. |
| Leave Management | Implemented for pilot | Workspace leave year, contractual annual entitlement defaults and employee overrides, weekly work patterns, annual/sick/unpaid/compassionate/other requests, overlap checks, pending holds, admin approval/decline, cancellation and audit events. Accrual, carry-over, part-year proration and statutory calculations remain future work. Employee API is ready for the existing app's next UI update. |
| Rota & Shifts | Web planning MVP implemented with Leave/Holiday integration | Weekly draft shifts with editing, publish/cancel, employee and branch assignment, overlap/leave/company-closure/availability checks, reusable week templates, audit events and a published-shifts API for the existing app. Next: swaps, coverage warnings, working-time/rest-rule enforcement and rota-to-attendance variance. |
| Documents | Client administration module implemented | Staff, organisation and employee document reports with CSV/PDF exports, private tenant-scoped downloads, document archive/restore with audit history, and an inactive employee archive view. Employee and organisation documents remain in their existing private stores. Configurable retention policies and bulk export controls remain future work. |
| Compliance & Training | Administrative MVP implemented | Private, tenant-scoped employee document register with categories, expiry dates, review/rejection notes, permission-gated downloads and audit events; training/certification records with provider, status and renewal dates; client dashboard due-soon indicators and automated reminders. Retention/export/deletion workflows and official legal checks remain future work. |
| Company Notices | Administrative MVP implemented | Client administrators draft, publish to all active employees, a workplace or one employee, and archive; optional per-recipient acknowledgements are recorded. Employee APIs are ready for later mobile UI work. Delivery reporting for scheduled notices and employee web self-service remain future work. |
| Onboarding & Offboarding | Administrative checklist MVP implemented | Tenant-scoped workflows with assigned tasks, due dates, notes and event history. Future-dated offboarding revokes sessions and app tokens on the effective business date. Expand task assignment/reminders, assets and workflow templates. |
| HR Reminders | Scheduled MVP implemented | Tenant-scoped reminders cover document/training expiry, employment and immigration record expiry, overdue lifecycle tasks and stale leave requests. Email digest depends on valid mail configuration and the host scheduler cron. Add reminder rules/preferences and per-recipient delivery/read tracking later. |
| HR Reports | Administrative summaries implemented | Date-filtered workforce, hiring/leaver, department, employment type, leave, attendance, published rota, expiry and lifecycle summaries with CSV/PDF exports. Labour costing, absence trends, reconciliation and richer scheduled reports remain future work. |
| Roles & Permissions | Client-managed web permissions MVP implemented | Default HR Manager, Rota Manager, Supervisor and Report Viewer templates plus custom role permission sets; route-level checks protect web actions while preserving the employee role/API used by the current app. Broaden and audit access policies before employee self-service or mobile manager actions. |
| Holiday Calendar | Implemented for pilot | Tenant-scoped manual regional holidays and local closures, calendar management, change history and exclusion from working-day leave counts. Public-holiday dates and substitute days must be entered and verified by each workspace; automated official-date import remains future work. |

Potential later modules: recruitment/onboarding workflow, training and certification, performance reviews, equipment register, employee self-service, payroll export/integrations, and HR consultant multi-client access. Payroll calculations should remain an integration or separately scoped product until tax, pension, and statutory rules are maintained and tested.

## Suggested delivery order

1. Module entitlements and per-client capacity controls (current stage).
2. Complete the enterprise HR web portal and verify client-owner/HR/location permissions with real staging accounts.
3. Validate Leave Management, Holiday Calendar, Rota, Compliance, Training and Notices together, including conflicts, audit records and tenant isolation.
4. Finish deeper rota operations: swaps, coverage, working-time/rest rules and attendance variance.
5. Expand onboarding/offboarding assignment, workflow templates and lifecycle reporting; add document retention/export controls.
6. Broaden HR reporting and reminder preferences, recipient-specific delivery status and notification audit.
7. After the web version is complete, design the employee self-service web pages and update the existing Flutter app to show role-appropriate features while preserving clock-in/out.
8. Add payroll/holiday integrations and consultant access after the core workflow, audit and permission models are stable.

## Enterprise foundations to preserve

- Keep every tenant-owned table scoped by `business_id`; verify tenant scope on every read and write, including file downloads and background jobs.
- Use roles and per-action permissions for platform administrators, client owners, HR administrators, location managers and employees. Avoid relying on hidden UI controls as security.
- Keep an immutable audit trail for administrative changes, approvals, corrections, document access and entitlement changes.
- Store employment documents on private storage, use short-lived authorized downloads, and define configurable retention/export/deletion flows.
- Support business timezone, holiday region, leave year, approval policy and document retention as explicit client settings.
- Keep the same business, user, module and permission rules across the web portal and mobile APIs. Use role-specific navigation and enforce access in backend policies/middleware as well as in the interface.
- Separate module availability from client entitlement. A module must be released and supportable before the Super Admin can enable it for a customer.
- Keep module entitlements, contract/package notes and capacity limits in the Super Admin workspace. Add billing automation only when the commercial model and payment provider are decided.
- Add migration, tenant-isolation, permission and module-gating tests for each new workflow before release. Staging/live behavior still needs verification; local tests cannot run in the current package workspace without PHP and Composer.

## Reference review

The supplied reference image groups the offer into employee management, attendance, leave, rota/shifts, compliance, reports, company notices and a holiday calendar. BritHR's public product description also presents employee lifecycle/document records, leave approvals and calendars, attendance/rota, sponsor compliance, analytics, security/audit, and advisor access. These are useful product categories; MentoClock should build its own workflow and interface around its current Laravel backend and client requirements.

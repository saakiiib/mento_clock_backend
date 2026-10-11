# MentoClock enterprise HR roadmap

## Product direction

Keep one multi-tenant product and one Super Admin console. Each client business receives its own workspace, users, locations, policies, records, and enabled module entitlements. Commercial agreements determine which live modules and capacity limits are enabled for each client.

The first implementation step is the SaaS Module Engine: catalogue, client entitlements, route/API enforcement, assignment history, and Super Admin selection. The engine is in place in this project. Employee Management and Attendance Tracking are required foundations; Dashboard & Reports is currently available as an optional module. Future modules appear as planned and cannot be assigned until their workflows have been built and verified.

## Module catalogue

| Module | Current state | Scope |
| --- | --- | --- |
| Employee Management | Available | Employee profile, workplace assignment, active status, emergency contact and employment start date. Expand with onboarding, contract/document records, role history and offboarding. |
| Attendance Tracking | Available | Mobile clock-in/out, geofence validation, attendance history, manager corrections and audit trail. Expand with break rules, timesheets and approval. |
| Dashboard & Reports | Available | Attendance reports and exports. Expand with labour cost and absence analytics when the required data and permissions exist. |
| Leave Management | Planned | Configurable annual leave years and accrual, bank-holiday treatment, request/approval workflow, balances and team calendar. |
| Rota & Shifts | Planned | Shift templates, availability, publishing, shift swaps, coverage warnings and links to attendance. |
| Compliance Tracking | Planned | Document metadata, expiry reminders, review evidence and access history. Keep sensitive files private and support retention rules. Right-to-work and sponsor records should be designed against current official guidance and reviewed before describing the product as a compliance guarantee. |
| Company Notices | Planned | Audience-targeted announcements, delivery status and optional acknowledgement records. |
| Holiday Calendar | Planned | UK nation and local-region public holidays, client-specific closures and leave-policy integration. |

Potential later modules: recruitment/onboarding workflow, training and certification, performance reviews, equipment register, employee self-service, payroll export/integrations, and HR consultant multi-client access. Payroll calculations should remain an integration or separately scoped product until tax, pension, and statutory rules are maintained and tested.

## Suggested delivery order

1. Module entitlements and per-client capacity controls (current stage).
2. Leave Management and Holiday Calendar, since policy settings and balances must agree before rota planning.
3. Rota & Shifts, integrated with existing attendance and employee branch assignments.
4. Employee document records and compliance reminders, with secure private storage and precise permissions.
5. Company Notices, acknowledgements and employee self-service.
6. Cross-module reporting, integrations and consultant access after the base workflows have stable audit and permission models.

## Enterprise foundations to preserve

- Keep every tenant-owned table scoped by `business_id`; verify tenant scope on every read and write, including file downloads and background jobs.
- Use roles and per-action permissions for platform administrators, client owners, HR administrators, location managers and employees. Avoid relying on hidden UI controls as security.
- Keep an immutable audit trail for administrative changes, approvals, corrections, document access and entitlement changes.
- Store employment documents on private storage, use short-lived authorized downloads, and define configurable retention/export/deletion flows.
- Support business timezone, holiday region, leave year, approval policy and document retention as explicit client settings.
- Separate module availability from client entitlement. A module must be released and supportable before the Super Admin can enable it for a customer.
- Keep module entitlements, contract/package notes and capacity limits in the Super Admin workspace. Add billing automation only when the commercial model and payment provider are decided.
- Add migration, tenant-isolation, permission and module-gating tests for each new workflow before release.

## Reference review

The supplied reference image groups the offer into employee management, attendance, leave, rota/shifts, compliance, reports, company notices and a holiday calendar. BritHR's public product description also presents employee lifecycle/document records, leave approvals and calendars, attendance/rota, sponsor compliance, analytics, security/audit, and advisor access. These are useful product categories; MentoClock should build its own workflow and interface around its current Laravel backend and client requirements.

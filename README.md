# MentoClock v2 — Laravel client portal and API

Start with `../START_HERE.md` for installation, SMTP, deployment and acceptance checks.

This is a separate attendance product with its own database, not an upgrade to the Tully Smiths database. Each subscribing business has an administrator and employees. Administrators create/edit employees, activate/deactivate accounts, manage branch assignments and correct attendance with an audit reason. Reports support daily, weekly and monthly periods, employee/branch filters, PDF/CSV download and email to the administrator's account address.

Laravel Sanctum provides hashed mobile bearer tokens expiring after 30 days. Employee and business activity are checked on every protected request. Password resets invalidate mobile tokens and database sessions. Employee edits and branch reassignment revoke mobile tokens; the employee signs in again. Laravel's password broker supplies expiring single-use reset links. SMTP configuration is required for delivery; the default log mailer sends no email.

## Install

```bash
composer install
cp .env.example .env
php artisan key:generate
# Set a NEW MySQL database and APP_URL in .env
php artisan migrate
php artisan mento:business
php artisan test
php artisan serve
```

Business onboarding is a terminal command; billing and a platform-wide administrator UI are outside this release. Every business can use the same hosted backend; its administrator sees only its own data.

## Data model

`businesses` own `users` and `branches`. `employee_branches` assigns multiple workplaces to an employee. `attendance_records` stores original branch, server timestamps and punch coordinates. `audit_logs` stores correction actor/reason/before/after. Composite foreign keys prevent cross-business assignments and attendance associations. `personal_access_tokens`, `password_reset_tokens` and `sessions` provide authentication. The legacy `api_tokens` table remains unused for migration compatibility with MentoClock v1. Global email uniqueness is intentional.

Reports clip records to the selected period and split elapsed seconds by business-local day; weekly periods begin Monday. Active time is counted to generation time. These are attendance totals, without breaks or payroll rules.

## Validation

The backend passed 15 feature tests with 56 assertions in PHP 8.3 using SQLite: tenant/role restrictions, geofences, repeated punches, edit and token revocation, overnight/DST reporting, PDF/CSV generation, report email attachment/recipient construction and password resets. Email tests use an in-memory mail transport, not external SMTP. Test MySQL concurrency and real SMTP delivery on the deployment environment.

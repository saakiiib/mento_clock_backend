# V32 client account editing and module request page

## Client administrator

Client administrators on shared client accounts can open **Account settings** from the workspace menu. They can update the client account name and shared contact details, their own login name, email and phone, or change their password by entering the current password and confirming the new one. Account contact details are synchronised to linked business profiles. Changing login details signs out other sessions and revokes the administrator's app tokens; the current browser session stays active when the client changes their own details.

Business names, workspace and employee limits, module access, and employee/business records are managed separately and are not editable from Account settings.

## Super Admin

The client account list and account detail page now link to **Edit client account**. Super Admin can update the shared contact information, the primary client administrator's name/email/phone, and optionally set a new password. Updating the primary login revokes that administrator's active sessions and app tokens. Each change is recorded in the platform audit log.

## Module requests

The Super Admin Module requests page uses a plain PHP outer iteration around the request list to avoid the reported Blade `endforeach` compile error. The page handles both an empty queue and populated requests. A feature test covers loading the page with no requests.

## Deployment

V32 introduces no database schema changes. Deploy the complete backend package, then clear Laravel's compiled views and caches:

```sh
php artisan optimize:clear
```

Run the existing test suite in the deployment environment:

```sh
php artisan test
```

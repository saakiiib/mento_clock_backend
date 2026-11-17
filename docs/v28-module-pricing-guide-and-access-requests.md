# V27: module pricing and access requests

V27 adds per-business module pricing and a client request/approval workflow. It does not replace or clear existing business, employee, branch or module records.

## Client portal

- Business administrators open **Plans & modules** from the left menu.
- The core plan shows clock-in/out, employee management and attendance reports. Its starting price is £15/month per business and includes one branch and up to five employees; Super Admin can change these values.
- Add-on prices are configured as a monthly amount per business or per active employee. A blank price is shown as “Price on request”.
- A client can request a trial or paid access for the currently selected business. The request does not activate access. Switching businesses from the header shows that business's own modules and requests.
- Checkout and recurring billing are not connected. A paid request is a request for a quote/agreement, and no payment is taken by this portal.

## Super Admin

- **Module pricing** manages the core price, included capacity and each add-on price/basis.
- **Module requests** lists client requests. Approving a trial requires an end date; the module is enabled only for that business and expires at the end of that date. Approving paid access requires the administrator to confirm the payment arrangement and is manually assigned.
- Declining a request does not change existing module access.
- Price changes and request decisions are added to the platform audit log.

## Super Admin module guide

- **Module guide** in the Super Admin menu explains every module with its purpose, preparation checklist, suggested demo steps, client-facing explanation and the corresponding client workspace route.
- The guide reflects the live module catalogue and labels modules that are not yet available.
- Demonstrations should use sample records in the client workspace. Super Admin access does not impersonate a client.

## Deployment

After uploading the full Laravel project, run `php artisan migrate --force` and `php artisan optimize:clear`. V27 adds one migration for module pricing, access type and request records. No manual database edits are required.

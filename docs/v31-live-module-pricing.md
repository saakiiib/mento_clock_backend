# V31: live module prices in the client portal and public website

V31 adds transparent monthly prices to the client **Plans & modules** page and the public homepage. Both pages read the Super Admin catalogue, so a price edited in **Platform → Module pricing** is shared in both places. Existing business, employee, branch, module access and request records are preserved.

## Pricing model

- Core plan is priced per business. It includes the configured number of branches and employees (default: one branch and five employees). Super Admin can set the core monthly price and the monthly top-up for each additional active employee.
- Add-ons can have a starting monthly price covering the included employee limit, plus a separate monthly price for each additional active employee.
- A fixed add-on can instead use one monthly price per business. Roles & Permissions uses this model by default.
- V31 initial add-on values follow the low-cost pricing proposal previously discussed. The new migration fills only unset prices, carries existing V30 catalogue prices forward, and leaves all rates editable.
- Prices are estimates in GBP per month, before applicable VAT. Extra branch capacity and final terms are confirmed by quote.

## Client experience

- The selected business sees its current estimated core price, employee count and every module’s starting price / top-up price.
- Add-on prices reflect that business’s active employee count.
- Module request/approval remains unchanged. A paid request is not a checkout and no payment is taken; Super Admin reviews it and confirms the agreement before activating access.
- Modules not yet available are marked **Coming soon** while their catalogue price remains visible.

## Public homepage

- The pricing section now shows the core plan and all add-on module prices from the same live catalogue.
- The page explains the employee top-up rule and offers the existing trial request and WhatsApp quote links.
- Existing trial, contact and client portal flows remain in place.

## Deploy

Upload the complete Laravel project and run `php artisan migrate --force`, then `php artisan optimize:clear`. The V31 migration only adds pricing columns and sets introductory rates where the catalogue has no price; it does not delete business data or replace configured prices. Test both `/` and an authenticated `/modules` page after deployment.

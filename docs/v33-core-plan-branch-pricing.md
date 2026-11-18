# V33 core plan pricing

## Core package

The client portal and public pricing section present Employee Management, Attendance Tracking and Dashboard & Reports together as one Core plan.

The default core price is £15 per business per month for one branch and up to five employees per branch. Each additional active branch adds £12/month and includes up to five more employees. An employee charge applies only to employees above the combined branch allowance, at the configured extra employee rate. Add-on module prices remain separate.

The calculated client amount is a monthly estimate. Super Admin can record a 0–100% core plan discount on each business profile; that discount only affects that business's core plan. For MK Sushi Bar, five active branches with a 50% discount calculate to £31.50/month before any VAT.

## Module requests

The client portal continues to allow add-on trial requests. The paid-access request button is hidden while checkout is not available. A paid request, if submitted through the API by another client, still only creates a pending Super Admin review record; it does not take a payment or activate access.

## Deployment

V33 adds `core_extra_branch_price_pence` to `module_pricing_settings` and `core_discount_percent` to `client_profiles`. Apply database migrations before opening the pricing pages:

```sh
php artisan migrate --force
php artisan optimize:clear
```

The new migration defaults extra branch pricing to £12/month and business-specific discounts to 0%.

# V35: add-on prices by branch

## Client display

Tiered add-on cards now show the base amount per active branch and the employee allowance per branch. They also show the extra employee rate and an estimate using employee assignments at each active branch. Fixed-price add-ons remain priced per business.

Example: Leave Management at £1.50 per branch, including five employees per branch, plus £0.30 per additional employee. If two branches have seven and three employees, the estimate is £3.60/month.

The module page clarifies that the displayed tiered estimate covers all active branches. If an agreement covers only selected branches, confirm that scope and price with Mento Software before enabling the module. Module access remains assigned at business level in this release.

## Deployment

This release changes the Laravel view and the add-on estimate query. It has no new database migration. After deployment, run `php artisan view:clear` and `php artisan optimize:clear`.

The feature test `test_tiered_add_on_price_uses_each_active_branch_capacity` covers the branch calculation. Run the full Laravel test suite in the deployment environment.

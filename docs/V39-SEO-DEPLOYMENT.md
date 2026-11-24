# V39 – SEO & Website Growth

Based on the supplied V38-era Laravel project. New public pages and SEO management are isolated from employee, attendance, API and support workflows. No production credentials or database export included.

## Deployment (Sakib)
1. Back up the production database and current application files. Test on staging with a separate database first. Preserve the production `.env`, `storage`, public uploads and hosting configuration.
2. Merge the changes or deploy the supplied full project using the established release process. Run `composer install --no-dev --optimize-autoloader` if dependencies need installing. Do not run `migrate:fresh`, `db:wipe` or reseed production.
3. `php artisan optimize:clear` then `php artisan migrate --force`. The migration creates two SEO tables and inserts 13 starting pages; it updates the homepage wording only where existing values match known defaults. Customised copy is preserved. Review the default public pages before promoting the staging release.
4. Delete the previous static `public/robots.txt` if it survives an overlay deployment. Apache rewrite included; on Nginx ensure `/robots.txt` and `/sitemap.xml` fall through to Laravel rather than a stale static file. Public web root should be Laravel `public/`. Keep the established server access controls.
5. `php artisan view:cache`, `php artisan route:cache` if part of your established deployment, `php artisan config:cache`. Confirm correct `APP_URL`, public website URL and HTTPS. Restart PHP OPcache if needed.
6. Verify `/`, `/features`, `/industries`, `/blog`, `/features/gps-clock-in-app`, `/sitemap.xml`, `/robots.txt`, `/favicon.ico`, `/login` and `/platform/website/seo`. Test the homepage trial form, a trial CTA from a new page, employee app clock-in/out and existing platform assistance workflow. Confirm platform support operators and business users cannot open the SEO manager.
7. On staging, install dev dependencies and run `php artisan test --filter=WebsiteSeoTest` plus the existing regression suite. Use a test database. Verify metadata and schema with Google's Rich Results Test, mobile layout and PageSpeed Insights. The SoftwareApplication schema does not claim ratings or offers; rich result eligibility is not guaranteed.
8. Verify Search Console using DNS (domain property) or the HTML verification token field for a URL-prefix property. Submit the sitemap and inspect key URLs. Indexing and ranking are decisions made by Google.

## Admin workflow
Super Admin > SEO & growth (`/platform/website/seo`). Homepage settings, discovery lists, drafts, publish/noindex, canonical overrides, social metadata, FAQs, internal links and redirects. Page type determines the URL prefix. The editor intentionally accepts plain text: separate sections with a blank line, heading on first line and paragraph after it; FAQ format `Question | Answer`. Custom social images reference existing local public assets only. Renaming a slug makes a scoped 301 redirect; unpublishing returns 404. Redirects only operate within the three SEO namespaces, never login, API or workspace routes. Changes are audited in existing platform audit logs.

## Tracking and privacy
No GA4, GTM, Meta Pixel or Google Ads tracking is enabled in this release. The existing website privacy and cookie notice explicitly say it has no analytics/advertising trackers. Before adding any optional trackers, implement an appropriate consent mechanism, update the privacy/cookie disclosures and supply verified tracking IDs. Search Console verification does not require a visitor tracking script.

## Validation limits
PHP source syntax was checked in the build environment. Composer/vendor and the required PHP extensions/database driver were unavailable, so Laravel migrations, Blade compilation, HTTP tests and visual browser checks could not be executed here. Treat as a staging candidate, not a verified production release.

## Rollback
Restore the pre-release application and database backup if necessary. The migration down method drops new SEO tables and preserves homepage copy; it is destructive to new SEO pages/redirects. Do not roll it back after content editing without backing up those records. Restore the old robots file if rolling back application code.

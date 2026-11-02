# Propeller Logistics

Laravel 13 application for consignment billing and shipment tracking.

## Run locally

Requires PHP 8.3+, Composer and PDO SQLite (or PDO MySQL).

```sh
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan logistics:admin admin@example.com --name="Administrator"
php artisan serve
```

The administrator command prompts for a password; no default account is seeded. Sign in at `/login`. Every user can manage their photo and password under **My profile**; the login email is read-only and profile submissions cannot change it. The login page includes **Forgot password?**. Customers can track consignments from `/` without an account using either the exact airway bill number or bill reference. The embedded tracking widget accepts both identifiers as well. Admins create staff accounts and download SQL backups. Staff can manage customers, vendors, airway bills and shipment updates.

Company name, address, logo and GSTIN are managed by administrators under **Settings**. Saved details appear on airway bills and printed copies. Configure SMTP and its sender address under **Settings → Email settings**, enable delivery, and set the full application base URL (including `/propeller` when used) so password-reset links reach the correct website. Blank SMTP password fields preserve the saved password. SMTP passwords are encrypted with `APP_KEY`; retain that key alongside encrypted backups. Without enabled SMTP or an existing delivery mailer, reset emails are unavailable and no reset links are logged. Timezone is configured with `APP_TIMEZONE` in `.env`. For MySQL, set `DB_CONNECTION=mysql` and the corresponding `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` values before migration. The web server document root must be `public/`.

## Workflows

- Register an airway number with a vendor and route under **Airway numbers**; a bill is optional. Publish tracking events from its detail page.
- Create an airway bill with customer, vendor, consignee, package, weight and charge details. A unique `REF-…` reference is generated automatically when saved; it appears on the bill and in list searches. Existing references are retained, and bills with missing references receive one during migration. An existing registered number can be used with the same vendor and route.
- Administrators manage bill terms under **Settings → Template settings**. New bills save the current terms; older bills without saved terms use the configured template. Terms print with their original line breaks.
- Shipper name, address, phone and ZIP/postcode are entered on each bill, with optional prefilling from the customer record. Both shipper and consignee phone numbers and ZIP/postcodes are required for new bills. Customer records can store an optional postcode for prefilling.
- Bills retain shipper details as recorded at creation. Chargeable weight must cover actual and volumetric weight. Totals use freight, other charges and tax; declared value is separate.
- Use **Print / save PDF** on the bill page for the browser print dialog.
- Public tracking shows route, vendor and published events, without recipient addresses, phone numbers or charges. Tracking updates are entered by staff; no carrier API integration is configured.
- Admin SQL downloads use the configured database dialect. Restore to a separate database using the matching database client. The dump includes account password hashes and must be stored privately. Company settings are included in SQL backups; uploaded logo files, other application files and `.env` are not. Back up `storage/app/private/company-logos` and `storage/app/private/profile-photos` separately to retain uploaded images.

## Lists and delivered archives

Workspace record lists and internal movement histories default to 10 rows per page, with 25, 50 and 100 options. Search and filters remain in pagination links. Customer, vendor and staff lists support text, contact and creation-date filters; booking options support status and creation-date filters.

Bills whose latest effective movement is **Delivered** appear in **Archived data** instead of the active dashboard, airway-bill and airway-number lists. Existing delivered consignments are included automatically. Records, PDFs and public tracking remain available. Movement ordering uses event time, then record ID for equal times; a later status correction can return a consignment to the active lists. Archive filters include airway number, customer, vendor, origin, destination, service, bill-date range and delivery-date range.

## Website integration

Administrators can copy the tracking iframe code from the dashboard or **Settings → Website integration** and paste it into an external website’s HTML block. The app must be reachable from that website’s visitors; for a public HTTPS website, use a public HTTPS app URL and generate the snippet while accessing that URL. The embedded form stays inside its frame and displays only public route, vendor and movement data. It never includes workspace navigation, recipient contact details or billing charges, even when the viewer is signed in.

The uploaded company logo brands the workspace and tracking widget. Navigation groups expand and collapse, with the current section opened automatically.

## Tests

```sh
php artisan test
vendor/bin/pint --format agent
```

Feature tests cover login/logout, role restrictions, customer/vendor updates, independent airway registration, public tracking privacy, billing totals, validation, historical shipper snapshots, and SQLite SQL restore including unique indexes. MySQL backup code is included but has not been integration-tested against a MySQL server.

Before production, configure HTTPS, `APP_ENV=production`, `APP_DEBUG=false`, company details and your production database. Use secure session cookies and keep backups outside the public web root.

# eSerbisyo Hub

eSerbisyo Hub is a municipal service request system for citizen-facing kiosks and staff-side request processing. Citizens can request documents or renewals, receive a reference number, and track request progress. Staff users can review requests, update statuses, verify payments, send readiness notifications, release documents, manage accounts, archive completed records, and export released records.

The project uses a PHP API with a MySQL database, static HTML/CSS/JavaScript frontends, and Composer for PHP dependencies.

## Main Features

- Kiosk interface for document and renewal requests
- Request tracking by reference number
- Staff login and role-based dashboard
- Request status workflow from queued to released
- Payment verification and release handling
- Staff account management
- Archive toggle for released records
- Excel export for released records
- Basic accessibility preference scripts

## File Structure

```text
eserbisyo-hub/
|-- README.md
|-- .env
|-- .env.example
|-- .gitignore
|-- composer.json
|-- composer.lock
|-- eserbisyo_hub.sql
|-- api/
|   |-- config/
|   |   `-- db.php
|   |-- middleware/
|   |   |-- auth_staff.php
|   |   |-- cors.php
|   |   |-- csrf.php
|   |   `-- require_role.php
|   |-- public/
|   |   |-- health.php
|   |   |-- requests/
|   |   |   |-- create.php
|   |   |   `-- track.php
|   |   `-- staff/
|   |       |-- accounts_delete.php
|   |       |-- accounts_list.php
|   |       |-- accounts_register.php
|   |       |-- accounts_reset_password.php
|   |       |-- accounts_toggle.php
|   |       |-- archive_toggle.php
|   |       |-- get_request.php
|   |       |-- list_requests.php
|   |       |-- login.php
|   |       |-- logout.php
|   |       |-- me.php
|   |       |-- release.php
|   |       |-- send_ready_sms.php
|   |       |-- stats.php
|   |       |-- update_status.php
|   |       `-- verify_payment.php
|   `-- services/
|       |-- reference_no.php
|       |-- response.php
|       `-- validators.php
|-- client/
|   |-- kiosk/
|   |   |-- assets/
|   |   |   |-- css/
|   |   |   |   `-- kiosk.css
|   |   |   |-- img/
|   |   |   |   |-- eserbisyo-lockup-light.png
|   |   |   |   `-- icons/
|   |   |   |       |-- copy.png
|   |   |   |       |-- credit-card.png
|   |   |   |       |-- document.png
|   |   |   |       |-- location.png
|   |   |   |       |-- magnifying-glass.png
|   |   |   |       |-- pocket-watch.png
|   |   |   |       |-- store.png
|   |   |   |       |-- sync.png
|   |   |   |       `-- verified.png
|   |   |   `-- js/
|   |   |       `-- track.js
|   |   `-- pages/
|   |       |-- index.html
|   |       |-- municipal-map.html
|   |       |-- request-step1.html
|   |       |-- request-step2.html
|   |       |-- request-step3.html
|   |       `-- track.html
|   `-- staff/
|       |-- assets/
|       |   |-- css/
|       |   |   `-- staff.css
|       |   |-- img/
|       |   |   |-- eserbisyo-lockup-light.png
|       |   |   `-- eserbisyo-mark-light.png
|       |   `-- js/
|       |       |-- a11y-prefs.js
|       |       |-- a11y.js
|       |       |-- accounts.js
|       |       |-- auth.js
|       |       |-- dashboard.js
|       |       |-- login.js
|       |       `-- request_detail.js
|       `-- pages/
|           |-- accounts.html
|           |-- dashboard.html
|           |-- login.html
|           `-- request_detail.html
|-- docs/
|   `-- Logo/
|       |-- eserbisyo-lockup-dark.png
|       |-- eserbisyo-lockup-light.png
|       |-- eserbisyo-mark-dark.png
|       `-- eserbisyo-mark-light.png
`-- vendor/
    `-- Composer dependencies
```

## Important Files

- `eserbisyo_hub.sql` contains the database schema and seed/sample data.
- `.env.example` shows the required database environment variables.
- `api/config/db.php` loads environment variables and creates the PDO database connection.
- `api/public/requests/create.php` handles kiosk request submission.
- `api/public/requests/track.php` handles public request tracking.
- `client/kiosk/pages/index.html` is the kiosk landing page.
- `client/staff/pages/dashboard.html` is the main staff dashboard.

## Local Setup Note

This project is intended to run inside XAMPP under `htdocs`. Import `eserbisyo_hub.sql` into MySQL, copy `.env.example` to `.env`, update the database credentials, and install Composer dependencies with:

```bash
composer install
```

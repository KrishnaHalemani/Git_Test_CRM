# CRM Pro — Code Index
# CRM Pro — Code Index (generated)

This document is a navigable index of the CRM_InfiniteVision (CRM2) repository. It highlights entry points, key backend files, API endpoints, and next steps. It was updated to reflect the repository state as of the latest scan.

## Project entry points

- `login.php` — Authentication page (POST username/password). Uses `authenticateUser()` from `db.php`. Redirects to `dashboard_advanced.php` on success.
- `dashboard_advanced.php` — Main dashboard page for logged-in users. Presents charts and calls `getLeads()` and `getDashboardStats()` from `db.php`.
- `leads_advanced.php` — Lead management UI (DataTables). Handles add/update/delete/bulk actions via POST `action` param that calls `addLead()`, `updateLead()`, `deleteLead()`.
- `index.php` — Public contact/lead capture page (form posts to `submit-lead.php`).
- `submit-lead.php` — Form handler that builds lead data and calls `addLead()`; redirects to `thank-you.html`.
- `export.php` — Export UI and direct-download endpoint (CSV/JSON). If `download=1` it emits CSV or JSON using `getLeads()`.
- `setup.php` — Setup/check page instructing how to create DB and import `database_schema.sql`.
- `logout.php` — Destroys session and redirects to `login.php`.

## Core backend / utilities

- `db.php` — Central DB layer and data-access helpers.
  - Connection logic: attempts PDO (preferred) then mysqli fallback. Globals: `$conn`, `$pdo`, `$db_type`, `$db_error`.
  - Authentication: `authenticateUser($username, $password)` — verifies password hash, updates `last_login`.
  - Lead operations: `getLeads($user_id = null, $role = null)`, `addLead($lead_data)`, `updateLead($id, $lead_data)`, `deleteLead($id)`, `getLeadById($id)` (placeholders exist for some functions).
  - Dashboard stats: `getDashboardStats()` — returns counts and monthly data placeholder.
  - Settings helpers: `getServices()`, `getSetting()`, `setSetting()`.

- `Task.php` — Exists but empty (placeholder).
- `test_db.php` — Simple script to verify DB connectivity and list users.

## Database schema

File: `database_schema.sql` — creates `crm_pro` DB and these tables (main ones listed):

- `users` (id, username, email, password_hash, full_name, role, status, created_at, last_login)
- `leads` (id, name, email, phone, company, service, status, source, priority, assigned_to, created_by, notes, follow_up_date, conversion_date, estimated_value, created_at)
- `lead_activities` (id, lead_id, user_id, activity_type, title, description, activity_date)
- `companies` (company details)
- `services` (id, name, description, base_price, is_active)
- `settings` (key/value store)

Plus
- Views: `lead_summary`, `dashboard_stats` for reporting.

Default seed data includes 3 users (`superadmin`, `admin`, `user`) and several `services` and `settings`.

## Static/UI files

- `dashboard_advanced.php`, `leads_advanced.php`, `export.php`, `index.php`, `login.php`, `setup.php`, `thank-you.php` — primary front-end pages with Bootstrap/Chart.js/DataTables.
- JS/CSS mostly loaded via CDNs (Bootstrap, FontAwesome, Chart.js, DataTables).

## Routes / form actions (summary)

- Contact form (on `index.php`) -> POST to `submit-lead.php` -> calls `addLead()` -> redirects to `thank-you.html`.
- Login form (on `login.php`) -> POST -> `authenticateUser()` -> sets `$_SESSION` keys: `user_id`, `username`, `role`, `full_name`.
- Leads management (`leads_advanced.php`) -> POST with `action` values: `add`, `update`, `delete`, `bulk_update`.
- Export (`export.php`) -> GET with `download=1&format=csv|json` to trigger file download.

## Important files to review first
## Public entry points (user-facing pages)

- `login.php` — Authentication page (POST username/password). Uses `authenticateUser()` in `db.php`. Redirects by role to:
  - `superadmin_dashboard.php` (superadmin)
  - `dashboard_advanced.php` (admin)
  - `user_dashboard.php` (user)
- `index.php` — Public lead capture / landing page. Posts to `submit-lead.php`.
- `submit-lead.php` — Accepts form POST and creates a lead via `addLead()`.
- `export.php` — Data export; supports `download=1` and `format=csv|json`.
- `setup.php` — Setup helper and checks for DB connectivity.
- `logout.php` — Clears session and redirects to `login.php`.

## Dashboards and admin pages

- `superadmin_dashboard.php` — Superadmin analytics and management UI (charts, admin/user lists, CRUD modals). NOTE: file was recently recreated to fix corruption; it now renders charts and tables but some form actions post to `admin_actions.php` and `settings_actions.php` which should be verified.
- `dashboard_advanced.php` — Admin dashboard used by non-superadmin users.
- `user_dashboard.php` — User-facing dashboard (quick lead lists, basic stats).
- `leads_advanced.php` — Lead management UI (DataTables) with add/update/delete and bulk actions.

## API endpoints (under `/api`)

- `api/receive_lead.php` — Endpoint for external lead reception (webhooks).
- `api/dashboard_stats.php` — Returns JSON stats used by charts.
- `api/file.php` — File upload/serve helper.
- `api/get_user_leads.php` — Returns leads for a specific user (used by front-end filters).
- `api/meta_webhook.php` — Integration endpoint for meta/webhook flows.

## Core backend

- `db.php` — Central data-access layer and helper functions. Key points:
  - Establishes DB connection (prefers PDO, falls back to mysqli). Exposes globals like `$pdo`, `$conn`, `$db_type`.
  - Authentication: `authenticateUser($username, $password)` — verifies password (bcrypt), sets last_login.
  - Role enforcement helper: `require_role($allowed_roles)` used at top of protected pages.
  - Data helpers: `getLeads()`, `getAdmins()`, `getAllUsers()`, `getDashboardCounts()`, `getDashboardStats()`, `getRecentActivities()` and CRUD helpers.
  - Several helper functions handle both PDO and mysqli branches; consolidating to PDO is recommended for clarity.

- `admin_actions.php` — Action handler for admin CRUD (add/edit/delete). Verify this file exists and handles POST actions from `superadmin_dashboard.php`.
- `settings_actions.php` — Handles settings save operations (company name, email, phone).

## Components & smaller pieces

- `components/import_modal.php` — Modal UI include for CSV import.
- `Task.php` — Placeholder (class present but minimal content).

## Database schema and fixtures

- `database_schema.sql` — Full schema for tables: `users`, `leads`, `lead_activities`, `companies`, `services`, `settings`, plus reporting views.
- `task_manager_schema.sql` — Schema for task manager submodule.

## Tests and utilities

- `test_all_functionality.php` — Script used to validate DB connectivity, test users, and login flows.
- `test_auth.php`, `test_db.php`, `test_user.php` — Small verification scripts for troubleshooting.

## Notable files to review first (priority)

1. `db.php` — central; changing it affects all flows.
2. `superadmin_dashboard.php` — UI was recreated; verify `admin_actions.php` and `settings_actions.php` backends to restore CRUD.
3. `dashboard_advanced.php` and `user_dashboard.php` — verify they still route correctly and their includes.
4. `api/` endpoints — ensure webhook and lead receive endpoints are functioning.

## Known / recommended next steps

- Verify `admin_actions.php` and `settings_actions.php` exist and correctly handle POSTs from the dashboard; implement missing handlers.
- Add sample `companies` rows to populate branch dropdowns used by modals.
- Consider consolidating DB access to PDO-only and adding a small test harness for core functions (`authenticateUser`, `getLeads`, `getDashboardStats`).
- Add a small developer script `bin/index-search.php` that produces a symbol index (function → file) for faster code navigation.

## How to use this index

1. Open `db.php` to confirm DB credentials ($host, $user, $pass, $db) and the `$db_type` branch. Fix if connecting to a different local MySQL socket.
2. Start with `login.php` to exercise auth flow; use test scripts under `test_*.php` to validate programmatically.
3. Inspect `superadmin_dashboard.php` and then `admin_actions.php` to trace CRUD flow and restore missing server-side handlers.

---

If you'd like, I can now:
- create or update a JSON search index mapping functions to files,
- implement missing handlers in `admin_actions.php` and `settings_actions.php`, or
- add a small `bin/index-search.php` script to regenerate this index on demand.

Generated on index run.

## Quick notes & potential improvements

- Passwords are verified using PHP `password_verify()`; seed password hashes are present in schema.
- `db.php` includes placeholder functions (`updateLead`, `deleteLead`, `getLeadById`, `getUserById`) that currently return simple values — review and implement DB-backed versions if needed.
- `Task.php` is empty — remove or implement.
- Several pages reference `*.html` in redirects (`thank-you.html`, `index.html`) while project has `thank-you.php` and `index.php` — check for mismatches and update redirects.
- Consider consolidating DB API to PDO-only to reduce conditional code paths.

## How to use this index

- Open `db.php` first to inspect/adjust DB credentials and data functions.
- Use `database_schema.sql` to recreate the schema locally: import into MySQL (`mysql -u root -p crm_pro < database_schema.sql`).
- Start by logging in via `login.php` (default credentials in README/setup). Then inspect `dashboard_advanced.php` and `leads_advanced.php` flows.

---

Generated automatically on index run. If you'd like, I can:
- Add a more detailed symbol list (every function and file line references),
- Create a simple grep-style search index (JSON) for quicker lookups,
- Replace placeholder functions in `db.php` with full implementations and add basic unit tests.


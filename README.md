# Bus Owner Control System

A PHP and MySQL operations and profit-management system for bus companies, designed around Tanzanian bus-company workflows: trips, seat bookings, parcels, expenses, cash closing and owner reports.

## Features

- Trips, passenger seat bookings and parcel tracking, with receipts
- Expenses, fuel and maintenance records, daily cash closing and reconciliation
- Owner dashboard and filtered business reports with CSV export and printing
- Role-based access for Owner, Manager, Accountant, Booking Agent and Parcel Clerk
- Notifications, backups and company branding
- Package tiers and licence limits for selling the system to several companies (SaaS-ready)

## Security highlights

- Passwords stored with `password_hash`, and PDO prepared statements for database queries
- CSRF tokens on state-changing forms, and a 30-minute idle session timeout
- Role-based permission checks on protected pages
- Audit log recording before/after snapshots of changes, with sensitive fields hidden
- New accounts receive a random one-time password instead of a shared default
- Database credentials can be supplied as environment variables

## Tech stack

PHP 8 · MySQL · PDO · HTML · CSS · JavaScript · Apache (XAMPP)

> **Demo data.** `database/sample_data.sql` contains invented demo companies, people and phone numbers only. The demo login below is for local testing; change it immediately on any real installation.

---

## Installation and version notes

Version: **2.5.0 SaaS Package/Licence Ready**

This is a PHP + MySQL bus company operations and profit management system built for Tanzanian-style bus-company workflows. Phase 1 focused on owner control, trips, passenger seats, parcels, expenses, reports, audit logs, and role access. Phase 2 added receipts, cash closing, reconciliation, maintenance, fuel, exports, notifications, backup, and branding. Phase 2.5 makes the system **package/licence ready** so you can sell it with one-time setup packages and optional monthly support.

## Default login

Email: `admin@buspro.local`

Password: `Admin123!`

Other demo accounts may exist when sample data is imported: `manager@buspro.local`, `accountant@buspro.local`, `agent@buspro.local`, and `parcel@buspro.local`.

## Fresh installation on XAMPP

1. Copy the `bus_owner_control_system` folder into `C:\xampp\htdocs\`.
2. Open phpMyAdmin.
3. Create a database named `bus_owner_control`.
4. Import `database/schema.sql`.
5. Optional: import `database/sample_data.sql`.
6. Open `http://localhost/bus_owner_control_system/public/`.

## Upgrade from Phase 2 / v2.0

Back up your database first, then import:

`database/migrations/phase2_5_saas_package_upgrade.sql`

This adds package tiers, licence fields, one-time setup pricing, optional monthly support fields, feature gates, and limit columns without deleting your existing Phase 2 data.

If your database is older than Phase 2, import migrations in this order:

1. `database/migrations/phase1_1_role_access.sql`
2. `database/migrations/phase1_2_reports_audit_upgrade.sql`
3. `database/migrations/phase2_operations_upgrade.sql`
4. `database/migrations/phase2_5_saas_package_upgrade.sql`

## Package model added

The system now supports four selling packages:

| Package | One-time setup | Optional monthly | Main idea |
|---|---:|---:|---|
| Starter Local | TZS 1,500,000 | TZS 150,000 | Small single-office bus company |
| Business Cloud | TZS 2,500,000 | TZS 300,000 | Growing company with branches, parcels, and cash control |
| Professional Multi-Branch | TZS 5,000,000 | TZS 600,000 | Serious multi-branch operator with exports, backup, branding, and full Phase 2 controls |
| Enterprise Custom | TZS 12,000,000+ | TZS 1,000,000+ | Large operator/custom deployment/integrations |

The monthly fee is not compulsory. It can be used for support, hosting, backups, updates, data entry, WhatsApp/SMS setup, staff training, or extra operational assistance.

## SaaS/licence features added in v2.5

- Package tiers table
- Packages & Licence page
- One-time setup fee control
- Optional monthly support fee control
- Licence status: trial, active, expired, suspended
- Support status: off, active, paused, expired
- Feature gating by package
- Limits by package:
  - users
  - branches/agents
  - buses
  - routes
  - trips per month
- Package usage summary on dashboard
- Operational blocking when licence is expired/suspended
- Owner/admin can still access Packages & Licence when licence is inactive
- Audit logs for package changes and blocked feature/limit attempts

## Important production model

This version is designed for a safe **one bus company per deployment/database** model. That is the safest way to sell this to Tanzanian bus companies first because each client’s data remains isolated.

For a full multi-tenant SaaS where many bus companies share one database, the next step would be adding `company_id` to every business table and filtering every query by company. Do that later only when you have real paying clients and hosting discipline.

## Phase 2 features retained

- Ticket print/download page
- Parcel receipt print/download page
- Daily cash closing
- Agent/branch reconciliation
- Maintenance reminders and records
- Fuel consumption tracking
- CSV exports for reports, cash closings, reconciliation, maintenance, and fuel
- Professional report export through the clean report page
- WhatsApp notification center with notification logs
- Manual SQL backup download with backup logs
- Company branding/settings for receipts and reports
- Dashboard additions: cash closing status, fuel this month, maintenance due, licence status, and simple revenue trend
- Audit logs for user actions

## Important positioning

This is not meant to replace LATRA-approved public ticketing systems. It is designed as a bus company internal control system for owners, managers, accountants, ticket agents, parcel officers, and branch offices.

## Recommended testing checklist

1. Import the v2.5 migration or fresh schema.
2. Login as owner.
3. Open Packages & Licence.
4. Switch package to Starter and confirm advanced features disappear/block.
5. Switch package back to Professional and confirm Phase 2 features return.
6. Try creating users/buses/branches beyond package limits.
7. Print a passenger ticket from Passenger Seats.
8. Print a parcel receipt from Parcels.
9. Create a daily cash closing.
10. Check Agent Reconciliation.
11. Add maintenance and fuel records.
12. Export CSV files.
13. Open WhatsApp notification links.
14. Download a backup if package allows backup.
15. Check Audit Logs to confirm actions were recorded.
16. Login as agent/parcel officer and confirm branch restrictions still work.

## Notes

- WhatsApp messages open through WhatsApp links. No paid SMS gateway is required in this version.
- Browser print dialog can be used to save tickets, parcel receipts, and clean reports as PDF.
- Before selling to a real client, change all demo passwords, configure company settings, and host it with HTTPS.

### v2.5.1 SaaS missing tables repair
If you see `SaaS setup warning: Missing table: package_tiers | Missing table: subscription_license | Missing table: audit_logs`, select the database in phpMyAdmin and import:

`database/migrations/phase2_5_1_saas_missing_tables_repair.sql`

This repair creates the missing SaaS package/licence/audit tables and upgrades old Phase 2 tables safely.

## v2.5.2 SaaS warning repair

If the system still says:

`SaaS setup warning: Missing table: package_tiers | Missing table: subscription_license | Missing table: audit_logs`

open this page in the browser:

`http://localhost/bus_owner_control_system/public/saas_repair.php`

Click **Run SaaS Repair Now**. This creates/repairs the SaaS tables inside the exact database used by `app/config.php`.

After repair succeeds, refresh the main system with **Ctrl + F5**. Delete `public/saas_repair.php` before deploying to a real/live server.

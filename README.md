# Bus Owner Control System

A management and profit-control system for bus companies, designed around how Tanzanian bus operators work: trips and seat bookings, parcels, fuel, maintenance and expenses, daily cash closing, and owner reports. It is built so one installation can be sold to several companies on different packages.

## How it works

```
Routes + buses + drivers ──► Trip ──► seat bookings (fares) ──► passenger manifest / tickets
                                 └──► parcels (fees, tracking code)
                                                │
Expenses, fuel, maintenance ───────────────────┤
                                                ▼
              Daily cash closing:  expected = fares + parcel fees − expenses paid
                                   counted  = cash + mobile money + bank
                                   variance = counted − expected  ──► owner reports
```

**No double-booked seats.** Before a booking is saved, the system checks that no other non-cancelled booking on the same trip holds that seat.

**Cash closing catches missing money.** At the end of the day each branch enters the cash, mobile money and bank amounts it actually holds. The system works out what it *should* hold from that day's fares and parcel fees, minus expenses paid, and shows the difference. Any shortfall is visible to the owner straight away.

**Packages and limits (SaaS).** Four package tiers (Starter Local, Business Cloud, Professional Multi-Branch and Enterprise Custom) switch features on and off and set limits, for example on the number of users. Pages check `require_feature()` and `require_limit_available()` before they run.

## Security design

- **Six roles with the least access needed:** Owner, Admin, Manager, Accountant, Ticket Agent and Parcel Officer. Each page checks the role on the server, so finance pages, user management and reports are closed to roles that don't need them.
- **Branch isolation:** ticket agents and parcel officers only see their own branch's bookings and parcels. Every query they run is filtered by their branch, and opening another branch's record by its ID is refused and logged.
- **Audit trail:** logins (successful and failed), access-denied events, idle timeouts and every change are recorded with the user, the record, and the values before and after. Passwords and tokens are automatically replaced with `[redacted]` before anything is written to the log.
- **CSRF protection:** a 32-byte random token is checked on form submissions with `hash_equals`; a failed check returns HTTP 419.
- **Sessions:** a new session ID at login, and logout after 30 minutes of inactivity.
- **Database access:** PDO with real prepared statements (emulated prepares turned off) and exceptions on errors.
- **No shared default password:** new users receive a random one-time password. Database credentials can come from environment variables.

## Database design

| Area | Tables |
|---|---|
| Operations | `routes`, `buses`, `drivers`, `trips`, `bookings`, `parcels`, `agents` (branches) |
| Money | `expenses`, `fuel_logs`, `maintenance_records`, `cash_closings` |
| Control | `users`, `audit_logs`, `notification_logs`, `backup_logs`, `company_settings` |
| Packages | `package_tiers`, `subscription_license` |

Upgrades are applied with the SQL files in `database/migrations/`, which add features without deleting existing data.

## Project structure

```
app/config.php        Settings (database details can come from environment variables)
app/core/             auth.php (roles, branch isolation, audit), csrf.php, db.php, saas.php (packages), reports
app/views/            Page layout and printable report template
public/               One page per module: trips, bookings, parcels, cash closings, fuel, maintenance, reports...
database/             schema.sql, sample_data.sql and upgrade migrations
```

## Tech stack

PHP 8 · MySQL · PDO · HTML · CSS · JavaScript · Apache (XAMPP)

## Setup

1. Copy the project into XAMPP's `htdocs`.
2. In phpMyAdmin, create a database called `bus_owner_control` and import `database/schema.sql`. Optionally import `database/sample_data.sql` for demo data.
3. Set the database details in `app/config.php`, or set `DB_HOST`, `DB_NAME`, `DB_USER` and `DB_PASS` as environment variables.
4. Open `http://localhost/bus_owner_control_system/public/` and sign in.

Demo login for local testing only: `admin@buspro.local` with password `Admin123!`. With sample data there are also `manager@`, `accountant@`, `agent@` and `parcel@buspro.local` accounts with the same password. Change these on any real installation.

> **Demo data.** `database/sample_data.sql` contains invented companies, people and phone numbers only.

## Author

Robert Baraka Kunenga, Computer Engineering, OSTIM Technical University · [github.com/robert6111](https://github.com/robert6111)

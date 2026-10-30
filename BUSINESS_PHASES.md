# Business Phases

## Phase 1 — Owner Control MVP

Completed features:

- Login and role access
- Owner/admin/manager/accountant/agent/parcel officer roles
- Fleet, routes, drivers, agents/branches
- Trips, passenger seats, parcels, expenses
- Dashboard and reports
- Audit logs and audit filters
- Branch-scoped access for agents and parcel officers
- Responsive layout and clean report printing

## Phase 2 — Operations, Cash Control, Receipts and Selling Readiness

Completed in v2.0:

- Passenger ticket print/download
- Parcel receipt print/download
- Daily cash closing
- Agent reconciliation
- Maintenance reminders and maintenance records
- Fuel consumption tracking
- CSV exports
- WhatsApp notification center and logs
- Manual SQL backup system
- Company branding settings
- Local licence control
- Dashboard improvements
- Audit logging for Phase 2 actions

## Phase 2.5 — SaaS Package/Licence Ready

Completed in v2.5:

- Package tiers: Starter, Business, Professional, Enterprise
- One-time setup fee model
- Optional monthly support model
- Package limits for users, branches, buses, routes, and monthly trips
- Feature gates by package
- Packages & Licence management page
- Licence status control: trial, active, expired, suspended
- Optional monthly support status: off, active, paused, expired
- Package usage summary on dashboard
- Operational blocking when a licence is expired/suspended
- Audit logging for package changes, blocked features, and limit blocks

## Recommended selling model

Start with one bus company per deployment/database. This keeps client data isolated and reduces production risk.

Suggested packages:

- Starter Local: one small office
- Business Cloud: several branches, parcels, cash closing
- Professional Multi-Branch: serious full-feature client
- Enterprise Custom: custom deployment/integrations

## Phase 3 — Cloud Production and Integrations

Recommended later after paying clients:

- HTTPS hosting and deployment scripts
- Automated off-server backups
- Real SMS API
- Mobile money API integration
- TRA/EFD receipt integration where legally required
- LATRA-approved ticketing integration or approved-provider data import
- Full multi-tenant SaaS with company_id isolation
- GPS/VTS integration if needed
- Advanced analytics and charts

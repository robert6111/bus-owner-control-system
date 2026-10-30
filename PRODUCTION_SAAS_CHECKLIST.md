# Production SaaS / Client Deployment Checklist

This system is now package/licence ready, but before giving it to a paying bus company you should still do these production steps.

## Safe deployment model for now

Use **one bus company per deployment/database**.

That means each client gets their own database and deployment. This avoids dangerous data mixing between bus companies.

## Required before selling

- Change all demo passwords.
- Change database password in `app/config.php`.
- Do not use MySQL root user in production.
- Host behind HTTPS.
- Disable PHP error display in production.
- Keep daily database backups outside the server/computer.
- Set the correct package under **Packages & Licence**.
- Test feature limits for the selected package.
- Test owner, manager, accountant, agent, and parcel officer accounts.
- Test audit logs after every important action.
- Test clean report PDF printing.
- Test ticket and parcel receipt PDF printing.

## Package/licence model

- Starter Local: limited basic operations.
- Business Cloud: parcels, cash closing, maintenance, fuel, notifications, exports.
- Professional Multi-Branch: full Phase 2 controls plus backup and higher limits.
- Enterprise Custom: high/unlimited limits and future integrations.

The optional monthly fee should be sold as support, hosting, backup, updates, staff training, data entry, SMS/WhatsApp setup, or operational help. It is not forced monthly subscription.

## Later full SaaS step

When you have paying clients, the next technical upgrade can be full multi-tenant SaaS. That requires adding `company_id` to every business table and filtering every query by the logged-in company. Do not rush that until the business model is proven.

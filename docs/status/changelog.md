# Documentation Changelog

## 2026-10-11

- Implemented plan 003: admin-only React catalog workspace, protected web/API routes, managed vendors, products, public-disk product images, and vendor-specific offers.
- Added `is_admin` default false with registration and mass-assignment protections; the non-production sample seeder provisions the requested admin account and sample catalog data.
- Added feature coverage for admin access, vendor CRUD, product images/offers, lowest active offer, and admin privilege protection.
- Replaced the earlier vendor-self-service direction with admin-managed vendor records; orders, discounts, public shop, and vendor login remain out of scope.

## 2026-10-10

- Added persistent project documentation based on inspected code, route list, installed package versions, and Boost database schema.
- Added the backlog and reusable plan template, with plan `001-project-foundation` tracking this rollout.
- Added one canonical agent task workflow while preserving Boost-managed guidance, skills, and MCP configuration.
- Implemented Step 1 React/API registration, login, and logout using Sanctum stateful sessions; added the Sanctum token table and auth feature tests.
- Updated product, architecture, API, and current-state docs to separate requested future marketplace scope from implemented auth.

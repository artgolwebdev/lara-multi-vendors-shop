# Feature Inventory

This inventory describes code verified in the repository, not an intended marketplace roadmap.

| Capability | Current implementation | Evidence |
| --- | --- | --- |
| Public welcome page | `/` returns the `welcome` Blade view | `routes/web.php` |
| Registration and login | Laravel UI `Auth::routes()` and its auth controllers/views | `routes/web.php`, `app/Http/Controllers/Auth/` |
| Authenticated home | `/home` uses `HomeController`; constructor applies `auth` middleware | `routes/web.php`, `app/Http/Controllers/HomeController.php` |
| Password reset | Laravel UI reset form and submission routes/controllers | `routes/web.php`, auth controllers/views |
| React example | Vite imports a sample component that mounts only when `#example` exists | `resources/js/components/Example.jsx` |

No vendor, product-catalog, cart, checkout, order, payment, or administrative domain feature was found. Do not plan these as committed product requirements without confirmation. See [requirements](requirements.md) and [API surface](../api/endpoints.md).
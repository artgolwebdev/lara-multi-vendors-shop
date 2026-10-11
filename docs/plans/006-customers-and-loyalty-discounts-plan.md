# Plan 006: Customers & Loyalty Discounts

## Goal

Add registered customer accounts (profile, password, discounts, orders placeholder)
and a `loyalty` discount rule type that the existing discount engine discovers
through its tagged rule binding, plus admin management of customers and their
selected-customer loyalty discounts.

## Implementation Tasks

### Phase 1: Database & Models
- [x] Create migration to add phone/address to users table
- [x] Create migration to add loyalty fields to discount_rules (loyalty_condition, audience)
- [x] Create migration for discount_rule_user pivot table
- [x] Update User model (fillable for phone/address, loyaltyDiscountRules relation)
- [x] Update DiscountRule model (TYPE_LOYALTY, constants, fillable, casts, customers relation)
- [x] Create DiscountContext DTO
- [x] Create CustomerOrderHistory interface and DefaultCustomerOrderHistory implementation
- [x] Update AppServiceProvider to bind CustomerOrderHistory and register LoyaltyCustomerDiscountRule

### Phase 2: Core Logic
- [x] Create LoyaltyCustomerDiscountRule class implementing DiscountRuleInterface
- [x] Update DiscountRuleInterface signature to accept optional DiscountContext
- [x] Update DiscountEngine to accept and pass context to rule implementations
- [x] Update existing rule classes to accept optional context parameter (backward compatible)

### Phase 3: Backend API - Account & Admin
- [x] Create API AccountController for profile, password, discounts
- [x] Create form requests for account updates (UpdateProfileRequest, UpdatePasswordRequest)
- [x] Add account API routes (protected)
- [x] Create Admin\CustomerController for listing, show, manage loyalty rules
- [x] Create admin API routes for customers
- [x] Update web routes for account SPA pages if needed

### Phase 4: Cart/Quote Integration
- [x] Update CartQuoter to accept optional customer parameter and pass context
- [x] Update CartQuoteController to pass authenticated user
- [x] Create API endpoint for public loyalty promotions (or include in quote)
- [x] Add promotions to quote response for guests with all-customer rules

### Phase 5: Frontend
- [x] Update StoreLayout header to show Login/Register vs Account/Logout based on auth state
- [x] Create customer account pages in store SPA (Profile, Password, My Discounts, My Orders placeholder)
- [x] Update Cart page to show loyalty discount labels and guest promotion banner
- [x] Update AuthApp to handle redirect after login/register
- [x] Add account routes to StoreApp

### Phase 6: Admin UI
- [x] Add Customers tab/link to AdminLayout navigation
- [x] Create Customers list page
- [x] Create Customer detail page with loyalty discounts management
- [x] Update DiscountRule form in admin to support loyalty type with conditional fields
- [x] Update discount rule admin UI to handle audience and selected customers

### Phase 7: Seeding & Testing
- [x] Update DatabaseSeeder to create sample customers and loyalty rule
- [x] Write unit tests for LoyaltyCustomerDiscountRule
- [x] Write feature tests for registration, access control, customers, quote integration
- [x] Run tests, pint, and build

## Acceptance Criteria

- [x] New users register with is_admin=false and cannot set is_admin
- [x] After registration, user is logged in and can be redirected appropriately
- [x] Customers get 403 when accessing /admin/* routes
- [x] Admins can list/search non-admin customers
- [x] Admin can grant/revoke selected-customer loyalty rules from customer detail
- [x] Authenticated eligible customers see loyalty discounts in cart quote
- [x] Guests see promotion banner for all-customer first-order rules
- [x] Customer account page allows editing profile and changing password
- [x] "My discounts" shows eligible loyalty discounts
- [x] Engine discovers LoyaltyCustomerDiscountRule via tagged binding without engine changes
- [x] All existing tests still pass

## Testing Strategy and Results

| Check | Expected result | Actual result |
| --- | --- | --- |
| Registration creates non-admin | User is_admin is false | Pass — `RegistrationTest` |
| Cannot set is_admin on register | Registration ignores/rejects is_admin | Pass — 422 via `prohibited` rule |
| Customer blocked from admin | 403 on /admin/* | Pass — `CustomerAccessControlTest` |
| Admin customers list | Only non-admin users shown, searchable, paginated | Pass — `AdminCustomersTest` |
| Grant/revoke selected rule | Works from customer detail and persists | Pass — `AdminCustomersTest` |
| Loyalty rule eligibility (first order) | Applies when completedOrdersCount==0 | Pass — `LoyaltyCustomerDiscountRuleTest` |
| Guest sees no loyalty discount | Quote returns no loyalty discount for guest | Pass — `LoyaltyQuoteIntegrationTest` |
| Authenticated eligible gets discount | Loyalty discount applied correctly | Pass — `LoyaltyQuoteIntegrationTest` |
| Promotions banner for guests | Shows public text for all-customer rules only | Pass — `LoyaltyQuoteIntegrationTest` |
| Unit tests pass | All new unit tests pass | Pass |
| Feature tests pass | All new feature tests pass | Pass |
| Full suite | All tests pass | Pass — 117 tests, 535 assertions |
| Migrations + seeders | `migrate:fresh --seed` succeeds | Pass (in-memory SQLite); forward `migrate` + seeders applied to configured MySQL |
| Frontend build | `npm run build` succeeds | Pass |

## Risks, Dependencies, and Open Questions

**Risks:**
- Changing DiscountEngine/Interface signature could affect existing code - mitigated by adding optional params (backward compatible).
- Need to ensure all existing rule implementations still work - covered by unchanged engine + full suite.

**Dependencies:**
- Orders plan will replace `DefaultCustomerOrderHistory` with a real implementation.
- One-time use enforcement will be handled during order creation (record applied loyalty rule on order).

**Decisions / Questions for You:**

1. **Stacking policy for loyalty discounts**: The existing engine stacks cumulatively (every matching rule). Decision: keep cumulative stacking, so loyalty discounts stack with other matching rules. Business rules can change this later.
2. **Audience modeling**: String constants (`all_customers`, `selected_customers`) - accepted.
3. **How orders plan plugs in**: Replace `DefaultCustomerOrderHistory` binding with an implementation that queries the orders table.
4. **First-order one-time use enforcement**: Eligibility check lives in the rule; the order creation transaction should also validate. Out of scope now, but the abstraction allows it.
5. **Scope of loyalty discounts**: Rule logic respects existing `category_id`/`vendor_id` filters when set.
6. **Phone/address fields**: Nullable columns added to `users`.

## Implementation Notes and Discoveries

- `AppliedRule` gained a `ruleType` field and `toArray()` exposes `rule_type`; `DiscountEngine` passes `$rule->type`. `PriceQuoteLine::toArray()` now exposes `is_loyalty` so the storefront can label loyalty lines distinctly.
- `CartQuoteController` passes `$request->user('web')` to the quoter; for guests it adds `data.promotions` containing only `all_customers` loyalty rules to power the storefront banner.
- Adding `loyalty` to `DiscountRule::types()` also updated the admin discount rule form to render conditional loyalty fields (condition, audience, selected customers).
- `RegisterRequest` accepts optional `phone`/`address`, and `AuthController` persists them.
- `AuthApp` reads `mode` and `redirect` query params: guest links point to `/account?mode=register&redirect=<path>` (or `/account?redirect=<path>` for login). On success it redirects back to the originating store path; the existing Laravel UI `/login` and `/register` routes are untouched.
- Authenticated users are rendered by `AccountPanel` (profile, password, my discounts, my orders placeholder) instead of the old "You're signed in" block.

## Final Summary and Remaining Work

Implemented customer accounts and loyalty discounts end to end: migrations, models,
`LoyaltyCustomerDiscountRule` discovered via tagged binding, account/admin APIs,
cart/quote integration with guest promotions, storefront + admin UI, seeders, and tests.

Verification: `php artisan test` — 117 passed / 535 assertions; `migrate:fresh --seed`
verified on in-memory SQLite; forward migrations and idempotent seeders applied to the
configured MySQL database; `npm run build` succeeded.

Remaining work (out of scope here):
- Replace `DefaultCustomerOrderHistory` with a real orders-backed implementation once
  the orders plan lands, and enforce first-order one-time use during order creation.

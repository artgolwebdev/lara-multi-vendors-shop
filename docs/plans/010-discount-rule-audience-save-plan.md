# 010: Prevent Null Audience When Saving Non-Loyalty Discount Rules

- ID: `010-discount-rule-audience-save`
- Status: completed
- Created: 2026-10-11
- Updated: 2026-10-11
- Completed: 2026-10-11

## Problem and Intended Outcome

Saving an existing discount rule from the admin UI can fail with MySQL error 1048 because the update writes `audience = NULL` to `discount_rules.audience`, which is non-nullable. The admin should be able to create and edit quantity, category, and loyalty rules without database errors, while retaining the audience selection for loyalty rules.

## Scope

### In Scope

- Normalize the audience field for non-loyalty rules at the API boundary and in the admin form payload.
- Cover non-loyalty create/edit and loyalty audience persistence with feature tests.
- Verify a registered customer's first-order welcome loyalty discount stacks with a quantity discount on a five-unit order, and that later orders receive only the quantity discount.
- Keep the existing database constraint and default; no schema migration is expected.

### Non-Goals

- Changing the audience choices or loyalty eligibility behavior.
- Making `discount_rules.audience` nullable or changing existing database data.
- Refactoring the broader discount engine or rule editor.

## References

- Requirements: [`docs/product/requirements.md`](../product/requirements.md), [`docs/product/features.md`](../product/features.md)
- Architecture: [`docs/architecture/backend.md`](../architecture/backend.md), [`docs/architecture/database.md`](../architecture/database.md), [`docs/architecture/frontend.md`](../architecture/frontend.md)
- Existing discount work: [`Plan 004`](004-discount-rules-plan.md), [`Plan 006`](006-customers-and-loyalty-discounts-plan.md)
- API: [`docs/api/endpoints.md`](../api/endpoints.md)

## Existing Implementation Findings

### Verified Facts

1. `DiscountRulePages.jsx` builds the save body with `audience: form.type === 'loyalty' ? form.audience : null` and sends it for both POST and PATCH.
2. Both `StoreDiscountRuleRequest` and `UpdateDiscountRuleRequest` validate `audience` as `nullable`, while only requiring it for loyalty rules. Neither request currently replaces null audience on non-loyalty rules.
3. `DiscountRuleController::update()` passes all validated fields to `DiscountRule::update()`, so the accepted null becomes `audience = NULL` in the SQL update.
4. Migration `2026_10_11_090103_add_loyalty_fields_to_discount_rules_table.php` defines `audience` as a non-null string with database default `all_customers`. The migration's default does not permit an explicit null on update (or on insert when null is supplied explicitly).
5. The reported SQL updates a rule with `audience = ?` and priority 27, matching the UI's unconditional audience key and nullable request validation.
6. Existing `AdminDiscountRulesTest` payloads omit `audience`, so they do not reproduce the frontend's explicit null. Existing test coverage does not assert the non-loyalty audience invariant.

### Root Cause

The UI sends `audience: null` for every quantity/category save. Laravel accepts that value because the request rule allows null for non-loyalty rule types. The controller then writes the null into a non-nullable MySQL column, which raises SQLSTATE 23000 / error 1048. The reported failure is therefore caused by a mismatch between the UI payload, request normalization, and database invariant.

### Unanswered Questions

- Confirm whether current rule 2 is a quantity/category rule or a loyalty rule changed to another type during the user's save. Either path is consistent with the observed null audience update.
- Confirm there are no manually altered production schemas; the committed migration is the schema contract used for this plan.

## Proposed Design

### Affected Components

- `resources/js/components/admin/DiscountRulePages.jsx`
- `app/Http/Requests/Admin/StoreDiscountRuleRequest.php`
- `app/Http/Requests/Admin/UpdateDiscountRuleRequest.php`
- `tests/Feature/AdminDiscountRulesTest.php`
- `tests/Feature/OrderCheckoutTest.php`

### Database

- Keep `audience` non-nullable with the `all_customers` default. It is a required persisted invariant; non-loyalty rules simply do not use its value.
- Do not add a migration or loosen the constraint.

### API / Routes

- Normalize `audience` to `DiscountRule::AUDIENCE_ALL_CUSTOMERS` for non-loyalty rule types in both store and update requests. This protects the invariant for all API clients, including requests that send explicit null.
- Keep loyalty requests conditional: `audience` remains required and restricted to `all_customers` or `selected_customers`.
- Ensure controller persistence and `syncCustomers()` continue to clear selected-customer pivots when a rule is no longer a selected-customer loyalty rule.

### Frontend

- Send `all_customers` for non-loyalty rules rather than `null`, matching the database/API invariant and avoiding invalid payloads from the admin editor.
- Retain the selected audience for loyalty rules.
- Preserve the existing cumulative stacking calculation; add a checkout-level test that verifies both rule contributions and the stored breakdown on the same order line.

### Security

- Authorization is unchanged; existing admin middleware and request authorization remain responsible for access control.

## Implementation Tasks

- [x] Normalize the audience value for non-loyalty rule types in both Form Requests.
- [x] Update the React rule form to send the non-null default for non-loyalty rules.
- [x] Add feature coverage for create and update requests that send a null audience, including a loyalty-to-non-loyalty transition; assert successful responses and persisted `all_customers` values.
- [x] Retain coverage that a loyalty rule saves its selected audience and selected-customer relations correctly.
- [x] Add a registered first-order checkout case for five units that receives both welcome loyalty and quantity discounts, then verify the first-order discount is unavailable on the customer's next order while the quantity discount still applies.
- [x] Run focused discount feature tests, the full PHPUnit suite, Pint, and the Vite build.
- [x] Record actual results and mark this plan/backlog complete after verification.

## Acceptance Criteria

- [x] Editing a quantity or category rule from the admin UI succeeds without SQLSTATE 23000 / error 1048.
- [x] Creating a non-loyalty rule with an omitted or null audience persists `all_customers`.
- [x] Updating a rule to a non-loyalty type persists `all_customers` and clears any selected-customer pivot assignments.
- [x] Loyalty rules still require and preserve their selected audience.
- [x] A registered customer with no prior orders receives both a matching welcome loyalty discount and a matching quantity discount; each appears in the persisted line breakdown and contributes to the order total.
- [x] The same customer does not receive the first-order loyalty discount again after placing an order, while matching quantity discounts continue to apply.
- [x] Invalid requests return validation responses rather than database exceptions.
- [x] Focused and full test suites, Pint, and Vite build pass.

## Testing Strategy and Results

| Check | Expected result | Actual result |
| --- | --- | --- |
| Feature tests: audience normalization, loyalty pivot, and repeat-order stacking | Correct defaults, selected audience persistence, both first-order discounts and quantity-only repeat order | Passed as part of full PHPUnit suite |
| `php artisan test --compact tests/Feature/AdminDiscountRulesTest.php tests/Feature/OrderCheckoutTest.php` | Focused discount and checkout tests pass | Passed: 30 tests, 151 assertions |
| `php artisan test --compact` | Full test suite passes | Passed: 150 tests, 694 assertions |
| `vendor/bin/pint --dirty --format agent` | Modified PHP files formatted | Passed |
| `npm run build` | Frontend production build succeeds | Passed; existing Sass deprecation and Tailwind/Lightning CSS unknown-at-rule warnings remain |

## Risks, Dependencies, and Open Questions

- The two Form Requests currently duplicate field normalization. Keep the invariant consistent in both; extract shared code only if the implementation can do so without broadening scope.
- The frontend change alone would not protect other API clients, and backend normalization alone would leave the editor sending an invalid null value. Apply both layers.
- No new dependencies or schema changes are anticipated.

## Implementation Notes and Discoveries

- Both store and update Form Requests now force non-loyalty rules to persist `all_customers`; the React editor also sends that value explicitly. Loyalty audiences remain validated and selected-customer pivots are synchronized as before.
- Checkout feature coverage confirms a registered customer's first five-unit, $100 order receives a $10 welcome loyalty discount and a $10 quantity discount (total $80); the same customer's next order receives only the $10 quantity discount (total $90).
- Full validation passed: 150 tests / 694 assertions, Pint, and production Vite build. Build emitted existing Sass deprecation and Tailwind directives warnings.

## Final Summary and Remaining Work

Implemented the audience invariant at the API and frontend boundaries without changing the schema. The checkout regression test confirms that welcome loyalty and quantity discounts stack on the same first order, and that the first-order loyalty rule does not repeat. All validation listed above passed.

# Assignment Audit: Multi-Vendor Order Processing

**Review date:** 2026-10-11. Read-only review; no application code/config/migrations/tests changed. Existing uncommitted changes were preserved. Tests were inventoried but not rerun.

## 1. Executive summary

**Estimated completion: 90% (review estimate).** Catalog offers, server-side quote/discount calculation, checkout, transactional order/sub-order persistence, queued notifications and confirmation are implemented. Both required discount rules exist, plus loyalty. Tests cover all requested bonus areas.

The key functional gap: checkout defaults to honoring a customer-selected vendor, although the assignment requires the lowest price. Lowest-price mode exists but is opt-in. Other risks: stale/conflicting test counts; production needs a queue worker; vendors have no accounts; some docs contradict current code.

## 2. Requirements traceability

| Requirement | Status | Evidence | Notes |
|---|---|---|---|
| Lowest price per product (assignment + backend 2) | Partial | CartQuoter::sortedOffers()/resolveOffer(), app/Services/Cart/CartQuoter.php:260–283; PlaceOrderService::applyVendorSelectionPolicy(), 119–129; config/shop.php:18 | Default customer_choice accepts an explicitly selected higher-priced active offer. |
| Group products by vendor into sub-orders | Done | CartQuoter::quote(), 37–165; PlaceOrderService::persistOrder(), 192–274 | One sub-order per vendor group. |
| Queue one NotifyVendorJob per sub-order | Done | PlaceOrderService::createOrder(), 91–112 | Dispatches after commit. |
| Simulated vendor notification log | Done | app/Jobs/NotifyVendorJob.php:32–66 | Structured vendor.notification event, state marked sent. |
| Quantity threshold bonus | Done | QuantityDiscountRule; tests/Unit/Services/Discounts/QuantityDiscountRuleTest.php | Aggregates units per product/vendor. |
| Category bonus | Done | CategoryDiscountRule; tests/Unit/Services/Discounts/CategoryDiscountRuleTest.php | Category match, optional vendor filter. |
| Backend accepts cart | Done | StoreOrderRequest; OrderController::store(), app/Http/Controllers/Api/OrderController.php:28–60 | POST /api/orders, validated payload. |
| Backend chooses lowest offer | Partial | CartQuoter; PlaceOrderService::applyVendorSelectionPolicy() | Same default-policy gap above. Inactive offers/vendors excluded. |
| Backend dynamically applies discounts | Done | DiscountEngine::calculate(); implementations | Active/in-window, priority ordered, cumulative and capped. |
| Main order and sub-orders | Done | PlaceOrderService::createOrder()/persistOrder() | Transaction, stock lock and snapshots. |
| Dispatch NotifyVendorJob per sub-order | Done | PlaceOrderService::createOrder(), lines 107–109 | afterCommit dispatch. |
| Log notification to Laravel log | Done | NotifyVendorJob::handle(), 42–65 | Configured log channel determines destination. |
| Dynamic rule binding/resolution | Done | AppServiceProvider::register(); DiscountEngine::implementationFor() | Tagged services; new rule also needs accepted rule type/fields. |
| Frontend product display | Done | StoreApp.jsx, store pages, public catalog APIs | Listing/category/product pages. |
| Cart and quantity management | Done | CartPage.jsx, CartLine.jsx, QuantityControl.jsx, cartSlice.js | Redux Toolkit and localStorage. |
| Vendor/price/discount display | Done | CartQuoter; CartPage.jsx | Server quote contains vendor and discounts. |
| Pre-submit review | Done | CheckoutPage.jsx | Grouped vendors, lines, discounts and total. |
| Submit through API | Done | CheckoutPage.handleSubmit(); /api/orders | Includes expected total and idempotency key. |
| Show order/sub-orders | Done | OrderConfirmationPage.jsx; OrderResource/SubOrderResource | Owner session or guest token read. |
| Grouping test | Done | OrderCheckoutTest::test_order_groups_lines_into_one_sub_order_per_vendor | Present; not rerun. |
| Lowest-price test | Done | OrderCheckoutTest::test_lowest_price_policy_ignores_the_chosen_vendor; CartQuoter tests | Tests opt-in mode, not default. |
| Discount tests | Done | Unit rule/engine/loyalty tests; checkout integration tests | See section 6. |
| Job dispatch tests | Done | OrderNotifyTest.php; OrderCheckoutTest.php | Dispatch and queue behavior covered. |
| DiscountRuleInterface + named rules | Done | app/Services/Discounts/ | Interface, CategoryDiscountRule, LoyaltyCustomerDiscountRule present. |
| Standard framework/frontend practices | Partial | Form Requests, Resources, services, transactions, separated React UI | Generally sound; see section 5. |

## 3. How it works

### Architecture and data

Laravel serves Blade mount views; React renders storefront/auth/admin. JSON endpoints are in routes/api.php. React uses React Router, Redux Toolkit and Axios; Vite builds assets. Backend layers include Form Requests, controllers, Resources, Eloquent models, CartQuoter, DiscountEngine, PlaceOrderService and a job.

Evidence: routes/web.php:6–65; routes/api.php:23–69; resources/js/app.js; resources/js/components/store/StoreApp.jsx; resources/js/components/AdminApp.jsx.

Catalog tables: products belong to categories; product_vendor holds vendor-specific offers/prices; vendors hold contacts. Product::lowestOffer() filters active offers/vendors (app/Models/Product.php:54–66); stock is shared per product. discount_rules hold type/value, filters, dates, active state and priority; discount_rule_user links selected loyalty customers.

orders store customer/delivery snapshots, status, payment, totals, optional user/guest token/idempotency key (database/migrations/2026_10_11_100322_create_orders_table.php:14–38). sub_orders store vendor snapshot, totals and notification state (…100323…:14–29). order_items snapshot product/vendor/name/SKU/category/price/quantity/rules/total (…100324…:14–31). Order has many SubOrders; SubOrder has many OrderItems.

### End-to-end flow and JSON

Cart state contains product_id, optional vendor_id and quantity; cartSlice.js persists it and refreshes the quote. StoreOrderRequest validates customer/delivery data, 1–50 lines, IDs/quantities, optional expected_total, idempotency_key and notes. OrderController::store() gets a session user if present and calls PlaceOrderService.

Within a DB transaction, PlaceOrderService applies vendor policy, recalculates through CartQuoter, checks expected_total, locks/validates shared stock, writes order/sub-orders/items snapshots, decrements stock and dispatches after-commit jobs. CartQuoter resolves offers, calls DiscountEngine and groups totals by vendor.

Simplified request: customer {name,email,phone,address}; delivery {name,email,phone,address}; lines [{product_id,quantity,vendor_id?}]; expected_total?; idempotency_key?; notes?. Guest requires name/email. Success is HTTP 201 Laravel envelope data containing order ID/number/status/payment/totals, customer, delivery, sub_orders and guest_token when guest. Sub-orders contain vendor/totals/notification state/items; items contain applied-rule breakdowns. React stores guest token in sessionStorage, clears cart and navigates to confirmation. Price change returns 409 and fresh quote; validation returns 422.

### Discount engine

DiscountRuleInterface defines supports(rule) and apply(lines, rule, context). QuantityDiscountRule, CategoryDiscountRule and LoyaltyCustomerDiscountRule implement it. AppServiceProvider tags implementations as discount.rules and binds DiscountEngine from the tag. Active rules are ordered by descending priority then ID; matching implementations stack cumulatively in order, capped at each line subtotal. Money uses integer cents. Loyalty receives customer context and order-history data. Adding a rule requires implementation, tag registration and rule type/field validation support.

Evidence: app/Services/Discounts/DiscountEngine.php:39–120; DiscountRuleInterface.php:14–22; AppServiceProvider.php:19–31.

### Queue, frontend and roles

NotifyVendorJob implements ShouldQueue, retries three times with backoff, eager-loads order/vendor/items, logs vendor.notification, then marks sent. Failure marks failed/logs error. QueueAutoProcessor invokes queue:work --stop-when-empty at termination only when shop.queue_auto_process is enabled. config/shop.php enables it by default only locally; elsewhere a real worker is needed. Queue backend is environment-configured in config/queue.php.

Store pages cover home/shop/category/product/cart/checkout/confirmation. Cart and checkout handle quote loading/errors, field validation and 409 reconfirmation; confirmation renders vendor groups/items.

Roles: admin has protected catalog/discount/customer/order tools; customer has account/profile/discounts/history/checkout; guest can browse and checkout, with guest-token order reads. Vendor users/workspaces and vendor authorization are absent.

## 4. Gap analysis

| Issue | Why it matters | Suggested fix | Effort |
|---|---|---|---|
| Default customer_choice may use a higher offer | Conflicts with mandatory lowest-price rule | Default assignment/demo to lowest_price or enforce cheapest offer at checkout | S |
| Historical test counts conflict; suite not rerun | Current verification unclear | Run suite and reconcile docs | S |
| Production queue requires worker | Jobs can remain queued | Configure/document target queue worker | S |
| Product stock is shared | Cannot model separate vendor inventory | Retain for demo or add offer stock if needed | M |
| Stale docs say order flow is absent | Misleads reviewers | Refresh architecture/product docs | S |

## 5. Code quality review

| Area | Rating | Example |
|---|---|---|
| Validation | Good | StoreOrderRequest nested validation and line bounds. |
| Transactions | Good | Order writes/stock in transaction; jobs after commit. |
| Money/rounding | Good | Integer cents and capped discount math. |
| N+1 | Acceptable | Batched offer loads; discount hint queries merit profiling at scale. |
| Errors | Good | Validation, out-of-stock, 409 price guard, 404 guest-read guard; frontend handles 422/409. |
| API Resources | Good | Dedicated order/sub-order/item Resources. |
| Separation/naming | Good | Quote, discounts, persistence, queue separated. |
| Security | Acceptable | Admin middleware and guest token hash_equals; no vendor tenancy. |
| Frontend structure | Good | Page/component/state/API separation, loading/errors. |
| Docs consistency | Needs work | architecture/backend.md, architecture/database.md, product/requirements.md and system-design/components.md still claim orders absent. |

## 6. Tests

Inventoried, not run:

| Bonus area | Tests |
|---|---|
| Grouping | tests/Feature/OrderCheckoutTest.php::test_order_groups_lines_into_one_sub_order_per_vendor |
| Lowest price | OrderCheckoutTest::test_lowest_price_policy_ignores_the_chosen_vendor; CartQuoter unit tests |
| Discounts | Unit quantity/category/engine/loyalty tests; OrderCheckoutTest; LoyaltyQuoteIntegrationTest |
| Jobs | tests/Feature/OrderNotifyTest.php; checkout job assertions |

Checkout tests also cover guest/customer, snapshots, COD, idempotency, price change, stock and stacking. Run result for this audit: **not run**. docs/status/current-state.md says 150 tests / 694 assertions; known-issues.md says 142 / 645; development/testing.md records older 97 / 490. Historical counts conflict. No JS test runner is documented; browser checkout coverage is unverified.

## 7. Beyond assignment

Admin catalog/discount/order tools, accounts, loyalty targeting, stock decrement, guest tokens, idempotency, price-change confirmation and retry state extend the brief. They help a fuller demo but add surface area. COD is the only payment method; no online provider. Stock is shared. Log-only vendor notification matches the requested simulation and sends no email.

## 8. Prioritized actions

### Must fix

1. Make lowest eligible offer the default checkout behavior and verify it.
2. Run PHPUnit and record exact counts; reconcile stale figures.
3. Confirm target queue worker processes notifications.
4. Correct docs that say order processing is unimplemented.

### Nice to have

1. Test default vendor policy.
2. Profile quote/listing queries, especially discount hints.
3. Document shared stock and absent vendor login.
4. Label COD and log notification as simulation choices.

## Evidence index

- Request/routes: routes/api.php:23–30; app/Http/Requests/StoreOrderRequest.php:18–41; app/Http/Controllers/Api/OrderController.php:28–60.
- Quote: app/Services/Cart/CartQuoter.php:37–165,242–283.
- Order transaction/policy/persistence: app/Services/Orders/PlaceOrderService.php:82–112,119–129,137–177,192–274.
- Discount engine: app/Services/Discounts/DiscountRuleInterface.php:14–22; DiscountEngine.php:39–120; AppServiceProvider.php:19–31.
- Queue: app/Jobs/NotifyVendorJob.php:16–76; app/Services/Orders/QueueAutoProcessor.php:15–30; config/shop.php:18,31–33.
- Response: app/Http/Resources/OrderResource.php:13–43; SubOrderResource.php:13–32; OrderItemResource.php:13–32.
- Frontend: resources/js/components/store/pages/CheckoutPage.jsx; CartPage.jsx; OrderConfirmationPage.jsx; resources/js/store/cartSlice.js.
- Tests: tests/Feature/OrderCheckoutTest.php; OrderNotifyTest.php; tests/Unit/Services/Discounts/; tests/Unit/LoyaltyCustomerDiscountRuleTest.php.

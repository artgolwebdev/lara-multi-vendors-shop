# 019: Fix "images field must be an array" Error When Editing a Product

- ID: `019-fix-product-update-images-validation`
- Status: completed
- Created: 2026-10-12
- Updated: 2026-10-12
- Completed: 2026-10-12

## Problem and Intended Outcome

When an admin edits an existing product — for example by adding a new vendor offer — and submits the form without selecting any new images, the API returns a 422 Unprocessable Entity with the error:

> "The images field must be an array."

No images are being uploaded in this case, yet the validation rejects the request. Creating a new product without images works correctly. The intended outcome is that submitting the product edit form with no new images selected succeeds, and existing images and offers are updated as expected.

## Scope

### In Scope

- Fix `UpdateProductRequest` so that the `images` field passes validation when no files are sent.
- Add a regression test confirming that a product can be updated without uploading any images.
- Add a regression test confirming that a product can be updated while also uploading new images.
- Run Pint and the full test suite.
- **Added during implementation (user request):** disable the image upload control in the product **edit** form while keeping product creation and editing functional, because durable image storage is not available on Vercel yet.
- **Added during implementation:** fix the product form submit loop, which stringified non-editable product payload fields (`images`, `offers`, `category`) back into the `FormData` body.

### Non-Goals

- No changes to `StoreProductRequest` rules other than the same proactive `nullable` consistency fix (applied as planned).
- No changes to routing or controller logic.
- No new product features or UI changes beyond disabling the edit-mode image upload control requested by the user.

## References

- Architecture: [frontend architecture](../architecture/frontend.md)
- Prior product implementation: [Plan 003](003-admin-vendors-products-plan.md), [Plan 005](005-product-listing-and-cart-plan.md)
- Current snapshot: [current state](../status/current-state.md)

## Existing Implementation Findings

### Verified Facts

1. **The frontend always sends a `multipart/form-data` request**, whether or not images are selected. It calls `body.append('_method', 'PUT')` and posts to `POST /api/admin/products/{id}` (`ProductPages.jsx:158–159`).

2. **When no images are selected**, `selectedImages` is an empty array and the loop `selectedImages.forEach((image) => body.append('images[]', image))` appends nothing (`ProductPages.jsx:154`). As a result, the `FormData` body contains **no `images` key at all** — the field is simply absent from the multipart payload.

3. **`UpdateProductRequest` declares this rule for images** (`UpdateProductRequest.php:38`):
   ```php
   'images' => ['sometimes', 'array', 'max:10'],
   ```
   The `sometimes` modifier means: *if the key is present in the input, apply the remaining rules*. When images are absent from a JSON request, `sometimes` correctly skips validation.

4. **The bug is triggered by `multipart/form-data` encoding**. When PHP parses a multipart request, Laravel's `$request->all()` merges `$_POST` and `$_FILES`. With no `images[]` field in the payload, `$_FILES` contains no `images` key. However, `$_POST` **does** include any non-file fields appended via `FormData.append()`. In this scenario the `images` key is absent from both `$_POST` and `$_FILES`, so `sometimes` *should* skip it — but the request is sent as `multipart/form-data` with `_method=PUT`, meaning Laravel processes it as a `PUT` request via method spoofing. **The actual trigger is that `FormData` without any `images[]` entry results in no `images` key, but Laravel's `sometimes` modifier and the `array` rule interact differently when the content type is multipart vs JSON.**

5. **Concrete reproduction path**: Open the product edit page with an existing product that has offers and images. Click "Add offer", fill in the vendor and price, do not select any file in the image input, and click Save. The response is `422 { errors: { images: ["The images field must be an array."] } }`.

6. **The create path (`StoreProductRequest`) has the identical rule** (`StoreProductRequest.php:38`). Creating a product without images works, suggesting the difference lies in how the `PUT` (spoofed) multipart request is processed vs a `POST` multipart request, or a subtle environmental difference. Either way, making the rule more permissive is safe for both requests.

7. **The existing update test** (`AdminCatalogTest::test_updating_product_synchronizes_vendor_offers`, line 189–214) sends a `patchJson()` request, which uses `application/json` encoding — it does not exercise the multipart/FormData path at all and therefore does not catch this bug.

8. **The `storeImages` controller method** uses `$request->file('images', [])` with a default empty array (`ProductController.php:116`), so it handles zero images correctly. The bug is purely in validation.

### Inferences

- The safest and most semantically correct fix is to change `'sometimes'` to `'nullable'` **or** to add `'nullable'` alongside `'sometimes'` in the `images` rule in `UpdateProductRequest`. This tells Laravel: "the field may be absent or null; if present and non-null, it must be an array of valid images." Alternatively, replacing `'array'` with no array constraint and relying on the `images.*` sub-rule is another option, but less explicit.
- The same latent issue likely exists in `StoreProductRequest`. Fixing it there too costs nothing and prevents a future regression.

### Unanswered Questions

- None. The root cause is fully determined from the source files and the error message.

## Proposed Design

### Affected Components

- `app/Http/Requests/Admin/UpdateProductRequest.php` — primary fix.
- `app/Http/Requests/Admin/StoreProductRequest.php` — apply the same fix proactively.
- `tests/Feature/AdminCatalogTest.php` — add two regression tests for the update-without-images and update-with-images paths using multipart requests.

### Database

Not applicable.

### API / Routes

Not applicable. The route and controller are unchanged; only the validation rule changes.

### Frontend

Not applicable. The frontend sends the correct payload; the bug is entirely server-side.

### Security

Not applicable. The change makes the `images` field more permissive only in the "absent or null" case. The `image`, `mimes`, and `max:2048` rules on `images.*` still fully validate any file that is actually submitted.

## Implementation Tasks

- [x] In `UpdateProductRequest::rules()`, change the `images` rule from `['sometimes', 'array', 'max:10']` to `['nullable', 'array', 'max:10']`.
- [x] Apply the same change to `StoreProductRequest::rules()` for consistency.
- [x] Add a regression test `test_admin_can_update_product_without_uploading_images` that sends a multipart `POST` (with `_method=PUT`) without any `images` field and asserts `200 OK`.
- [x] Add a regression test `test_admin_can_update_product_and_upload_new_images` that sends the same multipart request with new image files and asserts `200 OK` and the correct image count.
- [x] Add `test_product_update_rejects_invalid_image_files` to keep the `422` behavior for invalid uploads on the update path (part of acceptance criteria).
- [x] Fix `ProductForm.submit()` to skip payload keys not present in `emptyProduct`, so stringified `images`/`offers`/`category` objects are never submitted.
- [x] Disable the image upload control in edit mode (`productId` set); create mode keeps the file input.
- [x] Run `php artisan test --compact tests/Feature/AdminCatalogTest.php` and confirm all tests pass.
- [x] Run `vendor/bin/pint --dirty --format agent` to format the changed PHP files.
- [x] Update `docs/plans/backlog.md`, `docs/status/current-state.md`, and `docs/status/changelog.md`.
- [x] Commit and push.

## Acceptance Criteria

- [x] Submitting the product edit form with no new images selected returns `200 OK` and updates the product.
- [x] Submitting the product edit form with new images selected returns `200 OK` and the images are stored (API path; the edit UI now hides the upload control).
- [x] Submitting an invalid image (wrong type or oversized) still returns `422` with the correct field error.
- [x] All existing `AdminCatalogTest` tests continue to pass.
- [x] `vendor/bin/pint --dirty` reports no formatting issues.
- [x] Product creation still offers the image upload control and works unchanged.
- [x] Product editing (name, slug, offers, quantity, etc.) works without any image field.

## Testing Strategy and Results

| Check | Expected result | Actual result |
| --- | --- | --- |
| Update product without images (multipart, `images => ''`) | 200 OK | 200 OK (failed with 422 before the fix) |
| Update product with new images (multipart) | 200 OK, images stored | 200 OK, image present on the fake `public` disk |
| Update product with invalid image | 422, `images.0` error | 422, `images.0` error |
| Full `AdminCatalogTest` suite | All existing tests pass | 14 passed (83 assertions) |
| Full PHPUnit suite | All existing tests pass | 154 passed (709 assertions) |
| Pint formatting | No violations | Clean after fixing an unused import |
| `npm run build` | Success | Built in 7.01s (exit 0), pre-existing Sass/Tailwind warnings only |

## Risks, Dependencies, and Open Questions

- Changing `sometimes` to `nullable` is a minimal, safe change. `nullable` in Laravel means the field may be absent or `null`; it does not weaken the `images.*` sub-rules applied to any file that is actually present.
- The `StoreProductRequest` change is a proactive consistency fix. It is not strictly required by the reported bug, but the identical rule structure means the same failure mode could appear in the create flow under a different client or test environment.
- No migration, seeder, or dependency change is involved.

## Implementation Notes and Discoveries

1. **The plan's original root-cause analysis was partly wrong.** It claimed the `images` key is *absent* from the multipart payload when no files are selected, so `sometimes` should skip it. Reproduction showed the opposite: the edit form spreads the API product payload into its state (`setForm({ ...emptyProduct, ...product })`), so `form` contains `images` (an array), `offers`, and `category` (objects). The submit loop `Object.entries(form).forEach(... body.append(key, value))` therefore appends a **stringified** `images` value — `''` for a product with no images, `"[object Object]"` for a product with images. PHP receives a present, non-array `images` field and `sometimes` + `array` correctly rejects it with *"The images field must be an array."* This also explains why create never failed: `emptyProduct` has no `images`/`offers`/`category` keys.
2. The `offers` payload happens to survive because it is appended a second time as `offers[0][vendor_id]` etc. (PHP resolves the later array-shaped keys over the stringified value, verified with `parse_str`), and `category` is not a validated field. `images` has no such second append, so it alone produced the 422.
3. **Fixes applied:** `nullable` on `images` in both form requests (tolerates absent/null), plus a frontend guard so only keys present in `emptyProduct` are appended from the form state. Both were needed: `nullable` alone would still reject the stringified `"[object Object]"` value for products that have images.
4. **User-requested scope addition:** the edit form now shows a muted notice instead of the file input ("Adding images while editing is disabled..."); create keeps the upload control. This aligns with the known issue that product images use the local public disk, which is not durable on Vercel.
5. The regression test sends `images => ''` (mimicking the real browser payload after `ConvertEmptyStringsToNull`); it failed with the exact reported 422 before the fix and passes after.
6. Test execution required Herd's PHP 8.4 (`C:/Users/artyo/.config/herd/bin/php.bat`); the PATH `php` is 8.2.12, which fails Composer's platform check (`>= 8.4.1`).
7. Unrelated working-tree change observed during implementation: the `/run-seeder` route was removed from `routes/web.php` (not by this task); Pint then removed its now-unused `Artisan` import. The user reviewed and approved committing it together with Plan 019.

## Final Summary and Remaining Work



Implemented and verified. `UpdateProductRequest`/`StoreProductRequest` now use `nullable` for `images`; the React product form no longer stringifies payload objects and hides the upload control when editing. Added three regression tests (update without images, update with images, update with invalid image). Full suite: **154 passed (709 assertions)**; Pint clean; `npm run build` exit 0.

Remaining work (out of scope): durable object storage for product images (see known issues); re-enable edit-mode image uploads once storage is available. Committed and pushed with the same change set as the `/run-seeder` route removal from `routes/web.php`.

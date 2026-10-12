# 019: Fix "images field must be an array" Error When Editing a Product

- ID: `019-fix-product-update-images-validation`
- Status: ready
- Created: 2026-10-12
- Updated: 2026-10-12
- Completed: not completed

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

### Non-Goals

- No changes to `StoreProductRequest` (create path); it does not exhibit this bug.
- No changes to the product form JSX, routing, controller logic, or any other file unless the investigation reveals a secondary issue.
- No new product features or UI changes.

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

- [ ] In `UpdateProductRequest::rules()`, change the `images` rule from `['sometimes', 'array', 'max:10']` to `['nullable', 'array', 'max:10']`.
- [ ] Apply the same change to `StoreProductRequest::rules()` for consistency.
- [ ] Add a regression test `test_admin_can_update_product_without_uploading_images` that sends a multipart `POST` (with `_method=PUT`) without any `images` field and asserts `200 OK`.
- [ ] Add a regression test `test_admin_can_update_product_and_upload_new_images` that sends the same multipart request with new image files and asserts `200 OK` and the correct image count.
- [ ] Run `php artisan test --compact tests/Feature/AdminCatalogTest.php` and confirm all tests pass.
- [ ] Run `vendor/bin/pint --dirty --format agent` to format the changed PHP files.
- [ ] Update `docs/plans/backlog.md`, `docs/status/current-state.md`, and `docs/status/changelog.md`.
- [ ] Commit and push.

## Acceptance Criteria

- [ ] Submitting the product edit form with no new images selected returns `200 OK` and updates the product.
- [ ] Submitting the product edit form with new images selected returns `200 OK` and the images are stored.
- [ ] Submitting an invalid image (wrong type or oversized) still returns `422` with the correct field error.
- [ ] All existing `AdminCatalogTest` tests continue to pass.
- [ ] `vendor/bin/pint --dirty` reports no formatting issues.

## Testing Strategy and Results

| Check | Expected result | Actual result |
| --- | --- | --- |
| Update product without images (multipart) | 200 OK | Pending |
| Update product with new images (multipart) | 200 OK, images stored | Pending |
| Update product with invalid image | 422, `images.0` error | Pending |
| Full `AdminCatalogTest` suite | All existing tests pass | Pending |
| Pint formatting | No violations | Pending |

## Risks, Dependencies, and Open Questions

- Changing `sometimes` to `nullable` is a minimal, safe change. `nullable` in Laravel means the field may be absent or `null`; it does not weaken the `images.*` sub-rules applied to any file that is actually present.
- The `StoreProductRequest` change is a proactive consistency fix. It is not strictly required by the reported bug, but the identical rule structure means the same failure mode could appear in the create flow under a different client or test environment.
- No migration, seeder, or dependency change is involved.

## Implementation Notes and Discoveries

*(To be filled in during implementation.)*

## Final Summary and Remaining Work

*(To be filled in on completion.)*

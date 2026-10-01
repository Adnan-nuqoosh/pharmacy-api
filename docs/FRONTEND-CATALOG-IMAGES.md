# Catalog images: frontend integration

Applies to **Adnan-nuqoosh/pharmacy-api**, based on commit `4d40943`.
This is not the KHAM Commerce project. Import the updated `ADMIN-API-openapi.json`
and `CUSTOMER-API-openapi.json` into Apidog and set your own API base URL.

## One upload contract for all three modules

| Resource | Create | Update with/without file | Response record |
| --- | --- | --- | --- |
| Products | `POST /api/admin/products` | `POST /api/admin/products/{id}` | `data.product` |
| Categories | `POST /api/admin/categories` | `POST /api/admin/categories/{id}` | `data.category` |
| Brands | `POST /api/admin/brands` | `POST /api/admin/brands/{id}` | `data.brand` |

- Use `multipart/form-data`, field **`image`**, for an actual File. JPEG/PNG/WebP,
  maximum **5 MB** for each resource. Images are optional.
- Product create also requires `name`, `category_id`, `price`; category and brand
  create require `name`. Use a unique optional `slug` (generated from name otherwise).
- Send `Authorization: Bearer <ADMIN_TOKEN>` and `Accept: application/json`.
- **Do not manually set Content-Type** with browser FormData: the browser adds the boundary.
- `POST /{id}` works directly; do not send `_method=PUT`. `PATCH /{id}` remains
  available for JSON-only updates. Do not use raw multipart PATCH/PUT.
- Send boolean fields as `1`/`0` in FormData. Do not send the strings `true`/`false`.
- A response record includes `image` (stored path) and **`image_url`** (absolute
  display URL or null). Use `image_url` as the `<img src>` value.
- To keep an existing image, omit `image`. A null/empty image also keeps it.
  Do not re-submit an existing URL as a file; it fails validation.
- To delete only an image, send **`remove_image=1`** in an update.
- Sending an image and `remove_image=1` together returns 422.
- Legacy category upload field `icon` still works; category responses retain
  `icon` and add `image`, `image_url`, `icon_url`. Existing image paths are preserved.
- Brand upload accepts `logo` as an alias for `image`. Send only one alias per request.

## Browser example

```js
async function saveCatalog({ apiBase, token, resource, id, fields, file, removeImage = false }) {
  // apiBase includes /api, e.g. https://your-api.example/api
  const body = new FormData();
  Object.entries(fields).forEach(([key, value]) => {
    if (value !== undefined) body.append(key, value === null ? '' :
      typeof value === 'boolean' ? (value ? '1' : '0') : String(value));
  });
  if (file && removeImage) throw new Error('Choose upload or removal, not both.');
  if (file) body.append('image', file);
  if (removeImage) body.append('remove_image', '1');
  const response = await fetch(`${apiBase.replace(/\/$/, '')}/admin/${resource}${id ? `/${id}` : ''}`, {
    method: 'POST',
    headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
    body,
  });
  const result = await response.json();
  if (!response.ok) throw new Error((result.errors || []).join('\n') || result.message);
  return result.data;
}
// Input: <input type="file" accept="image/jpeg,image/png,image/webp" />
// file = input.files[0]
// Product fields: {name: 'Vitamin C', category_id: 1, brand_id: 2, price: 25, is_active: true}
// Category / brand fields: {name: 'Example', is_active: true}
// Display: result.product.image_url (or category.image_url / brand.image_url)
```

## Brands and existing products

- Admin CRUD: `GET/POST /api/admin/brands`, `GET/POST/PATCH/DELETE /api/admin/brands/{id}`.
- Public: `GET /api/brands`, `GET /api/brands/{slug}`. Inactive brands are hidden here.
- Link a product using nullable `brand_id`. The selected brand name is also stored
  in the legacy `brand_name` field on product save. Display `product.brand.name`
  when a relation exists; `brand_name` is a legacy fallback, not a live brand reference.
- Product admin/public list endpoints support `?brand_id=2`.
- Existing `brand_name` text is preserved by migration. Brands are not automatically
  inferred: create brands and link old products explicitly.
- A brand with linked products cannot be deleted through the admin API. Reassign
  or unlink products first. Set `brand_id` to null (JSON) or empty string (FormData)
  to unlink. Unlinking clears the legacy name unless you explicitly supply a fallback.

## Admin panel

Products, categories and brands have file upload controls using the public disk.
Product forms include a brand selector. A separate custom frontend must implement
its own file picker using the API contract above; this repo does not contain that frontend.

## Deployment (preserves existing database records)

1. Back up the database and `storage/app/public` before deploying.
2. Deploy this branch / the changed files into the existing project.
3. Run:

```sh
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan storage:link
php artisan optimize:clear
```

Do not use `migrate:fresh`, reset the database, or replace the production `.env`.
Set `APP_URL` to the public backend origin (without `/api`). The public disk URL
is built from `APP_URL` plus `/storage`. Ensure public storage is writable and the
web server serves `public/storage`. Existing symlink messages from `storage:link`
are harmless when the correct link is already present.

Set PHP `upload_max_filesize` to at least `8M` and `post_max_size` to at least `16M`;
also check your web server's request-body limit. These are host settings, not repo changes.

Verify create, replace, remove, and public image display for all three modules.
Run the automated feature suite locally with `php artisan test --filter=CatalogImagesTest`.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| No file-picker in custom frontend | Add a file input and send its File via FormData. |
| 422 | Inspect `errors`; check field name, MIME type, size, IDs and unique slug. |
| 401 / 403 | Use a valid admin token, not a normal customer token. |
| 405 on update | Use POST directly, without `_method=PUT`. |
| Upload succeeds, image returns 404 | Verify APP_URL, storage symlink and public disk permissions. |
| Request rejected before Laravel | Check PHP and web-server body limits. |
| Brands table missing | Run the new migration on the correct database. |

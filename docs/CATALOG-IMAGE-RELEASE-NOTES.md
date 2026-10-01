# Catalog image update — 2026-10-01

Repository: Adnan-nuqoosh/pharmacy-api. Base commit: `4d409435da9c65fe3de41c19138d5ef9c8a96837`.

## Changes

- Products/categories/brands accept an `image` file and return an absolute `image_url`.
- Category `icon` requests and existing stored paths remain compatible.
- Brand CRUD, public brand listing/detail, logo alias, product brand linking/filtering.
- Optional `remove_image`, preservation on omitted/empty upload, replacement cleanup after DB save.
- Invalid uploads, conflicting aliases, removal plus upload and duplicate slugs return validation errors.
- Fixed malformed product/category update validation rules.
- Filament public-disk image controls, category label and new Brands screens/product selector.
- Updated Apidog/OpenAPI documents and frontend integration examples.

## Apply the changed-files ZIP

Extract its contents into the **root of your existing pharmacy-api checkout** (where `artisan`
and `composer.json` live), merging folders. Review the changes before deploying.
The ZIP contains changed/new files only, not a standalone application. It does not
contain production `.env`, databases, uploaded media or `vendor`.

Alternatively, from a clean checkout of the base commit, use the separate Git patch:

```sh
git switch -c fix/catalog-image-uploads
git apply --check /path/to/pharmacy-api-catalog-images.patch
git apply /path/to/pharmacy-api-catalog-images.patch
```

Then install dependencies as needed, run the new migration and configure storage
as documented in `FRONTEND-CATALOG-IMAGES.md`. Preserve production `.env` and storage.
A newer or locally modified checkout may require resolving differences; do not force-overwrite them.

## Verification

- PHP 8.3.6 with the existing composer.lock; no dependency versions changed.
- Catalog feature suite: 10 tests, 91 assertions passed.
- Full suite: 12 tests, 93 assertions passed, with a temporary test APP_KEY supplied.
- Upload/replace/remove/keep paths, public URL responses, legacy icon/logo aliases,
  invalid files and size limits, authorization, brand filtering/linking/deletion guard,
  inactive brands, slug collisions and simulated database failure cleanup covered.
- PHP syntax checks and brand API/admin route registration passed.

Local tests used isolated SQLite and fake storage. Production PHP/web-server limits,
public storage serving and the separate custom frontend still need deployment verification.
GitHub was not updated: write credentials were unavailable in this session.

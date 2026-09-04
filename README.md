# Abwab Al Kheir Pharmacy — Backend API

Laravel backend for the Abwab Al Kheir Pharmacy mobile app and admin dashboard.
Built from the Figma design — covers the customer app, an admin REST API, and a ready-made admin panel (Filament).

**113 endpoints** — 52 customer + 61 admin.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 12 (PHP 8.2+) |
| Auth | Laravel Sanctum (Bearer tokens) + Firebase Phone OTP |
| Database | MySQL |
| Admin Panel | Filament 3 |
| File Storage | Laravel Storage (`public` disk) |

---

## Requirements

- PHP 8.2 or higher
- **`ext-intl` enabled** (required by Filament — in XAMPP, uncomment `extension=intl` in `php.ini`)
- Composer
- MySQL 5.7+ / MariaDB

---

## Installation

```bash
# 1. Clone
git clone https://github.com/Adnan-nuqoosh/pharmacy-api.git
cd pharmacy-api

# 2. Dependencies
composer install

# 3. Environment
cp .env.example .env
php artisan key:generate
```

Set your database in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pharmacy
DB_USERNAME=root
DB_PASSWORD=
```

```bash
# 4. Database + demo data
php artisan migrate
php artisan db:seed --class=AdminUserSeeder
php artisan db:seed --class=CatalogSeeder
php artisan db:seed --class=BannerSeeder
php artisan db:seed --class=ContentSeeder

# 5. Storage symlink (REQUIRED for image uploads)
php artisan storage:link

# 6. Run
php artisan serve
```

API is now at **`http://localhost:8000/api`**
Admin panel at **`http://localhost:8000/admin`**

### Seeded demo data
- 8 categories, 9 products, 2 banners
- 5 doctors with 7 days of appointment slots
- 6 rentable equipment items
- 8 FAQs
- 1 admin account (see [Authentication](#authentication) — credentials are not published here)

---

## API Documentation

Two OpenAPI 3.0 specs are in the [`docs/`](docs) folder. Import either into **Apidog**, **Postman**, or **Swagger UI**:

| File | Scope |
|---|---|
| `docs/CUSTOMER-API-openapi.json` | Mobile app — 52 endpoints |
| `docs/ADMIN-API-openapi.json` | Admin dashboard — 61 endpoints |

**Importing into Apidog:** Settings → Import Data → OpenAPI/Swagger → select the file → Confirm.

---

## Authentication

All protected endpoints expect these headers:

```
Authorization: Bearer <token>
Accept: application/json
```

`Accept: application/json` matters — without it Laravel returns HTML instead of JSON on validation errors.

### Customer

Three ways to sign up / log in — the client can offer any or all of them:

```http
POST /api/auth/register        # email + password
POST /api/auth/login           # accepts email OR phone in the "login" field
POST /api/auth/google           # Google Sign-In (send Google's id_token)
POST /api/auth/otp/verify      # Firebase Phone OTP — signup AND login in one call
```

Accounts created via `/auth/otp/verify` have no password (`password` is nullable) — Firebase handles verification instead. Accounts created via `/auth/register` use the normal email/password flow. Both can log in with `/auth/login` if a password is set.

**Forgot password** (email/password accounts only — OTP accounts don't need one). Three separate steps, matching the app's UI flow (Email → Enter OTP → New Password):

```http
POST /api/auth/forgot-password    # Step 1: { "email" } -> emails a 6-digit code, valid 15 min
POST /api/auth/verify-otp         # Step 2: { "email", "code" } -> returns a reset_token
POST /api/auth/reset-password     # Step 3: { "email", "reset_token", "password", "password_confirmation" }
```

The 6-digit code from Step 1 is single-use — the moment it's verified in Step 2, it's replaced by a `reset_token` (valid 10 minutes) that Step 3 requires instead. The original code cannot be reused for Step 3, even if intercepted.

`forgot-password`'s response is identical whether or not the email exists, to prevent user enumeration. Locally, `MAIL_MAILER=log` means the code is written to `storage/logs/laravel.log` instead of being emailed — search for "reset code" to find it while testing.

### Admin

```http
POST /api/admin/login
```

Admin credentials are intentionally not published in this README. They're set in `database/seeders/AdminUserSeeder.php` — **change the password there, and again before deploying to production.** Never commit real credentials, tokens, or secrets to the repository.

Admin access is protected at two levels: `/api/admin/login` rejects non-admin users even with correct credentials, and the `is_admin` middleware blocks customer tokens on every admin route.

---

## Endpoint Overview

### Customer API

| Area | Endpoints |
|---|---|
| **Auth** | register, login, google, otp/verify, forgot-password, verify-otp, reset-password, me, logout |
| **Home** | banners |
| **Catalog** | categories, category detail, products (search/filter/sort), product detail |
| **Reviews** | list with star breakdown, create, update, delete, my-reviews |
| **Cart** | view with totals, add, update quantity, remove, clear |
| **Addresses** | list, create, update, delete |
| **Orders** | checkout, list, detail, cancel, billing summary |
| **Prescriptions** | upload (both flows), list, detail, cancel |
| **Doctors** | list, specialities, detail with slots, book, my appointments, cancel |
| **Equipment** | list, detail, rent, my rentals, cancel |
| **Profile** | update, change password, FAQs |

### Admin API

| Area | Endpoints |
|---|---|
| **Dashboard** | stats, sales chart, top products, recent orders |
| **Users** | list, detail with order history |
| **Products** | full CRUD + image upload, toggle active |
| **Categories** | full CRUD + icon upload, drag-drop reorder |
| **Banners** | full CRUD + image upload |
| **Orders** | list with filters, detail, status update |
| **Prescriptions** | list, detail, approve, reject, stats |
| **Doctors** | CRUD, bulk slot creation, appointments management |
| **Equipment** | CRUD, rentals management |
| **FAQ** | CRUD |
| **Reviews** | moderation (approve / hide / delete) |

---

## Business Rules

Enforced server-side — the frontend doesn't need to replicate them, but should expect the resulting errors.

- **Delivery fee** — free above AED 100, otherwise AED 10. Configurable in `CartController::summary()`.
- **Product pricing** — every product response includes `final_price` (after discount) and `on_sale`, so the client never calculates prices.
- **Stock** — decremented at checkout; restored automatically when an order is cancelled by either the customer or an admin.
- **Order cancellation** — customers can cancel only while `pending` or `confirmed`. Admins cannot change a `delivered` or `cancelled` order.
- **Reviews** — a user can only review a product they have actually purchased, and only once. Ratings recalculate automatically on every change.
- **Appointments** — slots are locked during booking, so two users cannot book the same slot. Cancelling frees the slot again.
- **Categories** — a category containing products cannot be deleted (returns 422) to prevent cascade-deleting the products.
- **Prescriptions** — for the `with_prescription` flow, prescription images and both sides of the Emirates ID are mandatory (UAE regulatory requirement).
- **Password reset** — the Step 1 code and the Step 2 `reset_token` are each single-use with their own expiry (15 min / 10 min). A successful reset revokes all of that user's existing login sessions.

---

## Prescription Upload

`POST /api/prescriptions` — `multipart/form-data`. Two flows, matching the Figma Rx Upload screen.

**A) "I have a valid UAE Prescription"**

| Field | Required | Notes |
|---|---|---|
| `request_type` | ✅ | `with_prescription` |
| `prescriptions[]` | ✅ | 1–5 images |
| `emirates_id_front` | ✅ | image |
| `emirates_id_back` | ✅ | image |
| `insurance_card_front` | ➖ | optional |
| `insurance_card_back` | ➖ | optional |
| `address_id` | ✅ | |
| `payment_method` | ✅ | `online` or `cod` |
| `delivery_preference` | ➖ | |
| `notes` | ➖ | |

**B) "I don't have a Prescription"**

| Field | Required | Notes |
|---|---|---|
| `request_type` | ✅ | `without_prescription` |
| `images[]` | ✅ | 1–5 images |
| `address_id` | ✅ | |
| `payment_method` | ✅ | |
| `notes` | ➖ | what the customer is looking for |

Images (max 5 MB each) accept `jpg`, `jpeg`, `png`, `webp`. Responses include ready-to-use URLs (`image_url`, `emirates_id_front_url`, …).

---

## Response Format

Every successful response returns:

```json
{
  "success": true,
  "message": "Logged in successfully.",
  "data": { }
}
```

Every error response — including Laravel's own built-in errors (validation, auth, 404, rate limits) — is normalized to the same shape with an added `errors` array:

```json
{
  "success": false,
  "message": "The given data was invalid.",
  "data": null,
  "errors": ["The email has already been taken."]
}
```

| Code | Meaning |
|---|---|
| 200 | OK |
| 201 | Created |
| 401 | Unauthenticated — missing or invalid token |
| 403 | Forbidden — not yours, or admin-only |
| 404 | Resource or route not found |
| 422 | Validation error or business rule violation |
| 429 | Too many attempts (login is rate-limited to 5/min) |
| 500 | Server error |

This normalization is handled centrally in `bootstrap/app.php` (`withExceptions`), so individual controllers don't need to format error responses themselves.

---

## Image Uploads

HTML forms can't send files over `PUT`/`PATCH`, so update endpoints accept both:

| Method | Use |
|---|---|
| `POST /api/admin/products/{id}` | update **with** an image (`multipart/form-data`) |
| `PATCH /api/admin/products/{id}` | update **without** an image (JSON) |

The same pattern applies to categories, banners, doctors, and equipment.

---

## Google Sign-In Setup

Optional — everything else works without it.

Add to `config/services.php`:

```php
'google' => [
    'client_id' => env('GOOGLE_CLIENT_ID'),
],
```

Add to `.env`:

```env
GOOGLE_CLIENT_ID=xxxxxxxxx.apps.googleusercontent.com
```

Get the client ID from [Google Cloud Console](https://console.cloud.google.com) → APIs & Services → Credentials → OAuth client ID.

**Flow:** the client signs in with the Google SDK, then posts the resulting `id_token` to `POST /api/auth/google`. The backend verifies it with Google, checks the audience and email verification, and returns a Sanctum token. New users are created automatically; the response includes `needs_phone` so the client can prompt for a phone number.

---

## Firebase Phone OTP Setup

Powers `POST /api/auth/otp/verify`, used for both sign-up and login. There is **no separate "send OTP" endpoint for this flow** — OTP delivery and verification happen entirely on the client via the Firebase SDK; the backend only verifies the resulting token. (This is unrelated to the email-based Forgot Password OTP above, which the backend generates and sends itself.)

Add to `config/services.php`:

```php
'firebase' => [
    'api_key' => env('FIREBASE_API_KEY'),
],
```

Add to `.env`:

```env
FIREBASE_API_KEY=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

Get the key from [Firebase Console](https://console.firebase.google.com) → Project Settings → General → **Web API Key**. Enable Authentication → Sign-in method → Phone.

**Flow:**
1. Mobile app uses the Firebase SDK to send the OTP and let the user verify it — Laravel is not involved in this step.
2. Firebase returns an `id_token` to the client on success.
3. The client posts that token here:
   ```json
   { "id_token": "eyJhbGci...", "name": "Ahmed Kamal" }
   ```
4. Laravel verifies the token against Firebase's `accounts:lookup` endpoint, confirms the audience and phone number, then creates or logs in the matching user.

`name` is only required the first time a phone number is used (sign-up); existing phone numbers just log in.

> Firebase's free (Spark) tier caps new projects at 10 SMS/day. Add a billing account (Blaze plan) before going live — Firebase still gives a free monthly allowance, then charges per SMS.

---

## Admin Panel (Filament)

A ready-made web panel at `/admin` for pharmacy staff — no frontend work needed.

Manage products, categories, banners, orders, and prescriptions with image uploads, drag-drop reordering, approve/reject actions, and a stats dashboard.

To promote an existing user to admin:

```bash
php artisan tinker
```
```php
App\Models\User::where('email', 'staff@example.com')->update(['is_admin' => true]);
```

---

## CORS

If the frontend runs on a different origin, add it in `config/cors.php`:

```php
'paths' => ['api/*'],
'allowed_origins' => ['http://localhost:3000'],
```

---

## Not Yet Implemented

- **Online payment gateway** — `payment_method: online` is accepted and stored, but no gateway is wired up. A provider still needs to be chosen (Telr, PayTabs, Network International, Stripe) and credentials supplied.
- **Push notifications** — no screen existed in the design.
- **Phone-based password reset** — Forgot Password currently supports email accounts only. OTP-only accounts (signed up via Firebase) don't have a password to reset.
- **Production email delivery** — password reset codes currently rely on `MAIL_MAILER=log` locally. Set a real mailer (SMTP, Mailgun, SES, …) in `.env` before launch, or codes will never reach real users.

---

## Project Structure

```
app/
├── Http/
│   ├── Controllers/Api/          # Customer API
│   │   └── Admin/                # Admin API
│   ├── Middleware/IsAdmin.php    # Admin route guard
│   └── Requests/                 # Form request validation
├── Models/                       # 16 Eloquent models
└── Filament/                     # Admin panel resources
database/
├── migrations/
└── seeders/
docs/                             # OpenAPI specs
routes/api.php                    # All 113 endpoints
```

---

## Troubleshooting

| Problem | Fix |
|---|---|
| `Target class [is_admin] does not exist` | Register the alias in `bootstrap/app.php` (see below) |
| HTML returned instead of JSON | Add the `Accept: application/json` header |
| 404 on every route | `php artisan optimize:clear` |
| Images return broken URLs | `php artisan storage:link` |
| Filament install fails on `ext-intl` | Enable `extension=intl` in `php.ini`, restart |
| Migration for nullable `password` fails | `composer require doctrine/dbal` — needed for `->change()` in migrations |
| Firebase OTP returns 401 | Token expired, or `FIREBASE_API_KEY` is missing/wrong in `.env` |
| Password reset code never arrives | Check `storage/logs/laravel.log` (search "reset code") while `MAIL_MAILER=log`; set a real mailer for production |
| `reset-password` says "Pehle OTP verify karein" | Step 2 (`verify-otp`) was skipped, or the 6-digit code was sent instead of the `reset_token` it returns |
| 401 right after a password reset | Expected — resetting a password revokes all of that user's existing tokens. Log in again for a fresh token. |

The middleware alias in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'is_admin' => \App\Http\Middleware\IsAdmin::class,
    ]);
})
```
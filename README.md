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

```

DB_CONNECTION=mysql

DB_HOST=127.0.0.1

DB_PORT=3306

DB_DATABASE=pharmacy

DB_USERNAME=root

DB_PASSWORD=

```

Add the Firebase service-account configuration to `.env` (never commit the
real credentials):

```env
FIREBASE_PROJECT_ID=your-firebase-project-id
FIREBASE_CREDENTIALS=storage/app/firebase/service-account.json
```

Download the service-account JSON from Firebase Console and save it at
`storage/app/firebase/service-account.json`. Keep this file out of Git by
adding it to `.gitignore`.

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

- An admin account is created by `AdminUserSeeder`, but no password is
published in this README. Set the credentials securely through environment
variables or create/reset the account locally before first use.

- 8 categories, 9 products, 2 banners

- 5 doctors with 7 days of appointment slots

- 6 rentable equipment items

- 8 FAQs

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

```http

POST /api/auth/register

POST /api/auth/login      # accepts email OR phone in the "login" field

POST /api/auth/google     # Google Sign-In (send Google's id_token)

POST /api/auth/otp/send            # send Firebase OTP to a phone number

POST /api/auth/otp/verify          # verify Firebase ID token and login/register

POST /api/auth/forgot-password     # send password-reset OTP/link

POST /api/auth/reset-password      # verify reset proof and set new password

```

### Admin

```http

POST /api/admin/login

```

Admin credentials are intentionally not documented. Configure them securely
when running the seeder, or create/reset an administrator from the server. Do
not commit passwords, Firebase service-account JSON files, tokens, or other
secrets to Git.

Admin access is protected at two levels: `/api/admin/login` rejects non-admin users even with correct credentials, and the `is_admin` middleware blocks customer tokens on every admin route.

---

## Endpoint Overview

### Customer API

| Area | Endpoints |

|---|---|

| **Auth** | register, login, google, me, logout |
| **Phone & password recovery** | send OTP, verify OTP, forgot password, reset password |

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

These are enforced server-side — the frontend doesn't need to replicate them, but should expect the resulting errors.

- **Delivery fee** — free above AED 100, otherwise AED 10. Configurable in `CartController::summary()`.

- **Product pricing** — every product response includes `final_price` (after discount) and `on_sale`, so the client never calculates prices.

- **Stock** — decremented at checkout; restored automatically when an order is cancelled by either the customer or an admin.

- **Order cancellation** — customers can cancel only while `pending` or `confirmed`. Admins cannot change a `delivered` or `cancelled` order.

- **Reviews** — a user can only review a product they have actually purchased, and only once. Ratings recalculate automatically on every change.

- **Appointments** — slots are locked during booking, so two users cannot book the same slot. Cancelling frees the slot again.

- **Categories** — a category containing products cannot be deleted (returns 422) to prevent cascade-deleting the products.

- **Prescriptions** — for the `with_prescription` flow, prescription images and both sides of the Emirates ID are mandatory (UAE regulatory requirement).

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

Every endpoint returns the same shape:

```json

{

  "success": true,

  "message": "Logged in successfully.",

  "data": { }

}

```

| Code | Meaning |

|---|---|

| 200 | OK |

| 201 | Created |

| 401 | Unauthenticated — missing or invalid token |

| 403 | Forbidden — not yours, or admin-only |

| 422 | Validation error or business rule violation |

| 429 | Too many attempts (login is rate-limited to 5/min) |

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

```

GOOGLE_CLIENT_ID=xxxxxxxxx.apps.googleusercontent.com

```

Get the client ID from [Google Cloud Console](https://console.cloud.google.com) → APIs & Services → Credentials → OAuth client ID.

**Flow:** the client signs in with the Google SDK, then posts the resulting `id_token` to `POST /api/auth/google`. The backend verifies it with Google, checks the audience and email verification, and returns a Sanctum token. New users are created automatically; the response includes `needs_phone` so the client can prompt for a phone number.

---

## Firebase Phone OTP Setup

Firebase Phone Authentication handles OTP delivery and verification. The
mobile app must use the Firebase SDK to request the OTP and complete the code
verification. After successful verification, the app sends the Firebase
`id_token` to the Laravel API. Laravel verifies that token with the Firebase
Admin SDK before issuing a Sanctum token.

Open Firebase Console, create or
select the project, and enable Authentication → Sign-in method → Phone.

Register the Android and/or iOS application in Firebase.

Add the platform configuration files to the mobile app:
`google-services.json` for Android and `GoogleService-Info.plist` for iOS.

Generate a Firebase Admin service-account key for the backend and store it
outside the public directory.

Install and configure the Firebase Admin SDK used by the Laravel project.

### Send OTP

`POST /api/auth/otp/send`

```json
{
"phone": "+971501234567"
}
```

Use E.164 format for all phone numbers. OTP requests must be rate-limited and
Firebase App Check should be enabled in production to reduce abuse.

### Verify OTP and authenticate

After Firebase confirms the OTP on the client, send its ID token to:

`POST /api/auth/otp/verify`

```json
{
"id_token": "firebase-id-token"
}
```

The backend verifies the token signature, issuer, audience, expiry, and phone
number. If the phone belongs to an existing customer, that customer is logged
in. Otherwise, a customer account is created and the response can include
`needs_profile: true`. A Sanctum bearer token is returned on success.

Never accept a phone number as verified merely because the client sends it;
trust only the phone number contained in a valid Firebase token.

---

## Forgot Password

Password recovery supports accounts that use a password while keeping account
existence private.

### Request reset

`POST /api/auth/forgot-password`

```json
{
"login": "customer@example.com"
}
```

The `login` value may be an email address or an E.164 phone number. The API
always returns a neutral response such as If the account exists, reset
instructions have been sent. This prevents account enumeration.

Email accounts receive a short-lived, one-time Laravel password-reset link or token.

Phone accounts complete Firebase OTP verification and submit the resulting Firebase ID token as reset proof.

### Set a new password

`POST /api/auth/reset-password`

Email reset example:

```json
{
"email": "customer@example.com",
"token": "one-time-reset-token",
"password": "new-strong-password",
"password_confirmation": "new-strong-password"
}
```

Phone reset example:

```json
{
"phone": "+971501234567",
"firebase_id_token": "verified-firebase-id-token",
"password": "new-strong-password",
"password_confirmation": "new-strong-password"
}
```

Reset tokens/proofs are single-use and short-lived. After a successful reset,
all existing Sanctum tokens for that user are revoked so previously logged-in
devices must authenticate again. OTP and reset endpoints must be rate-limited.

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

---

## Project Structure

```

app/

├── Http/

│   ├── Controllers/Api/          # Customer API

│   │   └── Admin/                # Admin API

│   ├── Middleware/IsAdmin.php    # Admin route guard

│   └── Requests/                 # Form request validation

├── Models/                       # 16 Eloquent models

└── Filament/                     # Admin panel resources

database/

├── migrations/

└── seeders/

docs/                             # OpenAPI specs

routes/api.php                    # All 109 endpoints

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
| Firebase OTP fails | Confirm Phone Authentication is enabled, the app configuration matches the Firebase project, and the backend service account is valid |
| Firebase token is rejected | Send a fresh Firebase ID token and verify its project ID/audience matches `FIREBASE_PROJECT_ID` |
| Forgot-password response is always generic | This is intentional and prevents attackers from discovering registered accounts |

The middleware alias in `bootstrap/app.php`:

```php

->withMiddleware(function (Middleware $middleware) {

    $middleware->alias([

        'is_admin' => \App\Http\Middleware\IsAdmin::class,

    ]);

})

```
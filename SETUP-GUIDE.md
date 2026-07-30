# Abwab Al Kheir Pharmacy — Auth API (Laravel)

## 1. Project Setup (ek dafa)

```bash
# Naya Laravel project
composer create-project laravel/laravel pharmacy-api
cd pharmacy-api

# Sanctum install (API tokens ke liye)
php artisan install:api
# Ye command routes/api.php bana deti hai aur Sanctum install kar deti hai
```

## 2. Files Copy Karein

Is folder ki files apne project mein in jagahon par rakhein:

| File | Kahan rakhein |
|---|---|
| `app/Models/User.php` | Replace existing |
| `app/Http/Controllers/Api/AuthController.php` | Nayi file |
| `app/Http/Requests/RegisterRequest.php` | Nayi file |
| `app/Http/Requests/LoginRequest.php` | Nayi file |
| `database/migrations/2026_07_16_000001_add_phone_to_users_table.php` | Nayi file |
| `routes/api.php` | Replace / merge |

## 3. Database + Migration

`.env` mein database set karein:

```
DB_CONNECTION=mysql
DB_DATABASE=pharmacy
DB_USERNAME=root
DB_PASSWORD=
```

Phir:

```bash
php artisan migrate
php artisan serve
```

## 4. Endpoints

Base URL: `http://localhost:8000/api`

### POST /api/auth/register  (Sign Up screen)
Request body (JSON):
```json
{
  "name": "Savannah Nguyen",
  "email": "savannah@example.com",
  "phone": "+971501234567",
  "password": "secret123",
  "password_confirmation": "secret123"
}
```
Response 201:
```json
{
  "success": true,
  "message": "Account created successfully.",
  "data": {
    "user": { "id": 1, "name": "Savannah Nguyen", "email": "...", "phone": "..." },
    "token": "1|xxxxxxxxxxxxxxxxxxxx"
  }
}
```

### POST /api/auth/login  (Log In screen)
Email **ya** phone — dono chalte hain:
```json
{ "login": "savannah@example.com", "password": "secret123" }
```
ya
```json
{ "login": "+971501234567", "password": "secret123" }
```

### GET /api/auth/me  (Profile screen)
Header: `Authorization: Bearer <token>`

### POST /api/auth/logout
Header: `Authorization: Bearer <token>`

## 5. Frontend (React/Next.js) se Fetch

```javascript
// Login example
const res = await fetch("http://localhost:8000/api/auth/login", {
  method: "POST",
  headers: {
    "Content-Type": "application/json",
    "Accept": "application/json", // zaroori hai — Laravel isi se JSON errors deta hai
  },
  body: JSON.stringify({ login: email, password }),
});

const data = await res.json();

if (data.success) {
  // Token save karein (localStorage ya secure cookie)
  localStorage.setItem("token", data.data.token);
}

// Protected request example (Profile)
const me = await fetch("http://localhost:8000/api/auth/me", {
  headers: {
    "Accept": "application/json",
    "Authorization": `Bearer ${localStorage.getItem("token")}`,
  },
});
```

## 6. CORS (frontend alag domain par ho to)

`config/cors.php` mein:
```php
'paths' => ['api/*'],
'allowed_origins' => ['http://localhost:3000'], // apna frontend URL
```

## 7. Testing (curl)

```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"name":"Test User","email":"test@test.com","phone":"+971501234567","password":"secret123","password_confirmation":"secret123"}'
```

## Aage kya banega (roadmap)
1. ✅ Auth (register/login/logout/me)
2. ⬜ Forgot Password / OTP verification
3. ⬜ Categories API
4. ⬜ Products API (list, detail, search)
5. ⬜ Cart API
6. ⬜ Prescription upload API
7. ⬜ Checkout / Orders API
8. ⬜ Profile update API

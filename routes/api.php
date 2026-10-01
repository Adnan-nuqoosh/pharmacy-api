<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\Admin\AdminBrandController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\Admin\AdminAuthController;
use App\Http\Controllers\Api\Admin\AdminBannerController;
use App\Http\Controllers\Api\Admin\AdminCategoryController;
use App\Http\Controllers\Api\Admin\AdminContentController;
use App\Http\Controllers\Api\Admin\AdminDashboardController;
use App\Http\Controllers\Api\Admin\AdminOrderController;
use App\Http\Controllers\Api\Admin\AdminPrescriptionController;
use App\Http\Controllers\Api\Admin\AdminProductController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\EquipmentController;
use App\Http\Controllers\Api\FaqController;
use App\Http\Controllers\Api\FirebaseAuthController;
use App\Http\Controllers\Api\ForgotPasswordController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PrescriptionController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SocialAuthController;
use Illuminate\Support\Facades\Route;

/*
|==========================================================================
| CUSTOMER APIs (mobile app)
|==========================================================================
*/

// ---------- Auth ----------
Route::prefix('auth')->group(function () {
    Route::post('/register',   [AuthController::class, 'register']);
    Route::post('/login',      [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/google',     [SocialAuthController::class, 'google'])->middleware('throttle:10,1'); // "Sign up with Google"
    Route::post('/otp/verify', [FirebaseAuthController::class, 'verify'])->middleware('throttle:10,1'); // Sign Up + Login via Firebase OTP

    Route::post('/forgot-password', [ForgotPasswordController::class, 'sendCode'])->middleware('throttle:3,1'); // Forgot Password step 1
    Route::post('/reset-password',  [ForgotPasswordController::class, 'reset'])->middleware('throttle:5,1');    // Forgot Password step 2
    Route::post('/verify-otp', [ForgotPasswordController::class, 'verifyOtp'])->middleware('throttle:5,1');
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me',      [AuthController::class, 'me']);
    });
});

// ---------- Public: Home / Catalog ----------
Route::get('/banners',           [BannerController::class, 'index']);
Route::get('/categories',        [CategoryController::class, 'index']);
Route::get('/categories/{slug}', [CategoryController::class, 'show']);
Route::get('/products',          [ProductController::class, 'index']);
Route::get('/products/{slug}',   [ProductController::class, 'show']);

Route::get('/brands', [BrandController::class, 'index']);
Route::get('/brands/{slug}', [BrandController::class, 'show']);

// Reviews (list public, likhna login se)
Route::get('/products/{slug}/reviews', [ReviewController::class, 'index']);

// ---------- Public: Doctors / Equipment / FAQ ----------
Route::get('/doctors',              [DoctorController::class, 'index']);
Route::get('/doctors/specialities', [DoctorController::class, 'specialities']); // {doctor} se pehle
Route::get('/doctors/{doctor}',     [DoctorController::class, 'show']);

Route::get('/equipment',        [EquipmentController::class, 'index']);
Route::get('/equipment/{slug}', [EquipmentController::class, 'show']);

Route::get('/faqs', [FaqController::class, 'index']);

// ---------- Protected (customer login) ----------
Route::middleware('auth:sanctum')->group(function () {

    // Cart
    Route::get('/cart',               [CartController::class, 'index']);
    Route::post('/cart',              [CartController::class, 'store']);
    Route::patch('/cart/{cartItem}',  [CartController::class, 'update']);
    Route::delete('/cart/{cartItem}', [CartController::class, 'destroy']);
    Route::delete('/cart',            [CartController::class, 'clear']);

    // Addresses
    Route::get('/addresses',              [AddressController::class, 'index']);
    Route::post('/addresses',             [AddressController::class, 'store']);
    Route::patch('/addresses/{address}',  [AddressController::class, 'update']);
    Route::delete('/addresses/{address}', [AddressController::class, 'destroy']);

    // Checkout / Orders / Billing
    Route::post('/checkout',               [OrderController::class, 'checkout']);
    Route::get('/orders',                  [OrderController::class, 'index']);
    Route::get('/orders/{order}',          [OrderController::class, 'show']);
    Route::patch('/orders/{order}/cancel', [OrderController::class, 'cancel']);
    Route::get('/billing',                 [OrderController::class, 'billing']); // Profile > Billing

    // Prescriptions (Rx Upload — poora flow)
    Route::post('/prescriptions',                  [PrescriptionController::class, 'store']);
    Route::get('/prescriptions',                   [PrescriptionController::class, 'index']);
    Route::get('/prescriptions/{prescription}',    [PrescriptionController::class, 'show']);
    Route::delete('/prescriptions/{prescription}', [PrescriptionController::class, 'destroy']);

    // Reviews
    Route::post('/products/{slug}/reviews', [ReviewController::class, 'store']);
    Route::patch('/reviews/{review}',       [ReviewController::class, 'update']);
    Route::delete('/reviews/{review}',      [ReviewController::class, 'destroy']);
    Route::get('/my-reviews',               [ReviewController::class, 'myReviews']);

    // Doctor appointments
    Route::post('/appointments',                       [DoctorController::class, 'book']);
    Route::get('/appointments',                        [DoctorController::class, 'myAppointments']);
    Route::get('/appointments/{appointment}',          [DoctorController::class, 'showAppointment']);
    Route::patch('/appointments/{appointment}/cancel', [DoctorController::class, 'cancel']);

    // Equipment rentals
    Route::get('/equipment/rentals/my',                [EquipmentController::class, 'myRentals']);
    Route::post('/equipment/rentals',                  [EquipmentController::class, 'rent']);
    Route::patch('/equipment/rentals/{rental}/cancel', [EquipmentController::class, 'cancelRental']);

    // Profile
    Route::patch('/profile',                [ProfileController::class, 'update']);
    Route::post('/profile/change-password', [ProfileController::class, 'changePassword']);
});

/*
|==========================================================================
| ADMIN APIs
|==========================================================================
*/

Route::prefix('admin')->group(function () {

    Route::post('/login', [AdminAuthController::class, 'login'])->middleware('throttle:5,1');

    Route::middleware(['auth:sanctum', 'is_admin'])->group(function () {

        Route::post('/logout', [AdminAuthController::class, 'logout']);
        Route::get('/me',      [AdminAuthController::class, 'me']);

        // ---- Dashboard ----
        Route::get('/dashboard/stats',         [AdminDashboardController::class, 'stats']);
        Route::get('/dashboard/sales-chart',   [AdminDashboardController::class, 'salesChart']);
        Route::get('/dashboard/top-products',  [AdminDashboardController::class, 'topProducts']);
        Route::get('/dashboard/recent-orders', [AdminDashboardController::class, 'recentOrders']);

        // ---- Users ----
        Route::get('/users',        [AdminDashboardController::class, 'users']);
        Route::get('/users/{user}', [AdminDashboardController::class, 'userDetail']);

        // ---- Products ----
        Route::get('/products',              [AdminProductController::class, 'index']);
        Route::post('/products',             [AdminProductController::class, 'store']);
        Route::get('/products/{product}',    [AdminProductController::class, 'show']);
        Route::post('/products/{product}',   [AdminProductController::class, 'update']);
        Route::patch('/products/{product}',  [AdminProductController::class, 'update']);
        Route::delete('/products/{product}', [AdminProductController::class, 'destroy']);
        Route::patch('/products/{product}/toggle-active', [AdminProductController::class, 'toggleActive']);

        // ---- Brands ----
        Route::get('/brands', [AdminBrandController::class, 'index']);
        Route::post('/brands', [AdminBrandController::class, 'store']);
        Route::get('/brands/{brand}', [AdminBrandController::class, 'show']);
        Route::post('/brands/{brand}', [AdminBrandController::class, 'update']);
        Route::patch('/brands/{brand}', [AdminBrandController::class, 'update']);
        Route::delete('/brands/{brand}', [AdminBrandController::class, 'destroy']);

        // ---- Categories ----
        Route::get('/categories',               [AdminCategoryController::class, 'index']);
        Route::post('/categories/reorder',      [AdminCategoryController::class, 'reorder']);
        Route::post('/categories',              [AdminCategoryController::class, 'store']);
        Route::get('/categories/{category}',    [AdminCategoryController::class, 'show']);
        Route::post('/categories/{category}',   [AdminCategoryController::class, 'update']);
        Route::patch('/categories/{category}',  [AdminCategoryController::class, 'update']);
        Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy']);

        // ---- Banners ----
        Route::get('/banners',            [AdminBannerController::class, 'index']);
        Route::post('/banners',           [AdminBannerController::class, 'store']);
        Route::get('/banners/{banner}',   [AdminBannerController::class, 'show']);
        Route::post('/banners/{banner}',  [AdminBannerController::class, 'update']);
        Route::patch('/banners/{banner}', [AdminBannerController::class, 'update']);
        Route::delete('/banners/{banner}',[AdminBannerController::class, 'destroy']);

        // ---- Orders ----
        Route::get('/orders/stats/summary',    [AdminOrderController::class, 'stats']);
        Route::get('/orders',                  [AdminOrderController::class, 'index']);
        Route::get('/orders/{order}',          [AdminOrderController::class, 'show']);
        Route::patch('/orders/{order}/status', [AdminOrderController::class, 'updateStatus']);

        // ---- Prescriptions ----
        Route::get('/prescriptions/stats/summary',            [AdminPrescriptionController::class, 'stats']);
        Route::get('/prescriptions',                          [AdminPrescriptionController::class, 'index']);
        Route::get('/prescriptions/{prescription}',           [AdminPrescriptionController::class, 'show']);
        Route::patch('/prescriptions/{prescription}/approve', [AdminPrescriptionController::class, 'approve']);
        Route::patch('/prescriptions/{prescription}/reject',  [AdminPrescriptionController::class, 'reject']);

        // ---- Doctors & Appointments ----
        Route::get('/doctors',             [AdminContentController::class, 'doctors']);
        Route::post('/doctors',            [AdminContentController::class, 'storeDoctor']);
        Route::post('/doctors/{doctor}',   [AdminContentController::class, 'updateDoctor']);   // image ke sath
        Route::patch('/doctors/{doctor}',  [AdminContentController::class, 'updateDoctor']);   // JSON
        Route::delete('/doctors/{doctor}', [AdminContentController::class, 'destroyDoctor']);
        Route::post('/doctors/{doctor}/slots', [AdminContentController::class, 'createSlots']);
        Route::delete('/doctor-slots/{slot}',  [AdminContentController::class, 'deleteSlot']);

        Route::get('/appointments',                        [AdminContentController::class, 'appointments']);
        Route::patch('/appointments/{appointment}/status', [AdminContentController::class, 'updateAppointmentStatus']);

        // ---- Equipment & Rentals ----
        Route::get('/equipment',                [AdminContentController::class, 'equipment']);
        Route::post('/equipment',               [AdminContentController::class, 'storeEquipment']);
        Route::post('/equipment/{equipment}',   [AdminContentController::class, 'updateEquipment']);  // image ke sath
        Route::patch('/equipment/{equipment}',  [AdminContentController::class, 'updateEquipment']);  // JSON
        Route::delete('/equipment/{equipment}', [AdminContentController::class, 'destroyEquipment']);

        Route::get('/rentals',                   [AdminContentController::class, 'rentals']);
        Route::patch('/rentals/{rental}/status', [AdminContentController::class, 'updateRentalStatus']);

        // ---- FAQ ----
        Route::get('/faqs',         [AdminContentController::class, 'faqs']);
        Route::post('/faqs',        [AdminContentController::class, 'storeFaq']);
        Route::patch('/faqs/{faq}', [AdminContentController::class, 'updateFaq']);
        Route::delete('/faqs/{faq}',[AdminContentController::class, 'destroyFaq']);

        // ---- Reviews (moderation) ----
        Route::get('/reviews',                            [AdminContentController::class, 'reviews']);
        Route::patch('/reviews/{review}/toggle-approve',  [AdminContentController::class, 'toggleApproveReview']);
        Route::delete('/reviews/{review}',                [AdminContentController::class, 'destroyReview']);
    });
});
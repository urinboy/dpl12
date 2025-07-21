<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CityController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\FileUploadController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // Authentication routes
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);

        // Protected routes
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
            Route::get('profile', [AuthController::class, 'profile']);
        });
    });

    // Public routes - Categories
    Route::prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index']);
        Route::get('/main', [CategoryController::class, 'main']);
        Route::get('/{id}', [CategoryController::class, 'show']);
    });

    // Public routes - Products
    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index']);
        Route::get('/featured', [ProductController::class, 'featured']);
        Route::get('/search', [ProductController::class, 'search']);
        Route::get('/category/{categoryId}', [ProductController::class, 'byCategory']);
        Route::get('/{id}', [ProductController::class, 'show']);
        
        // Product reviews (public access)
        Route::get('/{product}/reviews', [ReviewController::class, 'index']);
    });

    // Public routes - Cities
    Route::prefix('cities')->group(function () {
        Route::get('/', [CityController::class, 'index']);
        Route::get('/delivery-available', [CityController::class, 'deliveryAvailable']);
        Route::get('/{id}', [CityController::class, 'show']);
        Route::get('/{id}/delivery-fee', [CityController::class, 'deliveryFee']);
    });

    // Protected routes (auth:sanctum middleware)
    Route::middleware('auth:sanctum')->group(function () {

        // Profile management
        Route::prefix('profile')->group(function () {
            Route::get('/', [ProfileController::class, 'show']);
            Route::put('/', [ProfileController::class, 'update']);
            Route::post('/avatar', [ProfileController::class, 'uploadAvatar']);
            Route::delete('/avatar', [ProfileController::class, 'deleteAvatar']);
            Route::post('/change-password', [ProfileController::class, 'changePassword']);
            Route::delete('/delete-account', [ProfileController::class, 'deleteAccount']);
            Route::get('/statistics', [ProfileController::class, 'statistics']);
            Route::get('/notification-settings', [ProfileController::class, 'getNotificationSettings']);
            Route::put('/notification-settings', [ProfileController::class, 'updateNotificationSettings']);
        });

        // File upload management
        Route::prefix('upload')->group(function () {
            Route::post('/image', [FileUploadController::class, 'uploadImage']);
            Route::post('/images', [FileUploadController::class, 'uploadMultipleImages']);
            Route::delete('/image', [FileUploadController::class, 'deleteImage']);
            Route::post('/crop', [FileUploadController::class, 'cropImage']);
            Route::post('/resize', [FileUploadController::class, 'resizeImage']);
            Route::get('/image-info', [FileUploadController::class, 'getImageInfo']);
            Route::post('/cleanup', [FileUploadController::class, 'cleanup']);
            Route::get('/storage-stats', [FileUploadController::class, 'storageStats']);
        });

        // Address management
        Route::prefix('addresses')->group(function () {
            Route::get('/', [AddressController::class, 'index']);
            Route::post('/', [AddressController::class, 'store']);
            Route::put('/{id}', [AddressController::class, 'update']);
            Route::delete('/{id}', [AddressController::class, 'destroy']);
            Route::post('/{id}/set-default', [AddressController::class, 'setDefault']);
        });

        // Cart management
        Route::prefix('cart')->group(function () {
            Route::get('/', [CartController::class, 'index']);
            Route::post('/', [CartController::class, 'store']);
            Route::put('/{id}', [CartController::class, 'update']);
            Route::delete('/{id}', [CartController::class, 'destroy']);
            Route::delete('/', [CartController::class, 'clear']);
        });

        // Order management
        Route::prefix('orders')->group(function () {
            Route::get('/', [OrderController::class, 'index']);
            Route::post('/', [OrderController::class, 'store']);
            Route::get('/{id}', [OrderController::class, 'show']);
            Route::post('/{id}/reorder', [OrderController::class, 'reorder']);
            Route::get('/{id}/status-history', [OrderController::class, 'statusHistory']);
            // Note: cancel method is missing in OrderController, need to add it
        });

        // Review management
        Route::prefix('reviews')->group(function () {
            Route::post('/', [ReviewController::class, 'store']);
            Route::get('/{id}', [ReviewController::class, 'show']);
            Route::put('/{id}', [ReviewController::class, 'update']);
            Route::delete('/{id}', [ReviewController::class, 'destroy']);
            Route::post('/{id}/helpful', [ReviewController::class, 'markHelpful']);
            Route::get('/my-reviews', [ReviewController::class, 'userReviews']);
            Route::get('/reviewable-products', [ReviewController::class, 'reviewableProducts']);
            
            // Admin routes (need role-based middleware)
            Route::post('/{id}/approve', [ReviewController::class, 'approve']);
            Route::post('/{id}/reject', [ReviewController::class, 'reject']);
            Route::get('/statistics', [ReviewController::class, 'statistics']);
        });

    });

});

// CSRF cookie route for SPA authentication
Route::middleware('web')->group(function () {
    Route::get('/sanctum/csrf-cookie', function (Request $request) {
        return response()->json(['message' => 'CSRF cookie set']);
    });
});

// API endpoint for checking API status
Route::get('/health', function () {
    return response()->json([
        'status' => 'OK',
        'timestamp' => now()->toDateTimeString(),
        'version' => 'v1'
    ]);
});

// Rate limiting for API routes (optional)
Route::middleware(['throttle:api'])->group(function () {
    // Additional rate-limited routes can be added here
});
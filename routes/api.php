<?php

use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\SalesController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;


Route::prefix('sales')->group(function () {
    // Public login route
    Route::post('login', [SalesController::class, 'login']);
    Route::post('send-otp', [SalesController::class, 'sendOtp']);
    Route::post('verify-otp', [SalesController::class, 'verifyOtp']);
    Route::post('forgot-password', [SalesController::class, 'forgot_password']);

    // Protected routes for authenticated sales employees
    Route::middleware(['auth'])->group(function () {
        Route::post('device-token', [SalesController::class, 'storeDeviceToken']);
        Route::post('logout', [SalesController::class, 'logout']);
        // Add more protected sales routes here

        Route::get('today-route-plan', [SalesController::class, 'todayRoutePlan']);
        Route::get('dashboard-analytics', [SalesController::class, 'dashboardAnalytics']);
        Route::post('add-shop', [SalesController::class, 'addShop'])->middleware('permission:create shop');
        Route::post('edit-shop', [SalesController::class, 'editShop'])->middleware('permission:create shop');
        Route::get('get-inventory', [SalesController::class, 'getInventory'])->middleware('permission:view inventory');
        Route::post('create-sales-order', [SalesController::class, 'createSalesOrder'])->middleware('permission:create salesorder');
        Route::post('edit-sales-order', [SalesController::class, 'editSalesOrder'])->middleware('permission:create salesorder');
        Route::get('sales-orders', [SalesController::class, 'salesOrders'])->middleware(['permission:view salesorder']);
        Route::post('geolocation-range', [SalesController::class, 'geolocationRange']);
        Route::post('mark-visited', [SalesController::class, 'markVisited']);
        Route::get('notifications', [SalesController::class, 'notifications']);

    });

    Route::get('/get-areas-by-pincode/{pincode}', [HomeController::class, 'getAreasByPincode']);
    Route::get('/get-villages-by-area', [HomeController::class, 'getVillagesByArea']);
});


Route::prefix('delivery')->group(function () {
    // Public login route
    Route::post('login', [DeliveryController::class, 'login']);
    Route::post('send-otp', [DeliveryController::class, 'sendOtp']);
    Route::post('verify-otp', [DeliveryController::class, 'verifyOtp']);
    Route::post('forgot-password', [DeliveryController::class, 'forgot_password']);

    // Protected routes for authenticated Delivery employees
    Route::middleware(['auth'])->group(function () {
        Route::post('device-token', [DeliveryController::class, 'storeDeviceToken']);
        Route::post('logout', [DeliveryController::class, 'logout']);
        // Add more protected Delivery routes here
        

        Route::get('pending-deliveries', [DeliveryController::class, 'pendingDeliveries']);
        Route::get('all-deliveries', [DeliveryController::class, 'allDeliveries']);
        Route::post('update-delivery-status', [DeliveryController::class, 'updateDeliveryStatus']);

        
        Route::get('notifications', [DeliveryController::class, 'notifications']);

    });

    
});


Route::post('/upload-villages', [SalesController::class, 'uploadVillages']);
Route::get('sales/run-notification', [SalesController::class, 'run_notification']);
Route::get('/salesorders/product/{id}', [SalesController::class, 'getOrdersByProduct']);
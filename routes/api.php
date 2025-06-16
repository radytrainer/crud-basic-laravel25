<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\CategoryController;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


// 1. Product Routes:
// Route::get('products', [ProductController::class, 'index']);
// Route::get('products/{id}', [ProductController::class, 'show']);
// Route::post('products', [ProductController::class, 'store']);
// Route::put('products/{id}', [ProductController::class, 'update']);
// Route::delete('products/{id}', [ProductController::class, 'destroy']);

// 2. Category Routes:
// Route::get('categories', [CategoryController::class, 'index']);
// Route::get('categories/{id}', [CategoryController::class, 'show']);
// Route::post('categories', [CategoryController::class, 'store']);
// Route::put('categories/{id}', [CategoryController::class, 'update']);
// Route::delete('categories/{id}', [CategoryController::class, 'destroy']);


// Or you can use the apiResource method to define the routes in a more concise way:
// Route::apiResource('products', ProductController::class);
// Route::apiResource('categories', CategoryController::class);


// Route::prefix('v1')->group(function () {
//     Route::apiResource('products', ProductController::class);
//     Route::apiResource('categories', CategoryController::class);
// });

Route::prefix('v1')->group(function () {
    Route::apiResources([
        'products' => ProductController::class,
        'categories' => CategoryController::class,
    ]);

});

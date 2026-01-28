<?php


use App\Http\Controllers\Client\ClientAuthController;
use App\Http\Controllers\Client\ClientController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'client'], function () {

    Route::post('/login', [ClientAuthController::class, 'login']);
    Route::get('/logout', [ClientAuthController::class, 'logout']);

    Route::middleware('auth:sanctum')->group(function () {

        //client
        Route::get('/dashboard', [ClientController::class, 'dashboard']);
        Route::get('/subscription', [ClientController::class, 'Subscriptionindex']);
        Route::post('/request-service', [ClientController::class, 'requestService']);
        Route::get('/payment', [ClientController::class, 'paymentindex']);
        Route::get('/me', [ClientAuthController::class, 'me']);
        Route::get('/staff', [ClientController::class, 'staff']);
        Route::post('/change-password', [ClientController::class, 'changePassword']);
        Route::get('account-info', [ClientController::class, 'accountInfo']);
    });
});

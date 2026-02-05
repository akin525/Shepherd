<?php


use App\Http\Controllers\Operations\AssessmentController;
use App\Http\Controllers\Operations\AuthController;
use App\Http\Controllers\Operations\DashboardController;
use App\Http\Controllers\Operations\IncidentController;
use App\Http\Controllers\Operations\PatrolController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'operations'], function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'getDashboardData']);
        Route::get('/attendance', [DashboardController::class, 'getAttendanceOverview']);

        Route::post('/assessments', [AssessmentController::class, 'store']);
        Route::get('/assessments', [AssessmentController::class, 'index']);
        Route::get('/assessments/{id}', [AssessmentController::class, 'show']);


        Route::get('/incidents', [IncidentController::class, 'index']);
        Route::post('/incidents', [IncidentController::class, 'store']);
        Route::get('/incidents/{id}', [IncidentController::class, 'show']);

        Route::get('/patrol-logs', [PatrolController::class, 'index']);
        Route::post('/patrol-logs', [PatrolController::class, 'store']);

    });

});

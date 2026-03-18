<?php


use App\Http\Controllers\Operations\AssessmentController;
use App\Http\Controllers\Operations\AuthController;
use App\Http\Controllers\Operations\DashboardController;
use App\Http\Controllers\Operations\IncidentController;
use App\Http\Controllers\Operations\ManningStructureController;
use App\Http\Controllers\Operations\PatrolController;
use App\Http\Controllers\Operations\SopGeneratorController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'operations'], function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'getDashboardData']);
        Route::get('/attendance', [DashboardController::class, 'getAttendanceOverview']);
        Route::get('/client', [DashboardController::class, 'getAllClient']);

        Route::post('/assessments', [AssessmentController::class, 'store']);
        Route::get('/assessments', [AssessmentController::class, 'index']);
        Route::get('/assessments/{id}', [AssessmentController::class, 'show']);


        Route::get('/incidents', [IncidentController::class, 'index']);
        Route::post('/incidents', [IncidentController::class, 'store']);
        Route::get('/incidents/{id}', [IncidentController::class, 'show']);

        Route::get('/patrol-logs', [PatrolController::class, 'index']);
        Route::post('/patrol-logs', [PatrolController::class, 'store']);

        Route::get('manning-structures', [ManningStructureController::class, 'index']);
        Route::post('manning-structures', [ManningStructureController::class, 'store']);
        Route::get('manning-structures/{id}', [ManningStructureController::class, 'show']);

        // SOP Generator Routes
        Route::get('sop-generators', [SopGeneratorController::class, 'index']);
        Route::post('sop-generators', [SopGeneratorController::class, 'store']);
        Route::get('sop-generators/{id}', [SopGeneratorController::class, 'show']);

    });

});

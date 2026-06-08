<?php


use App\Http\Controllers\Operations\AssessmentController;
use App\Http\Controllers\Operations\AuthController;
use App\Http\Controllers\Operations\DashboardController;
use App\Http\Controllers\Operations\FinanceCustomerRetainershipController;
use App\Http\Controllers\Operations\IncidentController;
use App\Http\Controllers\Operations\ManningStructureController;
use App\Http\Controllers\Operations\PatrolController;
use App\Http\Controllers\Operations\SopGeneratorController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'operations'], function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/login/superadmin/{id}', [AuthController::class, 'loginSuperadmin']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'getDashboardData']);
        Route::get('/attendance', [DashboardController::class, 'getAttendanceOverview']);
        Route::get('/client', [DashboardController::class, 'getAllClient']);
        Route::get('/client/s', [AuthController::class, 'clients']);
        Route::get('/staff', [AuthController::class, 'staff']);
        Route::get('/staff/details/{id}', [AuthController::class, 'staffDetails']);
        Route::get('/supervisors', [AuthController::class, 'getSupervisors']);
        Route::post('/supervisors', [AuthController::class, 'createSupervisor']);
        Route::post('/supervisors/assign-guard', [AuthController::class, 'assignSupervisorToGuard']);
        Route::get('/supervisors/{id}/guards', [AuthController::class, 'getSupervisorGuards']);
        Route::get('/guards/unassigned', [AuthController::class, 'getUnassignedGuards']);
        Route::post('/supervisors/unassign-guard', [AuthController::class, 'unassignGuard']);
        Route::post('/supervisors/reassign-guard', [AuthController::class, 'reassignGuard']);
        Route::post('/supervisors/assign-guards/bulk', [AuthController::class, 'bulkAssignGuards']);

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

        Route::group(['prefix' => 'retainership'], function () {
        Route::get('list', [FinanceCustomerRetainershipController::class, 'index']);
        Route::post('generate', [FinanceCustomerRetainershipController::class, 'generate']);
        Route::get('form/{id}', [FinanceCustomerRetainershipController::class, 'showByCode']);
        Route::get('signatory/{id}', [FinanceCustomerRetainershipController::class, 'signatoryByCode']);
        Route::get('equipment/{id}', [FinanceCustomerRetainershipController::class, 'equipmentByCode']);
        Route::get('service/{id}', [FinanceCustomerRetainershipController::class, 'serviceByCode']);
        Route::post('signature/update/{id}', [FinanceCustomerRetainershipController::class, 'updateSignatory']);
        Route::delete('delete/{id}', [FinanceCustomerRetainershipController::class, 'destroy']);
        });

    });

});

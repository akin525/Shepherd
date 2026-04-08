<?php


use App\Http\Controllers\supervisor\AnnouncementController;
use App\Http\Controllers\supervisor\AssetController;
use App\Http\Controllers\supervisor\AttendanceController;
use App\Http\Controllers\supervisor\AuthController;
use App\Http\Controllers\supervisor\EmployeeController;
use App\Http\Controllers\supervisor\IssueController;
use App\Http\Controllers\supervisor\LeaveController;
use App\Http\Controllers\supervisor\PayrollController;
use App\Http\Controllers\supervisor\ProfileController;
use App\Http\Controllers\supervisor\PerformanceController;
use App\Http\Controllers\supervisor\ResignationController;
use App\Http\Controllers\supervisor\TrainingController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Authentication
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);

    // Profile Management
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar']);


    //deployment
    Route::get('/deployment', [ProfileController::class, 'deployment']);


    //award
    Route::get('/awards', [PerformanceController::class, 'awards']);

    //training
    Route::get('/training', [TrainingController::class, 'index']);

    // Employee Management
    Route::apiResource('employees', EmployeeController::class);
    Route::get('/employees/{employee}/attendance', [EmployeeController::class, 'attendance']);
    Route::get('/employees/{employee}/leaves', [EmployeeController::class, 'leaves']);
    Route::get('/employees/{employee}/payroll', [EmployeeController::class, 'payroll']);

    // Attendance Management
    Route::get('/attendance', [AttendanceController::class, 'index']);
    Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn']);
    Route::post('/attendance/check-out', [AttendanceController::class, 'checkOut']);
    Route::get('/attendance/summary', [AttendanceController::class, 'summary']);
    Route::get('/attendance/my-attendance', [AttendanceController::class, 'myAttendance']);

    // Leave Management
    Route::apiResource('leaves', LeaveController::class);
    Route::get('/leave-types', [LeaveController::class, 'leaveTypes']);
    Route::get('/leave-balance', [LeaveController::class, 'leaveBalance']);
    Route::post('/leaves/{id}/approve', [LeaveController::class, 'approve']);
    Route::post('/leaves/{id}/reject', [LeaveController::class, 'reject']);

    // Payroll Management
    Route::get('/my-payroll', [PayrollController::class, 'payroll']);
    Route::get('/payroll/payslips', [PayrollController::class, 'payslips']);
    Route::get('/payroll/payslips/{id}', [PayrollController::class, 'showPayslip']);
    Route::get('/payroll/salary-breakdown', [PayrollController::class, 'salaryBreakdown']);

    //report issue
    Route::get('/issue-category', [IssueController::class, 'create']);
    Route::post('/issue', [IssueController::class, 'store']);

    //resign
    Route::put('/resign', [ResignationController::class, 'store']);

    // Announcements
    Route::get('/announcements', [AnnouncementController::class, 'index']);
    Route::get('/announcements/{id}', [AnnouncementController::class, 'show']);
    Route::post('/announcements/{id}/mark-read', [AnnouncementController::class, 'markAsRead']);

    // Performance Management
    Route::get('/performance/reviews', [PerformanceController::class, 'reviews']);
    Route::get('/performance/goals', [PerformanceController::class, 'goals']);
    Route::post('/performance/goals', [PerformanceController::class, 'storeGoal']);
    Route::get('/performance/indicators', [PerformanceController::class, 'indicators']);

    // Asset Management
    Route::get('/assets', [AssetController::class, 'index']);
    Route::get('/assets/{id}', [AssetController::class, 'show']);
    Route::post('/assets/{id}/return', [AssetController::class, 'returnAsset']);

    // Dashboard
    Route::get('/dashboard', [EmployeeController::class, 'dashboard']);
    Route::get('/dashboard/stats', [EmployeeController::class, 'dashboardStats']);


    Route::post('/request-overtime', [AttendanceController::class, 'requestOvertime']);
    Route::get('/overtime-history', [AttendanceController::class, 'getOvertimeHistory']);
    Route::get('/overtime-charts', [AttendanceController::class, 'getOvertimeChartData']);


    Route::get('/audit-log', [\App\Http\Controllers\AuditLogController::class, 'index']);

});


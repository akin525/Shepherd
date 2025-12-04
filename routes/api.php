<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\PerformanceController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\ProfileController;

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Public routes
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Authentication
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);

    // Profile Management
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar']);

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
    Route::get('/payroll', [PayrollController::class, 'index']);
    Route::get('/payroll/payslips', [PayrollController::class, 'payslips']);
    Route::get('/payroll/payslips/{id}', [PayrollController::class, 'showPayslip']);
    Route::get('/payroll/salary-breakdown', [PayrollController::class, 'salaryBreakdown']);

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
});

// Admin routes (requires admin role)
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::apiResource('employees', EmployeeController::class);
    Route::apiResource('announcements', AnnouncementController::class)->except(['index', 'show']);
    Route::post('/attendance/adjust', [AttendanceController::class, 'adjustAttendance']);
    Route::get('/reports/attendance', [AttendanceController::class, 'attendanceReport']);
    Route::get('/reports/payroll', [PayrollController::class, 'payrollReport']);
});

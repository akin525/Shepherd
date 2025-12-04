<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    /**
     * Display the authenticated user's profile.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found'
            ], 404);
        }

        $user->load([
            'employee',
            'employee.department',
            'employee.designation',
            'employee.branch'
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Profile retrieved successfully',
            'data' => [
                'user' => $user,
                'employment_info' => $this->getEmploymentInfo($user->employee),
                'quick_stats' => $this->getQuickStats($user->employee),
            ]
        ]);
    }

    /**
     * Update the authenticated user's profile.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        $validator = Validator::make($request->all(), [
            // User information
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,

            // Employee personal information
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'emergency_contact' => 'nullable|string|max:20',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_relation' => 'nullable|string|max:100',

            // Professional information
            'linkedin_profile' => 'nullable|url|max:255',
            'github_profile' => 'nullable|url|max:255',
            'personal_website' => 'nullable|url|max:255',
            'skills' => 'nullable|string|max:1000',
            'qualifications' => 'nullable|string|max:1000',
            'bio' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Update user information
            $user->update([
                'name' => $request->name,
                'email' => $request->email,
            ]);

            // Update employee information
            if ($employee) {
                $employee->update([
                    'name' => $request->name,
                    'email' => $request->email,
                    'phone' => $request->phone,
                    'address' => $request->address,
//                    'city' => $request->city,
//                    'state' => $request->state,
//                    'country' => $request->country,
//                    'postal_code' => $request->postal_code,
//                    'emergency_contact' => $request->emergency_contact,
//                    'emergency_contact_name' => $request->emergency_contact_name,
//                    'emergency_contact_relation' => $request->emergency_contact_relation,
                    'linkedn' => $request->linkedin_profile,
                    'git_url' => $request->github_profile,
//                    'personal_website' => $request->personal_website,
//                    'skills' => $request->skills,
//                    'qualifications' => $request->qualifications,
                ]);
            }

            // Reload relationships
            $user->load(['employee', 'employee.department', 'employee.designation']);

            return response()->json([
                'status' => true,
                'message' => 'Profile updated successfully',
                'data' => [
                    'user' => $user
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user avatar
     */
    public function updateAvatar(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();

            // Delete old avatar if exists
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }

            // Upload new avatar
            $file = $request->file('avatar');
            $avatarPath = $file->store('avatars', 'public');

            // Update user avatar
            $user->update(['avatar' => $avatarPath]);

            // Update employee avatar if exists
//            if ($user->employee) {
//                $user->employee->update(['avatar' => $avatarPath]);
//            }

            return response()->json([
                'status' => true,
                'message' => 'Avatar updated successfully',
                'data' => [
                    'avatar_url' => Storage::url($avatarPath),
                    'avatar' => $avatarPath
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update avatar',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Change password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();

            // Verify current password
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Current password is incorrect'
                ], 400);
            }

            // Update password
            $user->update([
                'password' => Hash::make($request->new_password)
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Password changed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to change password',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get employment information
     */
    public function getEmploymentInfo($employee): ?array
    {
        if (!$employee) {
            return null;
        }

        return [
            'employee_id' => $employee->employee_id,
            'date_of_joining' => $employee->date_of_joining,
            'employment_duration' => $employee->employment_duration ?? null,
            'department' => $employee->department->name ?? 'Not assigned',
            'designation' => $employee->designation->name ?? 'Not assigned',
            'branch' => $employee->branch->name ?? 'Not assigned',
            'salary' => $employee->salary,
            'salary_type' => $employee->salary_type,
            'status' => $employee->status,
            'is_active' => $employee->is_active,
        ];
    }

    /**
     * Get quick statistics for the user
     */
    public function getQuickStats($employee): ?array
    {
        if (!$employee) {
            return null;
        }

        $thisYear = now()->year;
        $thisMonth = now()->month;

        return [
            'attendance_this_month' => $employee->attendances()
                                               ->whereMonth('date', $thisMonth)
                                               ->whereYear('date', $thisYear)
                                               ->where('status', 'Present')
                                               ->count(),
            'leaves_pending' => $employee->leaves()
                                         ->where('status', 'Pending')
                                         ->count(),
            'leave_balance' => $employee->leave_balance ?? 0,
            'assets_assigned' => $employee->assets()
//                                         ->where('status', 'Assigned')
                                         ->count(),
//            'goals_completed' => $employee->goalTrackings()
//                                          ->where('status', 'Completed')
//                                          ->whereYear('end_date', $thisYear)
//                                          ->count(),
//            'last_appraisal_rating' => $employee->appraisals()
//                                              ->latest()
//                                              ->first()->rating ?? 0,
        ];
    }

    /**
     * Get profile completeness percentage
     */
    public function getProfileCompleteness(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        if (!$employee) {
            return response()->json([
                'status' => false,
                'message' => 'Employee profile not found'
            ], 404);
        }

        $requiredFields = [
            'name', 'email', 'phone', 'address', 'city', 'state',
            'country', 'postal_code', 'emergency_contact',
            'emergency_contact_name', 'emergency_contact_relation'
        ];

        $optionalFields = [
            'linkedin_profile', 'github_profile', 'personal_website',
            'skills', 'qualifications', 'bio'
        ];

        $completedRequired = 0;
        $completedOptional = 0;

        foreach ($requiredFields as $field) {
            if (!empty($employee->{$field})) {
                $completedRequired++;
            }
        }

        foreach ($optionalFields as $field) {
            if (!empty($employee->{$field})) {
                $completedOptional++;
            }
        }

        $requiredPercentage = ($completedRequired / count($requiredFields)) * 70; // 70% weight
        $optionalPercentage = ($completedOptional / count($optionalFields)) * 30; // 30% weight
        $totalPercentage = round($requiredPercentage + $optionalPercentage);

        $missingFields = [];
        foreach ($requiredFields as $field) {
            if (empty($employee->{$field})) {
                $missingFields[] = $field;
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Profile completeness retrieved successfully',
            'data' => [
                'completeness_percentage' => $totalPercentage,
                'completed_required' => $completedRequired,
                'completed_optional' => $completedOptional,
                'total_required' => count($requiredFields),
                'total_optional' => count($optionalFields),
                'missing_required_fields' => $missingFields,
                'has_avatar' => !empty($user->avatar),
                'is_complete' => $totalPercentage >= 90,
            ]
        ]);
    }

    /**
     * Get notification preferences
     */
    public function getNotificationPreferences(Request $request): JsonResponse
    {
        // Mock data - in real implementation, this would come from database
        $preferences = [
            'email_notifications' => true,
            'push_notifications' => true,
            'attendance_reminders' => true,
            'leave_notifications' => true,
            'payroll_notifications' => true,
            'announcement_notifications' => true,
            'performance_notifications' => true,
            'asset_notifications' => true,
        ];

        return response()->json([
            'status' => true,
            'message' => 'Notification preferences retrieved successfully',
            'data' => [
                'preferences' => $preferences
            ]
        ]);
    }

    /**
     * Update notification preferences
     */
    public function updateNotificationPreferences(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email_notifications' => 'boolean',
            'push_notifications' => 'boolean',
            'attendance_reminders' => 'boolean',
            'leave_notifications' => 'boolean',
            'payroll_notifications' => 'boolean',
            'announcement_notifications' => 'boolean',
            'performance_notifications' => 'boolean',
            'asset_notifications' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // In real implementation, save to database
            // For now, just return success

            return response()->json([
                'status' => true,
                'message' => 'Notification preferences updated successfully',
                'data' => [
                    'preferences' => $request->all()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update notification preferences',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

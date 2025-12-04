<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\AnnouncementEmployee;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnnouncementController extends Controller
{
    /**
     * Display a listing of announcements.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Announcement::with(['createdBy', 'departments', 'designations']);

        // Filter by status
//        if ($request->has('status')) {
//            $query->where('status', $request->status);
//        }
//
//        // Filter by category
//        if ($request->has('category')) {
//            $query->where('category', $request->category);
//        }

        // Search functionality
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('start_date', [$request->start_date, $request->end_date]);
        }

        // Get only active and current announcements for employees
        if (!Auth::user()->isAdmin()) {
            $query->where('start_date', '<=', now())
                  ->where(function ($q) {
                      $q->whereNull('end_date')
                        ->orWhere('end_date', '>=', now());
                  });
        }

        $announcements = $query->orderBy('created_at', 'desc')
                             ->paginate($request->get('per_page', 15));

        // Mark announcements as read for authenticated user
        if (Auth::user()->employee && !Auth::user()->isAdmin()) {
            $employeeId = Auth::user()->employee->id;
            $announcementIds = $announcements->pluck('id');

            // Mark announcements as read for this employee
//            AnnouncementEmployee::where('employee_id', $employeeId)
//                               ->whereIn('announcement_id', $announcementIds)
//                               ->whereNull('read_at')
//                               ->update(['read_at' => now()]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Announcements retrieved successfully',
            'data' => [
                'announcements' => $announcements,
                'unread_count' => $this->getUnreadCount(),
            ]
        ]);
    }

    /**
     * Display the specified announcement.
     */
    public function show(Announcement $announcement): JsonResponse
    {
        $announcement->load([
            'createdBy',
            'departments',
            'designations',
            'employees'
        ]);

        // Mark as read if employee
        if (Auth::user()->employee && !Auth::user()->isAdmin()) {
            $employeeId = Auth::user()->employee->id;

            AnnouncementEmployee::updateOrCreate(
                [
                    'announcement_id' => $announcement->id,
                ],
                [
                    'read_at' => now(),
                ]
            );
        }

        return response()->json([
            'status' => true,
            'message' => 'Announcement retrieved successfully',
            'data' => [
                'announcement' => $announcement,
                'is_read' => $this->isAnnouncementRead($announcement->id),
            ]
        ]);
    }

    /**
     * Store a newly created announcement (Admin only).
     */
    public function store(Request $request): JsonResponse
    {
        $validator = validator($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category' => 'nullable|string|max:100',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'nullable|date|after:start_date',
            'status' => 'required|in:Active,Inactive',
            'departments' => 'nullable|array',
            'departments.*' => 'exists:departments,id',
            'designations' => 'nullable|array',
            'designations.*' => 'exists:designations,id',
            'all_employees' => 'required|boolean',
            'attachment' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Handle file upload
            $attachmentPath = null;
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $fileName = time() . '_' . $file->getClientOriginalName();
                $attachmentPath = $file->storeAs('announcements', $fileName, 'public');
            }

            $announcement = Announcement::create([
                'title' => $request->title,
                'description' => $request->description,
                'category' => $request->category,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'status' => $request->status,
                'created_by' => Auth::id(),
                'attachment' => $attachmentPath,
            ]);

            // Attach departments
            if ($request->has('departments') && !empty($request->departments)) {
                $announcement->departments()->attach($request->departments);
            }

            // Attach designations
            if ($request->has('designations') && !empty($request->designations)) {
                $announcement->designations()->attach($request->designations);
            }

            // If all employees should see this, create employee announcements
            if ($request->boolean('all_employees')) {
                $employees = Employee::where('is_active', true)->get();
                foreach ($employees as $employee) {
                    AnnouncementEmployee::create([
                        'announcement_id' => $announcement->id,
                        'employee_id' => $employee->id,
                    ]);
                }
            } else {
                // Create employee announcements based on departments/designations
                $targetEmployees = Employee::where('is_active', true);

                if ($request->has('departments') && !empty($request->departments)) {
                    $targetEmployees->whereIn('department_id', $request->departments);
                }

                if ($request->has('designations') && !empty($request->designations)) {
                    $targetEmployees->whereIn('designation_id', $request->designations);
                }

                $employees = $targetEmployees->get();
                foreach ($employees as $employee) {
                    AnnouncementEmployee::create([
                        'announcement_id' => $announcement->id,
                        'employee_id' => $employee->id,
                    ]);
                }
            }

            DB::commit();

            $announcement->load(['createdBy', 'departments', 'designations']);

            return response()->json([
                'status' => true,
                'message' => 'Announcement created successfully',
                'data' => [
                    'announcement' => $announcement
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'status' => false,
                'message' => 'Failed to create announcement',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified announcement (Admin only).
     */
    public function update(Request $request, Announcement $announcement): JsonResponse
    {
        $validator = validator($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category' => 'nullable|string|max:100',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'status' => 'required|in:Active,Inactive',
            'departments' => 'nullable|array',
            'departments.*' => 'exists:departments,id',
            'designations' => 'nullable|array',
            'designations.*' => 'exists:designations,id',
            'all_employees' => 'required|boolean',
            'attachment' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Handle file upload
            $attachmentPath = $announcement->attachment;
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $fileName = time() . '_' . $file->getClientOriginalName();
                $attachmentPath = $file->storeAs('announcements', $fileName, 'public');
            }

            $announcement->update([
                'title' => $request->title,
                'description' => $request->description,
                'category' => $request->category,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'status' => $request->status,
                'attachment' => $attachmentPath,
            ]);

            // Sync departments
            if ($request->has('departments')) {
                $announcement->departments()->sync($request->departments);
            } else {
                $announcement->departments()->detach();
            }

            // Sync designations
            if ($request->has('designations')) {
                $announcement->designations()->sync($request->designations);
            } else {
                $announcement->designations()->detach();
            }

            // Update employee announcements
            AnnouncementEmployee::where('announcement_id', $announcement->id)->delete();

            if ($request->boolean('all_employees')) {
                $employees = Employee::where('is_active', true)->get();
                foreach ($employees as $employee) {
                    AnnouncementEmployee::create([
                        'announcement_id' => $announcement->id,
                        'employee_id' => $employee->id,
                    ]);
                }
            } else {
                $targetEmployees = Employee::where('is_active', true);

                if ($request->has('departments') && !empty($request->departments)) {
                    $targetEmployees->whereIn('department_id', $request->departments);
                }

                if ($request->has('designations') && !empty($request->designations)) {
                    $targetEmployees->whereIn('designation_id', $request->designations);
                }

                $employees = $targetEmployees->get();
                foreach ($employees as $employee) {
                    AnnouncementEmployee::create([
                        'announcement_id' => $announcement->id,
                        'employee_id' => $employee->id,
                    ]);
                }
            }

            DB::commit();

            $announcement->load(['createdBy', 'departments', 'designations']);

            return response()->json([
                'status' => true,
                'message' => 'Announcement updated successfully',
                'data' => [
                    'announcement' => $announcement
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'status' => false,
                'message' => 'Failed to update announcement',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified announcement (Admin only).
     */
    public function destroy(Announcement $announcement): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Delete employee announcements
            AnnouncementEmployee::where('announcement_id', $announcement->id)->delete();

            // Delete department/designation relationships
            $announcement->departments()->detach();
            $announcement->designations()->detach();

            // Delete announcement
            $announcement->delete();

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Announcement deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete announcement',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark announcement as read
     */
    public function markAsRead(Request $request, Announcement $announcement): JsonResponse
    {
        try {
            $employee = Auth::user()->employee;

            if (!$employee) {
                return response()->json([
                    'status' => false,
                    'message' => 'Employee profile not found'
                ], 404);
            }

            $announcementEmployee = AnnouncementEmployee::updateOrCreate(
                [
                    'announcement_id' => $announcement->id,
                    'employee_id' => $employee->id,
                ],
                [
                    'read_at' => now(),
                ]
            );

            return response()->json([
                'status' => true,
                'message' => 'Announcement marked as read',
                'data' => [
                    'read_at' => $announcementEmployee,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to mark announcement as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get unread count for authenticated user
     */
    private function getUnreadCount(): int
    {
        if (!Auth::user()->employee || Auth::user()->isAdmin()) {
            return 0;
        }

        $employeeId = Auth::user()->employee->id;

        return Announcement::where('start_date', '<=', now())
                          ->where(function ($q) {
                              $q->whereNull('end_date')
                                ->orWhere('end_date', '>=', now());
                          })
//                          ->whereHas('employees', function ($q) use ($employeeId) {
//                              $q->where('employee_id', $employeeId);
//                          })
                          ->count();
    }

    /**
     * Check if announcement is read by authenticated user
     */
    private function isAnnouncementRead(int $announcementId): bool
    {
        if (!Auth::user()->employee || Auth::user()->isAdmin()) {
            return true; // Admin sees all as read
        }

        $employeeId = Auth::user()->employee->id;

        return AnnouncementEmployee::where('announcement_id', $announcementId)
                                  ->where('employee_id', $employeeId)
                                  ->exists();
    }

    /**
     * Get announcement statistics (Admin only)
     */
    public function statistics(Request $request): JsonResponse
    {
        $totalAnnouncements = Announcement::count();
        $activeAnnouncements = Announcement::where('status', 'Active')->count();
        $totalReads = AnnouncementEmployee::whereNotNull('read_at')->count();
        $totalEmployees = Employee::where('is_active', true)->count();

        // Get category-wise breakdown
        $categoryBreakdown = Announcement::select('category')
                                        ->selectRaw('count(*) as count')
                                        ->whereNotNull('category')
                                        ->groupBy('category')
                                        ->get();

        return response()->json([
            'status' => true,
            'message' => 'Announcement statistics retrieved successfully',
            'data' => [
                'summary' => [
                    'total_announcements' => $totalAnnouncements,
                    'active_announcements' => $activeAnnouncements,
                    'total_reads' => $totalReads,
                    'total_employees' => $totalEmployees,
                    'read_rate' => $totalEmployees > 0 ? round(($totalReads / ($totalAnnouncements * $totalEmployees)) * 100, 2) : 0,
                ],
                'category_breakdown' => $categoryBreakdown,
            ]
        ]);
    }
}

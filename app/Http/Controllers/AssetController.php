<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AssetController extends Controller
{
    /**
     * Display a listing of assets.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Asset::with(['employee', 'category']);

        // For employees, only show their assigned assets
        if (!Auth::user()->isAdmin()) {
            $employee = Auth::user()->employee;
            if ($employee) {
                $query->where('employee_id', $employee->id);
            }
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by employee
        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        // Search functionality
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('asset_id', 'like', "%{$search}%")
                  ->orWhere('serial_number', 'like', "%{$search}%");
            });
        }

        $assets = $query->orderBy('created_at', 'desc')
                       ->paginate($request->get('per_page', 15));

        return response()->json([
            'status' => true,
            'message' => 'Assets retrieved successfully',
            'data' => [
                'assets' => $assets
            ]
        ]);
    }

    /**
     * Display the specified asset.
     */
    public function show(Asset $asset): JsonResponse
    {
        // Check authorization
        if (!Auth::user()->isAdmin() && $asset->employee_id !== Auth::user()->employee->id) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized to view this asset'
            ], 403);
        }

        $asset->load(['employee', 'category', 'createdBy']);

        return response()->json([
            'status' => true,
            'message' => 'Asset retrieved successfully',
            'data' => [
                'asset' => $asset
            ]
        ]);
    }

    /**
     * Store a newly created asset (Admin only).
     */
    public function store(Request $request): JsonResponse
    {
        $validator = validator($request->all(), [
            'name' => 'required|string|max:255',
            'asset_id' => 'required|string|max:255|unique:assets,asset_id',
            'category_id' => 'required|exists:categories,id',
            'serial_number' => 'nullable|string|max:255',
            'manufacturer' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'purchase_date' => 'nullable|date',
            'purchase_cost' => 'nullable|numeric|min:0',
            'warranty_expiry' => 'nullable|date',
            'status' => 'required|in:Available,Assigned,Maintenance,Retired',
            'description' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:1000',
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
            // Handle file upload
            $attachmentPath = null;
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $fileName = time() . '_' . $file->getClientOriginalName();
                $attachmentPath = $file->storeAs('assets', $fileName, 'public');
            }

            $asset = Asset::create([
                'name' => $request->name,
                'asset_id' => $request->asset_id,
                'category_id' => $request->category_id,
                'serial_number' => $request->serial_number,
                'manufacturer' => $request->manufacturer,
                'model' => $request->model,
                'purchase_date' => $request->purchase_date,
                'purchase_cost' => $request->purchase_cost,
                'warranty_expiry' => $request->warranty_expiry,
                'status' => $request->status,
                'description' => $request->description,
                'notes' => $request->notes,
                'attachment' => $attachmentPath,
                'created_by' => Auth::id(),
            ]);

            $asset->load(['employee', 'category', 'createdBy']);

            return response()->json([
                'status' => true,
                'message' => 'Asset created successfully',
                'data' => [
                    'asset' => $asset
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create asset',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified asset (Admin only).
     */
    public function update(Request $request, Asset $asset): JsonResponse
    {
        $validator = validator($request->all(), [
            'name' => 'required|string|max:255',
            'asset_id' => 'required|string|max:255|unique:assets,asset_id,' . $asset->id,
            'category_id' => 'required|exists:categories,id',
            'serial_number' => 'nullable|string|max:255',
            'manufacturer' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'purchase_date' => 'nullable|date',
            'purchase_cost' => 'nullable|numeric|min:0',
            'warranty_expiry' => 'nullable|date',
            'status' => 'required|in:Available,Assigned,Maintenance,Retired',
            'description' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:1000',
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
            // Handle file upload
            $attachmentPath = $asset->attachment;
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $fileName = time() . '_' . $file->getClientOriginalName();
                $attachmentPath = $file->storeAs('assets', $fileName, 'public');
            }

            $asset->update([
                'name' => $request->name,
                'asset_id' => $request->asset_id,
                'category_id' => $request->category_id,
                'serial_number' => $request->serial_number,
                'manufacturer' => $request->manufacturer,
                'model' => $request->model,
                'purchase_date' => $request->purchase_date,
                'purchase_cost' => $request->purchase_cost,
                'warranty_expiry' => $request->warranty_expiry,
                'status' => $request->status,
                'description' => $request->description,
                'notes' => $request->notes,
                'attachment' => $attachmentPath,
            ]);

            $asset->load(['employee', 'category', 'createdBy']);

            return response()->json([
                'status' => true,
                'message' => 'Asset updated successfully',
                'data' => [
                    'asset' => $asset
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update asset',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified asset (Admin only).
     */
    public function destroy(Asset $asset): JsonResponse
    {
        try {
            $asset->delete();

            return response()->json([
                'status' => true,
                'message' => 'Asset deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete asset',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign asset to employee (Admin only)
     */
    public function assign(Request $request, Asset $asset): JsonResponse
    {
        $validator = validator($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'assigned_date' => 'required|date',
            'expected_return_date' => 'nullable|date|after:assigned_date',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            if ($asset->status !== 'Available') {
                return response()->json([
                    'status' => false,
                    'message' => 'Asset is not available for assignment'
                ], 400);
            }

            $asset->update([
                'employee_id' => $request->employee_id,
                'status' => 'Assigned',
                'assigned_date' => $request->assigned_date,
                'expected_return_date' => $request->expected_return_date,
                'notes' => $request->notes,
            ]);

            $asset->load(['employee', 'category']);

            return response()->json([
                'status' => true,
                'message' => 'Asset assigned successfully',
                'data' => [
                    'asset' => $asset
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to assign asset',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Return asset (Admin only or assigned employee)
     */
    public function returnAsset(Request $request, Asset $asset): JsonResponse
    {
        $validator = validator($request->all(), [
            'return_date' => 'required|date',
            'return_condition' => 'required|in:Excellent,Good,Fair,Poor,Damaged',
            'return_notes' => 'nullable|string|max:1000',
            'damage_description' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check authorization
            if (!Auth::user()->isAdmin() && $asset->employee_id !== Auth::user()->employee->id) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized to return this asset'
                ], 403);
            }

            if ($asset->status !== 'Assigned') {
                return response()->json([
                    'status' => false,
                    'message' => 'Asset is not currently assigned'
                ], 400);
            }

            $asset->update([
                'status' => 'Available',
                'employee_id' => null,
                'return_date' => $request->return_date,
                'return_condition' => $request->return_condition,
                'return_notes' => $request->return_notes,
                'damage_description' => $request->damage_description,
            ]);

            $asset->load(['category', 'createdBy']);

            return response()->json([
                'status' => true,
                'message' => 'Asset returned successfully',
                'data' => [
                    'asset' => $asset
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to return asset',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get asset history (Admin only)
     */
    public function history(Request $request, Asset $asset): JsonResponse
    {
        // For now, return basic info. In a real implementation, you'd have an asset_history table
        $history = [
            'asset' => $asset->load(['employee', 'category', 'createdBy']),
            'assignments' => [
                [
                    'assigned_to' => $asset->employee,
                    'assigned_date' => $asset->assigned_date,
                    'expected_return_date' => $asset->expected_return_date,
                    'return_date' => $asset->return_date,
                    'return_condition' => $asset->return_condition,
                    'notes' => $asset->notes,
                ]
            ]
        ];

        return response()->json([
            'status' => true,
            'message' => 'Asset history retrieved successfully',
            'data' => $history
        ]);
    }

    /**
     * Get asset statistics (Admin only)
     */
    public function statistics(Request $request): JsonResponse
    {
        $totalAssets = Asset::count();
        $availableAssets = Asset::where('status', 'Available')->count();
        $assignedAssets = Asset::where('status', 'Assigned')->count();
        $maintenanceAssets = Asset::where('status', 'Maintenance')->count();
        $retiredAssets = Asset::where('status', 'Retired')->count();

        // Assets with expiring warranty (within 30 days)
        $expiringWarrantyAssets = Asset::whereNotNull('warranty_expiry')
                                      ->where('warranty_expiry', '<=', now()->addDays(30))
                                      ->where('warranty_expiry', '>', now())
                                      ->count();

        // Overdue expected returns
        $overdueReturns = Asset::whereNotNull('expected_return_date')
                              ->where('expected_return_date', '<', now())
                              ->where('status', 'Assigned')
                              ->count();

        return response()->json([
            'status' => true,
            'message' => 'Asset statistics retrieved successfully',
            'data' => [
                'summary' => [
                    'total_assets' => $totalAssets,
                    'available_assets' => $availableAssets,
                    'assigned_assets' => $assignedAssets,
                    'maintenance_assets' => $maintenanceAssets,
                    'retired_assets' => $retiredAssets,
                    'expiring_warranty' => $expiringWarrantyAssets,
                    'overdue_returns' => $overdueReturns,
                    'utilization_rate' => $totalAssets > 0 ? round(($assignedAssets / $totalAssets) * 100, 2) : 0,
                ]
            ]
        ]);
    }

    /**
     * Get my assets (for authenticated employee)
     */
    public function myAssets(Request $request): JsonResponse
    {
        $employee = Auth::user()->employee;

        if (!$employee) {
            return response()->json([
                'status' => false,
                'message' => 'Employee profile not found'
            ], 404);
        }

        $assets = $employee->assets()
                          ->with('category')
                          ->orderBy('assigned_date', 'desc')
                          ->paginate($request->get('per_page', 15));

        // Calculate summary
        $totalAssets = $employee->assets()->count();
        $assignedAssets = $employee->assets()->where('status', 'Assigned')->count();
        $overdueReturns = $employee->assets()
                                  ->where('status', 'Assigned')
                                  ->whereNotNull('expected_return_date')
                                  ->where('expected_return_date', '<', now())
                                  ->count();

        return response()->json([
            'status' => true,
            'message' => 'My assets retrieved successfully',
            'data' => [
                'assets' => $assets,
                'summary' => [
                    'total_assets' => $totalAssets,
                    'assigned_assets' => $assignedAssets,
                    'overdue_returns' => $overdueReturns,
                ]
            ]
        ]);
    }
}
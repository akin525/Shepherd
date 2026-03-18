<?php

namespace App\Http\Controllers\Operations;
use App\Http\Controllers\Controller;
use App\Models\ManningStructure;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ManningStructureController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = ManningStructure::query();

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('client_name', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%");
                });
            }

            $structures = $query->latest()->paginate($request->get('per_page', 10));

            $structures->getCollection()->transform(function ($item) {
                return [
                    'id' => $item->id,
                    'client_name' => $item->client_name,
                    'location' => $item->location,
                    'guards_count' => $item->total_guards,
                    // Extract shift count from JSON or default to 0
                    'shifts_count' => isset($item->shift_setup['number_of_shifts']) ? (int)$item->shift_setup['number_of_shifts'] : 0,
                    'start_date' => Carbon::parse($item->start_date)->format('d M Y'),
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Manning structures retrieved successfully',
                'data' => $structures
            ]);

        } catch (\Throwable $e) {
            return response()->json(['status' => false, 'message' => 'Failed to fetch data', 'error' => $e->getMessage()], 500);
        }
    }

    // Create New Manning Structure
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'client_name' => 'required|string',
            'location' => 'required|string',
            'start_date' => 'required|date',
            'total_guards' => 'required|integer',
            // Optional: Validate shift setup if sent from frontend immediately
            // 'shift_setup' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            // Default shift setup if not provided (matches your design defaults)
            $defaultShiftSetup = [
                'number_of_shifts' => "2 shifts",
                'shift_duration' => "12-hour shifts",
                'shift_timings' => "6AM–6PM / 6PM–6AM"
            ];

            $structure = ManningStructure::create([
                'client_name' => $request->client_name,
                'location' => $request->location,
                'start_date' => $request->start_date,
                'total_guards' => $request->total_guards,
                'shift_setup' => $request->shift_setup ?? $defaultShiftSetup
            ]);

            // Log creation
            AuditLogService::logCreate(
                Auth::user(),
                'Manning Structure',
                "{$request->client_name} - {$request->location}",
                "Manning structure created for {$request->client_name} at {$request->location} with {$request->total_guards} guards",
                $structure->toArray()
            );

            return response()->json([
                'status' => true,
                'message' => 'Manning structure created successfully',
                'data' => $structure
            ], 201);

        } catch (\Throwable $e) {
            // Log failure
            AuditLogService::logFailure(
                Auth::user(),
                'Created',
                'Manning Structure',
                $request->client_name ?? 'Unknown',
                "Failed to create manning structure: {$e->getMessage()}"
            );

            return response()->json(['status' => false, 'message' => 'Failed to create structure', 'error' => $e->getMessage()], 500);
        }
    }

    // Show Single Details
    public function show($id): JsonResponse
    {
        try {
            $structure = ManningStructure::find($id);

            if (!$structure) {
                return response()->json(['status' => false, 'message' => 'Structure not found'], 404);
            }

            // Log view action
            AuditLogService::logView(
                Auth::user(),
                'Manning Structure',
                "{$structure->client_name} - {$structure->location}",
                "Viewed manning structure details for {$structure->client_name} at {$structure->location}"
            );

            return response()->json([
                'status' => true,
                'data' => [
                    'id' => $structure->id,
                    'client_name' => $structure->client_name,
                    'location' => $structure->location,
                    'start_date' => Carbon::parse($structure->start_date)->format('d M Y'),
                    'total_guards' => $structure->total_guards,
                    'shift_setup' => $structure->shift_setup ?? [
                            'number_of_shifts' => 'Not Configured',
                            'shift_duration' => 'N/A',
                            'shift_timings' => 'N/A'
                        ]
                ]
            ]);

        } catch (\Throwable $e) {
            return response()->json(['status' => false, 'message' => 'Error loading details'], 500);
        }
    }
}

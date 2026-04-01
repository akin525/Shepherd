<?php

namespace App\Http\Controllers\Operations;
use App\Http\Controllers\Controller;
use App\Models\SopGenerator;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class SopGeneratorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = SopGenerator::query();

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('sop_title', 'like', "%{$search}%")
                        ->orWhere('client_name', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%");
                });
            }

            $sops = $query->latest()->paginate($request->get('per_page', 10));

            $sops->getCollection()->transform(function ($item) {
                return [
                    'id' => $item->id,
                    'sop_title' => $item->sop_title,
                    'client_name' => $item->client_name,
                    'location' => $item->location,
                    'effective_date' => Carbon::parse($item->effective_date)->format('d M Y'),
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'SOPs retrieved successfully',
                'data' => $sops
            ]);

        } catch (\Throwable $e) {
            return response()->json(['status' => false, 'message' => 'Failed to fetch SOPs'], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sop_title' => 'required|string',
            'client_name' => 'required|string',
            'location' => 'required|string',
            'effective_date' => 'required|date',
            // Arrays are optional initially, but validated if present
            'procedure_steps' => 'nullable|array',
            'responsibilities' => 'nullable|array',
            'emergency_instructions' => 'nullable|array',
            'document'=>'required|file',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            // Mock default data if arrays are empty (just for demo purposes)
            // In production, you might want to leave them null or empty

            if ($request->hasFile('document')) {
                $file = $request->file('document')->store('sop-document', 'public');
            }
            $sop = SopGenerator::create([
                'sop_title' => $request->sop_title,
                'client_name' => $request->client_name,
                'location' => $request->location,
                'effective_date' => $request->effective_date,
                'procedure_steps' => $request->procedure_steps ?? [],
                'responsibilities' => $request->responsibilities ?? [],
                'emergency_instructions' => $request->emergency_instructions ?? [],
                'document' => $file,
            ]);

            // Log SOP creation
            AuditLogService::logCreate(
                Auth::user(),
                'SOP',
                $request->sop_title,
                "SOP created: {$request->sop_title} for {$request->client_name} at {$request->location}",
                $sop->toArray()
            );

            return response()->json([
                'status' => true,
                'message' => 'SOP created successfully',
                'data' => $sop
            ], 201);

        } catch (\Throwable $e) {
            AuditLogService::logFailure(
                Auth::user(),
                'Created',
                'SOP',
                $request->sop_title ?? 'Unknown',
                "Failed to create SOP: {$e->getMessage()}"
            );

            return response()->json(['status' => false, 'message' => 'Failed to create SOP', 'error' => $e->getMessage()], 500);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $sop = SopGenerator::find($id);

            if (!$sop) {
                return response()->json(['status' => false, 'message' => 'SOP not found'], 404);
            }

            return response()->json([
                'status' => true,
                'data' => [
                    'id' => $sop->id,
                    'title' => $sop->sop_title,
                    'site' => $sop->location . ' (' . $sop->client_name . ')',
                    'effective_date' => Carbon::parse($sop->effective_date)->format('d M Y'),
                    'procedure_steps' => $sop->procedure_steps ?? [],
                    'responsibilities' => $sop->responsibilities ?? [],
                    'emergency_instructions' => $sop->emergency_instructions ?? [],
                ]
            ]);

        } catch (\Throwable $e) {
            return response()->json(['status' => false, 'message' => 'Error loading details'], 500);
        }
    }
}

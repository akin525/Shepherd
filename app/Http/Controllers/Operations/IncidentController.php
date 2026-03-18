<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Services\AuditLogService;

class IncidentController extends Controller
{

    public function index(Request $request): JsonResponse
    {
        try {
            $query = Incident::query();

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('incident_id', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhere('reported_by', 'like', "%{$search}%");
                });
            }

            $incidents = $query->latest()->paginate($request->get('per_page', 10));

            $incidents->getCollection()->transform(function ($item) {
                return [
                    'id' => $item->id,
                    'incident_id' => $item->incident_id,
                    'type' => $item->title,
                    'location' => $item->location,
                    // Format: "16 Jan 2026, 09:32 AM"
                    'date_time' => $item->incident_date->format('d M Y') . ', ' . ($item->incident_time ? \Carbon\Carbon::parse($item->incident_time)->format('h:i A') : ''),
                    'reported_by' => $item->reported_by,
                    'status' => ucfirst(str_replace('_', ' ', $item->status)), // "Under Review"
                    'raw_status' => $item->status
                ];
            });

            // Log view action
            AuditLogService::logView(
                Auth::user(),
                'Incidents',
                'List view',
                "Viewed incidents list with filters: " . ($request->search ? "search={$request->search}" : 'none')
            );

            return response()->json([
                'status' => true,
                'message' => 'Incidents retrieved successfully',
                'data' => $incidents
            ]);

        } catch (\Throwable $e) {
            return response()->json(['status' => false, 'message' => 'Error loading incidents'], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'guard_name' => 'required|string',
            'incident_type' => 'required|string',
            'incident_date' => 'required|date',
            'location' => 'required|string',
            'description' => 'required|string',
            'photos.*' => 'nullable|image|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $photoPaths = [];
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $photoPaths[] = $photo->store('incidents', 'public');
                }
            }

            $incidentId = 'INC-' . rand(3000, 9999);
            $incident = Incident::create([
                'incident_id' => $incidentId,
                'reported_by' => $request->guard_name,
                'title' => $request->incident_type,
                'incident_date' => $request->incident_date,
                'incident_time' => now()->format('H:i:s'),
                'location' => $request->location,
                'description' => $request->description,
                'evidence_photos' => $photoPaths,
                'status' => 'under_review'
            ]);

            // Log incident creation
            AuditLogService::logCreate(
                Auth::user(),
                'Incident',
                $incidentId,
                "Incident reported: {$request->incident_type} at {$request->location} by {$request->guard_name}",
                $incident->toArray()
            );

            return response()->json([
                'status' => true,
                'message' => 'Incident reported successfully',
                'data' => $incident
            ], 201);

        } catch (\Throwable $e) {
            // Log failure
            AuditLogService::logFailure(
                Auth::user(),
                'Incident',
                'Create incident',
                'Failed to report incident: ' . $e->getMessage()
            );
            
            return response()->json(['status' => false, 'message' => 'Failed to report incident'], 500);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $incident = Incident::where('incident_id', $id)->first();

            if (!$incident) {
                return response()->json(['status' => false, 'message' => 'Incident not found'], 404);
            }

            $data = [
                'header' => [
                    'id' => $incident->incident_id,
                    'type' => $incident->title,
                    'status' => ucfirst(str_replace('_', ' ', $incident->status)),
                    'location' => $incident->location,
                    'date_time' => $incident->incident_date->format('d M Y') . ', ' . \Carbon\Carbon::parse($incident->incident_time)->format('h:i A'),
                ],
                'description' => [
                    'what_happened' => $incident->description,
                    'how_it_happened' => 'Details pending investigation...',
                    'action_taken' => $incident->action_taken ?? 'None recorded',
                ],
                'reporter' => [
                    'name' => $incident->reported_by,
                    'site' => $incident->location,
                    'timestamp' => $incident->created_at->format('d M Y, h:i A')
                ],
                'evidence' => $incident->evidence_photos ?? []
            ];

            // Log view action
            AuditLogService::logView(
                Auth::user(),
                'Incident',
                $incident->incident_id,
                "Viewed incident details: {$incident->title} at {$incident->location}"
            );

            return response()->json([
                'status' => true,
                'data' => $data
            ]);

        } catch (\Throwable $e) {
            // Log failure
            AuditLogService::logFailure(
                Auth::user(),
                'Incident',
                'View incident',
                "Failed to view incident {$id}: " . $e->getMessage()
            );
            
            return response()->json(['status' => false, 'message' => 'Error loading details'], 500);
        }
    }
}

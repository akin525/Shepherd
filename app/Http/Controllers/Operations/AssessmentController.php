<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\SiteAssessment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Services\AuditLogService;

class AssessmentController extends Controller
{

    public function index(Request $request): JsonResponse
    {
        try {
            $query = SiteAssessment::with('client');

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('request_id', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhere('facility_type', 'like', "%{$search}%")
                        ->orWhereHas('client', function($c) use ($search) {
                            $c->where('name', 'like', "%{$search}%");
                        });
                });
            }

            $assessments = $query->latest()->paginate($request->get('per_page', 10));


            $assessments->getCollection()->transform(function ($item) {
                return [
                    'id' => $item->id,
                    'request_id' => $item->request_id,
                    'client_name' => $item->client->name ?? 'Unknown Client',
                    'location' => $item->location,
                    'facility_type' => $item->facility_type,
                    'request_date' => $item->created_at->format('d/m/Y'),
                    'status' => $item->status,

                ];
            });

            // Log view action
            AuditLogService::logView(
                Auth::user(),
                'Site Assessment',
                'List view',
                "Viewed assessments list with filters: " . ($request->search ? "search={$request->search}" : 'none')
            );

            return response()->json([
                'status' => true,
                'message' => 'Assessment requests retrieved successfully',
                'data' => $assessments
            ]);

        } catch (\Throwable $e) {
            // Log failure
            AuditLogService::logFailure(
                Auth::user(),
                'Site Assessment',
                'View list',
                'Failed to fetch assessments: ' . $e->getMessage()
            );

            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch assessments',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function show($id): JsonResponse
    {
        try {
            $assessment = SiteAssessment::with('client')->where('request_id', $id)->first();


            if (!$assessment) {
                $assessment = SiteAssessment::with('client')->find($id);
            }

            if (!$assessment) {
                return response()->json(['status' => false, 'message' => 'Assessment not found'], 404);
            }

            $data = [
                'header' => [
                    'request_id' => $assessment->request_id,
                    'client_name' => $assessment->client->name ?? 'N/A',
                    'site_name' => $assessment->site_name,
                    'site_address' => $assessment->site_address,
                    'facility_type' => $assessment->facility_type,
                ],
                'details' => [
                    'assessment_date' => $assessment->assessment_date ? \Carbon\Carbon::parse($assessment->assessment_date)->format('d F Y') : 'Pending',
                    'assessment_time' => $assessment->assessment_time ? \Carbon\Carbon::parse($assessment->assessment_time)->format('h:i A') : 'Pending',
                    'assessed_by' => $assessment->assessed_by ?? 'Not Assigned',
                    'status' => ucfirst($assessment->status),
                ],
                'requirements' => [
                    'guard_strength' => $assessment->guard_strength . ' Guards',
                    'cadre_type' => $assessment->cadre_type,
                    'armed_police' => $assessment->armed_police_required ? 'Yes' : 'No',
                    'shift_pattern' => $assessment->shift_pattern,
                ],
                // Decode JSON fields for lists
                'risks' => is_array($assessment->security_risks)
                    ? $assessment->security_risks
                    : json_decode($assessment->security_risks, true) ?? [],
                'observations' => is_array($assessment->general_observations)
                    ? $assessment->general_observations
                    : json_decode($assessment->general_observations, true) ?? [],
            ];

            // Log view action
            AuditLogService::logView(
                Auth::user(),
                'Site Assessment',
                $assessment->request_id,
                "Viewed assessment details for {$assessment->client->name} at {$assessment->site_name}"
            );

            return response()->json([
                'status' => true,
                'message' => 'Assessment details retrieved successfully',
                'data' => $data
            ]);

        } catch (\Throwable $e) {
            // Log failure
            AuditLogService::logFailure(
                Auth::user(),
                'Site Assessment',
                'View details',
                "Failed to load assessment {$id}: " . $e->getMessage()
            );

            return response()->json([
                'status' => false,
                'message' => 'Failed to load details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'client_id' => 'required|exists:users,id',
            'site_address' => 'required|string|max:255',
            'facility_type' => 'required|string|max:255',
            // Require exact coordinates
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'assessment_date' => 'nullable|date',
            'guard_requirements' => 'nullable|string',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {

            $attachmentPath = null;
            if ($request->hasFile('photo')) {
                $attachmentPath = $request->file('photo')->store('assessments', 'public');
            }

            $requestId = 'ASMT-' . date('Y') . '-' . rand(1000, 9999);

            $assessment = \App\Models\SiteAssessment::create([
                'request_id' => $requestId,
                'client_id' => $request->client_id,
                'site_name' => 'Site at ' . Str::limit($request->site_address, 20),
                'site_address' => $request->site_address,
                'location' => $request->input('location', 'Lagos'),
                'latitude' => $request->latitude,   // Saving coordinates
                'longitude' => $request->longitude, // Saving coordinates
                'facility_type' => $request->facility_type,
                'assessment_date' => $request->assessment_date,
                'guard_requirement_description' => $request->guard_requirements,
                'attachment' => $attachmentPath,
                'status' => 'pending'
            ]);

            // Log assessment creation with coordinates included
            AuditLogService::logCreate(
                Auth::user(),
                'Site Assessment',
                $requestId,
                "Site assessment requested for {$request->facility_type} at {$request->site_address} (Lat: {$request->latitude}, Lng: {$request->longitude})",
                $assessment->toArray()
            );

            return response()->json([
                'status' => true,
                'message' => 'Assessment request saved successfully.',
                'data' => $assessment
            ], 201);

        } catch (\Throwable $e) {
            // Log failure
            AuditLogService::logFailure(
                Auth::user(),
                'Site Assessment',
                'Create assessment',
                'Failed to save assessment: ' . $e->getMessage()
            );

            return response()->json([
                'status' => false,
                'message' => 'Failed to save assessment',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

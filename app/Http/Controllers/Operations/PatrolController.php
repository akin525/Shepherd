<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\PatrolLog;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PatrolController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = PatrolLog::query();
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('guard_name', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhere('patrol_area', 'like', "%{$search}%");
                });
            }

            $logs = $query->latest('patrol_date')->latest('patrol_time')
                ->paginate($request->get('per_page', 10));

            $logs->getCollection()->transform(function ($item) {
                // FIX: Parse date first, then force the time from the patrol_time column
                $dateTime = \Carbon\Carbon::parse($item->patrol_date)
                    ->setTimeFromTimeString($item->patrol_time);

                return [
                    'id' => $item->id,
                    'date_time' => $dateTime->format('d M Y, h:i A'),
                    'guard_name' => $item->guard_name,
                    'location' => $item->location,
                    'patrol_area' => $item->patrol_area,
                    'observation' => \Illuminate\Support\Str::limit($item->observation, 40),
                    'status' => ucfirst($item->status),
                    'is_escalated' => $item->status === 'escalated'
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Patrol logs retrieved successfully',
                'data' => $logs
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch logs',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'guards_on_site' => 'required|json',
            'essential_items' => 'required|json',

            'location' => 'required|string',
            'patrol_area' => 'required|string',
            'patrol_date' => 'required|date',
            'patrol_time' => 'required',
            'observation' => 'required|string',
            'incident_found' => 'required|boolean',
            'incident_description' => 'nullable|required_if:incident_found,1,true|string',
            'evidence.*' => 'nullable|file|mimes:jpg,jpeg,png,mp4,mov|max:20480',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $filePaths = [];
            if ($request->hasFile('evidence')) {
                foreach ($request->file('evidence') as $file) {
                    $filePaths[] = $file->store('patrols', 'public');
                }
            }

            $status = $request->boolean('incident_found') ? 'escalated' : 'completed';

            // Decode the essential items so Laravel can cast it to JSON in the database
            $essentialItems = json_decode($request->essential_items, true);

            $log = PatrolLog::create([
                // Map the new dynamic guards array directly to your existing column
                'guard_name' => $request->guards_on_site,
                'essential_items' => $essentialItems, // New Column
                'location' => $request->location,
                'patrol_area' => $request->patrol_area,
                'patrol_date' => $request->patrol_date,
                'patrol_time' => $request->patrol_time,
                'observation' => $request->observation,
                'incident_found' => $request->boolean('incident_found'),
                'incident_description' => $request->incident_description,
                'evidence_files' => $filePaths,
                'status' => $status
            ]);

            // Log patrol report
            AuditLogService::logCreate(
                $request->user(),
                'Patrol',
                "{$request->location} - {$request->patrol_area}",
                "Patrol report submitted for {$request->location}, area: {$request->patrol_area}, status: {$status}",
                $log->toArray()
            );

            return response()->json([
                'status' => true,
                'message' => 'Patrol report submitted successfully',
                'data' => $log
            ], 201);

        } catch (\Throwable $e) {
            AuditLogService::logFailure(
                $request->user(),
                'Created',
                'Patrol',
                $request->location ?? 'Unknown',
                "Failed to submit patrol report: {$e->getMessage()}"
            );

            return response()->json(['status' => false, 'message' => 'Failed to submit report', 'error' => $e->getMessage()], 500);
        }
    }

    public function saveGuardInspection(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'guards_on_site'       => 'required|json',
            'essential_items'      => 'required|json',
            'location'             => 'required|string',
            'patrol_area'          => 'required|string',
            'patrol_date'          => 'required|date',
            'patrol_time'          => 'required',
            'observation'          => 'required|string',
            'incident_found'       => 'required|boolean',
            'incident_description' => 'nullable|required_if:incident_found,1,true|string',
            'evidence.*'           => 'nullable|file|mimes:jpg,jpeg,png,mp4,mov|max:20480',
            'uniform_photo'        => 'nullable|file|mimes:jpg,jpeg,png|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $filePaths = [];
            if ($request->hasFile('evidence')) {
                foreach ($request->file('evidence') as $file) {
                    $filePaths[] = $file->store('patrols', 'public');
                }
            }

            $photoPath = null;
            if ($request->hasFile('uniform_photo')) {
                $photoPath = $request->file('uniform_photo')->store('inspections', 'public');
            }

            $metaData = [
                'uniform' => [
                    'proper_uniform' => $request->input('proper_uniform'),
                    'id_visible'     => $request->input('id_visible'),
                    'photo'          => $photoPath
                ],
                'alertness' => [
                    'awake'       => $request->input('awake'),
                    'phone_usage' => $request->input('phone_usage'),
                    'notes'       => $request->input('alertness_notes')
                ],
                'equipment' => [
                    'radio'      => $request->input('equipment_radio'),
                    'flashlight' => $request->input('equipment_flashlight'),
                    'log_book'   => $request->input('equipment_log_book'),
                    'whistle'    => $request->input('equipment_whistle'),
                    'others'     => $request->input('equipment_others')
                ],
                'misconduct' => [
                    'type'    => $request->input('misconduct_type'),
                    'details' => $request->input('misconduct_details')
                ]
            ];

            $status = $request->boolean('incident_found') ? 'escalated' : 'completed';
            $essentialItems = json_decode($request->essential_items, true);

            $log = PatrolLog::create([
                'user_id'              =>$request->user()->id,
                'guard_name'           => $request->guards_on_site,
                'location'             => $request->location,
                'patrol_area'          => $request->patrol_area,
                'patrol_date'          => $request->patrol_date,
                'patrol_time'          => $request->patrol_time,
                'observation'          => $request->observation,
                'essential_items'      => $essentialItems,
                'incident_found'       => $request->boolean('incident_found'),
                'incident_description' => $request->incident_description,
                'evidence_files'       => $filePaths,
                'status'               => $status,
                'meta'                 => $metaData
            ]);

            AuditLogService::logCreate(
                $request->user(),
                'Patrol',
                "{$request->location} - {$request->patrol_area}",
                "Patrol report submitted for {$request->location}, area: {$request->patrol_area}, status: {$status}",
                $log->toArray()
            );

            return response()->json([
                'status' => true,
                'message' => 'Patrol report and inspection saved successfully',
                'data' => $log
            ], 201);

        } catch (\Throwable $e) {
            AuditLogService::logFailure(
                $request->user(),
                'Created',
                'Patrol',
                $request->location ?? 'Unknown',
                "Failed to submit patrol report: {$e->getMessage()}"
            );

            return response()->json(['status' => false, 'message' => 'Failed to submit report', 'error' => $e->getMessage()], 500);
        }
    }

    public function getAssignments(Request $request)
    {
        try {
            $userId = $request->user()->id;
            $today = Carbon::today()->toDateString();

            $patrols = PatrolLog::where('user_id', $userId)
                ->where('patrol_date', $today)
                ->orderBy('patrol_time', 'asc') // Order by time of day
                ->get();

            $totalLocations = $patrols->count();
            $completedCount = $patrols->where('status', 'completed')->count();
            $highPriorityCount = $patrols->filter(function ($patrol) {
                $meta = is_string($patrol->meta) ? json_decode($patrol->meta, true) : $patrol->meta;
                return isset($meta['priority']) && $meta['priority'] === 'high';
            })->count();

            // 2. Format the Location List for the UI
            $locationList = $patrols->map(function ($patrol) {

                // Extract meta for frontend usage
                $meta = is_string($patrol->meta) ? json_decode($patrol->meta, true) : $patrol->meta;
                $priority = $meta['priority'] ?? 'normal';

                return [
                    'id'          => $patrol->id,
                    'location'    => $patrol->location,
                    // Format the database time (e.g., "08:00:00") to the UI format ("08:00 AM")
                    'time'        => Carbon::parse($patrol->patrol_time)->format('g:i A'),
                    'status'      => $patrol->status, // 'pending', 'completed', or 'escalated'
                    'priority'    => $priority,       // 'high' or 'normal'
                    'is_priority' => $priority === 'high'
                ];
            })->values(); // Re-index array

            // 3. Return the formatted payload
            return response()->json([
                'status' => true,
                'message' => 'Assignments retrieved successfully',
                'data' => [
                    'summary' => [
                        'total_locations' => $totalLocations,
                        'completed'       => $completedCount,
                        'high_priority'   => $highPriorityCount,
                    ],
                    'locations' => $locationList
                ]
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Failed to retrieve assignments',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function getReportSummary(Request $request)
    {
        try {
            $userId = $request->user()->id;
            $today = Carbon::today()->toDateString();

            $patrols = PatrolLog::where('user_id', $userId)
                ->where('patrol_date', $today)
                ->orderBy('patrol_time', 'asc')
                ->get();

            $totalAssigned = $patrols->count();
            $completedPatrols = $patrols->where('status', 'completed');
            $completedCount = $completedPatrols->count();
            $incidentsCount = $patrols->where('incident_found', 1)->count();
            $misconductCount = $patrols->filter(function ($patrol) {
                $meta = is_string($patrol->meta) ? json_decode($patrol->meta, true) : $patrol->meta;
                return isset($meta['misconduct']['type']) &&
                    $meta['misconduct']['type'] !== 'None' &&
                    $meta['misconduct']['type'] !== '';
            })->count();
            $feedbackCount = 3;
            $locationList = $patrols->map(function ($patrol) {
                $meta = is_string($patrol->meta) ? json_decode($patrol->meta, true) : $patrol->meta;
                $notes = $patrol->observation ?? 'Guard compliant, no issues'; // Default

                if (isset($meta['misconduct']['type']) && $meta['misconduct']['type'] !== 'None') {
                    $notes = "Misconduct: " . ($meta['misconduct']['details'] ?? $meta['misconduct']['type']);
                } elseif ($patrol->incident_found) {
                    $notes = "Incident: " . $patrol->incident_description;
                } elseif (isset($meta['priority']) && $meta['priority'] === 'high') {
                    $notes = "High Priority: " . $patrol->observation;
                }

                return [
                    'id'       => $patrol->id,
                    'location' => $patrol->location,
                    'time'     => Carbon::parse($patrol->patrol_time)->format('h:i A'),
                    'status'   => ucfirst($patrol->status), // e.g., "Completed"
                    'notes'    => $notes
                ];
            })->values();

            // 4. Return the formatted payload
            return response()->json([
                'status' => true,
                'message' => 'Report summary retrieved successfully',
                'data' => [
                    'summary' => [
                        // Formatted exactly like the UI: "3/3"
                        'locations_visited' => "{$completedCount}/{$totalAssigned}",
                        'misconduct_cases'  => $misconductCount,
                        'incidents_logged'  => $incidentsCount,
                        'client_feedback'   => $feedbackCount,
                    ],
                    'locations' => $locationList
                ]
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Failed to retrieve report summary',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}

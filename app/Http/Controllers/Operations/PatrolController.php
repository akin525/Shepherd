<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\PatrolLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
            'guard_name' => 'required|string',
            'location' => 'required|string',
            'patrol_area' => 'required|string',
            'patrol_date' => 'required|date',
            'patrol_time' => 'required',
            'observation' => 'required|string',
            'incident_found' => 'required|boolean',
            'incident_description' => 'nullable|required_if:incident_found,true|string',
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

            $log = PatrolLog::create([
                'guard_name' => $request->guard_name,
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

            return response()->json([
                'status' => true,
                'message' => 'Patrol report submitted successfully',
                'data' => $log
            ], 201);

        } catch (\Throwable $e) {
            return response()->json(['status' => false, 'message' => 'Failed to submit report', 'error' => $e->getMessage()], 500);
        }
    }
}

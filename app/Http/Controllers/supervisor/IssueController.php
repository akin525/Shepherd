<?php

namespace App\Http\Controllers\supervisor;

use App\Models\Issue;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class IssueController extends Controller
{
    public function create(Request $request): JsonResponse
    {

        $categories = [
            ['id' => 'payroll', 'name' => 'Payroll Issue'],
            ['id' => 'disciplinary', 'name' => 'Disciplinary Appeal'],
            ['id' => 'facility', 'name' => 'Facility/Equipment'],
            ['id' => 'harassment', 'name' => 'Harassment/Bullying'],
            ['id' => 'other', 'name' => 'Other'],
        ];

         $recipients = User::whereIn('type', ['admin', 'hr', 'supervisor'])
            ->select('id', 'name', 'type')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Form options retrieved',
            'data' => [
                'categories' => $categories,
                'recipients' => $recipients,
            ]
        ]);
    }


    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['status' => false, 'message' => 'User not found'], 404);
        }
        $employee = $user->employee;


        $validator = Validator::make($request->all(), [
            'title'        => 'required|string|max:255',
            'description'  => 'required|string',
            'category'     => 'required|string',
//            'recipient_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }
        $activeDeployment = $employee->deployments()
            ->where('status', 1)
            ->latest()
            ->first();

        $issue = Issue::create([
            'employee_id'  => $user->employee->id,
            'title'        => $request->title,
            'description'  => $request->description,
            'category'     => $request->category,
            'recipient_id' => $activeDeployment->client_id,
            'status'       => 'pending',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Issue reported successfully',
            'data' => $issue
        ]);
    }
}

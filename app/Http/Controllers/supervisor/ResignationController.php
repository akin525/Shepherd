<?php
namespace App\Http\Controllers\supervisor;

use App\Models\Resignation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ResignationController extends Controller
{

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user || !$user->employee) {
            return response()->json(['status' => false, 'message' => 'Employee record not found'], 404);
        }
        $employee = $user->employee;

        $validator = Validator::make($request->all(), [
            'description'      => 'required|string',
            'resignation_date' => 'nullable|date|after:today',
//            'recipient_id'     => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $noticeDate = now();

        $activeDeployment = $employee->deployments()
            ->where('status', 1)
            ->latest()
            ->first();

        $resignationDate = $request->input('resignation_date')
            ? Carbon::parse($request->input('resignation_date'))
            : now()->addDays(30);


        $resignation = Resignation::create([
            'employee_id'      => $user->employee->id,
            'notice_date'      => $noticeDate,
            'resignation_date' => $resignationDate,
            'description'      => $request->description,
            'created_by'       => $user->id,
        ]);

        if ($request->has('recipient_id')) {
            $recipient = User::find($request->recipient_id);
            // Mail::to($recipient)->send(new ResignationSubmitted($resignation));
        }

        return response()->json([
            'status' => true,
            'message' => 'Resignation submitted successfully',
            'data' => $resignation
        ]);
    }
}

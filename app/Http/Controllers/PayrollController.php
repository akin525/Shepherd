<?php

namespace App\Http\Controllers;

use App\Models\PaySlip;
use App\Models\Employee;
use App\Models\SetSalary;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class PayrollController extends Controller
{
    /**
     * Display a listing of payslips.
     */
    public function index(Request $request): JsonResponse
    {
        $query = PaySlip::with(['employee', 'employee.department', 'employee.designation']);

        // Filter by employee
        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        // Filter by month
        if ($request->has('month')) {
            $query->whereMonth('salary_month', $request->month);
        }

        // Filter by year
        if ($request->has('year')) {
            $query->whereYear('salary_month', $request->year);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('salary_month', [$request->start_date, $request->end_date]);
        }

        // For employees, only show their own payslips
        if (!Auth::user()->isAdmin()) {
            $employee = Auth::user()->employee;
            if ($employee) {
                $query->where('employee_id', $employee->id);
            }
        }

        $payslips = $query->orderBy('salary_month', 'desc')
                         ->paginate($request->get('per_page', 12));

        return response()->json([
            'status' => true,
            'message' => 'Payslips retrieved successfully',
            'data' => [
                'payslips' => $payslips
            ]
        ]);
    }

    /**
     * Get payslips for authenticated employee
     */
    public function payslips(Request $request): JsonResponse
    {
        $employee = Auth::user()->employee;

        if (!$employee) {
            return response()->json([
                'status' => false,
                'message' => 'Employee profile not found'
            ], 404);
        }

        $query = $employee->payslips();

        // Filter by month/year
        if ($request->has('month')) {
            $query->whereMonth('salary_month', $request->month);
        }
        if ($request->has('year')) {
            $query->whereYear('salary_month', $request->year);
        }

        $payslips = $query->orderBy('salary_month', 'desc')
                         ->paginate($request->get('per_page', 12));

        // Calculate summary
        $thisYear = Carbon::now()->year;
        $yearlyEarnings = $employee->payslips()
                                 ->whereYear('salary_month', $thisYear)
                                 ->sum('net_payble');

        $thisMonth = Carbon::now()->month;
        $monthlyEarnings = $employee->payslips()
                                  ->whereMonth('salary_month', $thisMonth)
                                  ->whereYear('salary_month', $thisYear)
                                  ->sum('net_payble');

        return response()->json([
            'status' => true,
            'message' => 'Payslips retrieved successfully',
            'data' => [
                'payslips' => $payslips,
                'summary' => [
                    'current_salary' => $employee->salary,
                    'salary_type' => $employee->salary_type,
                    'yearly_earnings' => $yearlyEarnings,
                    'monthly_earnings' => $monthlyEarnings,
                    'total_payslips' => $employee->payslips()->count(),
                ]
            ]
        ]);
    }

    /**
     * Display the specified payslip.
     */
    public function showPayslip(PaySlip $payslip): JsonResponse
    {
        // Check authorization
        if (!Auth::user()->isAdmin() && $payslip->employee_id !== Auth::user()->employee->id) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized to view this payslip'
            ], 403);
        }

        $payslip->load([
            'employee',
            'employee.department',
            'employee.designation',
            'employee.user'
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Payslip retrieved successfully',
            'data' => [
                'payslip' => $payslip
            ]
        ]);
    }

    /**
     * Get salary breakdown for authenticated employee
     */
    public function salaryBreakdown(Request $request): JsonResponse
    {
        $employee = Auth::user()->employee;

        if (!$employee) {
            return response()->json([
                'status' => false,
                'message' => 'Employee profile not found'
            ], 404);
        }

        $salary = $employee->salary ?? 0;
        $salaryType = $employee->salary_type ?? 'monthly';

        // Mock salary breakdown (should be based on actual salary structure)
        $breakdown = [
            'basic_salary' => $salary * 0.6,
            'house_rent_allowance' => $salary * 0.2,
            'transport_allowance' => $salary * 0.1,
            'medical_allowance' => $salary * 0.05,
            'other_allowances' => $salary * 0.05,
        ];

        // Calculate deductions (mock percentages)
        $deductions = [
            'provident_fund' => $salary * 0.08,
            'professional_tax' => $salary * 0.02,
            'income_tax' => $salary * 0.1,
            'insurance' => $salary * 0.03,
        ];

        $totalEarnings = array_sum($breakdown);
        $totalDeductions = array_sum($deductions);
        $netSalary = $totalEarnings - $totalDeductions;

        // Get monthly average from payslips
        $monthlyAverage = $employee->payslips()
                                  ->where('status', 'Paid')
                                  ->avg('net_payble') ?? $netSalary;

        return response()->json([
            'status' => true,
            'message' => 'Salary breakdown retrieved successfully',
            'data' => [
                'salary_info' => [
                    'basic_salary' => $salary,
                    'salary_type' => $salaryType,
                    'effective_date' => $employee->date_of_joining,
                ],
                'earnings' => $breakdown,
                'deductions' => $deductions,
                'summary' => [
                    'total_earnings' => $totalEarnings,
                    'total_deductions' => $totalDeductions,
                    'net_payble' => $netSalary,
                    'monthly_average' => round($monthlyAverage, 2),
                    'annual_ctc' => $netSalary * 12,
                ]
            ]
        ]);
    }

    /**
     * Get payroll statistics (Admin only)
     */
    public function payrollStats(Request $request): JsonResponse
    {
        $month = $request->get('month', Carbon::now()->month);
        $year = $request->get('year', Carbon::now()->year);

        $query = PaySlip::whereMonth('salary_month', $month)
                       ->whereYear('salary_month', $year);

        $totalPayout = $query->sum('net_payble');
        $totalEmployees = $query->count();
        $averageSalary = $query->avg('net_payble');
        $paidCount = $query->where('status', 'Paid')->count();
        $pendingCount = $query->where('status', 'Unpaid')->count();

        // Get department-wise breakdown
        $departmentBreakdown = PaySlip::with(['employee.department'])
                                     ->whereMonth('salary_month', $month)
                                     ->whereYear('salary_month', $year)
                                     ->get()
                                     ->groupBy(function ($payslip) {
                                         return $payslip->employee->department->name ?? 'Unassigned';
                                     })
                                     ->map(function ($departmentPayslips) {
                                         return [
                                             'employee_count' => $departmentPayslips->count(),
                                             'total_payout' => $departmentPayslips->sum('net_payble'),
                                             'average_salary' => $departmentPayslips->avg('net_payble'),
                                         ];
                                     });

        return response()->json([
            'status' => true,
            'message' => 'Payroll statistics retrieved successfully',
            'data' => [
                'period' => [
                    'month' => $month,
                    'year' => $year,
                    'month_name' => Carbon::create($year, $month)->format('F Y'),
                ],
                'summary' => [
                    'total_payout' => $totalPayout,
                    'total_employees' => $totalEmployees,
                    'average_salary' => round($averageSalary, 2),
                    'paid_count' => $paidCount,
                    'pending_count' => $pendingCount,
                    'payment_completion_rate' => $totalEmployees > 0 ? round(($paidCount / $totalEmployees) * 100, 2) : 0,
                ],
                'department_breakdown' => $departmentBreakdown,
            ]
        ]);
    }

    /**
     * Generate payslip (Admin only)
     */
    public function generatePayslip(Request $request): JsonResponse
    {
        $validator = validator($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:' . (Carbon::now()->year + 1),
            'basic_salary' => 'required|numeric|min:0',
            'allowances' => 'nullable|array',
            'deductions' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $employee = Employee::findOrFail($request->employee_id);

            // Check if payslip already exists
            $existingPayslip = PaySlip::where('employee_id', $employee->id)
                                     ->whereMonth('salary_month', $request->month)
                                     ->whereYear('salary_month', $request->year)
                                     ->first();

            if ($existingPayslip) {
                return response()->json([
                    'status' => false,
                    'message' => 'Payslip already exists for this period'
                ], 400);
            }

            $paymentDate = Carbon::create($request->year, $request->month, 1)->lastOfMonth();

            // Calculate totals (simplified calculation)
            $allowanceTotal = array_sum($request->allowances ?? []);
            $deductionTotal = array_sum($request->deductions ?? []);
            $grossSalary = $request->basic_salary + $allowanceTotal;
            $netSalary = $grossSalary - $deductionTotal;

            $payslip = PaySlip::create([
                'employee_id' => $employee->id,
                'salary_month' => $paymentDate,
                'basic_salary' => $request->basic_salary,
                'allowance' => json_encode($request->allowances ?? []),
                'deduction' => json_encode($request->deductions ?? []),
                'gross_salary' => $grossSalary,
                'net_payble' => $netSalary,
                'status' => 'Unpaid',
                'created_by' => Auth::id(),
                'payslip_type_id' => 1, // Default payslip type
            ]);

            $payslip->load(['employee', 'employee.department', 'employee.designation']);

            return response()->json([
                'status' => true,
                'message' => 'Payslip generated successfully',
                'data' => [
                    'payslip' => $payslip
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to generate payslip',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve payslip (Admin only)
     */
    public function approvePayslip(Request $request, PaySlip $payslip): JsonResponse
    {
        if ($payslip->status === 'Paid') {
            return response()->json([
                'status' => false,
                'message' => 'Payslip is already paid'
            ], 400);
        }

        try {
            $payslip->update([
                'status' => 'Paid',
                'approved_by' => Auth::id(),
                'approved_at' => Carbon::now(),
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Payslip approved successfully',
                'data' => [
                    'payslip' => $payslip->load('employee')
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to approve payslip',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payroll report (Admin only)
     */
    public function payrollReport(Request $request): JsonResponse
    {
        $month = $request->get('month', Carbon::now()->month);
        $year = $request->get('year', Carbon::now()->year);
        $departmentId = $request->get('department_id');

        $query = PaySlip::with(['employee', 'employee.department', 'employee.designation'])
                       ->whereMonth('salary_month', $month)
                       ->whereYear('salary_month', $year);

        if ($departmentId) {
            $query->whereHas('employee', function ($q) use ($departmentId) {
                $q->where('department_id', $departmentId);
            });
        }

        $payslips = $query->get();

        $report = [
            'period' => [
                'month' => $month,
                'year' => $year,
                'month_name' => Carbon::create($year, $month)->format('F Y'),
            ],
            'summary' => [
                'total_employees' => $payslips->count(),
                'total_payout' => $payslips->sum('net_payble'),
                'total_gross_salary' => $payslips->sum('gross_salary'),
                'total_deductions' => $payslips->sum('gross_salary') - $payslips->sum('net_payble'),
                'average_salary' => $payslips->avg('net_payble'),
                'paid_count' => $payslips->where('status', 'Paid')->count(),
                'unpaid_count' => $payslips->where('status', 'Unpaid')->count(),
            ],
            'payslips' => $payslips->map(function ($payslip) {
                return [
                    'id' => $payslip->id,
                    'employee' => [
                        'name' => $payslip->employee->name,
                        'employee_id' => $payslip->employee->employee_id,
                        'department' => $payslip->employee->department->name ?? 'N/A',
                        'designation' => $payslip->employee->designation->name ?? 'N/A',
                    ],
                    'salary_month' => $payslip->salary_month,
                    'basic_salary' => $payslip->basic_salary,
                    'gross_salary' => $payslip->gross_salary,
                    'net_payble' => $payslip->net_payble,
                    'status' => $payslip->status,
                ];
            }),
            'department_wise' => $payslips->groupBy(function ($payslip) {
                return $payslip->employee->department->name ?? 'Unassigned';
            })->map(function ($deptPayslips) {
                return [
                    'employee_count' => $deptPayslips->count(),
                    'total_payout' => $deptPayslips->sum('net_payble'),
                    'average_salary' => $deptPayslips->avg('net_payble'),
                ];
            }),
        ];

        return response()->json([
            'status' => true,
            'message' => 'Payroll report generated successfully',
            'data' => $report
        ]);
    }
}

<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\CustomerRetainershipContact;
use App\Models\CustomerRetainershipEquipment;
use App\Models\CustomerRetainershipForm;
use App\Models\CustomerRetainershipService;
use App\Models\CustomerRetainershipSignatory;
use App\Models\CustomerRetainershipTerritory;
use App\Models\Employee;
use App\Models\Equipment; // Assuming your base equipment table is named Equipment
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class FinanceCustomerRetainershipController extends Controller
{
    /**
     * List all Customer Retainership Forms.
     */
    public function index()
    {

        try {
            $forms = CustomerRetainershipForm::with(['client:id,name,email'])
                ->withCount(['contacts', 'services', 'equipments'])
                ->latest()
                ->paginate(20);

            return response()->json([
                'status' => true,
                'data' => [
                    'forms' => $forms,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Retainership List Error: '.$e->getMessage(),
            ], 500);
        }
    }

    public function generate(Request $request)
    {

        $authenticatedUser = $request->user();

        $validator = Validator::make($request->all(), [
            'client_id' => 'required|exists:clients,id',
            'issue_date' => 'required|date',
            'new_activation' => 'required|boolean',

            // Contacts
            'contact_detail' => 'required|array|min:1',
            'contact_detail.*.role' => 'required|string',
            'contact_detail.*.name' => 'required|string',

            // Territories
            'teritories' => 'required|array|min:1',
            'teritories.*.ops_officer_in_charge' => 'required|exists:employees,id',
            'teritories.*.responsible_staff' => 'required|exists:employees,id',
            'teritories.*.hod_in_charge' => 'required|exists:employees,id',
            'teritories.*.operations_manager' => 'required|exists:employees,id',
            'teritories.*.credit_controller_region' => 'required|exists:employees,id',
            'teritories.*.business_dev_manager' => 'required|exists:employees,id',

            // Services (Updated Validation)
            'service' => 'required|array|min:1',
            'service.*.grade' => 'required|exists:services,id', // 'grade' is the ID from services table
            'service.*.shift_pattern' => 'required|string',
            'service.*.quantity' => 'required|numeric|min:1',

            // Equipment
            'equipment' => 'nullable|array',
            'equipment.*.device' => 'required|exists:equipments,id',
            'equipment.*.cost' => 'required|numeric|min:1',
            'equipment.*.monthly_service_cost' => 'required|numeric|min:1',
            'equipment.*.quantity' => 'required|numeric|min:1',

            // Signatories
            'signatories' => 'required|array|min:1',
            'signatories.*.employee_id' => 'required|exists:employees,id',
            'signatories.*.role' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $form = CustomerRetainershipForm::create([
                'client_id' => $request->client_id,
                'code' => 'CRF-'.strtoupper(Str::random(5)).'-'.date('Ymd'),
                'issue_date' => $request->issue_date,
                'new_activation' => $request->new_activation,
            ]);

            // 1. Save Contacts
            foreach ($request->contact_detail as $contact) {
                CustomerRetainershipContact::create(array_merge($contact, ['form_id' => $form->id]));
            }

            // 2. Save Territory
            $territoryData = $request->teritories[0];
            CustomerRetainershipTerritory::create(array_merge($territoryData, ['form_id' => $form->id]));

            // 3. Save Services (Updated Calculation Logic)
            foreach ($request->service as $s) {
                $masterService = \App\Models\Service::findOrFail($s['grade']);

                // guard_monthly_net is gotten from master service rate
                $monthlyNet = (float) $masterService->rate;
                $qty = (int) $s['quantity'];

                // Assuming gross billing per guard is also based on the rate or a markup logic
                // If gross_billing is different, adjust here. For now using monthlyNet as base.
                $grossBilling = $monthlyNet;
                $billingPerMonth = $qty * $grossBilling;

                CustomerRetainershipService::create([
                    'form_id' => $form->id,
                    'service_id' => $masterService->id,
                    'grade' => $masterService->name, // Snapshot name
                    'shift_pattern' => $s['shift_pattern'],
                    'guard_monthly_net' => $monthlyNet,
                    'quantity' => $qty,
                    'gross_billing_per_guard' => $grossBilling,
                    'billing_per_month' => $billingPerMonth,
                ]);
            }

            // 4. Save Equipment
            if ($request->has('equipment')) {
                foreach ($request->equipment as $e) {
                    $masterEquip = Equipment::findOrFail($e['device']);
                    $billingPerMonth = (float) $e['quantity'] * (float) $e['monthly_service_cost'];

                    CustomerRetainershipEquipment::create([
                        'form_id' => $form->id,
                        'device' => $masterEquip->name,
                        'cost' => $e['cost'],
                        'quantity' => $e['quantity'],
                        'monthly_service_cost' => $e['monthly_service_cost'] ?? 0,
                        'billing_per_month' => $billingPerMonth,
                    ]);
                }
            }

            // 5. Save Signatories
            foreach ($request->signatories as $sig) {
                CustomerRetainershipSignatory::create(array_merge($sig, [
                    'form_id' => $form->id,
                    'status' => 'pending',
                ]));
            }

            ActivityLog::create([
                'user_id' => $authenticatedUser->id,
                'ip_address' => $request->ip(),
                'activity' => "Created Retainership Form {$form->code}",
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Retainership form created successfully.',
                'data' => $form->load(['contacts', 'territories', 'services', 'equipments', 'signatories']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get a single Retainership Form by its unique code with all nested relationships.
     */
    /**
     * Get a single Retainership Form by its unique code with Signatory Permission Check.
     */
    public function showByCode(Request $request, $code)
    {

        $authenticatedUser = $request->user();

        try {
            // 2. Fetch Form with Eager Loading
            $form = CustomerRetainershipForm::where('code', $code)
                ->with([
                    'client',
                    'contacts',
                    'territories.opsOfficer:id,name',
                    'territories.responsibleStaff:id,name',
                    'territories.hod:id,name',
                    'territories.operationsManager:id,name',
                    'territories.creditController:id,name',
                    'territories.bdm:id,name',
                    'services.serviceMaster',
                    'equipments.equipment',
                    // Nested eager loading: employee -> user
                    'signatories.employee' => function ($query) {
                        $query->select('id', 'user_id', 'name', 'email', 'phone')
                              ->with('user:id,signature'); // Adjust user columns as needed
                    },
                ])
                ->first();

            if (!$form) {
                return response()->json(['status' => false, 'message' => 'Form not found.'], 404);
            }

            // 1. Fetch the user as an Eloquent Model
            $userModel = User::find($authenticatedUser->id);

            if (!$userModel) {
                return response()->json(['status' => false, 'message' => 'User record not found.'], 404);
            }

            // 2. PERMISSION CHECK
            $isAdmin = (isset($userModel->type) && $userModel->type === 'admin');
            $isAuthorized = false;

            if ($isAdmin) {
                $isAuthorized = true;
            } else {
                /* Logic: Check user_roles table -> role_id -> role_permissions table -> permission_id (85)
                   We use whereHas to reach through the roles relationship to the rolePermissions relationship.
                */
                $hasPermission = User::where('id', $userModel->id)
                    ->whereHas('roles.rolePermissions', function ($query) {
                        $query->where('permission_id', 85);
                    })
                    ->exists();

                if ($hasPermission) {
                    $isAuthorized = true;
                } else {
                    // Fallback: Check if user is a designated Signatory for this specific form
                    $employee = Employee::where('user_id', $userModel->id)->first();
                    if ($employee) {
                        $isAuthorized = (bool) $form->signatories->firstWhere('employee_id', $employee->id);
                    }
                }
            }

            // 3. Final Authorization Check
            if (!$isAuthorized) {
                return response()->json([
                    'status' => false,
                    'message' => 'Access Denied: You do not have the required role permissions or signatory status.',
                ], 403);
            }

            // 5. Success
            return response()->json([
                'status' => true,
                'data' => $form,
            ], 200);
        } catch (\Exception $e) {
            \Log::error("Retainership Retrieval Access Error [Code: $code]: ".$e->getMessage());

            return response()->json([
                'status' => false,
                'message' => "Internal Server Error at line {$e->getLine()}: {$e->getMessage()}",
            ], 500);
        }
    }

    /**
     * Get the signatory list and status for a specific form by code.
     */
    public function signatoryByCode($code)
    {

        try {
            // 2. Fetch the Form and its Signatories with Employee details
            $form = CustomerRetainershipForm::where('code', $code)
                ->select('id', 'client_id', 'code', 'issue_date', 'new_activation') // Get core form info
                ->with([
                    'client:id,name',
                    'signatories' => function ($query) {
                        $query->select('id', 'form_id', 'employee_id', 'role', 'status', 'signed_at');
                    },
                    'signatories.employee:id,name,email,phone', // Deep load employee details for each signatory
                ])
                ->first();

            // 3. Error Handling if code is invalid
            if (!$form) {
                return response()->json([
                    'status' => false,
                    'message' => "Retainership record not found for code: {$code}",
                ], 404);
            }

            // 4. Return the structured data
            return response()->json([
                'status' => true,
                'data' => [
                    'form_code' => $form->code,
                    'client_name' => $form->client->name ?? 'N/A',
                    'issue_date' => $form->issue_date,
                    'signatories' => $form->signatories,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Signatory Retrieval Error: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get the list of equipment assigned to a form by code.
     */
    public function equipmentByCode($code)
    {

        try {
            // 2. Fetch Form with Equipment and Master Equipment Relationship
            $form = CustomerRetainershipForm::where('code', $code)
                ->select('id', 'client_id', 'code')
                ->with([
                    'client:id,name',
                    'equipments' => function ($query) {
                        $query->select(
                            'id',
                            'form_id',
                            'device',
                            'cost',
                            'quantity',
                            'monthly_service_cost',
                            'billing_per_month'
                        );
                    },
                    // Link to the master equipment table for extra details (category, brand, etc.)
                    'equipments.equipment',
                ])
                ->first();

            // 3. Error Handling
            if (!$form) {
                return response()->json([
                    'status' => false,
                    'message' => "Retainership record not found for code: {$code}",
                ], 404);
            }

            // 4. Return the equipment data
            return response()->json([
                'status' => true,
                'data' => [
                    'form_code' => $form->code,
                    'client_name' => $form->client->name ?? 'N/A',
                    'equipment_list' => $form->equipments,
                    'total_equipment_billing' => $form->equipments->sum('billing_per_month'),
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Equipment Retrieval Error: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get the list of services/guarding grades for a form by code.
     */
    public function serviceByCode($code)
    {


        try {
            // 2. Fetch Form with Services and Master Service Relationship
            $form = CustomerRetainershipForm::where('code', $code)
                ->select('id', 'client_id', 'code')
                ->with([
                    'client:id,name',
                    'services' => function ($query) {
                        $query->select(
                            'id',
                            'form_id',
                            'service_id',
                            'grade',
                            'shift_pattern',
                            'guard_monthly_net',
                            'quantity',
                            'gross_billing_per_guard',
                            'billing_per_month'
                        );
                    },
                    // Link to the master services table for official rates or categories
                    'services.serviceMaster',
                ])
                ->first();

            // 3. Error Handling
            if (!$form) {
                return response()->json([
                    'status' => false,
                    'message' => "Retainership record not found for code: {$code}",
                ], 404);
            }

            // 4. Return the service data
            return response()->json([
                'status' => true,
                'data' => [
                    'form_code' => $form->code,
                    'client_name' => $form->client->name ?? 'N/A',
                    'service_list' => $form->services,
                    'total_guards' => $form->services->sum('quantity'),
                    'total_service_billing' => $form->services->sum('billing_per_month'),
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Service Retrieval Error: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a signatory status (Manager or Admin Approval).
     */
    public function updateSignatory(Request $request, $id)
    {

        $authenticatedUser = $request->user();

        // 2. Validation
        $validator = Validator::make($request->all(), [
            'signatory_id' => 'required|exists:customer_retainership_signatories,id',
            'status' => 'required|in:signed,rejected',
            'feedback' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $form = CustomerRetainershipForm::where('code', $id)->firstOrFail();

        DB::beginTransaction();
        try {
            // 3. Fetch the specific signatory record
            $signatory = CustomerRetainershipSignatory::with('form')->findOrFail($request->signatory_id);

            // 4. SECURITY FIX: Check if Admin OR Designated Employee
            $isAdmin = (isset($authenticatedUser->type) && $authenticatedUser->type === 'admin');
            $isAuthorized = false;

            if ($isAdmin) {
                $isAuthorized = true;
            } else {
                // If not admin, check if the logged-in user is the assigned employee
                $employee = Employee::where('user_id', $authenticatedUser->id)->first();
                if ($employee && $employee->id == $signatory->employee_id) {
                    $isAuthorized = true;
                }
            }

            // return $employee->id. ' - '. $signatory->employee_id

            if (!$isAuthorized) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized: You do not have permission to sign for this role.',
                ], 403);
            }

            // 5. Prevent duplicate signing
            if ($signatory->status === 'signed') {
                return response()->json([
                    'status' => false,
                    'message' => 'This role has already been signed.',
                ], 400);
            }

            // 6. Perform the Update
            $signatory->update([
                'status' => $request->status,
                'signed_at' => $request->status === 'signed' ? now() : null,
                'feedback' => $request->feedback,
            ]);

            // 7. Check if the Form is now fully complete
            $totalSignatories = CustomerRetainershipSignatory::where('form_id', $signatory->form_id)->count();
            $signedCount = CustomerRetainershipSignatory::where('form_id', $signatory->form_id)
                ->where('status', 'signed')
                ->count();

            $isFullySigned = ($totalSignatories > 0 && $totalSignatories === $signedCount);

            // 8. Log the activity (Mentioning if an Admin signed on behalf of the role)
            $logMessage = "Signatory role [{$signatory->role}] marked as [{$request->status}] for form {$signatory->form->code}";
            if ($isAdmin && $authenticatedUser->id !== $signatory->employee_id) {
                $logMessage .= ' (Signed by Administrator)';
            }

            ActivityLog::create([
                'user_id' => $authenticatedUser->id,
                'ip_address' => $request->ip(),
                'activity' => $logMessage,
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => "Signatory status updated to {$request->status}.",
                'data' => [
                    'is_form_complete' => $isFullySigned,
                    'signatory' => $signatory,
                ],
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Signature Error: '.$e->getMessage().' on line '.$e->getLine(),
            ], 500);
        }
    }

    /**
     * Delete a Retainership Form and all its associated components.
     */
    public function destroy(Request $request, $id)
    {

        $authenticatedUser = $request->user();

        DB::beginTransaction();
        try {
            // 2. Find the form (using findOrFail to catch missing records)
            $form = CustomerRetainershipForm::whereCode($id)->firstOrFail();
            $formCode = $form->code;

            // 3. Optional: Add a check to prevent deletion if the form is already fully signed
            // if ($form->signatories()->where('status', 'signed')->count() > 0) {
            //     return response()->json(['status' => false, 'message' => 'Cannot delete a form that has been partially or fully signed.'], 400);
            // }

            // 4. Delete the parent form
            // Due to ON DELETE CASCADE, all child records will be deleted by the DB engine
            $form->delete();

            // 5. Log the deletion for audit purposes
            ActivityLog::create([
                'user_id' => $authenticatedUser->id,
                'ip_address' => request()->ip(),
                'activity' => "Deleted Retainership Form: {$formCode} (ID: {$id})",
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => "Retainership form {$formCode} and all associated data have been deleted successfully.",
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Form not found.',
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Deletion Error: '.$e->getMessage(),
            ], 500);
        }
    }
}

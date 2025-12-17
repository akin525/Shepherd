<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientDocument;
use App\Models\ClientStaff;
use App\Models\Deposit;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    /**
     * Display a listing of clients.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Client::with(['contacts', 'creator']);

        // Filter by status
        if ($request->has('status')) {
            $query->status($request->status);
        }

        // Search functionality
        if ($request->has('search')) {
            $query->search($request->search);
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $clients = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $clients,
        ]);
    }

    /**
     * Store a newly created client.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255|unique:clients,email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'company' => 'nullable|string|max:255',
            'website' => 'nullable|url|max:255',
            'industry' => 'nullable|string|max:255',
            'status' => 'enum:active,inactive,prospect|default:active',
            'notes' => 'nullable|string',
            'user_id' => 'nullable|exists:users,id',
            'contacts' => 'array',
            'contacts.*.name' => 'required|string|max:255',
            'contacts.*.email' => 'nullable|email|max:255',
            'contacts.*.phone' => 'nullable|string|max:20',
            'contacts.*.position' => 'nullable|string|max:255',
            'contacts.*.department' => 'nullable|string|max:255',
            'contacts.*.is_primary' => 'boolean',
        ]);

        DB::beginTransaction();
        try {
            $validated['created_by'] = Auth::id();
            $validated['updated_by'] = Auth::id();

            $client = Client::create($validated);

            // Create contacts if provided
            if (!empty($validated['contacts'])) {
                foreach ($validated['contacts'] as $contactData) {
                    $contactData['client_id'] = $client->id;
                    $contactData['created_by'] = Auth::id();
                    $contactData['updated_by'] = Auth::id();
                    ClientContact::create($contactData);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Client created successfully',
                'data' => $client->load(['contacts', 'creator']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create client',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified client.
     */
    public function show(Client $client): JsonResponse
    {
        $client->load(['contacts', 'documents', 'tickets', 'complaints', 'creator', 'user']);

        return response()->json([
            'success' => true,
            'data' => $client,
        ]);
    }

    /**
     * Update the specified client.
     */
    public function update(Request $request, Client $client): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('clients', 'email')->ignore($client->id),
            ],
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'company' => 'nullable|string|max:255',
            'website' => 'nullable|url|max:255',
            'industry' => 'nullable|string|max:255',
            'status' => 'sometimes|enum:active,inactive,prospect',
            'notes' => 'nullable|string',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $validated['updated_by'] = Auth::id();

        $client->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Client updated successfully',
            'data' => $client->load(['contacts', 'creator']),
        ]);
    }

    /**
     * Remove the specified client.
     */
    public function destroy(Client $client): JsonResponse
    {
        try {
            $client->delete();

            return response()->json([
                'success' => true,
                'message' => 'Client deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete client',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get client statistics.
     */
    public function statistics(): JsonResponse
    {
        $user = Auth::user();

        $query = Client::query();

        // If user is not admin, only show their clients
        if (!$user->isAdmin()) {
            $query->where('created_by', $user->id);
        }

        $stats = [
            'total' => $query->count(),
            'active' => $query->where('status', 'active')->count(),
            'inactive' => $query->where('status', 'inactive')->count(),
            'prospect' => $query->where('status', 'prospect')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get recent client activities.
     */
    public function recentActivities(Request $request): JsonResponse
    {
        $user = Auth::user();
        $limit = $request->get('limit', 10);

        $activities = [];

        // Recent tickets
        $tickets = Client::with(['tickets' => function ($query) use ($limit) {
            $query->latest()->limit($limit);
        }])->get()->flatMap(function ($client) {
            return $client->tickets->map(function ($ticket) use ($client) {
                return [
                    'type' => 'ticket',
                    'title' => "New ticket from {$client->name}",
                    'description' => $ticket->subject,
                    'date' => $ticket->created_at,
                    'status' => $ticket->status,
                ];
            });
        });

        // Recent complaints
        $complaints = Client::with(['complaints' => function ($query) use ($limit) {
            $query->latest()->limit($limit);
        }])->get()->flatMap(function ($client) {
            return $client->complaints->map(function ($complaint) use ($client) {
                return [
                    'type' => 'complaint',
                    'title' => "New complaint from {$client->name}",
                    'description' => $complaint->title,
                    'date' => $complaint->created_at,
                    'status' => $complaint->status,
                ];
            });
        });

        // Combine and sort
        $activities = $tickets->merge($complaints)
            ->sortByDesc('date')
            ->take($limit)
            ->values();

        return response()->json([
            'success' => true,
            'data' => $activities,
        ]);
    }

    /**
     * Upload document for client.
     */
    public function uploadDocument(Request $request, Client $client): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:255',
            'file' => 'required|file|max:10240', // 10MB max
        ]);

        try {
            $file = $request->file('file');
            $filePath = $file->store('client-documents', 'public');

            $documentData = [
                'client_id' => $client->id,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'file_path' => $filePath,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'file_type' => $file->getMimeType(),
                'category' => $validated['category'] ?? null,
                'status' => 'active',
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ];

            $document = ClientDocument::create($documentData);

            return response()->json([
                'success' => true,
                'message' => 'Document uploaded successfully',
                'data' => $document,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload document',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get client documents.
     */
    public function documents(Request $request, Client $client): JsonResponse
    {
        $query = $client->documents();

        // Filter by category
        if ($request->has('category')) {
            $query->category($request->category);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->status($request->status);
        }

        // Search
        if ($request->has('search')) {
            $query->search($request->search);
        }

        $documents = $query->latest()->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $documents,
        ]);
    }



    public function dashboard(Request $request)
    {
        $user = $request->user();

        // Fetch client record tied to the user
        $client = Client::where('user_id', $user->id)->firstOrFail();

        // Summary cards
        $activeStaffCount = ClientStaff::where('client_id', $client->id)
            ->where('status', 1) // 1 = active
            ->count();

        $totalPayments = Deposit::where('account_id', $user->id)
            ->sum('amount');

        // Subscriptions section (active plan + validity + next payment)
        // If you already store requested_plan and plan_expire_date on user, keep them
        $activeSubscription = Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->latest('start_date')
            ->first();

        // Fallbacks to user fields if subscription is not found
        $activePlanName = $activeSubscription->service_name
            ?? ($user->requested_plan ?? null);

        $validityStart = optional($activeSubscription)->start_date;
        $validityEnd   = optional($activeSubscription)->end_date
            ?? ($user->plan_expire_dat ?? $user->plan_expire_date ?? null);

        $nextPaymentDate = optional($activeSubscription)->next_payment_date
            ?? (optional($validityEnd) ? \Carbon\Carbon::parse($validityEnd)->subMonth() : null);

        // Subscription details table (Period | Service | Number of Staffs | Equipments | Status)
        // Example assumes SubscriptionItem rows hold these details
        $subscriptionDetails = SubscriptionItem::query()
            ->where('user_id', $user->id)
            ->latest() // adjust ordering to your needs
            ->take(10)
            ->get()
            ->map(function ($row) {
                // Normalize status to match UI dots: pending (yellow), paid (green)
                $status = strtolower($row->status);
                if ($status === 'payed') $status = 'paid';

                return [
                    'period'          => $row->period ?? $this->formatPeriod($row->start_date, $row->end_date),
                    'service'         => $row->service ?? $row->service_name,
                    'number_of_staff' => (int) ($row->staff_count ?? $row->number_of_staffs ?? 0),
                    'equipments'      => (int) ($row->equipments ?? $row->equipment_count ?? 0),
                    'status'          => $status, // 'pending' | 'paid'
//                    'download_url'    => route('subscriptions.download', ['id' => $row->id]),
                ];
            });

        // Payment table (Reference | Service | Number of Staffs | Status | Date)
        $payments = Payment::query()
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->take(10)
            ->get()
            ->map(function ($p) {
                $status = strtolower($p->status);
                if ($status === 'payed') $status = 'paid';

                return [
                    'reference'       => (string) ($p->reference ?? $p->reference_no ?? $p->id),
                    'service'         => $p->service ?? $p->service_name,
                    'number_of_staff' => (int) ($p->staff_count ?? $p->number_of_staffs ?? 0),
                    'status'          => $status, // 'pending' (orange) | 'deployed' (green) | 'paid' (green)
                    'date'            => optional($p->created_at)->format('d, F Y'),
//                    'download_url'    => route('payments.download', ['id' => $p->id]),
                ];
            });

        // Build response payload for the view
        $data = [
            // Header/UI meta
            'company_name'        => $user->name,
//            'welcome_title'       => 'Welcome Ann Hotets', // keep as per UI text
//            'welcome_subtitle'    => 'Start with a clear overview of what matters most',

            // Summary cards
            'active_staff'        => $activeStaffCount,
            'total_payment'       => $totalPayments,

            // Subscriptions quick info
            'active_subscription' => $activePlanName, // e.g., "Man Guarding"
            'validity_period'     => $this->formatValidity($validityStart, $validityEnd), // e.g., "Jan, 2026 - Feb, 2026"
            'next_payment_date'   => optional($nextPaymentDate)->format('d M, Y') ?? null,

            // Tables
            'subscription_rows'   => $subscriptionDetails,
            'payment_rows'        => $payments,
        ];

        return response()->json([
            'status' => true,
                'data' => $data,
                'message'=>'data retrieved successfully',
            ]);
    }



    public function Subscriptionindex(Request $request)
    {
        $user = $request->user();

        // Optional filters: service category, status, period
        $service = $request->string('service')->trim();         // e.g., 'Security', 'Operations', 'Man Guarding'
        $status  = $request->string('status')->trim();          // e.g., 'pending', 'paid'
        $period  = $request->string('period')->trim();          // e.g., 'January - March'

        // Active plans count (active subscriptions)
        $activePlansCount = Subscription::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->count();

        // Current active subscription (for validity period and next payment date cards)
        $activeSubscription = Subscription::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->latest('start_date')
            ->first();

        $validityStart = optional($activeSubscription)->start_date;
        $validityEnd   = optional($activeSubscription)->end_date;
        $nextPayment   = optional($activeSubscription)->next_payment_date;

        // Build query for table rows (Subscription Items)
        $itemsQuery = SubscriptionItem::query()
            ->where('user_id', $user->id);

        if ($service->isNotEmpty()) {
            $itemsQuery->where(function ($q) use ($service) {
                $q->where('service', $service)
                    ->orWhere('service_type', $service);
            });
        }

        if ($status->isNotEmpty()) {
            $itemsQuery->where('status', $status);
        }

        if ($period->isNotEmpty()) {
            $itemsQuery->where('period', $period);
        }

        // Sort newest first
        $items = $itemsQuery
            ->latest('created_at')
            ->paginate(15)
            ->through(function ($row) {
                $status = strtolower($row->status);
                if ($status === 'payed') $status = 'paid'; // normalize

                return [
                    'id'              => $row->id,
                    'period'          => $row->period ?: $this->formatPeriod($row->start_date, $row->end_date),
                    'service'         => $row->service ?? $row->service_type,
                    'number_of_staff' => (int) ($row->number_of_staffs ?? 0),
                    'equipments'      => (int) ($row->equipments ?? 0),
                    'status'          => $status, // 'pending' | 'paid'
                    'status_dot'      => $status === 'pending' ? 'yellow' : 'green',
                    'download_url'    => route('subscriptions.download', ['id' => $row->id]),
                ];
            });

        // For filter chips: unique services present
        $availableServices = SubscriptionItem::query()
            ->where('user_id', $user->id)
            ->select('service')
            ->whereNotNull('service')
            ->distinct()
            ->pluck('service')
            ->filter()
            ->values()
            ->all();

        // Build payload for the view
        $data = [
            // Header cards
            'cards' => [
                'active_plans'      => $activePlansCount,                             // e.g., 23
                'validity_period'   => $this->formatValidity($validityStart, $validityEnd), // "Jan, 2026 - Feb, 2026"
                'next_payment_date' => $nextPayment ? Carbon::parse($nextPayment)->format('d M, Y') : null, // "24 Jan, 2026"
            ],

            // Filters and list
            'filters' => [
                'service'            => $service->isNotEmpty() ? (string)$service : null,
                'status'             => $status->isNotEmpty() ? (string)$status : null,
                'period'             => $period->isNotEmpty() ? (string)$period : null,
                'available_services' => $availableServices, // for showing filter chips (Security, Operations, Man Guarding)
            ],

            // Table
            'items' => $items,

        ];

        return response()->json([
            'status' => true,
            'data' => $data,
            'message'=>'data retrieved successfully',
        ]);
    }

    /**
     * Format "Period" as "January - March" or "MMM, YYYY - MMM, YYYY".
     */
    protected function formatPeriod($start, $end)
    {
        if (!$start && !$end) {
            return 'N/A';
        }

        $s = $start ? \Carbon\Carbon::parse($start) : null;
        $e = $end ? \Carbon\Carbon::parse($end) : null;

        // If same year and contiguous months, show "January - March"
        if ($s && $e && $s->year === $e->year) {
            return $s->format('F') . ' - ' . $e->format('F');
        }

        // Fallback to "Jan, 2026 - Feb, 2026"
        return trim(
            ($s ? $s->format('M, Y') : '') .
            ($s && $e ? ' - ' : '') .
            ($e ? $e->format('M, Y') : '')
        ) ?: 'N/A';
    }

    /**
     * Format validity period exactly like the UI "Jan, 2026 - Feb, 2026".
     */
    protected function formatValidity($start, $end)
    {
        $s = $start ? \Carbon\Carbon::parse($start)->format('M, Y') : null;
        $e = $end ? \Carbon\Carbon::parse($end)->format('M, Y') : null;

        if ($s && $e) {
            return "{$s} - {$e}";
        }
        return $s ?? $e ?? 'N/A';
    }

}

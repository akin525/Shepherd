<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientDocument;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ClientDocumentController extends Controller
{
    /**
     * Display a listing of client documents.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $query = ClientDocument::with(['client', 'creator']);

        // Filter based on user role
        if (!$user->isAdmin()) {
            if ($user->isClient()) {
                // Client can only see their own documents
                $query->whereHas('client', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
            } else {
                // Employee can only see documents they created
                $query->where('created_by', $user->id);
            }
        }

        // Filter by client
        if ($request->has('client_id')) {
            $query->where('client_id', $request->client_id);
        }

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

        $perPage = $request->get('per_page', 15);
        $documents = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $documents,
        ]);
    }

    /**
     * Store a newly created client document.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:255',
            'file' => 'required|file|max:10240', // 10MB max
        ]);

        // Check permissions
        if (!$user->isAdmin()) {
            if ($user->isClient()) {
                // Client can only upload documents for themselves
                $client = Client::find($validated['client_id']);
                if ($client->user_id !== $user->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You can only upload documents for yourself',
                    ], 403);
                }
            }
        }

        try {
            $file = $request->file('file');
            $filePath = $file->store('client-documents', 'public');

            $documentData = [
                'client_id' => $validated['client_id'],
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'file_path' => $filePath,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'file_type' => $file->getMimeType(),
                'category' => $validated['category'] ?? null,
                'status' => 'active',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ];

            $document = ClientDocument::create($documentData);

            return response()->json([
                'success' => true,
                'message' => 'Document uploaded successfully',
                'data' => $document->load(['client', 'creator']),
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
     * Display the specified client document.
     */
    public function show(ClientDocument $document): JsonResponse
    {
        $user = Auth::user();

        // Check permissions
        if (!$user->isAdmin()) {
            if ($user->isClient()) {
                // Client can only see their own documents
                if ($document->client->user_id !== $user->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Access denied',
                    ], 403);
                }
            } else {
                // Employee can only see documents they created
                if ($document->created_by !== $user->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Access denied',
                    ], 403);
                }
            }
        }

        $document->load(['client', 'creator']);

        return response()->json([
            'success' => true,
            'data' => $document,
        ]);
    }

    /**
     * Update the specified client document.
     */
    public function update(Request $request, ClientDocument $document): JsonResponse
    {
        $user = Auth::user();

        // Check permissions
        if (!$user->isAdmin() && $document->created_by !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied',
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:255',
            'status' => 'sometimes|enum:active,inactive,archived',
        ]);

        $validated['updated_by'] = $user->id;

        $document->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Document updated successfully',
            'data' => $document->load(['client', 'creator']),
        ]);
    }

    /**
     * Remove the specified client document.
     */
    public function destroy(ClientDocument $document): JsonResponse
    {
        $user = Auth::user();

        // Check permissions
        if (!$user->isAdmin() && $document->created_by !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied',
            ], 403);
        }

        try {
            // Delete file from storage
            Storage::disk('public')->delete($document->file_path);

            // Delete record
            $document->delete();

            return response()->json([
                'success' => true,
                'message' => 'Document deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete document',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download the specified client document.
     */
    public function download(ClientDocument $document)
    {
        $user = Auth::user();

        // Check permissions
        if (!$user->isAdmin()) {
            if ($user->isClient()) {
                // Client can only download their own documents
                if ($document->client->user_id !== $user->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Access denied',
                    ], 403);
                }
            } else {
                // Employee can only download documents they created
                if ($document->created_by !== $user->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Access denied',
                    ], 403);
                }
            }
        }

        try {
            $filePath = $document->file_path;
            
            if (!Storage::disk('public')->exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found',
                ], 404);
            }

            return Storage::disk('public')->download($filePath, $document->file_name);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to download file',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get document categories.
     */
    public function categories(): JsonResponse
    {
        $categories = ClientDocument::select('category')
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->filter()
            ->values();

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Get document statistics.
     */
    public function statistics(): JsonResponse
    {
        $user = Auth::user();
        
        $query = ClientDocument::query();
        
        // Filter based on user role
        if (!$user->isAdmin()) {
            if ($user->isClient()) {
                $query->whereHas('client', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
            } else {
                $query->where('created_by', $user->id);
            }
        }

        $stats = [
            'total' => $query->count(),
            'active' => $query->status('active')->count(),
            'inactive' => $query->status('inactive')->count(),
            'archived' => $query->status('archived')->count(),
            'total_size' => $query->sum('file_size'),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
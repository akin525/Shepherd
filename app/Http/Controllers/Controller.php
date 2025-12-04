<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Send success response
     */
    protected function sendSuccess($data = null, string $message = 'Operation successful', int $code = 200)
    {
        $response = [
            'status' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }

    /**
     * Send error response
     */
    protected function sendError(string $message, $errors = null, int $code = 400)
    {
        $response = [
            'status' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    /**
     * Send validation error response
     */
    protected function sendValidationError($errors, string $message = 'Validation failed')
    {
        return response()->json([
            'status' => false,
            'message' => $message,
            'errors' => $errors,
        ], 422);
    }

    /**
     * Send not found response
     */
    protected function sendNotFound(string $message = 'Resource not found')
    {
        return response()->json([
            'status' => false,
            'message' => $message,
        ], 404);
    }

    /**
     * Send unauthorized response
     */
    protected function sendUnauthorized(string $message = 'Unauthorized')
    {
        return response()->json([
            'status' => false,
            'message' => $message,
        ], 401);
    }

    /**
     * Send forbidden response
     */
    protected function sendForbidden(string $message = 'Forbidden')
    {
        return response()->json([
            'status' => false,
            'message' => $message,
        ], 403);
    }

    /**
     * Send server error response
     */
    protected function sendServerError(string $message = 'Internal server error')
    {
        return response()->json([
            'status' => false,
            'message' => $message,
        ], 500);
    }

    /**
     * Send paginated response
     */
    protected function sendPaginated($paginator, string $message = 'Data retrieved successfully')
    {
        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => [
                'items' => $paginator->items(),
                'pagination' => [
                    'total' => $paginator->total(),
                    'count' => $paginator->count(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'total_pages' => $paginator->lastPage(),
                    'has_more_pages' => $paginator->hasMorePages(),
                    'next_page_url' => $paginator->nextPageUrl(),
                    'prev_page_url' => $paginator->previousPageUrl(),
                ]
            ]
        ]);
    }
}
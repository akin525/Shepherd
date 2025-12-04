<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @param  string  $role
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, string $role)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated. Please login to continue.'
            ], 401);
        }

        // Check if user has the required role
        if (!$this->hasRole($user, $role)) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized. You do not have the required permissions.',
                'required_role' => $role,
                'user_role' => $user->type,
            ], 403);
        }

        return $next($request);
    }

    /**
     * Check if user has the required role
     */
    private function hasRole($user, string $role): bool
    {
        $userRole = $user->type;

        switch ($role) {
            case 'admin':
                return $userRole === 'admin' || $userRole === 'company';
            case 'hr':
                return $userRole === 'hr' || $userRole === 'admin' || $userRole === 'company';
            case 'employee':
                return in_array($userRole, ['employee', 'hr', 'admin', 'company']);
            default:
                return $userRole === $role;
        }
    }
}
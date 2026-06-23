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
     * @param  \Closure  $next
     * @param  string  ...$roles
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please login first.',
                'error_code' => 'unauthenticated',
            ], 401);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user(); 
        $userRole = $user->role?->slug;

        // If no roles specified, just check if user is authenticated
        if (empty($roles)) {
            return $next($request);
        }

        // Check if user has any of the required roles
        foreach ($roles as $role) {
            if ($userRole === $role) {
                return $next($request);
            }
        }
 
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized. Required roles: ' . implode(' or ', $roles),
            'your_role' => $userRole ?? 'No role assigned',
            'required_roles' => $roles,
            'error_code' => 'insufficient_permissions',
        ], 403);
    }
}
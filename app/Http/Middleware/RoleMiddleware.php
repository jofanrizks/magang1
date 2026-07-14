<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = auth()->user();

        if (!$user || !in_array($user->role, $roles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk melakukan tindakan ini.'
            ], 403);
        }

        if ($user->must_change_password) {
            return response()->json([
                'success' => false,
                'code' => 'MUST_CHANGE_PASSWORD',
                'message' => 'Anda wajib mengganti password sebelum mengakses fitur ini.'
            ], 403);
        }

        return $next($request);
    }
}

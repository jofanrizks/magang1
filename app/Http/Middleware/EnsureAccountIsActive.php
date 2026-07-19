<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'code' => 'UNAUTHENTICATED',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($user->sts === 'disabled') {
            return response()->json([
                'success' => false,
                'code' => 'ACCOUNT_DISABLED',
                'message' => 'Akun Anda telah dinonaktifkan.',
            ], 403);
        }

        return $next($request);
    }
}

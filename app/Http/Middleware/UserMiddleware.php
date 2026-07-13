<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class UserMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check() || auth()->user()->role !== 'user') {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk melakukan tindakan ini.'
            ], 403);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountActiveMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->status === UserStatus::SUSPENDED) {
            return response()->json([
                'success' => false,
                'error_code' => 'ACCOUNT_SUSPENDED',
                'message' => 'Your account has been suspended. Please contact security administration.',
            ], 403);
        }

        return $next($request);
    }
}

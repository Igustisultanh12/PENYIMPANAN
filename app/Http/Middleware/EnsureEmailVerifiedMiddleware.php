<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailVerifiedMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && !$user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'error_code' => 'EMAIL_NOT_VERIFIED',
                'message' => 'Please verify your email address to access your storage drive.',
            ], 403);
        }

        return $next($request);
    }
}

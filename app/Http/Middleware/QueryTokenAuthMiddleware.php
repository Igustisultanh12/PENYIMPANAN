<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class QueryTokenAuthMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // If Bearer token is not in header, check query parameter 'token' or 'api_token'
        if (!$request->bearerToken()) {
            $token = $request->query('token') ?? $request->query('api_token');
            if ($token && is_string($token)) {
                $request->headers->set('Authorization', 'Bearer ' . $token);
            }
        }

        return $next($request);
    }
}

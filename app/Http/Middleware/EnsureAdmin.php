<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isAdmin()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Administrator access required.'], 403);
        }

        abort(403, 'Administrator access required.');
    }
}

<?php

namespace App\Http\Middleware;

use App\Support\InstallService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (InstallService::isInstalled()) {
            return $next($request);
        }

        if ($request->routeIs('setup.*')) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'System not installed. Complete setup at /setup first.',
            ], 503);
        }

        return redirect()->route('setup.show');
    }
}

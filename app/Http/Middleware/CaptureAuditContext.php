<?php

namespace App\Http\Middleware;

use App\Support\AuditContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CaptureAuditContext
{
    public function handle(Request $request, Closure $next): Response
    {
        AuditContext::set(
            $request->user(),
            $request->ip(),
            $request->userAgent(),
        );

        try {
            return $next($request);
        } finally {
            AuditContext::clear();
        }
    }
}

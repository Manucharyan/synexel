<?php

namespace App\Http\Middleware;

use App\Support\InstallService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! InstallService::isInstalled()) {
            return $next($request);
        }

        if ($request->user()) {
            return redirect()->route('workbooks.index');
        }

        return redirect()->route('login');
    }
}

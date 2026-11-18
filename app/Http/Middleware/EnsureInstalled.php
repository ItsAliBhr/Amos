<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureInstalled
{
    public function handle(Request $request, Closure $next)
    {
        $isInstallRoute = $request->is('install') || $request->is('install/*');
        $installed = amos_installed();

        if (! $installed && ! $isInstallRoute) {
            return redirect('/install');
        }
        if ($installed && $isInstallRoute) {
            abort(404); // installer is locked after a successful install
        }

        return $next($request);
    }
}

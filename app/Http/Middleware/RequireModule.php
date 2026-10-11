<?php

namespace App\Http\Middleware;

use App\Services\ModuleAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireModule
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $businessId = $request->user()?->business_id;
        abort_unless($businessId && app(ModuleAccess::class)->enabled((int) $businessId, $module), 403, 'This feature is not included in your client workspace. Contact your account administrator.');

        return $next($request);
    }
}

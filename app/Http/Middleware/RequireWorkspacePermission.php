<?php

namespace App\Http\Middleware;

use App\Services\WorkspacePermissions;
use Closure;
use Illuminate\Http\Request;

class RequireWorkspacePermission
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        abort_unless(app(WorkspacePermissions::class)->has($request->user(), $permission), 403, 'You do not have permission to view this page.');
        return $next($request);
    }
}

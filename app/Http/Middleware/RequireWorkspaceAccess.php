<?php

namespace App\Http\Middleware;

use App\Services\WorkspacePermissions;
use Closure;
use Illuminate\Http\Request;

class RequireWorkspaceAccess
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(app(WorkspacePermissions::class)->has($request->user(), 'workspace.access'), 403);
        return $next($request);
    }
}

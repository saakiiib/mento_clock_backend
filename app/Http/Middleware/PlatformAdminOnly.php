<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class PlatformAdminOnly
{
    public function handle($request, Closure $next)
    {
        // Use the explicit guard. Never treat a platform account as a business user.
        $admin = Auth::guard('platform')->user();
        if (!$admin) return redirect()->route('login');
        if (!$admin->active || $request->session()->get('platform_version') !== $admin->auth_version) {
            Auth::guard('platform')->logout();
            $request->session()->forget('platform_version');
            return redirect()->route('login')->with('status', 'Please sign in again. Your administrator session has changed.');
        }
        return $next($request);
    }
}

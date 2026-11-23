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
        // Support operators are deliberately not Super Admins. Prevent lateral
        // access to billing, admin accounts, client management, or other platform URLs.
        if (\Illuminate\Support\Facades\DB::table('platform_support_operators')->where('admin_id', $admin->id)->exists()) {
            $path = $request->path();
            if ($path === 'platform') return redirect('/platform/assist');
            abort_unless($path === 'platform/assist' || str_starts_with($path, 'platform/assist/') || in_array($path, ['platform/logout', 'platform/security'], true), 403);
        }
        return $next($request);
    }
}

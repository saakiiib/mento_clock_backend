<?php
namespace App\Http\Middleware;
use Closure;
class BusinessAdmin {
 public function handle($request,Closure $next){abort_unless($request->user()?->role==='admin',403);return $next($request);}
}

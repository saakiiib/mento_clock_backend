<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Support\Facades\DB;
class ActiveBusiness {
 public function handle($request,Closure $next){
  $u=$request->user();abort_unless($u && $u->active && DB::table('businesses')->where('id',$u->business_id)->where('active',true)->exists(),403,'Your account or business is inactive.');
  return $next($request);
 }
}

<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Support\Facades\DB;
class ActiveBusiness {
 public function handle($request,Closure $next){
  $u=$request->user();abort_unless($u && $u->active,403,'Your account or business is inactive.');
  $businessId=(int)$u->getRawOriginal('business_id');
  if($u->role==='admin' && $u->client_account_id){
   $requested=$request->header('X-Business-ID');
   if($requested===null && $request->hasSession())$requested=$request->session()->get('active_business_id');
   if($requested!==null)$businessId=(int)$requested;
   $business=DB::table('businesses')->where('id',$businessId)->where('client_account_id',$u->client_account_id)->where('active',true)->first();
   if(!$business && !$request->header('X-Business-ID') && $request->hasSession()){
    $businessId=(int)$u->getRawOriginal('business_id');
    $request->session()->forget('active_business_id');
    $business=DB::table('businesses')->where('id',$businessId)->where('client_account_id',$u->client_account_id)->where('active',true)->first();
   }
   abort_unless($business,403,'This business is not available to your client account.');
   $request->attributes->set('active_business_id',$businessId);
  }
  abort_unless(DB::table('businesses')->where('id',$businessId)->where('active',true)->exists(),403,'Your account or business is inactive.');
  return $next($request);
 }
}

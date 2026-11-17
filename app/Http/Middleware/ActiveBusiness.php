<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Support\Facades\DB;
class ActiveBusiness {
 public function handle($request,Closure $next){
  $u=$request->user();abort_unless($u && $u->active,403,'Your account or business is inactive.');
  $accountId=$u->client_account_id?:DB::table('businesses')->where('id',(int)$u->getRawOriginal('business_id'))->value('client_account_id');
  if($accountId){
   abort_unless(DB::table('client_accounts')->where('id',$accountId)->where('active',true)->exists(),403,'This client account is suspended. Please contact Mento Software.');
  }
  $businessId=(int)$u->getRawOriginal('business_id');
  if($u->role==='admin' && $accountId){
   $requested=$request->header('X-Business-ID');
   if($requested===null && $request->hasSession())$requested=$request->session()->get('active_business_id');
   $business=$requested!==null
    ?DB::table('businesses')->where('id',(int)$requested)->where('client_account_id',$accountId)->where('active',true)->first()
    :null;
   if(!$business && $request->header('X-Business-ID')===null){
    $business=DB::table('businesses')->where('client_account_id',$accountId)->where('active',true)
     ->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END',[(int)$u->getRawOriginal('business_id')])->orderBy('id')->first();
    if($business && $request->hasSession())$request->session()->put('active_business_id',(int)$business->id);
    if($business)$businessId=(int)$business->id;
   }
   abort_unless($business,403,'This business is not available to your client account.');
   $businessId=(int)$business->id;
   $request->attributes->set('active_business_id',$businessId);
  }
  abort_unless(DB::table('businesses')->where('id',$businessId)->where('active',true)->exists(),403,'Your account or business is inactive.');
  return $next($request);
 }
}

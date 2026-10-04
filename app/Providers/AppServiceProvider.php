<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
class AppServiceProvider extends ServiceProvider {
 public function register(): void {}
 public function boot(): void {
  Auth::viaRequest('mento-token', function ($request) {
   $token = $request->bearerToken(); if (!$token) return null;
   $row = DB::table('api_tokens')->where('token_hash',hash('sha256',$token))->where('expires_at','>',now())->first();
   if (!$row) return null;
   $user = User::find($row->user_id);
   if (!$user || !$user->active || !DB::table('businesses')->where('id',$user->business_id)->where('active',true)->exists()) return null;
   $request->attributes->set('mento_token_id',$row->id);
   return $user;
  });
 }
}

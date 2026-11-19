<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
class AppServiceProvider extends ServiceProvider {
 public function register(): void {}
 public function boot(): void {
  RateLimiter::for('mobile-login', function(Request $request) {
   $identity = mb_strtolower(trim((string)$request->input('email', '')));
   $key = hash('sha256', $identity.'|'.$request->ip());
   return [Limit::perMinute(10)->by('account-ip:'.$key), Limit::perMinute(120)->by('ip:'.$request->ip())];
  });
  ResetPassword::createUrlUsing(fn($u,$token)=>url('/reset-password/'.$token).'?email='.urlencode($u->email));
 }
}

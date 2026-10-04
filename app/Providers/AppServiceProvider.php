<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;
class AppServiceProvider extends ServiceProvider {
 public function register(): void {}
 public function boot(): void {
  ResetPassword::createUrlUsing(fn($u,$token)=>url('/reset-password/'.$token).'?email='.urlencode($u->email));
 }
}

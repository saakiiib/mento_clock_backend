<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClockController;
Route::prefix('v1')->group(function(){
 Route::post('auth/login',[ClockController::class,'login'])->middleware('throttle:5,1');
 Route::middleware(['auth:api','throttle:60,1'])->group(function(){
  Route::post('auth/logout',[ClockController::class,'logout']);Route::get('attendance',[ClockController::class,'history']);Route::post('attendance/clock-in',[ClockController::class,'clockIn']);Route::post('attendance/{id}/clock-out',[ClockController::class,'clockOut'])->whereNumber('id');
 });
});

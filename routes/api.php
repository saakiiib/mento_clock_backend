<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{ClockController,PasswordController,ReportController};
Route::prefix('v1')->group(function(){
 Route::post('auth/login',[ClockController::class,'login'])->middleware('throttle:5,1');
 Route::post('auth/forgot-password',[PasswordController::class,'forgot'])->middleware('throttle:3,1');
 Route::middleware(['auth:sanctum','active.business','throttle:60,1'])->group(function(){
  Route::post('auth/logout',[ClockController::class,'logout']);Route::get('me',[ClockController::class,'me']);Route::post('profile',[ClockController::class,'profile']);
  Route::get('attendance',[ClockController::class,'history']);Route::post('attendance/clock-in',[ClockController::class,'clockIn']);Route::post('attendance/{id}/clock-out',[ClockController::class,'clockOut'])->whereNumber('id');
  Route::get('reports',[ReportController::class,'employee']);
 });
});

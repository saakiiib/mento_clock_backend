<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{ClientApiController,ClockController,PasswordController,ReportController};
Route::post('auth/login',[ClockController::class,'login'])->middleware('throttle:5,1');
Route::post('auth/forgot-password',[PasswordController::class,'forgot'])->middleware('throttle:3,1');
Route::middleware(['auth:sanctum','active.business','throttle:60,1'])->group(function(){
 Route::post('auth/logout',[ClockController::class,'logout']);Route::get('me',[ClockController::class,'me']);Route::post('profile',[ClockController::class,'profile']);
 Route::get('attendance',[ClockController::class,'history']);Route::post('attendance/clock-in',[ClockController::class,'clockIn']);Route::post('attendance/{id}/clock-out',[ClockController::class,'clockOut'])->whereNumber('id');
 Route::get('reports',[ReportController::class,'employee']);
});

// Client administrators can manage only records belonging to their own business.
Route::middleware(['auth:sanctum','active.business','business.admin','throttle:60,1'])->prefix('manager')->group(function(){
 Route::get('dashboard',[ClientApiController::class,'dashboard']);
 Route::get('employees',[ClientApiController::class,'employees']);
 Route::post('employees',[ClientApiController::class,'storeEmployee']);
 Route::put('employees/{id}',[ClientApiController::class,'updateEmployee'])->whereNumber('id');
 Route::patch('employees/{id}/status',[ClientApiController::class,'toggleEmployee'])->whereNumber('id');
 Route::get('branches',[ClientApiController::class,'branches']);
 Route::post('branches',[ClientApiController::class,'storeBranch']);
 Route::put('branches/{id}',[ClientApiController::class,'updateBranch'])->whereNumber('id');
 Route::get('attendance',[ClientApiController::class,'attendance']);
 Route::post('attendance/{id}/correct',[ClientApiController::class,'correctAttendance'])->whereNumber('id');
 Route::get('reports',[ClientApiController::class,'report']);
 Route::get('reports/download',[ClientApiController::class,'downloadReport']);
 Route::post('reports/email',[ClientApiController::class,'emailReport'])->middleware('throttle:3,1');
});

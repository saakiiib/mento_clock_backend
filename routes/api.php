<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{ClientApiController,ClockController,LeaveController,PasswordController,ReportController,RotaController};
Route::post('auth/login',[ClockController::class,'login'])->middleware('throttle:5,1');
Route::post('auth/forgot-password',[PasswordController::class,'forgot'])->middleware('throttle:3,1');
Route::middleware(['auth:sanctum','active.business','throttle:60,1'])->group(function(){
 Route::post('auth/logout',[ClockController::class,'logout']);Route::get('me',[ClockController::class,'me']);Route::post('profile',[ClockController::class,'profile']);
 Route::get('attendance',[ClockController::class,'history'])->middleware('module.enabled:attendance_tracking');Route::post('attendance/clock-in',[ClockController::class,'clockIn'])->middleware('module.enabled:attendance_tracking');Route::post('attendance/{id}/clock-out',[ClockController::class,'clockOut'])->whereNumber('id')->middleware('module.enabled:attendance_tracking');
 Route::get('reports',[ReportController::class,'employee'])->middleware('module.enabled:reports_analytics');
 Route::get('leave',[LeaveController::class,'mine'])->middleware('module.enabled:leave_management');
 Route::post('leave/requests',[LeaveController::class,'submit'])->middleware('module.enabled:leave_management');
 Route::post('leave/requests/{id}/cancel',[LeaveController::class,'cancelMine'])->whereNumber('id')->middleware('module.enabled:leave_management');
 Route::get('rota',[RotaController::class,'mine'])->middleware('module.enabled:rota_shifts');
});

// Client administrators can manage only records belonging to their own business.
Route::middleware(['auth:sanctum','active.business','business.admin','throttle:60,1'])->prefix('manager')->group(function(){
 Route::get('dashboard',[ClientApiController::class,'dashboard'])->middleware('module.enabled:employee_management');
 Route::get('employees',[ClientApiController::class,'employees'])->middleware('module.enabled:employee_management');
 Route::post('employees',[ClientApiController::class,'storeEmployee'])->middleware('module.enabled:employee_management');
 Route::put('employees/{id}',[ClientApiController::class,'updateEmployee'])->whereNumber('id')->middleware('module.enabled:employee_management');
 Route::patch('employees/{id}/status',[ClientApiController::class,'toggleEmployee'])->whereNumber('id')->middleware('module.enabled:employee_management');
 Route::get('branches',[ClientApiController::class,'branches'])->middleware('module.enabled:employee_management');
 Route::post('branches',[ClientApiController::class,'storeBranch'])->middleware('module.enabled:employee_management');
 Route::put('branches/{id}',[ClientApiController::class,'updateBranch'])->whereNumber('id')->middleware('module.enabled:employee_management');
 Route::get('attendance',[ClientApiController::class,'attendance'])->middleware('module.enabled:attendance_tracking');
 Route::post('attendance/{id}/correct',[ClientApiController::class,'correctAttendance'])->middleware('module.enabled:attendance_tracking');
 Route::get('reports',[ClientApiController::class,'report'])->middleware('module.enabled:reports_analytics');
 Route::get('reports/download',[ClientApiController::class,'downloadReport'])->middleware('module.enabled:reports_analytics');
 Route::post('reports/email',[ClientApiController::class,'emailReport'])->middleware(['module.enabled:reports_analytics','throttle:3,1']);
});

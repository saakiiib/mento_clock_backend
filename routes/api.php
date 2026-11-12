<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{ClientApiController,ClockController,LeaveController,PasswordController,ReportController,RotaController,NoticeController};
Route::post('auth/login',[ClockController::class,'login']);
Route::post('auth/forgot-password',[PasswordController::class,'forgot']);
Route::middleware(['auth:sanctum','active.business'])->group(function(){
 Route::post('auth/logout',[ClockController::class,'logout']);Route::get('me',[ClockController::class,'me']);Route::post('profile',[ClockController::class,'profile']);
 Route::get('attendance',[ClockController::class,'history'])->middleware('module.enabled:attendance_tracking');Route::post('attendance/clock-in',[ClockController::class,'clockIn'])->middleware('module.enabled:attendance_tracking');Route::post('attendance/{id}/clock-out',[ClockController::class,'clockOut'])->whereNumber('id')->middleware('module.enabled:attendance_tracking');
 Route::get('reports',[ReportController::class,'employee'])->middleware('module.enabled:reports_analytics');
 Route::get('leave',[LeaveController::class,'mine'])->middleware('module.enabled:leave_management');
 Route::post('leave/preview',[LeaveController::class,'preview'])->middleware('module.enabled:leave_management');
 Route::post('leave/requests',[LeaveController::class,'submit'])->middleware('module.enabled:leave_management');
 Route::post('leave/requests/{id}/cancel',[LeaveController::class,'cancelMine'])->whereNumber('id')->middleware('module.enabled:leave_management');
 Route::get('rota',[RotaController::class,'mine'])->middleware('module.enabled:rota_shifts');
 Route::get('notices',[NoticeController::class,'mine'])->middleware('module.enabled:company_notices');
 Route::post('notices/{id}/acknowledge',[NoticeController::class,'acknowledge'])->whereNumber('id')->middleware('module.enabled:company_notices');
});

// Client administrators can manage only records belonging to their own business.
Route::middleware(['auth:sanctum','active.business','business.admin'])->prefix('manager')->group(function(){
 Route::get('dashboard',[ClientApiController::class,'dashboard']);
 Route::get('employees',[ClientApiController::class,'employees'])->middleware('module.enabled:employee_management');
 Route::post('employees',[ClientApiController::class,'storeEmployee'])->middleware('module.enabled:employee_management');
 Route::put('employees/{id}',[ClientApiController::class,'updateEmployee'])->whereNumber('id')->middleware('module.enabled:employee_management');
 Route::patch('employees/{id}/status',[ClientApiController::class,'toggleEmployee'])->whereNumber('id')->middleware('module.enabled:employee_management');
 Route::get('branches',[ClientApiController::class,'branches'])->middleware('module.enabled:employee_management');
 Route::post('branches',[ClientApiController::class,'storeBranch'])->middleware('module.enabled:employee_management');
 Route::put('branches/{id}',[ClientApiController::class,'updateBranch'])->whereNumber('id')->middleware('module.enabled:employee_management');
 Route::get('attendance',[ClientApiController::class,'attendance'])->middleware('module.enabled:attendance_tracking');
 Route::post('attendance/{id}/correct',[ClientApiController::class,'correctAttendance'])->middleware('module.enabled:attendance_tracking');
 Route::post('attendance/{id}/finalize',[ClientApiController::class,'finalizeAttendance'])->middleware('module.enabled:attendance_tracking');
 Route::get('leave',[LeaveController::class,'managerRequests'])->middleware('module.enabled:leave_management');
 Route::post('leave/requests/{id}/review',[LeaveController::class,'review'])->whereNumber('id')->middleware('module.enabled:leave_management');
 Route::get('rota',[RotaController::class,'managerWeek'])->middleware('module.enabled:rota_shifts');
 Route::post('rota/shifts',[RotaController::class,'store'])->middleware('module.enabled:rota_shifts');
 Route::put('rota/shifts/{shiftId}',[RotaController::class,'updateDraft'])->whereNumber('shiftId')->middleware('module.enabled:rota_shifts');
 Route::post('rota/shifts/{shiftId}/cancel',[RotaController::class,'delete'])->whereNumber('shiftId')->middleware('module.enabled:rota_shifts');
 Route::post('rota/publish-week',[RotaController::class,'publishWeek'])->middleware('module.enabled:rota_shifts');
 Route::get('reports',[ClientApiController::class,'report'])->middleware('module.enabled:reports_analytics');
 Route::get('reports/download',[ClientApiController::class,'downloadReport'])->middleware('module.enabled:reports_analytics');
 Route::post('reports/email',[ClientApiController::class,'emailReport'])->middleware('module.enabled:reports_analytics');
 Route::get('reports/employees',[ClientApiController::class,'reportEmployees'])->middleware('module.enabled:reports_analytics');
 Route::put('reports/employees/{id}/holiday-hours',[ClientApiController::class,'updateReportHolidayHours'])->whereNumber('id')->middleware('module.enabled:leave_management');
});

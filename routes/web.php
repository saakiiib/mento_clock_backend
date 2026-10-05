<?php
use Illuminate\Support\Facades\{Route,Auth};
use Illuminate\Http\Request;
use App\Http\Controllers\{AdminController,PasswordController,ReportController,PlatformController};
Route::get('/login',function(){if(Auth::guard('platform')->check())return redirect('/platform');if(Auth::guard('web')->check())return redirect('/');return view('login');})->name('login');
Route::post('/login',[AdminController::class,'login'])->middleware('throttle:5,1');
Route::view('/forgot-password','auth.forgot')->name('password.request');
Route::post('/forgot-password',[PasswordController::class,'forgot'])->middleware('throttle:3,1');
Route::get('/reset-password/{token}',[PasswordController::class,'form'])->name('password.reset');
Route::post('/reset-password',[PasswordController::class,'reset'])->middleware('throttle:5,1');
Route::middleware(['auth','active.business','business.admin'])->group(function(){
 Route::get('/',[AdminController::class,'index']);
 foreach(['people','workplaces','attendance','company'] as $page)Route::get('/'.$page,[AdminController::class,'index']);
 Route::post('/branches',[AdminController::class,'branch']);Route::get('/branches/{id}/edit',[AdminController::class,'editBranch'])->whereNumber('id');Route::post('/branches/{id}/edit',[AdminController::class,'updateBranch'])->whereNumber('id');
 Route::post('/employees',[AdminController::class,'employee']);Route::get('/employees/{id}/edit',[AdminController::class,'editEmployee'])->whereNumber('id');Route::post('/employees/{id}/edit',[AdminController::class,'updateEmployee'])->whereNumber('id');
 Route::post('/employees/{id}/assignments',[AdminController::class,'assignments'])->whereNumber('id');Route::post('/employees/{id}/status',[AdminController::class,'toggle'])->whereNumber('id');Route::post('/attendance/{id}/correct',[AdminController::class,'correction'])->whereNumber('id');
 Route::get('/reports',[ReportController::class,'index']);Route::get('/reports/download',[ReportController::class,'download']);Route::post('/reports/email',[ReportController::class,'email'])->middleware('throttle:3,1');
});
Route::post('/logout',function(Request $r){Auth::logout();$r->session()->invalidate();$r->session()->regenerateToken();return redirect('/login');})->middleware('auth');

// Platform accounts use an independent guard and have no employee/attendance API access.
Route::prefix('platform')->middleware('platform.admin')->group(function(){
 Route::get('/',[PlatformController::class,'index']);
 Route::get('/clients/create',[PlatformController::class,'create']);
 Route::post('/clients',[PlatformController::class,'store']);
 Route::get('/clients/{id}',[PlatformController::class,'show'])->whereNumber('id');
 Route::post('/clients/{id}',[PlatformController::class,'update'])->whereNumber('id');
 Route::post('/clients/{id}/status',[PlatformController::class,'status'])->whereNumber('id');
 Route::post('/clients/{id}/admins',[PlatformController::class,'addAdmin'])->whereNumber('id');
 Route::post('/clients/{id}/admins/{userId}',[PlatformController::class,'updateAdmin'])->whereNumber('id')->whereNumber('userId');
 Route::get('/security',[PlatformController::class,'security']);
 Route::post('/security',[PlatformController::class,'password'])->middleware('throttle:5,1');
 Route::post('/logout',function(Request $r){Auth::guard('platform')->logout();$r->session()->invalidate();$r->session()->regenerateToken();return redirect('/login');});
});

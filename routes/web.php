<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\AdminController;
Route::view('/login','login')->name('login');
Route::post('/login',[AdminController::class,'login'])->middleware('throttle:5,1');
Route::middleware('auth')->group(function(){
 Route::get('/',[AdminController::class,'index']);
 Route::post('/branches',[AdminController::class,'branch']);
 Route::post('/employees',[AdminController::class,'employee']);
 Route::post('/employees/{id}/assignments',[AdminController::class,'assignments'])->whereNumber('id');
 Route::post('/employees/{id}/status',[AdminController::class,'toggle'])->whereNumber('id');
 Route::post('/attendance/{id}/correct',[AdminController::class,'correction'])->whereNumber('id');
 Route::get('/export',[AdminController::class,'export']);
 Route::post('/logout',function(Request $r){Auth::logout();$r->session()->invalidate();$r->session()->regenerateToken();return redirect('/login');});
});

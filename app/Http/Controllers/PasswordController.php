<?php
namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
class PasswordController {
 public function forgot(Request $r){
  $v=$r->validate(['email'=>'required|email|max:255']);
  Password::sendResetLink($v);
  $message='If an account matches this email, a reset link will be sent. Check your inbox.';
  return $r->expectsJson()?response()->json(['message'=>$message]):back()->with('status',$message);
 }
 public function form(Request $r,string $token){return view('auth.reset',['token'=>$token,'email'=>$r->query('email','')]);}
 public function reset(Request $r){
   $v=$r->validate(['token'=>'required|string','email'=>'required|email','password'=>'required|string|min:6|max:100|confirmed']);
  $status=Password::reset($v,function(User $u,string $password){
   DB::transaction(function()use($u,$password){$u->forceFill(['password'=>$password,'remember_token'=>Str::random(60)])->save();$u->tokens()->delete();DB::table('sessions')->where('user_id',$u->id)->delete();});
  });
  if($status!==Password::PASSWORD_RESET)return back()->withErrors(['email'=>'This reset link is invalid or expired. Request a new link.']);
  Auth::logout();$r->session()->invalidate();$r->session()->regenerateToken();return redirect('/login')->with('status','Password updated. Sign in with your new password.');
 }
}

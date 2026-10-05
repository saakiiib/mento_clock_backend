<?php
namespace App\Http\Controllers;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
class ClockController {
 public function login(Request $r) {
  $v=$r->validate(['email'=>'required|email','password'=>'required|string']);
  $u=User::where('email',$v['email'])->first();
  abort_unless($u && Hash::check($v['password'],$u->password) && $u->active,401,'Invalid credentials or inactive account.');
  $b=DB::table('businesses')->where('id',$u->business_id)->where('active',true)->first();abort_unless($b,403,'Business is inactive.');
  $abilities = $u->role === 'admin' ? ['*'] : ['attendance'];
  $token=$u->createToken('mentoclock-mobile',$abilities,now()->addDays(30))->plainTextToken;
  return response()->json(['token'=>$token,'user'=>$this->account($u)]);
 }
 private function account(User $u): array {
  $b=DB::table('businesses')->find($u->business_id);
  $branches=DB::table('branches')->join('employee_branches','branches.id','=','employee_branches.branch_id')->where('employee_branches.user_id',$u->id)->where('branches.business_id',$u->business_id)->where('branches.active',true)->select('branches.id','branches.name','branches.address')->get();
  return ['id'=>$u->id,'name'=>$u->name,'email'=>$u->email,'phone'=>$u->phone,'employee_code'=>$u->employee_code,'job_title'=>$u->job_title,'employment_start_date'=>$u->employment_start_date,'emergency_contact_name'=>$u->emergency_contact_name,'emergency_contact_phone'=>$u->emergency_contact_phone,'role'=>$u->role,'business_name'=>$b->name,'timezone'=>$b->timezone,'branches'=>$branches];
 }
 public function me(Request $r){return response()->json(['user'=>$this->account($r->user())]);}
 public function profile(Request $r){
  $v=$r->validate(['phone'=>'nullable|string|max:40']);$r->user()->update($v);return $this->me($r);
 }
 public function logout(Request $r){$r->user()->currentAccessToken()?->delete();return response()->json(['message'=>'Signed out.']);}
 public function history(Request $r){
  $rows=DB::table('attendance_records')->where('business_id',$r->user()->business_id)->where('user_id',$r->user()->id)->where(function($q){$q->where('clock_in','>=',now('UTC')->subDays(93))->orWhereNull('clock_out');})->orderByDesc('clock_in')->get();
  return response()->json(['data'=>$rows->map(function($a){$b=DB::table('branches')->where('id',$a->branch_id)->where('business_id',$a->business_id)->first();return ['id'=>(string)$a->id,'branch'=>['id'=>$b->id,'name'=>$b->name,'address'=>$b->address],'clock_in'=>Carbon::parse($a->clock_in,'UTC')->toIso8601String(),'clock_out'=>$a->clock_out ? Carbon::parse($a->clock_out,'UTC')->toIso8601String():null];})]);
 }
 private function location(Request $r,$branch): array {
  $v=$r->validate(['latitude'=>'required|numeric|between:-90,90','longitude'=>'required|numeric|between:-180,180','accuracy_m'=>'required|numeric|min:0|max:50','captured_at'=>'required|date']);
  abort_if(abs(now('UTC')->timestamp-Carbon::parse($v['captured_at'])->timestamp)>120,422,'Location is stale. Please try again.');
  $lat1=deg2rad((float)$branch->latitude);$lat2=deg2rad((float)$v['latitude']);$dl=$lat2-$lat1;$dn=deg2rad((float)$v['longitude']-(float)$branch->longitude);
  $a=sin($dl/2)**2+cos($lat1)*cos($lat2)*sin($dn/2)**2;$distance=6371000*2*atan2(sqrt($a),sqrt(max(0,1-$a)));
  abort_if($distance+(float)$v['accuracy_m']>$branch->radius_m,422,'Your location could not be verified inside this workplace. Move closer or ask your manager.');return $v;
 }
 public function clockIn(Request $r){
  $r->validate(['branch_id'=>'required|integer']);$u=$r->user();
  return DB::transaction(function()use($r,$u){
   User::where('id',$u->id)->lockForUpdate()->firstOrFail();
   $branch=DB::table('branches')->where('id',$r->integer('branch_id'))->where('business_id',$u->business_id)->where('active',true)->first();
   abort_unless($branch && DB::table('employee_branches')->where('user_id',$u->id)->where('business_id',$u->business_id)->where('branch_id',$branch->id)->exists(),403,'You are not assigned to this workplace.');
   abort_if(DB::table('attendance_records')->where('user_id',$u->id)->whereNull('clock_out')->exists(),409,'You are already clocked in.');$v=$this->location($r,$branch);
   $utcNow=Carbon::now('UTC');$id=DB::table('attendance_records')->insertGetId(['business_id'=>$u->business_id,'user_id'=>$u->id,'branch_id'=>$branch->id,'clock_in'=>$utcNow,'in_lat'=>$v['latitude'],'in_lng'=>$v['longitude'],'created_at'=>$utcNow,'updated_at'=>$utcNow]);return response()->json(['id'=>$id,'message'=>'Clocked in.'],201);
  });
 }
 public function clockOut(Request $r,$id){
  $u=$r->user();return DB::transaction(function()use($r,$u,$id){
   User::where('id',$u->id)->lockForUpdate()->firstOrFail();
   $a=DB::table('attendance_records')->where('id',$id)->where('business_id',$u->business_id)->where('user_id',$u->id)->lockForUpdate()->first();abort_unless($a,404);
   if($a->clock_out)return response()->json(['message'=>'Already clocked out.']);
   $branch=DB::table('branches')->where('id',$a->branch_id)->where('business_id',$u->business_id)->first();$v=$this->location($r,$branch);
   $utcNow=Carbon::now('UTC');DB::table('attendance_records')->where('id',$a->id)->update(['clock_out'=>$utcNow,'out_lat'=>$v['latitude'],'out_lng'=>$v['longitude'],'updated_at'=>$utcNow]);return response()->json(['message'=>'Clocked out.']);
  });
 }
}

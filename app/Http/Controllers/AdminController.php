<?php
namespace App\Http\Controllers;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
class AdminController {
 public function login(Request $r){
  $v=$r->validate(['email'=>'required|email','password'=>'required|string']);
  if(!Auth::attempt([...$v,'active'=>true,'role'=>'admin']))return back()->withErrors(['email'=>'Invalid administrator credentials.']);
  $r->session()->regenerate();return redirect('/');
 }
 private function business(Request $r): int {
  $u=$r->user();abort_unless($u && $u->role==='admin' && $u->active,403);
  abort_unless(DB::table('businesses')->where('id',$u->business_id)->where('active',true)->exists(),403);return $u->business_id;
 }
 public function index(Request $r){
  $id=$this->business($r);
  $business=DB::table('businesses')->find($id);
  $branches=DB::table('branches')->where('business_id',$id)->get();
  $employees=User::where('business_id',$id)->get();
  $records=DB::table('attendance_records as a')->join('users as u','u.id','=','a.user_id')->join('branches as b','b.id','=','a.branch_id')->where('a.business_id',$id)->orderByDesc('a.clock_in')->select('a.*','u.name as employee','b.name as branch')->limit(200)->get();
  return view('dashboard',compact('business','branches','employees','records'));
 }
 public function branch(Request $r){
  $id=$this->business($r);$v=$r->validate(['name'=>'required|string|max:100','address'=>'required|string|max:255','latitude'=>'required|numeric|between:-90,90','longitude'=>'required|numeric|between:-180,180','radius_m'=>'required|integer|min:50|max:1000']);
  DB::table('branches')->insert([...$v,'business_id'=>$id,'active'=>true,'created_at'=>now(),'updated_at'=>now()]);return back()->with('status','Branch created.');
 }
 public function employee(Request $r){
  $id=$this->business($r);$v=$r->validate(['name'=>'required|string|max:100','email'=>'required|email|max:255|unique:users,email','password'=>'required|string|min:12|max:100','branches'=>'required|array|min:1','branches.*'=>['required','integer',Rule::exists('branches','id')->where('business_id',$id)->where('active',true)]]);
  DB::transaction(function()use($id,$v){$u=User::create(['name'=>$v['name'],'email'=>$v['email'],'password'=>$v['password'],'business_id'=>$id,'role'=>'employee','active'=>true]);foreach(array_unique($v['branches']) as $b) DB::table('employee_branches')->insert(['business_id'=>$id,'user_id'=>$u->id,'branch_id'=>$b]);});return back()->with('status','Employee created. Share their login credentials securely.');
 }
 public function assignments(Request $r,$id){
  $business=$this->business($r);$u=User::where('business_id',$business)->findOrFail($id);
  $v=$r->validate(['branches'=>'required|array|min:1','branches.*'=>['integer',Rule::exists('branches','id')->where('business_id',$business)->where('active',true)]]);
  DB::transaction(function()use($u,$business,$v){User::where('id',$u->id)->lockForUpdate()->first();DB::table('employee_branches')->where('user_id',$u->id)->delete();foreach(array_unique($v['branches'])as $b)DB::table('employee_branches')->insert(['business_id'=>$business,'user_id'=>$u->id,'branch_id'=>$b]);});return back()->with('status','Branch assignments updated. Employee should sign in again to refresh their branches.');
 }
 public function toggle(Request $r,$id){
  $business=$this->business($r);abort_if((int)$id===$r->user()->id,422,'You cannot deactivate yourself.');
  $u=User::where('business_id',$business)->findOrFail($id);$u->active=!$u->active;$u->save();if(!$u->active)DB::table('api_tokens')->where('user_id',$u->id)->delete();return back()->with('status','Employee status updated.');
 }
 public function correction(Request $r,$id){
  $business=$this->business($r);$v=$r->validate(['clock_in'=>'required|date','clock_out'=>'nullable|date|after:clock_in','reason'=>'required|string|min:5|max:500']);
  $timezone=DB::table('businesses')->find($business)->timezone;
  DB::transaction(function()use($r,$id,$business,$v,$timezone){
   $candidate=DB::table('attendance_records')->where('id',$id)->where('business_id',$business)->first();abort_unless($candidate,404);
   User::where('id',$candidate->user_id)->lockForUpdate()->first();
   $a=DB::table('attendance_records')->where('id',$id)->where('business_id',$business)->lockForUpdate()->first();
   $in=Carbon::parse($v['clock_in'],$timezone)->utc();$out=empty($v['clock_out'])?null:Carbon::parse($v['clock_out'],$timezone)->utc();
   abort_if($in->isFuture() || ($out && $out->isFuture()),422,'Attendance cannot be in the future.');
   abort_if(!$out && DB::table('attendance_records')->where('user_id',$a->user_id)->where('id','!=',$a->id)->whereNull('clock_out')->exists(),409,'Employee already has another open record.');
   $overlap=DB::table('attendance_records')->where('user_id',$a->user_id)->where('id','!=',$a->id)->where(function($q)use($in){$q->whereNull('clock_out')->orWhere('clock_out','>',$in);});if($out)$overlap->where('clock_in','<',$out);abort_if($overlap->exists(),422,'This time overlaps another attendance record.');
   $after=['clock_in'=>$in->toDateTimeString(),'clock_out'=>$out?->toDateTimeString()];DB::table('attendance_records')->where('id',$a->id)->update([...$after,'updated_at'=>now()]);
   DB::table('audit_logs')->insert(['business_id'=>$business,'actor_id'=>$r->user()->id,'attendance_id'=>$a->id,'action'=>'corrected','reason'=>$v['reason'],'before'=>json_encode(['clock_in'=>$a->clock_in,'clock_out'=>$a->clock_out]),'after'=>json_encode($after),'created_at'=>now()]);
  });return back()->with('status','Attendance corrected and audit logged.');
 }
 public function export(Request $r){
  $id=$this->business($r);$tz=DB::table('businesses')->find($id)->timezone;
  return response()->streamDownload(function()use($id,$tz){$out=fopen('php://output','w');fputcsv($out,['Employee','Branch','Clock in ('.$tz.')','Clock out ('.$tz.')','Hours']);DB::table('attendance_records as a')->join('users as u','u.id','=','a.user_id')->join('branches as b','b.id','=','a.branch_id')->where('a.business_id',$id)->orderBy('a.id')->select('a.*','u.name','b.name as branch')->chunk(500,function($rows)use($out,$tz){foreach($rows as $a){$in=Carbon::parse($a->clock_in,'UTC');$end=$a->clock_out?Carbon::parse($a->clock_out,'UTC'):null;$safe=fn($s)=>preg_match('/^[=+@\\-\\t\\r]/u',$s)?"'".$s:$s;fputcsv($out,[$safe($a->name),$safe($a->branch),$in->copy()->tz($tz)->format('Y-m-d H:i:s'),$end?->copy()->tz($tz)->format('Y-m-d H:i:s'),$end?round($in->diffInSeconds($end)/3600,2):'Active']);}});fclose($out);},'mento-clock-attendance.csv',['Content-Type'=>'text/csv']);
 }
}

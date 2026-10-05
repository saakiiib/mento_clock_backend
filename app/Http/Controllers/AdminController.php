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
  if(Auth::guard('web')->attempt([...$v,'active'=>true,'role'=>'admin'])){
   if(!DB::table('businesses')->where('id',Auth::guard('web')->user()->business_id)->where('active',true)->exists()){Auth::guard('web')->logout();return back()->withErrors(['email'=>'This workspace is paused. Please contact Mento Software.']);}
   Auth::guard('platform')->logout();$r->session()->forget('platform_version');$r->session()->regenerate();return redirect('/');
  }
  if(Auth::guard('platform')->attempt([...$v,'active'=>true])){
   Auth::guard('web')->logout();$r->session()->regenerate();$r->session()->put('platform_version',Auth::guard('platform')->user()->auth_version);return redirect('/platform');
  }
  return back()->withErrors(['email'=>'The email or password is incorrect, or this account is inactive.']);
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
  $section=in_array($r->path(),['people','workplaces','attendance','company'])?$r->path():'overview';
  $assigned=DB::table('employee_branches')->where('business_id',$id)->get()->groupBy('user_id')->map(fn($rows)=>$rows->pluck('branch_id')->all());
  $today=Carbon::now($business->timezone)->toDateString();
  $todayReport=app(\App\Services\AttendanceReport::class)->make($id,['period'=>'daily','date'=>$today]);
  $weekReport=app(\App\Services\AttendanceReport::class)->make($id,['period'=>'weekly','date'=>$today]);
  $workingCount=DB::table('attendance_records')->where('business_id',$id)->whereNull('clock_out')->count();
  $profile=DB::table('client_profiles')->where('business_id',$id)->first();
  return view('dashboard',compact('business','branches','employees','records','section','assigned','todayReport','weekReport','workingCount','profile'));
 }
 public function branch(Request $r){
  $id=$this->business($r);$v=$r->validate(['name'=>'required|string|max:100','address'=>'required|string|max:255','latitude'=>'required|numeric|between:-90,90','longitude'=>'required|numeric|between:-180,180','radius_m'=>'required|integer|min:50|max:1000']);
  DB::table('branches')->insert([...$v,'business_id'=>$id,'active'=>true,'created_at'=>now(),'updated_at'=>now()]);return back()->with('status','Branch created.');
 }
 public function employee(Request $r){
  $id=$this->business($r);$v=$r->validate(['name'=>'required|string|max:100','email'=>'required|email|max:255|unique:users,email|unique:platform_admins,email','password'=>'required|string|min:6|max:100','branches'=>'required|array|min:1','branches.*'=>['required','integer',Rule::exists('branches','id')->where('business_id',$id)->where('active',true)]]);
  DB::transaction(function()use($id,$v){$u=User::create(['name'=>$v['name'],'email'=>$v['email'],'password'=>$v['password'],'business_id'=>$id,'role'=>'employee','active'=>true]);foreach(array_unique($v['branches']) as $b) DB::table('employee_branches')->insert(['business_id'=>$id,'user_id'=>$u->id,'branch_id'=>$b]);});return back()->with('status','Employee created. Share their login credentials securely.');
 }
 public function editEmployee(Request $r,$id){
  $business=$this->business($r);$employee=User::where('business_id',$business)->findOrFail($id);
  return view('employees.edit',['employee'=>$employee,'branches'=>DB::table('branches')->where('business_id',$business)->where('active',true)->get(),'assigned'=>DB::table('employee_branches')->where('user_id',$employee->id)->pluck('branch_id')->all()]);
 }
 public function updateEmployee(Request $r,$id){
  $business=$this->business($r);$employee=User::where('business_id',$business)->findOrFail($id);
  $v=$r->validate(['name'=>'required|string|max:100','email'=>['required','email','max:255',Rule::unique('users','email')->ignore($employee->id),Rule::unique('platform_admins','email')],'phone'=>'nullable|string|max:40','employee_code'=>'nullable|string|max:40','branches'=>'required|array|min:1','branches.*'=>['integer',Rule::exists('branches','id')->where('business_id',$business)->where('active',true)]]);
  DB::transaction(function()use($employee,$business,$v){User::where('id',$employee->id)->lockForUpdate()->first();$employee->update(collect($v)->except('branches')->all());$employee->tokens()->delete();DB::table('employee_branches')->where('user_id',$employee->id)->delete();foreach(array_unique($v['branches'])as $b)DB::table('employee_branches')->insert(['business_id'=>$business,'user_id'=>$employee->id,'branch_id'=>$b]);});return redirect('/')->with('status','Employee updated. They should sign in again.');
 }
 public function editBranch(Request $r,$id){$business=$this->business($r);return view('branches.edit',['branch'=>DB::table('branches')->where('business_id',$business)->where('id',$id)->firstOrFail()]);}
 public function updateBranch(Request $r,$id){
  $business=$this->business($r);$branch=DB::table('branches')->where('business_id',$business)->where('id',$id)->firstOrFail();
  $v=$r->validate(['name'=>'required|string|max:100','address'=>'required|string|max:255','latitude'=>'required|numeric|between:-90,90','longitude'=>'required|numeric|between:-180,180','radius_m'=>'required|integer|min:50|max:1000']);DB::table('branches')->where('id',$branch->id)->update([...$v,'updated_at'=>now()]);return redirect('/')->with('status','Workplace updated.');
 }
 public function assignments(Request $r,$id){
  $business=$this->business($r);$u=User::where('business_id',$business)->findOrFail($id);
  $v=$r->validate(['branches'=>'required|array|min:1','branches.*'=>['integer',Rule::exists('branches','id')->where('business_id',$business)->where('active',true)]]);
  DB::transaction(function()use($u,$business,$v){User::where('id',$u->id)->lockForUpdate()->first();$u->tokens()->delete();DB::table('employee_branches')->where('user_id',$u->id)->delete();foreach(array_unique($v['branches'])as $b)DB::table('employee_branches')->insert(['business_id'=>$business,'user_id'=>$u->id,'branch_id'=>$b]);});return back()->with('status','Branch assignments updated. Employee should sign in again to refresh their branches.');
 }
 public function toggle(Request $r,$id){
  $business=$this->business($r);abort_if((int)$id===$r->user()->id,422,'You cannot deactivate yourself.');
  $u=User::where('business_id',$business)->findOrFail($id);$u->active=!$u->active;$u->save();if(!$u->active)$u->tokens()->delete();return back()->with('status','Employee status updated.');
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
}

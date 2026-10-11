<?php
namespace App\Http\Controllers;
use App\Models\User;
use App\Services\BusinessCapacity;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
class AdminController {
 public function __construct(private BusinessCapacity $capacity) {}
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
  $branches=DB::table('branches')->where('business_id',$id)->get()->map(function($branch)use($id){$branch->employee_count=$this->capacity->activeEmployeeCount($id,(int)$branch->id);$branch->employee_limit=$this->capacity->employeeLimit($id,(int)$branch->id);return $branch;});
  $employees=User::where('business_id',$id)->get();
  $records=DB::table('attendance_records as a')->join('users as u','u.id','=','a.user_id')->join('branches as b','b.id','=','a.branch_id')->where('a.business_id',$id)->orderByDesc('a.clock_in')->select('a.*','u.name as employee','b.name as branch')->limit(200)->get();
  $section=in_array($r->path(),['people','workplaces','attendance'])?$r->path():'overview';
  $assigned=DB::table('employee_branches')->where('business_id',$id)->get()->groupBy('user_id')->map(fn($rows)=>$rows->pluck('branch_id')->all());
  $today=Carbon::now($business->timezone)->toDateString();
  $todayReport=app(\App\Services\AttendanceReport::class)->make($id,['period'=>'daily','date'=>$today]);
  $weekReport=app(\App\Services\AttendanceReport::class)->make($id,['period'=>'weekly','date'=>$today]);
  $workingCount=DB::table('attendance_records')->where('business_id',$id)->whereNull('clock_out')->count();
  $profile=DB::table('client_profiles')->where('business_id',$id)->first();
  $organisationFields=[
   $profile->organisation_name??$business->name,$profile->organisation_type??null,
   ($profile->organisation_email??null)?:($profile->contact_email??null),($profile->authorised_email??null)?:($profile->contact_email??null),
   ($profile->registered_office??null)?:(($profile->address_line_1??null)?:($profile->address??null)),$profile->operating_hours??null,
  ];
  $organisationProfileCompletion=(int)round(count(array_filter($organisationFields,fn($value)=>is_string($value)?trim($value)!=='':!empty($value)))/count($organisationFields)*100);
  $moduleAccess=app(\App\Services\ModuleAccess::class);
  $pendingLeaveCount=0;$upcomingShiftCount=0;$upcomingHolidayCount=0;$complianceDueCount=0;$unacknowledgedNoticeCount=0;
  $localNow=Carbon::now($business->timezone);
  if($moduleAccess->enabled($id,'leave_management'))$pendingLeaveCount=DB::table('leave_requests')->where('business_id',$id)->where('status','pending')->count();
  if($moduleAccess->enabled($id,'rota_shifts'))$upcomingShiftCount=DB::table('rota_shifts')->where('business_id',$id)->where('status','published')->whereBetween('starts_at',[$localNow->copy()->utc()->toDateTimeString(),$localNow->copy()->addDays(7)->utc()->toDateTimeString()])->count();
  if($moduleAccess->enabled($id,'holiday_calendar'))$upcomingHolidayCount=DB::table('business_holidays')->where('business_id',$id)->whereBetween('holiday_date',[$localNow->toDateString(),$localNow->copy()->addDays(30)->toDateString()])->count();
  if($moduleAccess->enabled($id,'compliance_tracking')){
   $dueDate=$localNow->copy()->addDays(60)->toDateString();
   $complianceDueCount=DB::table('employee_documents')->where('business_id',$id)->whereNotNull('expiry_date')->where('expiry_date','<=',$dueDate)->where('status','!=','rejected')->count()
    +DB::table('employee_training_records')->where('business_id',$id)->whereIn('status',['valid','expired'])->whereNotNull('expires_on')->where('expires_on','<=',$dueDate)->count();
  }
  if($moduleAccess->enabled($id,'company_notices'))$unacknowledgedNoticeCount=DB::table('company_notice_receipts as r')->join('company_notices as n','n.id','=','r.notice_id')->where('r.business_id',$id)->whereNull('r.acknowledged_at')->where('n.status','published')->where('n.acknowledgement_required',true)->where(function($q){$q->whereNull('n.publish_at')->orWhere('n.publish_at','<=',now('UTC'));})->where(function($q){$q->whereNull('n.expires_at')->orWhere('n.expires_at','>',now('UTC'));})->count();
  $definitions=[
   'employee_management'=>['title'=>'People','description'=>'Employee profiles, workplace access and team details.','href'=>'/people','icon'=>'people','count'=>$employees->where('role','employee')->where('active',true)->count(),'unit'=>'active employees'],
   'attendance_tracking'=>['title'=>'Attendance','description'=>'See clock-ins and review attendance activity.','href'=>'/attendance','icon'=>'clock','count'=>$workingCount,'unit'=>'employees clocked in now'],
   'reports_analytics'=>['title'=>'Reports','description'=>'Review hours and download workforce reports.','href'=>'/reports','icon'=>'report','count'=>number_format($weekReport['total_hours'],1),'unit'=>'hours recorded this week'],
   'leave_management'=>['title'=>'Leave management','description'=>'Review requests, balances and employee leave.','href'=>'/leave','icon'=>'calendar','count'=>$pendingLeaveCount,'unit'=>'requests waiting for review'],
   'holiday_calendar'=>['title'=>'Holiday calendar','description'=>'View public holidays and company closure dates.','href'=>'/calendar','icon'=>'calendar','count'=>$upcomingHolidayCount,'unit'=>'calendar dates in the next 30 days'],
   'rota_shifts'=>['title'=>'Rota & shifts','description'=>'Plan the week and check published employee shifts.','href'=>'/rota','icon'=>'clock','count'=>$upcomingShiftCount,'unit'=>'published shifts in the next 7 days'],
   'compliance_tracking'=>['title'=>'Compliance & training','description'=>'Review private employee records, renewals and training.','href'=>'/compliance','icon'=>'shield','count'=>$complianceDueCount,'unit'=>'records due or expired'],
   'company_notices'=>['title'=>'Company notices','description'=>'Share team updates and follow required acknowledgements.','href'=>'/notices','icon'=>'report','count'=>$unacknowledgedNoticeCount,'unit'=>'acknowledgements outstanding'],
  ];
  $overviewModules=[];$overviewStats=[];
  foreach($definitions as $code=>$module){
   if(!$moduleAccess->enabled($id,$code))continue;
   $overviewModules[]=['code'=>$code,...$module];
   if(in_array($code,['employee_management','attendance_tracking','leave_management','rota_shifts','compliance_tracking','company_notices'],true))$overviewStats[]=['title'=>$module['title'],'count'=>$module['count'],'unit'=>$module['unit'],'icon'=>$module['icon'],'href'=>$module['href'],'attention'=>in_array($code,['leave_management','compliance_tracking','company_notices'],true)&&$module['count']>0];
  }
  $overviewModules[]=['code'=>'organisation_profile','title'=>'Organisation profile','description'=>'Keep your legal details, contacts, working hours and business documents up to date.','href'=>'/company','icon'=>'building','count'=>$organisationProfileCompletion,'unit'=>'% complete'];
  return view('dashboard',compact('business','branches','employees','records','section','assigned','todayReport','weekReport','workingCount','profile','overviewModules','overviewStats','pendingLeaveCount','upcomingShiftCount','upcomingHolidayCount','complianceDueCount','unacknowledgedNoticeCount','organisationProfileCompletion'));
 }
 private function employeeProfileRules(int $businessId): array {
  $authority=Rule::exists('users','id')->where('business_id',$businessId)->where('active',true);
  return [
   'first_name'=>'required|string|max:100','middle_name'=>'nullable|string|max:100','last_name'=>'required|string|max:100',
   'email'=>'required|email|max:255','phone'=>'required|string|max:40','alternative_phone'=>'nullable|string|max:40',
   'employee_code'=>'nullable|string|max:40','gender'=>'nullable|string|in:female,male,non_binary,prefer_not_to_say',
   'national_insurance_number'=>'nullable|string|max:30','date_of_birth'=>'nullable|date_format:Y-m-d|before:today',
   'marital_status'=>'nullable|string|max:50','nationality'=>'nullable|string|max:100',
   'home_address_line_1'=>'nullable|string|max:255','home_address_line_2'=>'nullable|string|max:255',
   'home_city'=>'nullable|string|max:120','home_county'=>'nullable|string|max:120',
   'home_postcode'=>'nullable|string|max:20','home_country'=>'nullable|string|max:100',
   'bank_name'=>'nullable|string|max:150','sort_code'=>'nullable|string|max:20','bank_account_number'=>'nullable|string|max:40',
   'emergency_contact_name'=>'nullable|string|max:100','emergency_contact_relationship'=>'nullable|string|max:80',
   'emergency_contact_phone'=>'nullable|string|max:40','emergency_contact_email'=>'nullable|email|max:255',
   'emergency_contact_address'=>'nullable|string|max:1000',
   'passport_number'=>'nullable|string|max:60','passport_nationality'=>'nullable|string|max:100',
   'passport_issued_by'=>'nullable|string|max:150','passport_issued_date'=>'nullable|date_format:Y-m-d',
   'passport_expiry_date'=>'nullable|date_format:Y-m-d','visa_brp_number'=>'nullable|string|max:60',
   'visa_nationality'=>'nullable|string|max:100','visa_issued_by'=>'nullable|string|max:150',
   'visa_issued_date'=>'nullable|date_format:Y-m-d','visa_expiry_date'=>'nullable|date_format:Y-m-d',
   'cos_number'=>'nullable|string|max:80','cos_start_date'=>'nullable|date_format:Y-m-d','cos_end_date'=>'nullable|date_format:Y-m-d',
   'department'=>'nullable|string|max:120','job_title'=>'nullable|string|max:100',
   'employment_type'=>'nullable|string|in:full_time,part_time,fixed_term,casual,zero_hours,contractor,apprentice,other',
   'shift_name'=>'nullable|string|max:120','late_policy'=>'nullable|string|max:120','day_off_rule'=>'nullable|string|max:120',
   'employment_start_date'=>'nullable|date_format:Y-m-d','date_of_confirmation'=>'nullable|date_format:Y-m-d',
   'contract_start_date'=>'nullable|date_format:Y-m-d','contract_end_date'=>'nullable|date_format:Y-m-d',
   'job_location'=>'nullable|string|max:180','job_description'=>'nullable|string|max:10000',
   'reporting_authority_id'=>['nullable','integer',$authority],
   'leave_sanction_authority_id'=>['nullable','integer',$authority],
  ];
 }
 private function employeeProfilePayload(array $validated): array {
  $fields=[
   'first_name','middle_name','last_name','email','phone','alternative_phone','employee_code','gender',
   'national_insurance_number','date_of_birth','marital_status','nationality','home_address_line_1',
   'home_address_line_2','home_city','home_county','home_postcode','home_country','bank_name','sort_code',
   'bank_account_number','emergency_contact_name','emergency_contact_relationship','emergency_contact_phone',
   'emergency_contact_email','emergency_contact_address','passport_number','passport_nationality',
   'passport_issued_by','passport_issued_date','passport_expiry_date','visa_brp_number','visa_nationality',
   'visa_issued_by','visa_issued_date','visa_expiry_date','cos_number','cos_start_date','cos_end_date',
   'department','job_title','employment_type','shift_name','late_policy','day_off_rule','employment_start_date',
   'date_of_confirmation','contract_start_date','contract_end_date','job_location','job_description',
   'reporting_authority_id','leave_sanction_authority_id',
  ];
  $payload=collect($validated)->only($fields)->all();
  $payload['name']=trim(implode(' ',array_filter([
   $validated['first_name']??null,$validated['middle_name']??null,$validated['last_name']??null
  ],fn($part)=>filled($part))));
  return $payload;
 }
 private function employeePrivateInputs(): array {
  return [
   'password','password_confirmation','date_of_birth','national_insurance_number','nationality',
   'home_address_line_1','home_address_line_2','home_city','home_county','home_postcode','home_country',
   'bank_name','sort_code','bank_account_number','emergency_contact_name','emergency_contact_relationship',
   'emergency_contact_phone','emergency_contact_email','emergency_contact_address','passport_number',
   'passport_nationality','passport_issued_by','passport_issued_date','passport_expiry_date',
   'visa_brp_number','visa_nationality','visa_issued_by','visa_issued_date','visa_expiry_date',
   'cos_number','cos_start_date','cos_end_date',
  ];
 }
 private function validateEmployeeForm(Request $request, array $rules): array|\Illuminate\Http\RedirectResponse {
  $validator=Validator::make($request->all(),$rules);
  if($validator->fails()){
   return back()->withErrors($validator)->withInput($request->except($this->employeePrivateInputs()));
  }
  return $validator->validated();
 }
 private function employeeFormData(int $businessId): array {
  $branches=DB::table('branches')->where('business_id',$businessId)->where('active',true)->get()->map(function($branch)use($businessId){
   $branch->employee_count=$this->capacity->activeEmployeeCount($businessId,(int)$branch->id);
   $branch->employee_limit=$this->capacity->employeeLimit($businessId,(int)$branch->id);
   return $branch;
  });
  $authorities=User::where('business_id',$businessId)->where('active',true)->orderBy('name')->get(['id','name','email','role']);
  return compact('branches','authorities');
 }
 public function createEmployee(Request $r){
  $businessId=$this->business($r);$form=$this->employeeFormData($businessId);
  return view('employees.create',[...$form,'employee'=>null,'assigned'=>[]]);
 }
 public function branch(Request $r){
  $id=$this->business($r);$v=$r->validate(['name'=>'required|string|max:100','address'=>'required|string|max:255','latitude'=>'required|numeric|between:-90,90','longitude'=>'required|numeric|between:-180,180','radius_m'=>'required|integer|min:50|max:1000']);
  DB::transaction(function()use($id,$v){DB::table('businesses')->where('id',$id)->lockForUpdate()->firstOrFail();$this->capacity->ensureBranchSlot($id);DB::table('branches')->insert([...$v,'business_id'=>$id,'active'=>true,'created_at'=>now('UTC'),'updated_at'=>now('UTC')]);});return back()->with('status','Branch created.');
 }
 public function employee(Request $r){
  $id=$this->business($r);$rules=$this->employeeProfileRules($id);
  $rules['email']=['required','email','max:255','unique:users,email','unique:platform_admins,email'];
  $rules['password']='required|string|min:12|max:72';
  $rules['branches']='required|array|min:1';$rules['branches.*']=['required','integer',Rule::exists('branches','id')->where('business_id',$id)->where('active',true)];
  $v=$this->validateEmployeeForm($r,$rules);if($v instanceof \Illuminate\Http\RedirectResponse)return $v;$payload=$this->employeeProfilePayload($v);
  DB::transaction(function()use($id,$v,$payload){
   DB::table('businesses')->where('id',$id)->lockForUpdate()->firstOrFail();$this->capacity->ensureEmployeeAssignments($id,$v['branches']);
   $u=User::create([...$payload,'password'=>$v['password'],'business_id'=>$id,'role'=>'employee','active'=>true]);
   foreach(array_unique($v['branches']) as $b)DB::table('employee_branches')->insert(['business_id'=>$id,'user_id'=>$u->id,'branch_id'=>$b]);
  });
  return redirect('/people')->with('status','Employee created. Share their login credentials securely.');
 }
 public function editEmployee(Request $r,$id){
  $business=$this->business($r);$employee=User::where('business_id',$business)->where('role','employee')->findOrFail($id);
  return view('employees.edit',['employee'=>$employee,...$this->employeeFormData($business),'assigned'=>DB::table('employee_branches')->where('business_id',$business)->where('user_id',$employee->id)->pluck('branch_id')->all()]);
 }
 public function updateEmployee(Request $r,$id){
  $business=$this->business($r);$employee=User::where('business_id',$business)->where('role','employee')->findOrFail($id);
  $rules=$this->employeeProfileRules($business);$rules['email']=['required','email','max:255',Rule::unique('users','email')->ignore($employee->id),Rule::unique('platform_admins','email')];
  $rules['password']='nullable|string|min:12|max:72';$rules['branches']='required|array|min:1';$rules['branches.*']=['integer',Rule::exists('branches','id')->where('business_id',$business)->where('active',true)];
  $v=$this->validateEmployeeForm($r,$rules);if($v instanceof \Illuminate\Http\RedirectResponse)return $v;abort_if((int)($v['reporting_authority_id']??0)===$employee->id||(int)($v['leave_sanction_authority_id']??0)===$employee->id,422,'An employee cannot be their own authority.');
  $payload=$this->employeeProfilePayload($v);
  DB::transaction(function()use($employee,$business,$v,$payload){
   DB::table('businesses')->where('id',$business)->lockForUpdate()->firstOrFail();$current=User::where('business_id',$business)->where('id',$employee->id)->lockForUpdate()->firstOrFail();
   if($current->active)$this->capacity->ensureEmployeeAssignments($business,$v['branches'],$current->id);
   $current->update($payload);if(!empty($v['password']))$current->update(['password'=>$v['password']]);$current->tokens()->delete();
   DB::table('employee_branches')->where('business_id',$business)->where('user_id',$current->id)->delete();
   foreach(array_unique($v['branches'])as $b)DB::table('employee_branches')->insert(['business_id'=>$business,'user_id'=>$current->id,'branch_id'=>$b]);
  });
  return redirect('/people')->with('status','Employee profile updated. They should sign in again.');
 }
 public function editBranch(Request $r,$id){$business=$this->business($r);return view('branches.edit',['branch'=>DB::table('branches')->where('business_id',$business)->where('id',$id)->firstOrFail()]);}
 public function updateBranch(Request $r,$id){
  $business=$this->business($r);$branch=DB::table('branches')->where('business_id',$business)->where('id',$id)->firstOrFail();
  $v=$r->validate(['name'=>'required|string|max:100','address'=>'required|string|max:255','latitude'=>'required|numeric|between:-90,90','longitude'=>'required|numeric|between:-180,180','radius_m'=>'required|integer|min:50|max:1000']);DB::table('branches')->where('id',$branch->id)->update([...$v,'updated_at'=>now()]);return redirect('/')->with('status','Workplace updated.');
 }
 public function assignments(Request $r,$id){
  $business=$this->business($r);$u=User::where('business_id',$business)->findOrFail($id);
  $v=$r->validate(['branches'=>'required|array|min:1','branches.*'=>['integer',Rule::exists('branches','id')->where('business_id',$business)->where('active',true)]]);
  DB::transaction(function()use($u,$business,$v){DB::table('businesses')->where('id',$business)->lockForUpdate()->firstOrFail();$current=User::where('business_id',$business)->where('id',$u->id)->lockForUpdate()->firstOrFail();if($current->active)$this->capacity->ensureEmployeeAssignments($business,$v['branches'],$current->id);$current->tokens()->delete();DB::table('employee_branches')->where('business_id',$business)->where('user_id',$current->id)->delete();foreach(array_unique($v['branches'])as $b)DB::table('employee_branches')->insert(['business_id'=>$business,'user_id'=>$current->id,'branch_id'=>$b]);});return back()->with('status','Branch assignments updated. Employee should sign in again to refresh their branches.');
 }
 public function toggle(Request $r,$id){
  $business=$this->business($r);abort_if((int)$id===$r->user()->id,422,'You cannot deactivate yourself.');
  DB::transaction(function()use($business,$id){DB::table('businesses')->where('id',$business)->lockForUpdate()->firstOrFail();$u=User::where('business_id',$business)->where('role','employee')->where('id',$id)->lockForUpdate()->firstOrFail();if(!$u->active)$this->capacity->ensureCanActivateEmployee($business,$u->id);$u->active=!$u->active;$u->save();if(!$u->active)$u->tokens()->delete();});return back()->with('status','Employee status updated.');
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

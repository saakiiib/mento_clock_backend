<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Services\BusinessCapacity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Client-approved, business-scoped assistance. NEVER impersonates a client account. */
class V38AssistanceController
{
    public function __construct(private BusinessCapacity $capacity) {}

    private function business(Request $r): int
    {
        $u = Auth::guard('web')->user();
        abort_unless($u && $u->role === 'admin' && $u->active && app(\App\Services\WorkspacePermissions::class)->has($u, 'workspace.access'), 403);
        $bid = (int)$u->business_id;
        abort_unless(DB::table('businesses')->where('id',$bid)->where('active',true)->exists(),403);
        return $bid;
    }
    private function operator(): bool
    {
        return DB::table('platform_support_operators')->where('admin_id',Auth::guard('platform')->id())->exists();
    }
    private function superOnly(): void { abort_if($this->operator(),403,'Only full Super Admin can perform this action.'); }
    private function sessionForWrite(int $sessionId): object
    {
        $session = DB::table('client_assistance_sessions')->where('id',$sessionId)->first();
        abort_unless($session,404);
        abort_unless($session->status==='active' && !$session->revoked_at && $session->expires_at && now('UTC')->lt(\Carbon\Carbon::parse($session->expires_at)),403,'Client assistance approval expired or revoked.');
        abort_unless((int)$session->operator_id===(int)Auth::guard('platform')->id(),403,'This session belongs to another support operator.');
        abort_unless(DB::table('businesses')->where('id',$session->business_id)->where('active',true)->exists(),403);
        return $session;
    }
    private function lockSession(object $session): void
    {
        $current=DB::table('client_assistance_sessions')->where('id',$session->id)->lockForUpdate()->firstOrFail();
        abort_unless($current->status==='active' && !$current->revoked_at && $current->expires_at && now('UTC')->lt(\Carbon\Carbon::parse($current->expires_at)) && (int)$current->operator_id===(int)Auth::guard('platform')->id(),403,'Client assistance authorisation expired or revoked.');
    }
    private function log(object $session,string $action,?string $type,?int $id,array $before,array $after,string $reason): void
    {
        DB::table('client_assistance_events')->insert(['session_id'=>$session->id,'business_id'=>$session->business_id,'actor_id'=>Auth::guard('platform')->id(),'action'=>$action,'subject_type'=>$type,'subject_id'=>$id,'before_json'=>json_encode($before,JSON_THROW_ON_ERROR),'after_json'=>json_encode($after,JSON_THROW_ON_ERROR),'reason'=>$reason,'created_at'=>now('UTC')]);
        DB::table('platform_audit_logs')->insert(['actor_id'=>Auth::guard('platform')->id(),'business_id'=>$session->business_id,'action'=>'V38 '.$action,'details'=>json_encode(['session_id'=>$session->id,'subject_type'=>$type,'subject_id'=>$id,'reason'=>$reason],JSON_THROW_ON_ERROR),'created_at'=>now('UTC')]);
        DB::table('client_assistance_notices')->insert(['business_id'=>$session->business_id,'subject'=>'MentoClock support: '.str_replace('_',' ',$action),'body'=>'Support performed '.str_replace('_',' ',$action).' at '.now('UTC')->format('Y-m-d H:i').' UTC. Reference session #'.$session->id.'.','created_at'=>now('UTC')]);
    }
    public function clientIndex(Request $r)
    {
        $business=$this->business($r);
        $sessions=DB::table('client_assistance_sessions')->where('business_id',$business)->orderByDesc('id')->limit(20)->get();
        $notices=DB::table('client_assistance_notices')->where('business_id',$business)->orderByDesc('id')->limit(20)->get();
        return view('assistance.client',compact('sessions','notices','business'));
    }
    public function requestAssistance(Request $r)
    {
        $business=$this->business($r);
        $v=$r->validate(['purpose'=>'required|string|min:10|max:300']);
        abort_if(DB::table('client_assistance_sessions')->where('business_id',$business)->whereIn('status',['pending','active'])->where(function($q){$q->where('status','pending')->orWhere('expires_at','>',now('UTC'));})->exists(),422,'A support request or active session already exists.');
        DB::table('client_assistance_sessions')->insert(['business_id'=>$business,'requested_by'=>Auth::guard('web')->id(),'status'=>'pending','purpose'=>$v['purpose'],'created_at'=>now('UTC'),'updated_at'=>now('UTC')]);
        return back()->with('status','Support requested. Mento Software must accept before making changes.');
    }
    public function revoke(Request $r,int $session)
    {
        $business=$this->business($r);
        DB::table('client_assistance_sessions')->where('id',$session)->where('business_id',$business)->whereIn('status',['pending','active'])->update(['status'=>'revoked','revoked_at'=>now('UTC'),'updated_at'=>now('UTC')]);
        return back()->with('status','Assistance access revoked.');
    }
    public function platformIndex()
    {
        $sessions=DB::table('client_assistance_sessions as s')->join('businesses as b','b.id','=','s.business_id')->leftJoin('platform_admins as pa','pa.id','=','s.operator_id')
          ->orderByDesc('s.id')->limit(100)->get(['s.*','b.name as business_name','pa.name as operator_name']);
        $operators=DB::table('platform_admins as pa')->leftJoin('platform_support_operators as o','o.admin_id','=','pa.id')->where('pa.active',true)->orderBy('pa.name')->get(['pa.id','pa.name','pa.email','o.admin_id as is_operator']);
        return view('assistance.platform',compact('sessions','operators'));
    }
    public function operatorRole(Request $r)
    {
        $this->superOnly();$v=$r->validate(['admin_id'=>'required|integer|exists:platform_admins,id','is_operator'=>'required|boolean']);
        abort_if((int)$v['admin_id']===(int)Auth::guard('platform')->id(),422,'Do not convert your current Super Admin account to a support operator.');
        DB::transaction(function()use($v){
            if($v['is_operator'])DB::table('platform_support_operators')->updateOrInsert(['admin_id'=>$v['admin_id']],['assigned_by'=>Auth::guard('platform')->id(),'created_at'=>now('UTC')]);
            else DB::table('platform_support_operators')->where('admin_id',$v['admin_id'])->delete();
            DB::table('platform_admins')->where('id',$v['admin_id'])->increment('auth_version');
            DB::table('platform_audit_logs')->insert(['actor_id'=>Auth::guard('platform')->id(),'business_id'=>null,'action'=>'Support operator role updated','details'=>json_encode(['target_admin_id'=>$v['admin_id'],'is_operator'=>(bool)$v['is_operator']]),'created_at'=>now('UTC')]);
        });
        return back()->with('status','Role updated. Target user must sign in again.');
    }
    public function activate(Request $r,int $session)
    {
        $v=$r->validate(['operator_id'=>'required|integer|exists:platform_admins,id','hours'=>'required|integer|min:1|max:24']);
        $operatorId=(int)$v['operator_id'];
        if($this->operator())abort_unless($operatorId===(int)Auth::guard('platform')->id(),403);
        abort_unless(DB::table('platform_admins')->where('id',$operatorId)->where('active',true)->exists(),422);
        DB::transaction(function()use($session,$operatorId,$v){
            $row=DB::table('client_assistance_sessions')->where('id',$session)->lockForUpdate()->firstOrFail();
            abort_unless($row->status==='pending',422,'Only pending requests may be activated.');
            abort_unless(DB::table('businesses')->where('id',$row->business_id)->where('active',true)->exists(),422);
            DB::table('client_assistance_sessions')->where('id',$session)->update(['status'=>'active','approved_by'=>$row->requested_by,'operator_id'=>$operatorId,'expires_at'=>now('UTC')->addHours((int)$v['hours']),'updated_at'=>now('UTC')]);
            DB::table('platform_audit_logs')->insert(['actor_id'=>Auth::guard('platform')->id(),'business_id'=>$row->business_id,'action'=>'Assistance session activated','details'=>json_encode(['session_id'=>$session,'operator_id'=>$operatorId,'hours'=>(int)$v['hours']]),'created_at'=>now('UTC')]);
            DB::table('client_assistance_notices')->insert(['business_id'=>$row->business_id,'subject'=>'Assistance session activated','body'=>'Your requested assistance session #'.$session.' is active for '.(int)$v['hours'].' hour(s). You may revoke it from Request support.','created_at'=>now('UTC')]);
        });
        return redirect('/platform/assist/sessions/'.$session)->with('status','Client assistance activated.');
    }
    public function session(int $session)
    {
        $s=$this->sessionForWrite($session);$bid=(int)$s->business_id;
        $business=DB::table('businesses')->findOrFail($bid);
        $branches=DB::table('branches')->where('business_id',$bid)->orderBy('name')->get();
        $employees=User::where('business_id',$bid)->where('role','employee')->orderBy('name')->paginate(25);
        $events=DB::table('client_assistance_events')->where('session_id',$session)->orderByDesc('id')->limit(50)->get();
        return view('assistance.session',compact('s','business','branches','employees','events'));
    }
    public function createBranch(Request $r,int $session)
    {
        $s=$this->sessionForWrite($session);$bid=(int)$s->business_id;
        $v=$r->validate(['name'=>'required|string|max:100','address'=>'nullable|string|max:255','latitude'=>'nullable|required_with:longitude|numeric|between:-90,90','longitude'=>'nullable|required_with:latitude|numeric|between:-180,180','radius_m'=>'required|integer|min:50|max:1000','location_required'=>'required|boolean','reason'=>'required|string|min:5|max:300']);
        $id=DB::transaction(function()use($s,$bid,$v){
            $this->lockSession($s);
            DB::table('businesses')->where('id',$bid)->lockForUpdate()->firstOrFail();$this->capacity->ensureBranchSlot($bid);
            $id=DB::table('branches')->insertGetId(['business_id'=>$bid,'name'=>$v['name'],'address'=>$v['address']??null,'latitude'=>$v['latitude']??null,'longitude'=>$v['longitude']??null,'radius_m'=>$v['radius_m'],'location_required'=>$v['location_required'],'active'=>true,'created_at'=>now('UTC'),'updated_at'=>now('UTC')]);
            $this->log($s,'branch_created','branch',$id,[],['name'=>$v['name'],'address'=>$v['address']??null,'location_required'=>(bool)$v['location_required']],$v['reason']);return $id;
        });
        return back()->with('status','Workplace #'.$id.' created and audited.');
    }
    public function createEmployee(Request $r,int $session)
    {
        $s=$this->sessionForWrite($session);$bid=(int)$s->business_id;
        $v=$r->validate(['name'=>'required|string|max:100','email'=>['required','email','max:255','unique:users,email','unique:platform_admins,email'],'password'=>'required|string|min:12|max:72','branch_ids'=>'required|array|min:1','branch_ids.*'=>['required','integer',Rule::exists('branches','id')->where('business_id',$bid)->where('active',true)],'reason'=>'required|string|min:5|max:300']);
        $ids=array_values(array_unique(array_map('intval',$v['branch_ids'])));
        $id=DB::transaction(function()use($s,$bid,$v,$ids){
            $this->lockSession($s);
            $business=DB::table('businesses')->where('id',$bid)->lockForUpdate()->firstOrFail();$this->capacity->ensureEmployeeAssignments($bid,$ids);
            $u=User::create(['name'=>$v['name'],'email'=>strtolower(trim($v['email'])),'password'=>$v['password'],'business_id'=>$bid,'client_account_id'=>$business->client_account_id,'role'=>'employee','active'=>true]);
            foreach($ids as $branchId)DB::table('employee_branches')->insert(['business_id'=>$bid,'branch_id'=>$branchId,'user_id'=>$u->id]);
            $this->log($s,'employee_created','employee',$u->id,[],['name'=>$v['name'],'branch_ids'=>$ids],$v['reason']);return $u->id;
        });
        return back()->with('status','Employee #'.$id.' created. Share their password privately.');
    }
    public function assign(Request $r,int $session,int $employee)
    {
        $s=$this->sessionForWrite($session);$bid=(int)$s->business_id;
        $v=$r->validate(['branch_ids'=>'required|array|min:1','branch_ids.*'=>['required','integer',Rule::exists('branches','id')->where('business_id',$bid)->where('active',true)],'reason'=>'required|string|min:5|max:300']);
        $new=array_values(array_unique(array_map('intval',$v['branch_ids'])));
        DB::transaction(function()use($s,$bid,$employee,$v,$new){
            $this->lockSession($s);
            DB::table('businesses')->where('id',$bid)->lockForUpdate()->firstOrFail();
            $u=User::where('business_id',$bid)->where('role','employee')->lockForUpdate()->findOrFail($employee);
            $old=DB::table('employee_branches')->where('business_id',$bid)->where('user_id',$employee)->pluck('branch_id')->map(fn($n)=>(int)$n)->all();
            $open=DB::table('attendance_records')->where('business_id',$bid)->where('user_id',$employee)->whereNull('clock_out')->pluck('branch_id')->map(fn($n)=>(int)$n)->all();
            $old=array_values(array_unique($old));sort($old);
            sort($new);
            abort_if($open && $old!==$new,422,'Employee has an active shift. Change workplaces after clock-out to avoid interruption.');
            if($u->active)$this->capacity->ensureEmployeeAssignments($bid,$new,$employee);
            if($old===$new)return;
            DB::table('employee_branches')->where('business_id',$bid)->where('user_id',$employee)->delete();
            foreach($new as $branchId)DB::table('employee_branches')->insert(['business_id'=>$bid,'user_id'=>$employee,'branch_id'=>$branchId]);
            $u->tokens()->delete();
            $this->log($s,'employee_workplaces_changed','employee',$employee,['branch_ids'=>$old],['branch_ids'=>$new],$v['reason']);
        });
        return back()->with('status','Workplaces updated and audited. Employee must sign in again.');
    }
}

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
    private function assistedScope(int $id, bool $standing): object
    {
        if (!$standing) {
            $session = $this->sessionForWrite($id);
            $session->standing = false;
            return $session;
        }
        $this->superOnly(); // Standing support is reserved for the full Super Admin role.
        $row = DB::table('client_support_authorizations')->where('business_id', $id)
            ->whereNull('revoked_at')->first();
        abort_unless($row && $row->granted_at && DB::table('businesses')->where('id', $id)->where('active', true)->exists(),
            403, 'Client has not enabled ongoing support access. Request a time-limited session instead.');
        return (object)['id'=>null, 'business_id'=>$id, 'standing'=>true, 'authorization_id'=>$row->id];
    }
    private function lockSession(object $session): void
    {
        if (!empty($session->standing)) {
            abort_unless(DB::table('client_support_authorizations')
                ->where('business_id', $session->business_id)->where('id', $session->authorization_id)
                ->whereNull('revoked_at')->lockForUpdate()->exists(), 403,
                'Client revoked ongoing support access.');
            return;
        }
        $current=DB::table('client_assistance_sessions')->where('id',$session->id)->lockForUpdate()->firstOrFail();
        abort_unless($current->status==='active' && !$current->revoked_at && $current->expires_at && now('UTC')->lt(\Carbon\Carbon::parse($current->expires_at)) && (int)$current->operator_id===(int)Auth::guard('platform')->id(),403,'Client assistance authorisation expired or revoked.');
    }
    private function log(object $session,string $action,?string $type,?int $id,array $before,array $after,string $reason): void
    {
        $eventTable = !empty($session->standing) ? 'client_standing_support_events' : 'client_assistance_events';
        $eventData = ['business_id'=>$session->business_id,'actor_id'=>Auth::guard('platform')->id(),'action'=>$action,'subject_type'=>$type,'subject_id'=>$id,'before_json'=>json_encode($before,JSON_THROW_ON_ERROR),'after_json'=>json_encode($after,JSON_THROW_ON_ERROR),'reason'=>$reason,'created_at'=>now('UTC')];
        if (!empty($session->standing)) $eventData['authorization_id']=$session->authorization_id;
        else $eventData['session_id']=$session->id;
        DB::table($eventTable)->insert($eventData);
        DB::table('platform_audit_logs')->insert(['actor_id'=>Auth::guard('platform')->id(),'business_id'=>$session->business_id,'action'=>'V38 '.$action,'details'=>json_encode(['access_mode'=>!empty($session->standing)?'standing':'session','session_id'=>$session->id,'subject_type'=>$type,'subject_id'=>$id,'reason'=>$reason],JSON_THROW_ON_ERROR),'created_at'=>now('UTC')]);
        DB::table('client_assistance_notices')->insert(['business_id'=>$session->business_id,'subject'=>'MentoClock support: '.str_replace('_',' ',$action),'body'=>'Support performed '.str_replace('_',' ',$action).' at '.now('UTC')->format('Y-m-d H:i').' UTC. Access: '.(!empty($session->standing)?'ongoing authorisation':'session #'.$session->id).'.','created_at'=>now('UTC')]);
    }
    public function clientIndex(Request $r)
    {
        $business=$this->business($r);
        $sessions=DB::table('client_assistance_sessions')->where('business_id',$business)->orderByDesc('id')->limit(20)->get();
        $notices=DB::table('client_assistance_notices')->where('business_id',$business)->orderByDesc('id')->limit(20)->get();
        $standing = DB::table('client_support_authorizations')->where('business_id',$business)->first();
        $standingEvents = DB::table('client_standing_support_events as e')->join('platform_admins as p','p.id','=','e.actor_id')
            ->where('e.business_id',$business)->orderByDesc('e.id')->limit(30)
            ->get(['e.created_at','e.action','e.reason','p.name as actor_name']);
        return view('assistance.client',compact('sessions','notices','business','standing','standingEvents'));
    }
    public function standingAccess(Request $r)
    {
        $bid=$this->business($r);
        $v=$r->validate(['decision'=>'required|in:grant,revoke','acknowledge'=>'required_if:decision,grant|accepted']);
        DB::transaction(function () use ($bid,$v) {
            $row=DB::table('client_support_authorizations')->where('business_id',$bid)->lockForUpdate()->first();
            $grant=$v['decision']==='grant';
            if ($row && $grant && $row->granted_at && !$row->revoked_at) return;
            if (!$grant && (!$row || $row->revoked_at)) return;
            $values=['granted_by'=>$grant?Auth::guard('web')->id():($row->granted_by??null),
                'granted_at'=>$grant?now('UTC'):($row->granted_at??null),
                'revoked_by'=>$grant?null:Auth::guard('web')->id(),
                'revoked_at'=>$grant?null:now('UTC'),'updated_at'=>now('UTC')];
            if (!$row) {
                if (!$grant) return;
                DB::table('client_support_authorizations')->insert(array_merge(['business_id'=>$bid,'created_at'=>now('UTC')],$values));
            } else DB::table('client_support_authorizations')->where('id',$row->id)->update($values);
            DB::table('client_assistance_notices')->insert(['business_id'=>$bid,
                'subject'=>$grant?'Ongoing support enabled':'Ongoing support revoked',
                'body'=>$grant?'Your business administrator enabled MentoClock ongoing setup support. You can revoke it here at any time.':'Your business administrator revoked ongoing setup support.',
                'created_at'=>now('UTC')]);
            DB::table('client_support_authorization_history')->insert([
                'business_id'=>$bid,'actor_user_id'=>Auth::guard('web')->id(),
                'action'=>$grant?'grant':'revoke','created_at'=>now('UTC')]);
        });
        return back()->with('status',$v['decision']==='grant'?'Ongoing support access enabled.':'Ongoing support access revoked.');
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
        $pendingCount = DB::table('client_assistance_sessions')->where('status','pending')->count();
        $businesses = DB::table('businesses as b')->leftJoin('client_support_authorizations as a','a.business_id','=','b.id')
            ->where('b.active',true)->orderBy('b.name')
            ->get(['b.id','b.name','a.granted_at','a.revoked_at']);
        return view('assistance.platform',compact('sessions','operators','pendingCount','businesses'));
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
            if ($row->status==='active' && (int)$row->operator_id===$operatorId && $row->expires_at && now('UTC')->lt(\Carbon\Carbon::parse($row->expires_at))) return;
            if ($row->status!=='pending') throw ValidationException::withMessages(['session'=>'This request is no longer pending. Refresh the page to see its latest status.']);
            abort_unless(DB::table('businesses')->where('id',$row->business_id)->where('active',true)->exists(),422);
            DB::table('client_assistance_sessions')->where('id',$session)->update(['status'=>'active','approved_by'=>$row->requested_by,'operator_id'=>$operatorId,'expires_at'=>now('UTC')->addHours((int)$v['hours']),'updated_at'=>now('UTC')]);
            DB::table('platform_audit_logs')->insert(['actor_id'=>Auth::guard('platform')->id(),'business_id'=>$row->business_id,'action'=>'Assistance session activated','details'=>json_encode(['session_id'=>$session,'operator_id'=>$operatorId,'hours'=>(int)$v['hours']]),'created_at'=>now('UTC')]);
            DB::table('client_assistance_notices')->insert(['business_id'=>$row->business_id,'subject'=>'Assistance session activated','body'=>'Your requested assistance session #'.$session.' is active for '.(int)$v['hours'].' hour(s). You may revoke it from Request support.','created_at'=>now('UTC')]);
        });
        return redirect('/platform/assist/sessions/'.$session)->with('status','Client assistance activated.');
    }
    public function manage(int $business) { return $this->renderAssistance($business,true); }
    public function session(int $session) { return $this->renderAssistance($session,false); }
    private function renderAssistance(int $id,bool $standing)
    {
        $s=$this->assistedScope($id,$standing);$bid=(int)$s->business_id;
        $business=DB::table('businesses')->where('id',$bid)->first();
        abort_unless($business, 404, 'Business not found.');
        if ($standing) {
            DB::table('client_standing_support_events')->insert([
                'authorization_id'=>$s->authorization_id,'business_id'=>$bid,
                'actor_id'=>Auth::guard('platform')->id(), 'action'=>'support_workspace_opened',
                'reason'=>'Opened client support workspace', 'created_at'=>now('UTC')]);
        }
        $branches=DB::table('branches')->where('business_id',$bid)->orderBy('name')->get();
        $employees=User::where('business_id',$bid)->where('role','employee')->orderBy('name')->paginate(25);
        $events = $standing
            ? DB::table('client_standing_support_events')->where('business_id',$bid)->orderByDesc('id')->limit(50)->get()
            : DB::table('client_assistance_events')->where('session_id',$id)->orderByDesc('id')->limit(50)->get();
        $basePath=$standing?'/platform/assist/manage/'.$bid:'/platform/assist/sessions/'.$id;
        return view('assistance.session',compact('s','business','branches','employees','events','basePath'));
    }
    public function standingBranch(Request $r,int $business) { return $this->createBranch($r,$business); }
    public function standingEmployee(Request $r,int $business) { return $this->createEmployee($r,$business); }
    public function standingAssign(Request $r,int $business,int $employee) { return $this->assign($r,$business,$employee); }
    public function createBranch(Request $r,int $session)
    {
        $s=$this->assistedScope($session, $r->is('platform/assist/manage/*'));$bid=(int)$s->business_id;
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
        $s=$this->assistedScope($session, $r->is('platform/assist/manage/*'));$bid=(int)$s->business_id;
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
        $s=$this->assistedScope($session, $r->is('platform/assist/manage/*'));$bid=(int)$s->business_id;
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

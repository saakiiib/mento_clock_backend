<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class PlatformAttendanceMonitorController {
 public function index(Request $r){
  $q=DB::table('attendance_attempt_events as e')->leftJoin('users as u','u.id','=','e.user_id')
   ->leftJoin('businesses as b','b.id','=','e.business_id')->leftJoin('branches as br','br.id','=','e.branch_id')
   ->select('e.*','u.name as employee_name','b.name as business_name','br.name as branch_name');
  if($r->filled('business_id')) $q->where('e.business_id',(int)$r->input('business_id'));
  if($r->filled('outcome') && in_array($r->input('outcome'),['accepted','rejected','error'])) $q->where('e.outcome',$r->input('outcome'));
  if($r->filled('employee_id')) $q->where('e.user_id',(int)$r->input('employee_id'));
  if($r->filled('request_id') && preg_match('/^[0-9a-f-]{36}$/i',(string)$r->input('request_id'))) $q->where('e.request_id',$r->input('request_id'));
  if($r->filled('action') && in_array($r->input('action'),['clock_in','clock_out'],true)) $q->where('e.action',$r->input('action'));
  if($r->filled('reason_code')) $q->where('e.reason_code',substr((string)$r->input('reason_code'),0,80));
  $events=$q->orderByDesc('e.id')->paginate(40)->withQueryString();
  $since=now('UTC')->subHours(24);
  $statsQuery=DB::table('attendance_attempt_events')->where('created_at','>=',$since);
  if($r->filled('business_id')) $statsQuery->where('business_id',(int)$r->input('business_id'));
  $stats=$statsQuery->selectRaw('outcome, count(*) as total')->groupBy('outcome')->pluck('total','outcome');
  // Read-only reconciliation: unusual open shifts, never automatically close or edit them.
  $longOpenQuery=DB::table('attendance_records as ar')->join('users as u','u.id','=','ar.user_id')
   ->join('businesses as b','b.id','=','ar.business_id')->leftJoin('branches as br','br.id','=','ar.branch_id')
   ->whereNull('ar.clock_out')->where('ar.clock_in','<',now('UTC')->subHours(16))
   ->select('ar.id','ar.business_id','ar.clock_in','u.name as employee_name','b.name as business_name','br.name as branch_name');
  if($r->filled('business_id')) $longOpenQuery->where('ar.business_id',(int)$r->input('business_id'));
  $longOpen=$longOpenQuery->orderBy('ar.clock_in')->limit(30)->get();
  $failedByReasonQuery=DB::table('attendance_attempt_events')->where('created_at','>=',$since)->whereIn('outcome',['rejected','error']);
  if($r->filled('business_id')) $failedByReasonQuery->where('business_id',(int)$r->input('business_id'));
  $failureReasons=$failedByReasonQuery->selectRaw('reason_code, count(*) as total')->groupBy('reason_code')->orderByDesc('total')->limit(8)->get();
  $alerts=DB::table('attendance_incident_alerts as a')->leftJoin('businesses as b','b.id','=','a.business_id')->select('a.*','b.name as business_name')->whereNull('a.resolved_at')->orderByDesc('a.id')->limit(20)->get();
  $diagnostics=DB::table('employee_diagnostic_reports as d')->join('users as u','u.id','=','d.user_id')->join('businesses as b','b.id','=','d.business_id')->select('d.*','u.name as employee_name','b.name as business_name')->orderByDesc('d.id')->limit(20)->get();
  $audits=DB::table('audit_logs as a')->leftJoin('users as u','u.id','=','a.actor_id')->leftJoin('businesses as b','b.id','=','a.business_id')->select('a.id','a.business_id','a.attendance_id','a.action','a.reason','a.created_at','u.name as actor_name','b.name as business_name')->whereNotNull('a.attendance_id')->orderByDesc('a.id')->limit(30)->get();
  $businesses=DB::table('businesses')->orderBy('name')->get(['id','name']);
  return view('platform.attendance-monitor',compact('events','stats','alerts','diagnostics','audits','businesses','longOpen','failureReasons'));
 }
 public function resolve(Request $r,int $id){
  DB::table('attendance_incident_alerts')->where('id',$id)->whereNull('resolved_at')->update(['resolved_at'=>now('UTC'),'updated_at'=>now('UTC')]);
  return redirect('/platform/attendance-monitor')->with('status','Incident acknowledged.');
 }
 public function health(Request $r){
  $db='ok';try{DB::select('SELECT 1');}catch(\Throwable $e){$db='error';}
  return response()->json(['status'=>$db==='ok'?'ok':'degraded','database'=>$db,'checked_at'=>now('UTC')->toIso8601String()],$db==='ok'?200:503);
 }
}

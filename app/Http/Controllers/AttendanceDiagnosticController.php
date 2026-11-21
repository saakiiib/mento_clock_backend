<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class AttendanceDiagnosticController {
 public function state(Request $request){
  $u=$request->user();
  $active=DB::table('attendance_records')->where('business_id',$u->business_id)->where('user_id',$u->id)->whereNull('clock_out')->orderByDesc('clock_in')->first();
  return response()->json(['server_time'=>now('UTC')->toIso8601String(), 'is_clocked_in'=>(bool)$active,
    'active_attendance'=>$active?['id'=>(int)$active->id,'branch_id'=>(int)$active->branch_id,'clock_in'=>\Carbon\Carbon::parse($active->clock_in,'UTC')->toIso8601String()]:null,
    'state_authority'=>'server']);
 }
 public function report(Request $request){
  $v=$request->validate(['issue_type'=>'required|in:clock_in_failed,clock_out_failed,state_mismatch,missing_record,other',
    'client_state'=>'nullable|in:clocked_in,clocked_out,unknown','client_attendance_id'=>'nullable|integer|min:1',
    'description'=>'nullable|string|max:1000','app_version'=>'nullable|string|max:60','platform'=>'nullable|in:ios,android,web,other']);
  $u=$request->user();$active=DB::table('attendance_records')->where('business_id',$u->business_id)->where('user_id',$u->id)->whereNull('clock_out')->orderByDesc('clock_in')->first();
  $stateMismatch=isset($v['client_state']) && in_array($v['client_state'],['clocked_in','clocked_out'],true) && (($v['client_state']==='clocked_in')!==((bool)$active));
  $idMismatch=isset($v['client_attendance_id']) && $active && (int)$v['client_attendance_id']!==(int)$active->id;
  $mismatch=(bool)($stateMismatch || $idMismatch);
  $id=(string)Str::uuid();
  DB::table('employee_diagnostic_reports')->insert(['business_id'=>$u->business_id,'user_id'=>$u->id,'request_id'=>$id,
   'issue_type'=>$v['issue_type'],'client_state'=>$v['client_state']??null,'client_attendance_id'=>$v['client_attendance_id']??null,
   'server_attendance_id'=>$active?->id,'state_mismatch'=>$mismatch,'description'=>$v['description']??null,
   'app_version'=>$v['app_version']??null,'platform'=>$v['platform']??null,'created_at'=>now('UTC')]);
  return response()->json(['report_id'=>$id,'server_is_clocked_in'=>(bool)$active,'state_mismatch'=>$mismatch,
   'message'=>'Diagnostic report received. Your attendance record has not been changed.'],201);
 }
}

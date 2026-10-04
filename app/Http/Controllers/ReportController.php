<?php
namespace App\Http\Controllers;
use App\Services\AttendanceReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Validation\Rule;
class ReportController {
 public function __construct(private AttendanceReport $reports){}
 private function filters(Request $r): array {
  $business=$r->user()->business_id;
  return $r->validate(['period'=>'nullable|in:daily,weekly,monthly','date'=>'nullable|date_format:Y-m-d','employee_id'=>['nullable','integer',Rule::exists('users','id')->where('business_id',$business)],'branch_id'=>['nullable','integer',Rule::exists('branches','id')->where('business_id',$business)]]);
 }
 public function employee(Request $r){return response()->json($this->reports->make($r->user()->business_id,$this->filters($r),$r->user()->id));}
 public function index(Request $r){
  $filters=$this->filters($r);$report=$this->reports->make($r->user()->business_id,$filters);
  return view('reports.index',['report'=>$report,'filters'=>$filters,'employees'=>DB::table('users')->where('business_id',$r->user()->business_id)->get(),'branches'=>DB::table('branches')->where('business_id',$r->user()->business_id)->get()]);
 }
 public function download(Request $r){
  $report=$this->reports->make($r->user()->business_id,$this->filters($r));$r->validate(['format'=>'nullable|in:csv,pdf']);
  if($r->query('format')==='pdf')return Pdf::loadView('reports.pdf',compact('report'))->setPaper('a4','landscape')->download('mentoclock-'.$report['start'].'.pdf');
  return response($this->reports->csv($report),200,['Content-Type'=>'text/csv; charset=UTF-8','Content-Disposition'=>'attachment; filename="mentoclock-'.$report['start'].'.csv"']);
 }
 public function email(Request $r){
  $report=$this->reports->make($r->user()->business_id,$this->filters($r));
  // Reports are sent only to the signed-in administrator's account email address.
  // No arbitrary recipient field avoids accidental cross-business disclosure.
  $email=$r->user()->email;$pdf=Pdf::loadView('reports.pdf',compact('report'))->setPaper('a4','landscape')->output();$csv=$this->reports->csv($report);
  Mail::send('reports.email',compact('report'),function($m)use($email,$pdf,$csv,$report){$m->to($email)->subject('MentoClock attendance report · '.$report['start'].' to '.$report['end'])->attachData($pdf,'attendance.pdf',['mime'=>'application/pdf'])->attachData($csv,'attendance.csv',['mime'=>'text/csv']);});
  return back()->with('status','Report sent to your account email address.');
 }
}

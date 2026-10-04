<?php
namespace App\Services;
use Carbon\CarbonImmutable as C;
use Illuminate\Support\Facades\DB;
class AttendanceReport {
 public function make(int $businessId,array $filters,?int $forcedUser=null): array {
  $business=DB::table('businesses')->find($businessId);$tz=$business->timezone;
  $anchor=C::parse($filters['date']??C::now($tz)->toDateString(),$tz)->startOfDay();$period=$filters['period']??'daily';
  $start=match($period){'weekly'=>$anchor->startOfWeek(C::MONDAY),'monthly'=>$anchor->startOfMonth(),default=>$anchor};
  $end=match($period){'weekly'=>$start->addWeek(),'monthly'=>$start->addMonth(),default=>$start->addDay()};
  $user=$forcedUser??($filters['employee_id']??null);$branch=$filters['branch_id']??null;
  $q=DB::table('attendance_records as a')->join('users as u','u.id','=','a.user_id')->join('branches as b','b.id','=','a.branch_id')->where('a.business_id',$businessId)->where('a.clock_in','<',$end->utc())->where(function($q)use($start){$q->whereNull('a.clock_out')->orWhere('a.clock_out','>',$start->utc());});
  if($user)$q->where('a.user_id',$user);if($branch)$q->where('a.branch_id',$branch);
  $raw=$q->orderBy('a.clock_in')->select('a.*','u.name as employee','u.employee_code','b.name as branch')->get();$days=[];$total=0;$active=0;$records=[];$now=C::now('UTC');
  foreach($raw as $row){
   $in=C::parse($row->clock_in,'UTC');$out=$row->clock_out?C::parse($row->clock_out,'UTC'):null;
   $from=$in->max($start->utc());$to=($out??$now)->min($end->utc())->min($now);
   if($to->lessThanOrEqualTo($from))continue;
   $seconds=(int)$from->diffInSeconds($to);$total+=$seconds;if(!$out)$active++;
   $records[]=['id'=>$row->id,'employee'=>$row->employee,'employee_code'=>$row->employee_code,'branch'=>$row->branch,'clock_in'=>$in->tz($tz)->toIso8601String(),'clock_out'=>$out?->tz($tz)->toIso8601String(),'seconds'=>$seconds,'hours'=>round($seconds/3600,2),'status'=>$out?'Completed':'Active'];
   $cursor=$from;
   while($cursor->lessThan($to)){
    $key=$cursor->tz($tz)->toDateString();$boundary=$cursor->tz($tz)->startOfDay()->addDay()->utc()->min($to);$part=(int)$cursor->diffInSeconds($boundary);
    $days[$key]=($days[$key]??0)+$part;$cursor=$boundary;
   }
  }
  $daily=[];for($d=$start;$d->lessThan($end);$d=$d->addDay()){$key=$d->toDateString();$daily[]=['date'=>$key,'seconds'=>$days[$key]??0,'hours'=>round(($days[$key]??0)/3600,2)];}
  return ['business'=>$business->name,'timezone'=>$tz,'period'=>$period,'start'=>$start->toDateString(),'end'=>$end->subDay()->toDateString(),'total_seconds'=>$total,'total_hours'=>round($total/3600,2),'active_records'=>$active,'record_count'=>count($records),'daily'=>$daily,'records'=>$records];
 }
 public function csv(array $report): string {
  $f=fopen('php://temp','r+');$safe=fn($v)=>preg_match('/^[\s]*[=+@\-]/u',(string)$v)?"'".$v:$v;fputcsv($f,array_map($safe,['Business',$report['business'],'Timezone',$report['timezone'],'Period',$report['start'].' to '.$report['end']]));
  fputcsv($f,['Employee','Employee code','Branch','Clock in','Clock out','Hours in period','Status']);
  foreach($report['records'] as $r){$values=[$r['employee'],$r['employee_code']??'',$r['branch'],$r['clock_in'],$r['clock_out']??'',(string)$r['hours'],$r['status']];fputcsv($f,array_map(fn($v)=>preg_match('/^[\s]*[=+@\-]/u',(string)$v)?"'".$v:$v,$values));}
  rewind($f);$csv=stream_get_contents($f);fclose($f);return $csv;
 }
}

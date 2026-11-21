<?php
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::call(fn () => DB::table('marketing_site_enquiries')->where('created_at', '<', now('UTC')->subDays(90))->delete())->daily();
Artisan::command('hr:generate-reminders', function () {
    $created = app(\App\Services\HrReminderService::class)->run();
    $this->info($created.' new HR reminder(s) generated.');
})->purpose('Generate tenant-scoped HR reminders and send configured email digests.');
Schedule::command('hr:generate-reminders')->dailyAt('07:00')->timezone('Europe/London')->name('hr:generate-reminders')->withoutOverlapping();
Schedule::call(fn () => app(\App\Services\EmployeeLifecycle::class)->processDueOffboarding())
    ->name('mentoclock.process-due-offboarding')
    ->everyFifteenMinutes()->timezone('UTC')->withoutOverlapping();

// V37 monitoring only; never changes attendance records.
Artisan::command('attendance:detect-incidents',function(){
 $window=now('UTC')->subMinutes(5);
 $groups=DB::table('attendance_attempt_events')->where('created_at','>=',$window)->whereIn('outcome',['rejected','error'])->whereNotNull('business_id')
  ->selectRaw('business_id,branch_id,count(*) as failures')->groupBy('business_id','branch_id')->havingRaw('count(*) >= 3')->get();
 $count=0;
 foreach($groups as $g){
  $exists=DB::table('attendance_incident_alerts')->where('business_id',$g->business_id)->where('code','repeated_failures')
    ->where(function($q)use($g){$g->branch_id===null?$q->whereNull('branch_id'):$q->where('branch_id',$g->branch_id);})
    ->whereNull('resolved_at')->where('created_at','>=',now('UTC')->subHour())->exists();
  if(!$exists){DB::table('attendance_incident_alerts')->insert(['business_id'=>$g->business_id,'branch_id'=>$g->branch_id,
   'code'=>'repeated_failures','severity'=>'warning','message'=>'Repeated rejected or failed attendance requests in a five-minute window.',
   'event_count'=>$g->failures,'window_started_at'=>$window,'created_at'=>now('UTC'),'updated_at'=>now('UTC')]);$count++;}
 }
 // Escalate repeated mobile/server state mismatches from employee reports.
 $recent=now('UTC')->subMinutes(15);
 $mismatches=DB::table('employee_diagnostic_reports')->where('created_at','>=',$recent)->where('state_mismatch',true)
  ->selectRaw('business_id,count(*) as total')->groupBy('business_id')->havingRaw('count(*) >= 2')->get();
 foreach($mismatches as $m){
  $existing=DB::table('attendance_incident_alerts')->where('business_id',$m->business_id)->where('code','state_mismatch')
   ->whereNull('resolved_at')->exists();
  if(!$existing){DB::table('attendance_incident_alerts')->insert(['business_id'=>$m->business_id,'branch_id'=>null,
   'code'=>'state_mismatch','severity'=>'warning','message'=>'Repeated mobile/server attendance state mismatches reported within 15 minutes.',
   'event_count'=>$m->total,'window_started_at'=>$recent,'created_at'=>now('UTC'),'updated_at'=>now('UTC')]);$count++;}
 }
 $this->info($count.' new attendance alerts.');
})->purpose('Detect repeated attendance failures without changing shifts.');
Schedule::command('attendance:detect-incidents')->everyFiveMinutes()->withoutOverlapping();
Schedule::call(function(){DB::table('attendance_attempt_events')->where('created_at','<',now('UTC')->subDays(90))->delete();
 DB::table('employee_diagnostic_reports')->where('created_at','<',now('UTC')->subDays(90))->delete();
 DB::table('attendance_incident_alerts')->whereNotNull('resolved_at')->where('created_at','<',now('UTC')->subDays(180))->delete();
})->daily()->name('attendance:prune-monitoring')->withoutOverlapping();

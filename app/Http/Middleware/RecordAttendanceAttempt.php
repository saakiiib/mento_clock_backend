<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
class RecordAttendanceAttempt {
 public function handle(Request $request, Closure $next): Response {
  $id=(string)Str::uuid();$started=microtime(true);$request->attributes->set('attendance_request_id',$id);
  try {
   $response=$next($request);
  } catch (\Throwable $e) {
   // Capture failures including validation and exceptions without changing their handling.
   $this->record($request,$id,$started,$e instanceof \Illuminate\Validation\ValidationException ? 422 : ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface ? $e->getStatusCode():500),null,$e);
   throw $e;
  }
  $response->headers->set('X-Attendance-Request-ID',$id);
  $this->record($request,$id,$started,$response->getStatusCode(),$response,null);
  return $response;
 }
 private function record(Request $r,string $id,float $started,int $status,?Response $response,?\Throwable $exception):void {
  try {
   $user=$r->user();$data=$response && method_exists($response,'getData') ? (array)$response->getData(true) : [];
   $errors=$data['errors']??[];
   $code=isset($data['code']) && is_string($data['code']) ? $data['code'] : null;
   if(!$code && $status===422 && is_array($errors)) $code='validation_'.(array_key_first($errors)?:'error');
   if(!$code && $status===409) $code='conflict';
   if(!$code && $status===403) $code='not_authorized';
   if(!$code && $status>=500) $code='server_error';
   if(!$code && $status>=400) $code='http_'.$status;
   $branchId=filter_var($r->input('branch_id'),FILTER_VALIDATE_INT)?:null;
   if(!$branchId && $r->route('id')){
    $branchId=DB::table('attendance_records')->where('id',$r->route('id'))->where('user_id',$user?->id)->value('branch_id');
   }
   $action=$r->is('*/clock-in')?'clock_in':'clock_out';
   $attendanceId=$action==='clock_out' ? (filter_var($r->route('id'),FILTER_VALIDATE_INT)?:null) : ($status<300 ? ($data['id']??null):null);
   DB::table('attendance_attempt_events')->insert([
    'request_id'=>$id,'business_id'=>$user?->business_id,'user_id'=>$user?->id,'branch_id'=>$branchId,
    'attendance_id'=>is_numeric($attendanceId)?(int)$attendanceId:null,'action'=>$action,
    'outcome'=>$status<300?'accepted':($status<500?'rejected':'error'),
    'reason_code'=>substr((string)($code ?:($exception?'exception':'accepted')),0,80),
    'http_status'=>$status,'duration_ms'=>min(4294967295,(int)((microtime(true)-$started)*1000)),
    'app_version'=>substr((string)$r->header('X-App-Version',''),0,60)?:null,
    'device_platform'=>substr((string)$r->header('X-Device-Platform',''),0,30)?:null,
    'gps_accuracy_m'=>is_numeric($r->input('accuracy_m'))?max(0,min(999999,(float)$r->input('accuracy_m'))):null,
    'location_supplied'=>$r->filled('latitude')&&$r->filled('longitude'),
    'details'=>json_encode(['route'=>$action,'exception_type'=>$exception?class_basename($exception):null]),
    'created_at'=>now('UTC'),
   ]);
  }catch(\Throwable $e){Log::error('Attendance attempt monitoring failed',['request_id'=>$id,'exception'=>get_class($e)]);}
 }
}

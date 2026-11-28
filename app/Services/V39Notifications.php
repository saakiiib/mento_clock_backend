<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
class V39Notifications {
 public static function audit(?int $messageId,string $guard,int $actorId,?int $businessId,string $event,array $details=[]): void {
  DB::table('v39_audit_events')->insert(['message_id'=>$messageId,'actor_guard'=>$guard,'actor_id'=>$actorId,'business_id'=>$businessId,'event'=>$event,'details'=>json_encode($details),'created_at'=>now('UTC')]);
 }
 public static function publish(int $messageId): int {
  return DB::transaction(function() use($messageId){
   $m=DB::table('v39_messages')->where('id',$messageId)->lockForUpdate()->first();
   if(!$m || $m->status!=='draft') return 0;
   if($m->scheduled_at && now('UTC')->lt(\Carbon\Carbon::parse($m->scheduled_at)))return 0;
   $q=DB::table('users as u')->join('businesses as b','b.id','=','u.business_id')->where('u.active',true)->where('u.role','employee')->where('b.active',true);
   if($m->business_id) $q->where('u.business_id',$m->business_id);
   if($m->target_type==='branch') {
    $branch=DB::table('v39_audit_events')->where('message_id',$m->id)->where('event','target')->value('details');
    $branchId=(int)(json_decode($branch??'{}',true)['branch_id']??0);
    if(!$branchId) throw ValidationException::withMessages(['target'=>'Missing workplace selection.']);
    $q->whereExists(function($sub) use($branchId){$sub->selectRaw('1')->from('employee_branches as eb')->whereColumn('eb.user_id','u.id')->where('eb.branch_id',$branchId);});
   } elseif($m->target_type==='employee') {
    $target=DB::table('v39_audit_events')->where('message_id',$m->id)->where('event','target')->value('details');
    $id=(int)(json_decode($target??'{}',true)['employee_id']??0);
    if(!$id)throw ValidationException::withMessages(['target'=>'Missing employee selection.']);
    $q->where('u.id',$id);
   }
   $count=0; $at=now('UTC');
   $q->select('u.id','u.business_id')->orderBy('u.id')->chunk(200,function($people)use($m,$at,&$count){
    $rows=[];foreach($people as $p)$rows[]=['message_id'=>$m->id,'user_id'=>$p->id,'business_id'=>$p->business_id,'published_at'=>$at,'created_at'=>$at,'updated_at'=>$at];
    if($rows){DB::table('v39_recipients')->insertOrIgnore($rows);$count+=count($rows);}
   });
   DB::table('v39_messages')->where('id',$m->id)->update(['status'=>'published','published_at'=>$at,'updated_at'=>$at]);
   self::audit($m->id,$m->actor_guard,(int)$m->actor_id,$m->business_id,'published',['recipient_count'=>$count]);
   return $count;
  });
 }
 public static function publishDue(): int {
  $count=0;
  DB::table('v39_messages')->where('status','draft')->whereNotNull('scheduled_at')->where('scheduled_at','<=',now('UTC'))->orderBy('id')->limit(100)->pluck('id')->each(function($id)use(&$count){try{$count+=self::publish((int)$id);}catch(\Throwable $e){Log::warning('V39 scheduled publish failed',['message_id'=>$id,'error'=>$e->getMessage()]);}});
  return $count;
 }
}

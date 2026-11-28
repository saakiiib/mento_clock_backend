<?php
namespace App\Http\Controllers;
use App\Services\V39Notifications;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class V39NotificationController {
 private function scope(Request $r,bool $platform): array {
  if($platform){abort_unless(Auth::guard('platform')->check(),403);abort_if(DB::table('platform_support_operators')->where('admin_id',Auth::guard('platform')->id())->exists(),403,'Only full Super Admin can broadcast messages.');return ['platform',(int)Auth::guard('platform')->id(),null];}
  $user=Auth::guard('web')->user();abort_unless($user && $user->role==='admin' && $user->active && app(\App\Services\WorkspacePermissions::class)->has($user,'workspace.access'),403);
  $business=(int)$r->session()->get('active_business_id',(int)$user->business_id);
  abort_unless(DB::table('businesses')->where('id',$business)->where('active',true)->exists(),403);
  $allowed=$business===(int)$user->business_id || ($user->client_account_id && DB::table('businesses')->where('id',$business)->where('client_account_id',$user->client_account_id)->exists());
  abort_unless($allowed,403);return ['client',(int)$user->id,$business];
 }
 public function platformIndex(Request $r){return $this->index($r,true);}
 public function clientIndex(Request $r){return $this->index($r,false);}
 private function index(Request $r,bool $platform){
  [$guard,$actor,$bid]=$this->scope($r,$platform);
  $messages=DB::table('v39_messages as m')->when($bid,fn($q)=>$q->where('m.business_id',$bid))
   ->when(!$platform,fn($q)=>$q->where('m.actor_guard','client'))
   ->leftJoin('businesses as b','b.id','=','m.business_id')
   ->select('m.*','b.name as business_name')->orderByDesc('m.id')->paginate(15);
  $businesses=$platform?DB::table('businesses')->where('active',true)->orderBy('name')->get(['id','name']):collect();
  $branches=$bid?DB::table('branches')->where('business_id',$bid)->where('active',true)->orderBy('name')->get(['id','name']):collect();
  $employees=$bid?DB::table('users')->where('business_id',$bid)->where('role','employee')->where('active',true)->orderBy('name')->get(['id','name']):collect();
  $feedback=DB::table('v39_feedback as f')->join('users as u','u.id','=','f.user_id')->when($bid,fn($q)=>$q->where('f.business_id',$bid))->orderByDesc('f.id')->limit(25)->get(['f.created_at','f.comment','u.name']);
  $stats=DB::table('v39_recipients')->select('message_id')->selectRaw('count(*) as recipients,sum(case when read_at is not null then 1 else 0 end) as reads,sum(case when acknowledged_at is not null then 1 else 0 end) as acknowledgements,sum(case when completed_at is not null then 1 else 0 end) as completions')->whereIn('message_id',$messages->pluck('id'))->groupBy('message_id')->get()->keyBy('message_id');
  return view('v39.notifications',compact('messages','businesses','branches','employees','stats','platform','bid','feedback'));
 }
 public function platformTargets(Request $r){return $this->targets($r,true);}
 public function clientTargets(Request $r){return $this->targets($r,false);}
 private function targets(Request $r,bool $platform){
  [$guard,$actor,$bid]=$this->scope($r,$platform);
  $requested=$platform?(int)$r->query('business_id',0):$bid;
  if(!$requested)return response()->json(['branches'=>[],'employees'=>[]]);
  if(!$platform)abort_unless($requested===$bid,403);
  abort_unless(DB::table('businesses')->where('id',$requested)->where('active',true)->exists(),404);
  return response()->json(['branches'=>DB::table('branches')->where('business_id',$requested)->where('active',true)->orderBy('name')->get(['id','name']),
   'employees'=>DB::table('users')->where('business_id',$requested)->where('role','employee')->where('active',true)->orderBy('name')->get(['id','name'])]);
 }
 public function platformStore(Request $r){return $this->store($r,true);}
 public function clientStore(Request $r){return $this->store($r,false);}
 private function store(Request $r,bool $platform){
  [$guard,$actor,$bid]=$this->scope($r,$platform);
  $v=$r->validate(['title'=>'required|string|max:160','body'=>'required|string|max:2000','action_type'=>'required|in:none,profile,feedback,review,update,attendance','target_type'=>'required|in:all,business,branch,employee','business_id'=>'nullable|integer','branch_id'=>'nullable|integer','employee_id'=>'nullable|integer','requires_ack'=>'nullable|boolean','scheduled_at'=>'nullable|date|after:now']);
  $target=$v['target_type'];$chosen=$platform?(int)($v['business_id']??0):$bid;
  if(!$platform && $target==='all')$target='business';
  if($platform && $target!=='all')abort_unless($chosen && DB::table('businesses')->where('id',$chosen)->where('active',true)->exists(),422,'Choose a business.');
  if(!$platform)abort_unless($chosen===$bid,403);
  if($target==='branch')abort_unless(DB::table('branches')->where('id',(int)($v['branch_id']??0))->where('business_id',$chosen)->exists(),422,'Invalid workplace for selected business.');
  if($target==='employee')abort_unless(DB::table('users')->where('id',(int)($v['employee_id']??0))->where('business_id',$chosen)->where('role','employee')->exists(),422,'Invalid employee for selected business.');
  if($platform && $target==='all')$chosen=null;
  $mid=DB::transaction(function()use($v,$guard,$actor,$chosen,$target){
   $mid=DB::table('v39_messages')->insertGetId(['business_id'=>$chosen,'actor_guard'=>$guard,'actor_id'=>$actor,'title'=>$v['title'],'body'=>$v['body'],'action_type'=>$v['action_type'],'requires_ack'=>!empty($v['requires_ack']),'target_type'=>$target,'status'=>'draft','scheduled_at'=>$v['scheduled_at']??null,'created_at'=>now('UTC'),'updated_at'=>now('UTC')]);
   V39Notifications::audit($mid,$guard,$actor,$chosen,'created',['target_type'=>$target]);
   V39Notifications::audit($mid,$guard,$actor,$chosen,'target',['branch_id'=>(int)($v['branch_id']??0),'employee_id'=>(int)($v['employee_id']??0)]);
   if(empty($v['scheduled_at']))V39Notifications::publish($mid);
   return $mid;
  });
  return back()->with('status','Announcement saved'.(empty($v['scheduled_at'])?' and published.':' for scheduled delivery.'));
 }
 public function inbox(Request $r){
  $user=$r->user();abort_unless($user && $user->active,403);
  $rows=DB::table('v39_recipients as rec')->join('v39_messages as m','m.id','=','rec.message_id')
   ->where('rec.user_id',$user->id)->where('rec.business_id',$user->business_id)->whereNotNull('rec.published_at')
   ->select('rec.id','rec.read_at','rec.acknowledged_at','rec.completed_at','m.title','m.body','m.action_type','m.requires_ack','rec.published_at')
   ->orderByDesc('rec.id')->paginate(30);
  $unread=DB::table('v39_recipients')->where('user_id',$user->id)->where('business_id',$user->business_id)->whereNull('read_at')->whereNotNull('published_at')->count();
  return response()->json(['data'=>$rows->items(),'unread_count'=>$unread,'next_page_url'=>$rows->nextPageUrl()]);
 }
 public function read(Request $r,int $id){return $this->mark($r,$id,'read_at');}
 public function acknowledge(Request $r,int $id){return $this->mark($r,$id,'acknowledged_at');}
 public function complete(Request $r,int $id){return $this->mark($r,$id,'completed_at');}
 private function mark(Request $r,int $id,string $field){
  $user=$r->user();abort_unless($user && $user->active,403);
  $rec=DB::table('v39_recipients as rec')->join('v39_messages as m','m.id','=','rec.message_id')->where('rec.id',$id)->where('rec.user_id',$user->id)->where('rec.business_id',$user->business_id)->whereNotNull('rec.published_at')->first(['rec.id','rec.message_id','m.requires_ack','m.action_type']);
  abort_unless($rec,404);
  if($field==='completed_at')abort_unless(in_array($rec->action_type,['profile','feedback','update','attendance'],true),422,'This message has no completion action.');
  if($field==='acknowledged_at')abort_unless($rec->requires_ack,422,'Acknowledgement is not required.');
  $data=[$field=>now('UTC'),'updated_at'=>now('UTC')];if($field!=='read_at')$data['read_at']=now('UTC');
  DB::table('v39_recipients')->where('id',$rec->id)->whereNull($field)->update($data);
  return response()->json(['ok'=>true]);
 }
 public function feedback(Request $r) {
  $v=$r->validate(['comment'=>'required|string|min:5|max:1500','recipient_id'=>'nullable|integer']);
  $u=$r->user();abort_unless($u && $u->active,403);
  $rid=!empty($v['recipient_id'])?(int)$v['recipient_id']:null;
  if($rid)abort_unless(DB::table('v39_recipients')->where('id',$rid)->where('user_id',$u->id)->where('business_id',$u->business_id)->exists(),403);
  DB::transaction(function()use($v,$u,$rid){
   DB::table('v39_feedback')->insert(['user_id'=>$u->id,'business_id'=>$u->business_id,'recipient_id'=>$rid,'comment'=>$v['comment'],'created_at'=>now('UTC'),'updated_at'=>now('UTC')]);
   if($rid)DB::table('v39_recipients')->where('id',$rid)->where('user_id',$u->id)->update(['read_at'=>now('UTC'),'completed_at'=>now('UTC'),'updated_at'=>now('UTC')]);
  });
  return response()->json(['ok'=>true],201);
 }
 public function device(Request $r){
  $v=$r->validate(['token'=>'required|string|min:30|max:4096','platform'=>'required|in:ios,android']);$u=$r->user();
  DB::table('v39_device_tokens')->updateOrInsert(['token_hash'=>hash('sha256',$v['token'])],['user_id'=>$u->id,'platform'=>$v['platform'],'token_encrypted'=>Crypt::encryptString($v['token']),'last_seen_at'=>now('UTC'),'updated_at'=>now('UTC')]);
  return response()->json(['ok'=>true]);
 }
 public function removeDevice(Request $r){$v=$r->validate(['token'=>'required|string|min:30|max:4096']);DB::table('v39_device_tokens')->where('user_id',$r->user()->id)->where('token_hash',hash('sha256',$v['token']))->delete();return response()->json(['ok'=>true]);}
}

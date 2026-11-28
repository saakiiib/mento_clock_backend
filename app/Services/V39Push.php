<?php
namespace App\Services;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Optional opt-in FCM HTTP v1 sender. Uses service-account file outside webroot. */
class V39Push {
 private static function credentials(): ?array {
  if(!config('v39.push_enabled'))return null;
  $path=config('v39.service_account_path');
  if(!$path || !is_file($path) || is_link($path))return null;
  $json=json_decode((string)file_get_contents($path),true);
  return is_array($json) && !empty($json['private_key']) && !empty($json['client_email']) && !empty($json['project_id'])?$json:null;
 }
 private static function accessToken(array $c): string {
  return Cache::remember('v39_fcm_access_'.hash('sha256',$c['client_email']),3300,function()use($c){
   $encode=static fn($data)=>rtrim(strtr(base64_encode(json_encode($data,JSON_UNESCAPED_SLASHES)),'+/','-_'),'=');
   $now=time();$head=$encode(['alg'=>'RS256','typ'=>'JWT']);
   $body=$encode(['iss'=>$c['client_email'],'scope'=>'https://www.googleapis.com/auth/firebase.messaging','aud'=>'https://oauth2.googleapis.com/token','iat'=>$now,'exp'=>$now+3600]);
   if(!openssl_sign($head.'.'.$body,$sig,$c['private_key'],OPENSSL_ALGO_SHA256))throw new \RuntimeException('FCM signing failed.');
   $jwt=$head.'.'.$body.'.'.rtrim(strtr(base64_encode($sig),'+/','-_'),'=');
   $res=Http::asForm()->timeout(12)->post('https://oauth2.googleapis.com/token',['grant_type'=>'urn:ietf:params:oauth:grant-type:jwt-bearer','assertion'=>$jwt]);
   if(!$res->successful() || !$res->json('access_token'))throw new \RuntimeException('FCM OAuth token request failed.');
   return $res->json('access_token');
  });
 }
 public static function sendPending(int $limit=100): int {
  $c=self::credentials();if(!$c)return 0;
  $sent=0;$token=self::accessToken($c);
  $items=DB::table('v39_recipients as r')->join('v39_messages as m','m.id','=','r.message_id')
   ->where('r.push_status','not_configured')->whereNotNull('r.published_at')->select('r.id','r.user_id','m.title','m.body','m.id as message_id')
   ->orderBy('r.id')->limit(min($limit,500))->get();
  foreach($items as $item){
   $devices=DB::table('v39_device_tokens')->where('user_id',$item->user_id)->get();
   if($devices->isEmpty())continue; // Retry when a device first registers.
   $any=false;$failed=false;
   foreach($devices as $device){
    try {
     $body=['message'=>['token'=>Crypt::decryptString($device->token_encrypted),'notification'=>['title'=>$item->title,'body'=>mb_strimwidth($item->body,0,180,'…')], 'data'=>['type'=>'v39_notification','message_id'=>(string)$item->message_id]]];
     $res=Http::withToken($token)->timeout(10)->post('https://fcm.googleapis.com/v1/projects/'.rawurlencode($c['project_id']).'/messages:send',$body);
     if($res->successful()){$any=true;continue;}
     $failed=true;
     if(in_array($res->status(),[400,404,410],true) && (str_contains($res->body(),'UNREGISTERED')||str_contains($res->body(),'INVALID_ARGUMENT'))){DB::table('v39_device_tokens')->where('id',$device->id)->delete();}
    }catch(\Throwable $e){$failed=true;Log::warning('V39 push delivery error',['recipient_id'=>$item->id,'exception'=>get_class($e)]);}
   }
   DB::table('v39_recipients')->where('id',$item->id)->update(['push_status'=>$any?($failed?'partial':'sent'):'failed','push_attempts'=>DB::raw('push_attempts + 1'),'push_sent_at'=>$any?now('UTC'):null,'push_error'=>$failed?'Push gateway rejected at least one device':null,'updated_at'=>now('UTC')]);
   if($any)$sent++;
  }
  return $sent;
 }
}

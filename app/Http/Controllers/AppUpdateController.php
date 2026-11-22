<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
class AppUpdateController {
 public function show(Request $r){
  $v=$r->validate(['platform'=>'required|in:android,ios','version'=>'nullable|string|max:40','build'=>'nullable|integer|min:0']);
  $release=config('app_update.'.$v['platform'],[]);
  $latestVersion=(string)($release['version']??'');
  $latestBuild=(int)($release['build']??0);
  $currentVersion=(string)($v['version']??'');
  $currentBuild=(int)($v['build']??0);
  $updateAvailable=false;
  if($latestBuild>0 && $currentBuild>0){$updateAvailable=$latestBuild>$currentBuild;}
  elseif($latestVersion!=='' && $currentVersion!==''){$updateAvailable=$this->isNewer($latestVersion,$currentVersion);}
  $required=$updateAvailable && filter_var(config('app_update.required',false),FILTER_VALIDATE_BOOLEAN);
  $message=trim((string)config('app_update.message',''));
  if($message===''){
   $message=$updateAvailable
    ? ($latestVersion!=='' ? "MentoClock {$latestVersion} is now available. Update to get the latest improvements and fixes." : 'A new version of MentoClock is now available.')
    : 'You are using the latest version of MentoClock.';
  }
  return response()->json([
   'update_available'=>$updateAvailable,
   'update_required'=>$required,
   'latest_version'=>$latestVersion,
   'latest_build'=>$latestBuild,
   'store_url'=>(string)($release['store_url']??''),
   'message'=>$message,
   'remind_later_hours'=>(int)config('app_update.remind_later_hours',24),
  ])->header('Cache-Control','no-store');
 }
 private function isNewer(string $latest,string $current): bool {
  $a=array_map('intval',explode('.',$latest));
  $b=array_map('intval',explode('.',$current));
  for($i=0;$i<max(count($a),count($b));$i++){
   $x=$a[$i]??0;$y=$b[$i]??0;
   if($x!==$y)return $x>$y;
  }
  return false;
 }
}

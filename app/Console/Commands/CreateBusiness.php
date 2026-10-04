<?php
namespace App\Console\Commands;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
class CreateBusiness extends Command {
 protected $signature='mento:business';
 protected $description='Create a business and its administrator in the separate MentoClock database';
 public function handle(): int {
  $name=$this->ask('Business name');$timezone=$this->ask('Business timezone','Europe/London');$admin=$this->ask('Administrator name');$email=$this->ask('Administrator email');$password=$this->secret('Administrator password (12+ characters)');
  $v=Validator::make(compact('name','admin','email','password','timezone'),['timezone'=>'required|timezone','name'=>'required|string|max:100','admin'=>'required|string|max:100','email'=>'required|email|max:255|unique:users,email','password'=>'required|string|min:12|max:100']);
  if($v->fails()){foreach($v->errors()->all()as $e)$this->error($e);return self::FAILURE;}
  DB::transaction(function()use($name,$admin,$email,$password,$timezone){$id=DB::table('businesses')->insertGetId(['name'=>$name,'timezone'=>$timezone,'active'=>true,'created_at'=>now(),'updated_at'=>now()]);User::create(['business_id'=>$id,'name'=>$admin,'email'=>$email,'password'=>$password,'role'=>'admin','active'=>true]);});$this->info('Business created. Administrator can sign in to the web console.');return self::SUCCESS;
 }
}

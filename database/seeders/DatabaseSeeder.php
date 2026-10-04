<?php
namespace Database\Seeders;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
class DatabaseSeeder extends Seeder {
  public function run(): void {
   $password=getenv('MENTO_SETUP_PASSWORD') ?: 'Admin12345678';
   $email=getenv('MENTO_SETUP_EMAIL') ?: 'admin@example.test';
  DB::transaction(function()use($password,$email){
   if(User::where('email',$email)->exists())throw new \RuntimeException('Account already exists.');
   $business=DB::table('businesses')->insertGetId(['name'=>'Sushi Bar','timezone'=>'Europe/London','active'=>true,'created_at'=>now(),'updated_at'=>now()]);
   User::create(['business_id'=>$business,'name'=>'Business Administrator','email'=>$email,'password'=>$password,'role'=>'admin','active'=>true]);
  });
 }
}

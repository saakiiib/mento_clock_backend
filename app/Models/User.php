<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
class User extends Authenticatable {
 use Notifiable, HasApiTokens;
 protected $fillable=['business_id','name','email','password','role','active','phone','employee_code','job_title','employment_start_date','emergency_contact_name','emergency_contact_phone'];
 protected $hidden=['password','remember_token'];
 protected function casts(): array {return ['password'=>'hashed','active'=>'boolean'];}
}

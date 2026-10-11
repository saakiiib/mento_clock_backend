<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
class User extends Authenticatable {
 use Notifiable, HasApiTokens;
 protected $fillable=[
  'business_id','name','email','password','role','active','phone','employee_code','job_title',
  'employment_start_date','emergency_contact_name','emergency_contact_phone','first_name',
  'middle_name','last_name','gender','national_insurance_number','date_of_birth','marital_status',
  'nationality','home_address_line_1','home_address_line_2','home_city','home_county',
  'home_postcode','home_country','alternative_phone','bank_name','sort_code','bank_account_number',
  'emergency_contact_relationship','emergency_contact_email','emergency_contact_address',
  'passport_number','passport_nationality','passport_issued_by','passport_issued_date',
  'passport_expiry_date','visa_brp_number','visa_nationality','visa_issued_by','visa_issued_date',
  'visa_expiry_date','cos_number','cos_start_date','cos_end_date','department','employment_type',
  'shift_name','late_policy','day_off_rule','date_of_confirmation','contract_start_date',
  'contract_end_date','job_location','job_description','reporting_authority_id',
  'leave_sanction_authority_id'
 ];
 protected $hidden=[
  'password','remember_token','gender','national_insurance_number','date_of_birth','marital_status',
  'nationality','home_address_line_1','home_address_line_2','home_city','home_county','home_postcode',
  'home_country','alternative_phone','bank_name','sort_code','bank_account_number',
  'emergency_contact_name','emergency_contact_relationship','emergency_contact_phone',
  'emergency_contact_email','emergency_contact_address','passport_number','passport_nationality',
  'passport_issued_by','passport_issued_date','passport_expiry_date','visa_brp_number','visa_nationality',
  'visa_issued_by','visa_issued_date','visa_expiry_date','cos_number','cos_start_date','cos_end_date'
 ];
 protected function casts(): array {return [
  'password'=>'hashed','active'=>'boolean','national_insurance_number'=>'encrypted',
  'home_address_line_1'=>'encrypted','home_address_line_2'=>'encrypted','home_city'=>'encrypted',
  'home_county'=>'encrypted','home_postcode'=>'encrypted','home_country'=>'encrypted',
  'emergency_contact_address'=>'encrypted',
  'bank_name'=>'encrypted','sort_code'=>'encrypted','bank_account_number'=>'encrypted',
  'passport_number'=>'encrypted','visa_brp_number'=>'encrypted','cos_number'=>'encrypted',
  'date_of_birth'=>'date:Y-m-d','employment_start_date'=>'date:Y-m-d',
  'date_of_confirmation'=>'date:Y-m-d','contract_start_date'=>'date:Y-m-d',
  'contract_end_date'=>'date:Y-m-d','passport_issued_date'=>'date:Y-m-d',
  'passport_expiry_date'=>'date:Y-m-d','visa_issued_date'=>'date:Y-m-d',
  'visa_expiry_date'=>'date:Y-m-d','cos_start_date'=>'date:Y-m-d','cos_end_date'=>'date:Y-m-d'
 ];}
}

<?php
namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class V38EmployeeWorkplaceController {
 public function edit(Request $request,int $id) {
  $business=(int)$request->user()->business_id;
  $employee=User::where('business_id',$business)->where('role','employee')->findOrFail($id);
  $branches=DB::table('branches')->where('business_id',$business)->where('active',true)->orderBy('name')->get();
  $assigned=DB::table('employee_branches')->where('business_id',$business)->where('user_id',$id)->pluck('branch_id')->all();
  $open=DB::table('attendance_records as a')->join('branches as b','b.id','=','a.branch_id')->where('a.business_id',$business)->where('a.user_id',$id)->whereNull('a.clock_out')->get(['b.id','b.name']);
  return view('employees.workplaces',compact('employee','branches','assigned','open'));
 }
}

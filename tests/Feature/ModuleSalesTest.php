<?php

namespace Tests\Feature;

use App\Models\{PlatformAdmin, User};
use App\Services\ModuleAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Auth, DB};
use Tests\TestCase;

class ModuleSalesTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_open_module_requests_page_with_no_requests(): void
    {
        $platform = PlatformAdmin::create(['name'=>'Module Operator','email'=>'module-operator@example.test','password'=>'platform-password-123','active'=>true]);
        $this->actingAs($platform,'platform')->withSession(['platform_version'=>$platform->auth_version]);
        Auth::shouldUse('web');

        $this->get('/platform/module-requests')->assertOk()
            ->assertSee('Module requests')->assertSee('No module requests yet');
    }

    public function test_super_admin_can_render_a_pending_module_request(): void
    {
        $businessId = DB::table('businesses')->insertGetId([
            'name' => 'Request Kitchen', 'timezone' => 'Europe/London', 'active' => true,
        ]);
        $module = DB::table('modules')->where('code', 'leave_management')->firstOrFail();
        $admin = User::create([
            'business_id' => $businessId, 'name' => 'Kitchen Owner', 'email' => 'request-owner@example.test',
            'password' => 'test-password-123', 'role' => 'admin', 'active' => true,
        ]);
        DB::table('client_module_requests')->insert([
            'business_id' => $businessId, 'module_id' => $module->id, 'requested_by' => $admin->id,
            'desired_access' => 'trial', 'status' => 'pending', 'request_note' => 'Please enable a trial.',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $platform = PlatformAdmin::create([
            'name' => 'Module Operator', 'email' => 'pending-module-operator@example.test',
            'password' => 'platform-password-123', 'active' => true,
        ]);
        $this->actingAs($platform, 'platform')->withSession(['platform_version' => $platform->auth_version]);
        Auth::shouldUse('web');

        $this->get('/platform/module-requests')->assertOk()
            ->assertSee('Request Kitchen')->assertSee('Leave Management')
            ->assertSee('Please enable a trial.')->assertSee('Save decision');
    }

    public function test_super_admin_can_open_module_guide_with_walkthroughs(): void
    {
        $platform = PlatformAdmin::create(['name'=>'Guide Operator','email'=>'guide@example.test','password'=>'platform-password-123','active'=>true]);
        $this->actingAs($platform,'platform')->withSession(['platform_version'=>$platform->auth_version]);
        Auth::shouldUse('web');

        $this->get('/platform/module-guide')->assertOk()
            ->assertSee('Suggested walkthrough')->assertSee('Attendance Tracking')
            ->assertSee('Employee Management')->assertSee('Rota & Shifts');
    }

    public function test_core_price_uses_branch_capacity_and_business_discount_and_hides_paid_request_button(): void
    {
        $businessId = DB::table('businesses')->insertGetId([
            'name' => 'MK Sushi Bar Ltd', 'timezone' => 'Europe/London', 'active' => true,
        ]);
        DB::table('client_profiles')->insert([
            'business_id' => $businessId, 'branch_limit' => 8, 'employees_per_branch_limit' => 5,
            'core_discount_percent' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        for ($index = 1; $index <= 5; $index++) {
            DB::table('branches')->insert([
                'business_id' => $businessId, 'name' => 'Branch '.$index, 'address' => 'Test address',
                'latitude' => 52.0, 'longitude' => -0.5, 'radius_m' => 100, 'active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $admin = User::create([
            'business_id' => $businessId, 'name' => 'Owner', 'email' => 'sushi-owner@example.test',
            'password' => 'test-password-123', 'role' => 'admin', 'active' => true,
        ]);
        for ($index = 1; $index <= 19; $index++) {
            User::create([
                'business_id' => $businessId, 'name' => 'Employee '.$index,
                'email' => 'employee'.$index.'@example.test', 'password' => 'test-password-123',
                'role' => 'employee', 'active' => true,
            ]);
        }

        $platform = PlatformAdmin::create([
            'name' => 'Pricing Operator', 'email' => 'pricing-operator@example.test',
            'password' => 'platform-password-123', 'active' => true,
        ]);
        $this->actingAs($platform, 'platform')->withSession(['platform_version' => $platform->auth_version]);
        Auth::shouldUse('web');
        $this->post('/platform/clients/'.$businessId.'/pricing', ['core_discount_percent' => 50])
            ->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseHas('client_profiles', ['business_id' => $businessId, 'core_discount_percent' => 50]);

        $this->actingAs($admin, 'web');
        $this->actingAs($admin)->get('/modules')->assertOk()
            ->assertSee('£31.50/month')->assertSee('Standard £63.00')
            ->assertSee('50% agreed discount')->assertDontSee('Request paid access');
    }

    public function test_business_admin_can_request_a_module_but_access_waits_for_super_admin_approval(): void
    {
        $businessId = DB::table('businesses')->insertGetId(['name'=>'Trial Kitchen','timezone'=>'Europe/London','active'=>true]);
        $module = DB::table('modules')->where('code','leave_management')->first();
        DB::table('modules')->where('id',$module->id)->update([
            'available'=>true,'monthly_price_pence'=>500,'included_price_pence'=>500,
            'extra_employee_price_pence'=>100,'pricing_model'=>'tiered','price_basis'=>'per_employee',
        ]);
        $admin = User::create(['business_id'=>$businessId,'name'=>'Owner','email'=>'owner@example.test','password'=>'test-password-123','role'=>'admin','active'=>true]);

        $this->actingAs($admin)->get('/modules')->assertOk()->assertSee('Plans & modules')->assertSee('£5.00/month');
        $this->post('/modules/'.$module->id.'/request',['desired_access'=>'trial','request_note'=>'Please let us test leave requests.'])
            ->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseHas('client_module_requests',['business_id'=>$businessId,'module_id'=>$module->id,'status'=>'pending','desired_access'=>'trial']);
        $this->assertFalse(app(ModuleAccess::class)->enabled($businessId,'leave_management'));

        $platform = PlatformAdmin::create(['name'=>'Operator','email'=>'operator@example.test','password'=>'platform-password-123','active'=>true]);
        $this->actingAs($platform,'platform')->withSession(['platform_version'=>$platform->auth_version]);
        Auth::shouldUse('web');
        $requestId = DB::table('client_module_requests')->value('id');
        $endDate = now('Europe/London')->addDays(14)->toDateString();
        $this->post('/platform/module-requests/'.$requestId,[
            'decision'=>'approved','granted_type'=>'trial','trial_ends_at'=>$endDate,'review_note'=>'Trial approved.',
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('client_module_requests',['id'=>$requestId,'status'=>'approved','granted_type'=>'trial']);
        $this->assertDatabaseHas('business_modules',['business_id'=>$businessId,'module_id'=>$module->id,'status'=>'active','entitlement_type'=>'trial']);
        $this->assertTrue(app(ModuleAccess::class)->enabled($businessId,'leave_management'));
    }

    public function test_only_one_pending_request_per_module_and_business_is_accepted(): void
    {
        $businessId = DB::table('businesses')->insertGetId(['name'=>'Request Kitchen','timezone'=>'Europe/London','active'=>true]);
        $module = DB::table('modules')->where('code','leave_management')->first();
        DB::table('modules')->where('id',$module->id)->update(['available'=>true]);
        $admin = User::create(['business_id'=>$businessId,'name'=>'Owner','email'=>'owner2@example.test','password'=>'test-password-123','role'=>'admin','active'=>true]);
        $this->actingAs($admin);
        $this->post('/modules/'.$module->id.'/request',['desired_access'=>'paid'])->assertRedirect();
        $this->post('/modules/'.$module->id.'/request',['desired_access'=>'paid'])->assertSessionHasErrors('module');
        $this->assertSame(1, DB::table('client_module_requests')->where('business_id',$businessId)->where('module_id',$module->id)->count());
    }
}

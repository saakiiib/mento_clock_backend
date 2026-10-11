<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ComplianceAndNoticesTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(string $key): array
    {
        $businessId = DB::table('businesses')->insertGetId(['name' => 'Workspace '.$key, 'timezone' => 'Europe/London', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        foreach (DB::table('modules')->whereIn('code', ['employee_management', 'attendance_tracking', 'compliance_tracking', 'company_notices'])->get(['id']) as $module) {
            DB::table('business_modules')->insert(['business_id' => $businessId, 'module_id' => $module->id, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        }
        $admin = User::create(['business_id' => $businessId, 'name' => 'Admin '.$key, 'email' => $key.'-admin@example.test', 'password' => 'secure-manager-password', 'role' => 'admin', 'active' => true]);
        $employee = User::create(['business_id' => $businessId, 'name' => 'Employee '.$key, 'email' => $key.'-employee@example.test', 'password' => 'secure-employee-password', 'role' => 'employee', 'active' => true]);
        return [$businessId, $admin, $employee];
    }

    public function test_compliance_upload_review_and_tenant_scoped_download(): void
    {
        Storage::fake('local');
        [$businessA, $adminA, $employeeA] = $this->workspace('compliance-a');
        [, $adminB] = $this->workspace('compliance-b');

        $this->actingAs($adminA)->post('/compliance/documents', [
            'employee_id' => $employeeA->id, 'category' => 'right_to_work', 'title' => 'RTW check',
            'expiry_date' => now()->addYear()->toDateString(), 'document' => UploadedFile::fake()->create('right-to-work.pdf', 20, 'application/pdf'),
        ])->assertRedirect()->assertSessionHas('status');
        $document = DB::table('employee_documents')->where('business_id', $businessA)->firstOrFail();
        Storage::disk('local')->assertExists($document->storage_path);
        $this->actingAs($adminB)->get('/compliance/documents/'.$document->id.'/download')->assertNotFound();
        $this->actingAs($adminA)->post('/compliance/documents/'.$document->id.'/review', ['status' => 'rejected', 'review_note' => 'Please upload a clearer copy.'])->assertRedirect();
        $this->assertDatabaseHas('employee_documents', ['id' => $document->id, 'business_id' => $businessA, 'status' => 'rejected', 'review_note' => 'Please upload a clearer copy.']);
        $this->assertDatabaseHas('hr_module_events', ['business_id' => $businessA, 'subject_type' => 'employee_document', 'action' => 'reviewed']);
        $this->actingAs($adminA)->post('/compliance/training', [
            'employee_id' => $employeeA->id, 'course_name' => 'First aid at work', 'provider' => 'Training Co',
            'completed_on' => now()->subMonth()->toDateString(), 'expires_on' => now()->addYear()->toDateString(), 'status' => 'valid',
        ])->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseHas('employee_training_records', ['business_id' => $businessA, 'user_id' => $employeeA->id, 'course_name' => 'First aid at work']);
    }

    public function test_notice_delivery_and_acknowledgement_are_limited_to_the_published_audience(): void
    {
        [$businessId, $admin, $employee] = $this->workspace('notice-a');
        [, , $otherEmployee] = $this->workspace('notice-b');
        $this->actingAs($admin)->post('/notices', [
            'title' => 'Policy update', 'body' => 'Please read this update.', 'audience' => 'employee',
            'target_id' => $employee->id, 'acknowledgement_required' => '1',
        ])->assertRedirect();
        $notice = DB::table('company_notices')->where('business_id', $businessId)->firstOrFail();
        $this->assertSame('draft', $notice->status);
        $this->actingAs($admin)->post('/notices/'.$notice->id.'/publish')->assertRedirect()->assertSessionHas('status');
        $this->actingAs($employee, 'sanctum')->getJson('/api/notices')->assertOk()->assertJsonPath('notices.0.id', $notice->id);
        $this->actingAs($employee, 'sanctum')->postJson('/api/notices/'.$notice->id.'/acknowledge')->assertOk();
        $this->assertDatabaseHas('company_notice_receipts', ['business_id' => $businessId, 'notice_id' => $notice->id, 'user_id' => $employee->id]);
        $this->actingAs($otherEmployee, 'sanctum')->getJson('/api/notices')->assertOk()->assertJsonCount(0, 'notices');
        $this->actingAs($otherEmployee, 'sanctum')->postJson('/api/notices/'.$notice->id.'/acknowledge')->assertNotFound();
    }

    public function test_compliance_and_notice_routes_require_module_entitlement(): void
    {
        [$businessId, $admin] = $this->workspace('module-gate');
        $moduleId = DB::table('modules')->where('code', 'compliance_tracking')->value('id');
        DB::table('business_modules')->where('business_id', $businessId)->where('module_id', $moduleId)->delete();
        $this->actingAs($admin)->get('/compliance')->assertForbidden();
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentManagementTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(string $key): array
    {
        $businessId = DB::table('businesses')->insertGetId(['name' => 'Documents '.$key, 'timezone' => 'Europe/London', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $moduleId = DB::table('modules')->where('code', 'document_management')->value('id');
        DB::table('business_modules')->insert(['business_id' => $businessId, 'module_id' => $moduleId, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $admin = User::create(['business_id' => $businessId, 'name' => 'Admin '.$key, 'email' => $key.'-admin@example.test', 'password' => 'secure-manager-password', 'role' => 'admin', 'active' => true]);
        $employee = User::create(['business_id' => $businessId, 'name' => 'Employee '.$key, 'email' => $key.'-employee@example.test', 'password' => 'secure-employee-password', 'role' => 'employee', 'active' => true]);
        return [$businessId, $admin, $employee];
    }

    public function test_documents_dashboard_lists_reports_and_archives_private_files(): void
    {
        Storage::fake('local');
        [$businessId, $admin, $employee] = $this->workspace('documents-a');
        $path = 'employee-documents/'.$businessId.'/'.$employee->id.'/contract.pdf';
        Storage::disk('local')->put($path, 'private document');
        $documentId = DB::table('employee_documents')->insertGetId([
            'business_id' => $businessId, 'user_id' => $employee->id, 'category' => 'contract', 'title' => 'Employment contract',
            'original_name' => 'contract.pdf', 'storage_path' => $path, 'mime_type' => 'application/pdf', 'size_bytes' => 16,
            'status' => 'accepted', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($admin)->get('/documents')->assertOk()->assertSee('Staff document report');
        $this->actingAs($admin)->get('/documents/download-report?tab=staff&format=csv')->assertOk()->assertSee('Employee');
        $this->actingAs($admin)->get('/documents?tab=organisation')->assertOk()->assertSee('Organisation document report');
        $this->actingAs($admin)->get('/documents?tab=employee&employee_id='.$employee->id)->assertOk()->assertSee('Employment contract');
        $this->actingAs($admin)->post('/documents/employee/'.$documentId.'/archive')->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseHas('employee_documents', ['id' => $documentId, 'business_id' => $businessId]);
        $this->assertNotNull(DB::table('employee_documents')->where('id', $documentId)->value('archived_at'));
        $this->assertDatabaseHas('hr_module_events', ['business_id' => $businessId, 'subject_type' => 'employee_document', 'action' => 'document_archived']);
        $this->actingAs($admin)->get('/documents?tab=archive')->assertOk()->assertSee('Employment contract');
        $this->actingAs($admin)->get('/documents/employee/'.$documentId.'/download')->assertOk();
        $this->actingAs($admin)->post('/documents/employee/'.$documentId.'/restore')->assertRedirect();
        $this->assertNull(DB::table('employee_documents')->where('id', $documentId)->value('archived_at'));
    }

    public function test_documents_page_requires_super_admin_module_assignment(): void
    {
        [$businessId, $admin] = $this->workspace('documents-module-gate');
        $moduleId = DB::table('modules')->where('code', 'document_management')->value('id');
        DB::table('business_modules')->where('business_id', $businessId)->where('module_id', $moduleId)->delete();

        $this->actingAs($admin)->get('/documents')->assertForbidden();
    }

    public function test_document_downloads_are_tenant_scoped(): void
    {
        Storage::fake('local');
        [$businessId, , $employee] = $this->workspace('documents-owner');
        [, $otherAdmin] = $this->workspace('documents-other');
        $path = 'employee-documents/'.$businessId.'/'.$employee->id.'/private.pdf';
        Storage::disk('local')->put($path, 'private');
        $documentId = DB::table('employee_documents')->insertGetId([
            'business_id' => $businessId, 'user_id' => $employee->id, 'category' => 'contract', 'title' => 'Private contract',
            'original_name' => 'private.pdf', 'storage_path' => $path, 'mime_type' => 'application/pdf', 'size_bytes' => 7,
            'status' => 'accepted', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($otherAdmin)->get('/documents/employee/'.$documentId.'/download')->assertNotFound();
    }
}

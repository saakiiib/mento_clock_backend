<?php

namespace App\Http\Controllers;

use App\Services\WorkspacePermissions;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DocumentManagementController
{
    public function __construct(private WorkspacePermissions $permissions) {}

    private function businessId(Request $request, string $permission): int
    {
        abort_unless($request->user()?->active && $request->user()->business_id, 403);
        $this->permissions->require($request->user(), $permission);

        return (int) $request->user()->business_id;
    }

    private function data(Request $request): array
    {
        $businessId = $this->businessId($request, 'documents.view');
        $filters = $request->validate([
            'tab' => 'nullable|in:staff,organisation,employee,archive,employee-archive',
            'employee_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        $tab = $filters['tab'] ?? 'staff';
        $employeeId = $filters['employee_id'] ?? null;
        if ($employeeId) {
            abort_unless(DB::table('users')->where('business_id', $businessId)->where('role', 'employee')->where('id', $employeeId)->exists(), 404);
        }

        $employees = DB::table('users')->where('business_id', $businessId)->where('role', 'employee')->orderBy('name')->get(['id', 'name', 'email', 'employee_code', 'department', 'job_title', 'active', 'employment_end_date']);
        $staffReport = DB::table('users as u')->leftJoin('employee_documents as d', function ($join) use ($businessId) {
            $join->on('d.user_id', '=', 'u.id')->where('d.business_id', '=', $businessId)->whereNull('d.archived_at');
        })->where('u.business_id', $businessId)->where('u.role', 'employee')->where('u.active', true)
            ->groupBy('u.id', 'u.name', 'u.email', 'u.employee_code', 'u.department', 'u.job_title')
            ->orderBy('u.name')->get(['u.id', 'u.name', 'u.email', 'u.employee_code', 'u.department', 'u.job_title', DB::raw('COUNT(d.id) as document_count'), DB::raw("SUM(CASE WHEN d.status = 'pending_review' THEN 1 ELSE 0 END) as pending_count")]);

        $employeeDocuments = DB::table('employee_documents as d')->join('users as u', function ($join) use ($businessId) {
            $join->on('u.id', '=', 'd.user_id')->where('u.business_id', '=', $businessId);
        })->where('d.business_id', $businessId)->when($employeeId, fn ($query) => $query->where('u.id', $employeeId))
            ->orderBy('u.name')->orderByDesc('d.created_at')
            ->get(['d.id', 'd.user_id', 'd.category', 'd.title', 'd.original_name', 'd.status', 'd.expiry_date', 'd.archived_at', 'd.created_at', 'u.name as employee_name', 'u.employee_code']);

        $organisationDocuments = DB::table('client_organisation_documents')->where('business_id', $businessId)
            ->orderByDesc('created_at')->get(['id', 'category', 'original_name', 'mime_type', 'size_bytes', 'archived_at', 'created_at']);
        $archivedEmployees = $employees->where('active', false)->values();

        return compact('businessId', 'tab', 'employeeId', 'employees', 'staffReport', 'employeeDocuments', 'organisationDocuments', 'archivedEmployees');
    }

    public function index(Request $request)
    {
        $data = $this->data($request);
        return view('documents.index', $data);
    }

    public function download(Request $request, string $kind, int $documentId)
    {
        $businessId = $this->businessId($request, 'documents.download');
        abort_unless(in_array($kind, ['employee', 'organisation'], true), 404);
        $table = $kind === 'employee' ? 'employee_documents' : 'client_organisation_documents';
        $document = DB::table($table)->where('business_id', $businessId)->where('id', $documentId)->first();
        abort_unless($document && Storage::disk('local')->exists($document->storage_path), 404);
        DB::table('hr_module_events')->insert([
            'business_id' => $businessId, 'actor_id' => $request->user()->id,
            'subject_type' => $kind.'_document', 'subject_id' => $documentId,
            'action' => 'document_downloaded',
            'details' => json_encode(['category' => $document->category, 'title' => $document->title ?? $document->original_name], JSON_THROW_ON_ERROR),
            'created_at' => now('UTC'),
        ]);
        return Storage::disk('local')->download($document->storage_path, $document->original_name, ['Content-Type' => $document->mime_type]);
    }

    public function archive(Request $request, string $kind, int $documentId)
    {
        return $this->setArchived($request, $kind, $documentId, true);
    }

    public function restore(Request $request, string $kind, int $documentId)
    {
        return $this->setArchived($request, $kind, $documentId, false);
    }

    private function setArchived(Request $request, string $kind, int $documentId, bool $archive)
    {
        $businessId = $this->businessId($request, 'documents.manage');
        abort_unless(in_array($kind, ['employee', 'organisation'], true), 404);
        $table = $kind === 'employee' ? 'employee_documents' : 'client_organisation_documents';
        $document = DB::table($table)->where('business_id', $businessId)->where('id', $documentId)->first();
        abort_unless($document, 404);
        $now = now('UTC');
        DB::table($table)->where('business_id', $businessId)->where('id', $documentId)->update([
            'archived_at' => $archive ? $now : null,
            'archived_by' => $archive ? $request->user()->id : null,
        ]);
        DB::table('hr_module_events')->insert([
            'business_id' => $businessId, 'actor_id' => $request->user()->id,
            'subject_type' => $kind.'_document', 'subject_id' => $documentId,
            'action' => $archive ? 'document_archived' : 'document_restored',
            'details' => json_encode(['category' => $document->category, 'title' => $document->title ?? $document->original_name], JSON_THROW_ON_ERROR),
            'created_at' => $now,
        ]);

        return back()->with('status', $archive ? 'Document moved to the archive.' : 'Document restored to the active register.');
    }

    public function downloadReport(Request $request)
    {
        $this->permissions->require($request->user(), 'reports.export');
        $data = $this->data($request);
        $format = $request->validate(['format' => 'nullable|in:csv,pdf'])['format'] ?? 'csv';
        abort_unless(in_array($data['tab'], ['staff', 'organisation', 'employee'], true), 422, 'Choose a document report before exporting.');
        $reportName = match ($data['tab']) {
            'organisation' => 'organisation-documents',
            'employee' => 'employee-documents',
            default => 'staff-documents',
        };
        if ($format === 'pdf') {
            return Pdf::loadView('documents.report-pdf', $data)->setPaper('a4', 'landscape')->download('mentoclock-'.$reportName.'.pdf');
        }

        $rows = [['Document report', 'Employee', 'Employee code', 'Category', 'Title / file', 'Status', 'Expiry / created']];
        if ($data['tab'] === 'organisation') {
            foreach ($data['organisationDocuments']->whereNull('archived_at') as $document) {
                $rows[] = ['Organisation', '', '', $document->category, $document->original_name, 'Active', $document->created_at];
            }
        } elseif ($data['tab'] === 'employee') {
            foreach ($data['employeeDocuments']->whereNull('archived_at') as $document) {
                $rows[] = ['Employee', $document->employee_name, $document->employee_code, $document->category, $document->title, $document->status, $document->expiry_date ?? $document->created_at];
            }
        } else {
            foreach ($data['staffReport'] as $employee) {
                $rows[] = ['Staff', $employee->name, $employee->employee_code, $employee->department, $employee->document_count.' documents', $employee->pending_count.' pending review', $employee->job_title];
            }
        }
        $csv = implode("\r\n", array_map(fn ($row) => implode(',', array_map(fn ($value) => '"'.str_replace('"', '""', (string) $value).'"', $row)), $rows));
        return response("\xEF\xBB\xBF".$csv, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="mentoclock-'.$reportName.'.csv"']);
    }
}

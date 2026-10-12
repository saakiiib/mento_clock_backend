@extends('layout')

@section('title', 'Documents')

@section('content')
<div class="page-heading">
    <div>
        <span class="eyebrow">PEOPLE RECORDS</span>
        <h1>Documents</h1>
        <p>Review employee and organisation documents, export reports and keep archived records accessible.</p>
    </div>
    @if (in_array($tab, ['staff', 'organisation', 'employee'], true))
        <div class="actions">
            <a class="button secondary" href="{{ url('/documents/download-report?'.http_build_query(array_merge(request()->query(), ['format' => 'csv']))) }}">Download CSV</a>
            <a class="button" href="{{ url('/documents/download-report?'.http_build_query(array_merge(request()->query(), ['format' => 'pdf']))) }}">Download PDF</a>
        </div>
    @endif
</div>

<nav class="document-tabs" aria-label="Document sections">
    @foreach (['staff' => 'Staff report', 'organisation' => 'Organisation report', 'employee' => 'Employee report', 'archive' => 'Document archive', 'employee-archive' => 'Employee archive'] as $key => $label)
        <a href="{{ url('/documents?tab='.$key) }}" class="{{ $tab === $key ? 'current' : '' }}" @if ($tab === $key) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>

@if ($tab === 'staff')
    <section class="card">
        <div class="card-head">
            <div><span class="eyebrow">ACTIVE EMPLOYEES</span><h2>Staff document report</h2><p>See each employee’s active file count and pending reviews.</p></div>
            <span class="badge neutral">{{ $staffReport->count() }} employees</span>
        </div>
        <div class="scroll">
            <table>
                <thead><tr><th>Employee</th><th>Department / role</th><th>Documents</th><th>Pending review</th><th></th></tr></thead>
                <tbody>
                    @forelse ($staffReport as $employee)
                        <tr>
                            <td><strong>{{ $employee->name }}</strong><small class="table-sub">{{ $employee->employee_code ?: $employee->email }}</small></td>
                            <td>{{ $employee->department ?: 'Unassigned' }}<small class="table-sub">{{ $employee->job_title ?: 'No job title recorded' }}</small></td>
                            <td>{{ $employee->document_count }}</td>
                            <td>{{ $employee->pending_count }}</td>
                            <td><a href="{{ url('/documents?'.http_build_query(['tab' => 'employee', 'employee_id' => $employee->id])) }}">View employee report</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty-state"><h3>No active employees yet.</h3><p>Add employees before creating staff document reports.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@elseif ($tab === 'organisation')
    <section class="card">
        <div class="card-head"><div><span class="eyebrow">BUSINESS RECORDS</span><h2>Organisation document report</h2><p>Documents uploaded to the organisation profile.</p></div></div>
        <div class="scroll">
            <table>
                <thead><tr><th>File</th><th>Category</th><th>Uploaded</th><th>Size</th><th>Actions</th></tr></thead>
                <tbody>
                    @forelse ($organisationDocuments->whereNull('archived_at') as $document)
                        <tr>
                            <td>{{ $document->original_name }}</td><td>{{ str_replace('_', ' ', ucfirst($document->category)) }}</td>
                            <td>{{ Carbon\Carbon::parse($document->created_at)->format('d M Y') }}</td><td>{{ number_format($document->size_bytes / 1024, 0) }} KB</td>
                            <td><div class="actions">@if (app(\App\Services\WorkspacePermissions::class)->has(auth()->user(), 'documents.download'))<a href="{{ url('/documents/organisation/'.$document->id.'/download') }}">Download</a>@endif
                                @if (app(\App\Services\WorkspacePermissions::class)->has(auth()->user(), 'documents.manage'))
                                    <form method="post" action="{{ url('/documents/organisation/'.$document->id.'/archive') }}" data-confirm="Move this organisation document to the archive?">@csrf<button class="secondary">Archive</button></form>
                                @endif
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty-state"><h3>No organisation documents found.</h3><p>Upload organisation records from the organisation profile page.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@elseif ($tab === 'employee')
    <section class="card">
        <div class="card-head"><div><span class="eyebrow">EMPLOYEE RECORDS</span><h2>Employee document report</h2><p>Filter the report to one employee or review every active employee document.</p></div></div>
        <form method="get" action="{{ url('/documents') }}" class="document-filter">
            <input type="hidden" name="tab" value="employee">
            <label for="employee_id">Employee</label>
            <select id="employee_id" name="employee_id"><option value="">All employees</option>@foreach ($employees as $employee)<option value="{{ $employee->id }}" @selected((string) $employeeId === (string) $employee->id)>{{ $employee->name }}</option>@endforeach</select>
            <button class="secondary">Apply filter</button>
        </form>
        <div class="scroll">
            <table>
                <thead><tr><th>Employee</th><th>Document</th><th>Category</th><th>Status</th><th>Expiry</th><th>Actions</th></tr></thead>
                <tbody>
                    @forelse ($employeeDocuments->whereNull('archived_at') as $document)
                        <tr>
                            <td>{{ $document->employee_name }}<small class="table-sub">{{ $document->employee_code }}</small></td><td>{{ $document->title }}</td><td>{{ str_replace('_', ' ', ucfirst($document->category)) }}</td>
                            <td>{{ str_replace('_', ' ', ucfirst($document->status)) }}</td><td>{{ $document->expiry_date ? Carbon\Carbon::parse($document->expiry_date)->format('d M Y') : 'No expiry recorded' }}</td>
                            <td><div class="actions">@if (app(\App\Services\WorkspacePermissions::class)->has(auth()->user(), 'documents.download'))<a href="{{ url('/documents/employee/'.$document->id.'/download') }}">Download</a>@endif
                                @if (app(\App\Services\WorkspacePermissions::class)->has(auth()->user(), 'documents.manage'))
                                    <form method="post" action="{{ url('/documents/employee/'.$document->id.'/archive') }}" data-confirm="Move this employee document to the archive?">@csrf<button class="secondary">Archive</button></form>
                                @endif
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty-state"><h3>No employee documents found.</h3><p>Upload employee records in Compliance &amp; Training.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@elseif ($tab === 'archive')
    <section class="card">
        <div class="card-head"><div><span class="eyebrow">RETAINED RECORDS</span><h2>Document archive</h2><p>Archived files remain stored privately and can be restored to their active register.</p></div></div>
        <h3>Employee documents</h3>
        <div class="scroll"><table>
            <thead><tr><th>Employee</th><th>Document</th><th>Category</th><th>Archived</th><th>Actions</th></tr></thead>
            <tbody>
                @forelse ($employeeDocuments->whereNotNull('archived_at') as $document)
                    <tr><td>{{ $document->employee_name }}</td><td>{{ $document->title }}</td><td>{{ str_replace('_', ' ', ucfirst($document->category)) }}</td><td>{{ Carbon\Carbon::parse($document->archived_at)->format('d M Y') }}</td><td><div class="actions">@if (app(\App\Services\WorkspacePermissions::class)->has(auth()->user(), 'documents.download'))<a href="{{ url('/documents/employee/'.$document->id.'/download') }}">Download</a>@endif @if (app(\App\Services\WorkspacePermissions::class)->has(auth()->user(), 'documents.manage'))<form method="post" action="{{ url('/documents/employee/'.$document->id.'/restore') }}">@csrf<button class="secondary">Restore</button></form>@endif</div></td></tr>
                @empty
                    <tr><td colspan="5">No archived employee documents.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        <h3>Organisation documents</h3>
        <div class="scroll"><table>
            <thead><tr><th>File</th><th>Category</th><th>Archived</th><th>Actions</th></tr></thead>
            <tbody>
                @forelse ($organisationDocuments->whereNotNull('archived_at') as $document)
                    <tr><td>{{ $document->original_name }}</td><td>{{ str_replace('_', ' ', ucfirst($document->category)) }}</td><td>{{ Carbon\Carbon::parse($document->archived_at)->format('d M Y') }}</td><td><div class="actions">@if (app(\App\Services\WorkspacePermissions::class)->has(auth()->user(), 'documents.download'))<a href="{{ url('/documents/organisation/'.$document->id.'/download') }}">Download</a>@endif @if (app(\App\Services\WorkspacePermissions::class)->has(auth()->user(), 'documents.manage'))<form method="post" action="{{ url('/documents/organisation/'.$document->id.'/restore') }}">@csrf<button class="secondary">Restore</button></form>@endif</div></td></tr>
                @empty
                    <tr><td colspan="4">No archived organisation documents.</td></tr>
                @endforelse
            </tbody>
        </table></div>
    </section>
@else
    <section class="card">
        <div class="card-head"><div><span class="eyebrow">INACTIVE EMPLOYEES</span><h2>Employee archive</h2><p>Inactive employee accounts and their records remain available for authorised review.</p></div><span class="badge neutral">{{ $archivedEmployees->count() }} employees</span></div>
        <div class="scroll"><table>
            <thead><tr><th>Employee</th><th>Department / role</th><th>Employee code</th><th>Employment end date</th><th>Documents</th></tr></thead>
            <tbody>
                @forelse ($archivedEmployees as $employee)
                    <tr><td><strong>{{ $employee->name }}</strong><small class="table-sub">{{ $employee->email }}</small></td><td>{{ $employee->department ?: 'Unassigned' }}<small class="table-sub">{{ $employee->job_title ?: 'No job title recorded' }}</small></td><td>{{ $employee->employee_code ?: '—' }}</td><td>{{ $employee->employment_end_date ? Carbon\Carbon::parse($employee->employment_end_date)->format('d M Y') : 'Not recorded' }}</td><td><a href="{{ url('/documents?'.http_build_query(['tab' => 'employee', 'employee_id' => $employee->id])) }}">View employee report</a></td></tr>
                @empty
                    <tr><td colspan="5"><div class="empty-state"><h3>No inactive employee accounts.</h3><p>When an employee leaves, deactivate their account to keep the profile and attendance history in the archive.</p></div></td></tr>
                @endforelse
            </tbody>
        </table></div>
    </section>
@endif
@endsection

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        body{font-family:DejaVu Sans,sans-serif;color:#25272b;font-size:10px;line-height:1.45}
        h1{font-size:21px;margin:0 0 5px}p{color:#68717d;margin:0 0 18px}
        table{border-collapse:collapse;width:100%}th{background:#f3f4f6;text-align:left;color:#596270;font-size:8px;text-transform:uppercase}
        th,td{border-bottom:1px solid #e6e8eb;padding:8px;vertical-align:top}small{color:#69727e}
    </style>
</head>
<body>
    <h1>MentoClock · {{ ucfirst(str_replace('-', ' ', $tab)) }} document report</h1>
    <p>Generated {{ now()->format('d M Y H:i') }}</p>
    @if ($tab === 'organisation')
        <table><thead><tr><th>File</th><th>Category</th><th>Status</th><th>Uploaded</th></tr></thead><tbody>
            @foreach ($organisationDocuments->whereNull('archived_at') as $document)
                <tr><td>{{ $document->original_name }}</td><td>{{ str_replace('_', ' ', $document->category) }}</td><td>Active</td><td>{{ Carbon\Carbon::parse($document->created_at)->format('d M Y') }}</td></tr>
            @endforeach
        </tbody></table>
    @elseif ($tab === 'employee')
        <table><thead><tr><th>Employee</th><th>Code</th><th>Document</th><th>Category</th><th>Status</th><th>Expiry</th></tr></thead><tbody>
            @foreach ($employeeDocuments->whereNull('archived_at') as $document)
                <tr><td>{{ $document->employee_name }}</td><td>{{ $document->employee_code }}</td><td>{{ $document->title }}</td><td>{{ str_replace('_', ' ', $document->category) }}</td><td>{{ str_replace('_', ' ', $document->status) }}</td><td>{{ $document->expiry_date ?: '—' }}</td></tr>
            @endforeach
        </tbody></table>
    @else
        <table><thead><tr><th>Employee</th><th>Code</th><th>Department</th><th>Job title</th><th>Documents</th><th>Pending review</th></tr></thead><tbody>
            @foreach ($staffReport as $employee)
                <tr><td>{{ $employee->name }}</td><td>{{ $employee->employee_code }}</td><td>{{ $employee->department ?: 'Unassigned' }}</td><td>{{ $employee->job_title ?: '—' }}</td><td>{{ $employee->document_count }}</td><td>{{ $employee->pending_count }}</td></tr>
            @endforeach
        </tbody></table>
    @endif
</body>
</html>

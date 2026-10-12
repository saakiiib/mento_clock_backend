<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>MentoClock Rota · {{ $periodLabel }}</title>
    <style>
        @page { margin: 28px 30px; }
        body { color: #25272b; font: 10px DejaVu Sans, sans-serif; }
        header { border-bottom: 2px solid #f37021; margin-bottom: 18px; padding-bottom: 12px; }
        h1 { font-size: 21px; margin: 0 0 5px; }
        p { color: #727985; margin: 0; }
        .meta { color: #727985; font-size: 9px; margin-top: 10px; }
        table { border-collapse: collapse; width: 100%; }
        th { background: #f4f5f6; color: #4f5660; font-size: 8px; text-align: left; text-transform: uppercase; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 7px 8px; vertical-align: top; }
        .status { font-weight: bold; }
        .empty { border: 1px solid #e5e7eb; color: #727985; padding: 20px; text-align: center; }
        footer { border-top: 1px solid #e5e7eb; color: #8b919a; font-size: 8px; margin-top: 18px; padding-top: 8px; }
    </style>
</head>
<body>
<header>
    <h1>MentoClock · Rota</h1>
    <p>{{ $periodLabel }}</p>
    <div class="meta">Times shown in {{ $timezone }} · Draft shifts are planning items and have not been published.</div>
</header>
@if($shifts->isEmpty())
    <div class="empty">No draft or published shifts were found for this period.</div>
@else
    <table>
        <thead><tr><th>Day</th><th>Time</th><th>Employee</th><th>Shift</th><th>Workplace</th><th>Break</th><th>Status</th><th>Notes</th></tr></thead>
        <tbody>
        @foreach($shifts as $shift)
            @php
                $shiftStart = \Carbon\Carbon::parse($shift->starts_at, 'UTC')->setTimezone($timezone);
                $shiftEnd = \Carbon\Carbon::parse($shift->ends_at, 'UTC')->setTimezone($timezone);
            @endphp
            <tr>
                <td>{{ $shiftStart->format('D, d M Y') }}</td>
                <td>{{ $shiftStart->format('H:i') }}–{{ $shiftEnd->format('H:i') }}</td>
                <td>{{ $shift->employee_name }}{{ $shift->employee_code ? ' · '.$shift->employee_code : '' }}</td>
                <td>{{ $shift->title }}</td>
                <td>{{ $shift->branch_name ?: '—' }}</td>
                <td>{{ $shift->break_minutes }} min</td>
                <td class="status">{{ ucfirst($shift->status) }}</td>
                <td>{{ $shift->notes ?: '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif
<footer>Generated {{ now($timezone)->format('d M Y, H:i') }} · Cancelled shifts are omitted.</footer>
</body>
</html>

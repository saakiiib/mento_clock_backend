<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $businessName }} · Rota · {{ $periodLabel }}</title>
    <style>
        @page { margin: 26px 28px; }
        body { color: #25272b; font: 9pt DejaVu Sans, sans-serif; }
        header { border-bottom: 2px solid #f37021; margin-bottom: 12px; padding-bottom: 9px; }
        .business-line { color: #303844; font-size: 10pt; margin-bottom: 6px; white-space: nowrap; }
        .business-name { font-weight: bold; }
        .business-address { color: #727985; }
        h1 { font-size: 19px; margin: 0 0 4px; }
        p { color: #727985; margin: 0; }
        .week-label { color: #4f5660; font-size: 10pt; font-weight: bold; margin: 0 0 7px; }
        table { border-collapse: collapse; table-layout: fixed; width: 100%; }
        th { background: #f1f3f5; color: #4f5660; font-size: 8pt; text-align: left; }
        th, td { border: 1px solid #dfe3e8; padding: 6px 5px; vertical-align: top; }
        th:first-child, td:first-child { width: 17%; }
        th:not(:first-child), td:not(:first-child) { width: 11.85%; }
        td.employee { background: #f8f9fa; }
        .employee-name { font-size: 9pt; font-weight: bold; }
        small { color: #727985; display: block; font-size: 7pt; margin-top: 3px; }
        .shift { border-bottom: 1px solid #e5e7eb; margin-bottom: 4px; padding-bottom: 4px; }
        .shift:last-child { border-bottom: 0; margin-bottom: 0; padding-bottom: 0; }
        .shift-time { font-size: 9pt; white-space: nowrap; }
        .status { color: #555d68; font-size: 7pt; }
        .off { color: #9298a1; font-size: 8pt; }
        .outside-month { background: #f8f9fa; }
        .page-break { page-break-after: always; }
        footer { border-top: 1px solid #e5e7eb; color: #8b919a; font-size: 7pt; margin-top: 12px; padding-top: 6px; }
    </style>
</head>
<body>
<header>
    <div class="business-line"><span class="business-name">{{ $businessName }}</span>@if($businessAddress)<span class="business-address"> · {{ $businessAddress }}</span>@endif</div>
    <h1>Staff rota</h1>
    <p>{{ $periodLabel }}</p>
    <p class="meta">Draft shifts are planning items and have not been published.</p>
</header>

@if($employees->isEmpty())
    <p>No active employees were found for this business.</p>
@else
    @foreach($weeks as $week)
        <section class="{{ !$loop->last ? 'page-break' : '' }}">
            @if($period === 'month')
                @php $visibleDays = array_values(array_filter($week)); @endphp
                <div class="week-label">Week of {{ $visibleDays[0]->format('D, d M Y') }}</div>
            @endif
            <table>
                <thead><tr><th>Employee</th>@foreach($week as $day)<th>{{ $day ? $day->format('D d M') : '' }}</th>@endforeach</tr></thead>
                <tbody>
                @foreach($employees as $employee)
                    <tr>
                        <td class="employee"><div class="employee-name">{{ $employee->name }}</div>@if($employee->employee_code)<small>{{ $employee->employee_code }}</small>@endif</td>
                        @foreach($week as $day)
                            @if(!$day)
                                <td class="outside-month"></td>
                            @else
                                @php $dayShifts = $shiftGrid[$employee->id][$day->toDateString()] ?? []; @endphp
                                <td>
                                    @forelse($dayShifts as $shift)
                                        <div class="shift">
                                            <div class="shift-time">{{ $shift->local_start->format('H:i') }}–{{ $shift->local_end->format('H:i') }}</div>
                                            @if($shift->branch_name)<small>{{ $shift->branch_name }}</small>@endif
                                            <small class="status">{{ ucfirst($shift->status) }}</small>
                                            @if($showBreaks && (int) $shift->break_minutes > 0)<small>Break: {{ $shift->break_minutes }} min</small>@endif
                                        </div>
                                    @empty
                                        <span class="off">Off</span>
                                    @endforelse
                                </td>
                            @endif
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>
    @endforeach
@endif
<footer>Generated {{ now($timezone)->format('d M Y, H:i') }} · Cancelled shifts are omitted.</footer>
</body>
</html>

@extends('layout')
@section('title','Onboarding & offboarding')
@section('content')
<div class="page-heading"><div><span class="eyebrow">PEOPLE OPERATIONS</span><h1>Onboarding & offboarding</h1><p>Coordinate joining and leaving checklists, owners and completion dates.</p></div></div>

@if(app(\App\Services\WorkspacePermissions::class)->has(auth()->user(),'lifecycle.manage'))
<section class="card"><div class="card-head"><div><span class="eyebrow">NEW WORKFLOW</span><h2>Start an employee checklist</h2><p>New employees can have an onboarding checklist; use offboarding before their access is withdrawn.</p></div></div>
<form method="post" action="{{url('/lifecycle')}}" class="form-two">@csrf<div><label for="user_id">Employee</label><select name="user_id" id="user_id" required><option value="">Choose employee</option>@foreach($employees as $employee)<option value="{{$employee->id}}">{{$employee->name}}{{$employee->employee_code?' · '.$employee->employee_code:''}}</option>@endforeach</select></div><div><label for="type">Workflow</label><select name="type" id="type" required><option value="onboarding">Onboarding</option><option value="offboarding">Offboarding</option></select></div><div><label for="effective_date">Start / last working date</label><input type="date" id="effective_date" name="effective_date" min="{{now()->toDateString()}}"></div><div><label for="notes">Notes</label><input id="notes" name="notes" maxlength="3000"></div><div class="form-actions"><button>Start checklist</button></div></form></section>
@endif

<section class="card"><div class="card-head"><div><span class="eyebrow">WORKFLOWS</span><h2>Employee checklists</h2><p>All records are limited to your organisation.</p></div></div>
@forelse($cases as $case)
<details class="lifecycle-case" @if($loop->first) open @endif><summary><span><strong>{{$case->employee_name}}</strong>@if($case->employee_code)<small> · {{$case->employee_code}}</small>@endif</span><span class="badge {{$case->status==='complete'?'success':'warning'}}">{{ucfirst($case->type)}} · {{str_replace('_',' ',$case->status)}}</span><span class="caption">{{ $case->effective_date ? \Carbon\Carbon::parse($case->effective_date)->format('d M Y') : 'No date set' }}</span></summary>
@if($case->notes)<p class="caption">{{$case->notes}}</p>@endif
<div class="scroll"><table><thead><tr><th>Checklist item</th><th>Due</th><th>Status</th><th>Notes / update</th></tr></thead><tbody>
@foreach($tasks[$case->id]??[] as $task)
    @php
        $taskTitle = mb_strtolower($task->title);
        $profileUrl = url('/employees/'.$case->user_id.'/edit');
        $complianceUrl = url('/compliance?'.http_build_query(['employee_id' => $case->user_id]));
        $relatedAction = match (true) {
            str_contains($taskTitle, 'right-to-work') => ['href' => $complianceUrl.'&category=right_to_work#employee-documents', 'label' => 'Review right-to-work evidence', 'permission' => 'compliance.view'],
            str_contains($taskTitle, 'contract') || str_contains($taskTitle, 'final documents') => ['href' => $complianceUrl.'&category=contract#employee-documents', 'label' => 'Open employee documents', 'permission' => 'compliance.view'],
            str_contains($taskTitle, 'payroll') || str_contains($taskTitle, 'bank details') => ['href' => $profileUrl.'#bank-details', 'label' => 'Open bank details', 'permission' => 'people.sensitive.view'],
            str_contains($taskTitle, 'emergency contact') => ['href' => $profileUrl.'#emergency-contact', 'label' => 'Open emergency contact', 'permission' => 'people.sensitive.view'],
            str_contains($taskTitle, 'workplace') || str_contains($taskTitle, 'employment') || str_contains($taskTitle, 'last working date') => ['href' => $profileUrl.'#employment-access', 'label' => 'Open employment details', 'permission' => 'people.sensitive.view'],
            str_contains($taskTitle, 'training') || str_contains($taskTitle, 'induction') => ['href' => $complianceUrl.'&section=training#add-training-record', 'label' => 'Open training records', 'permission' => 'compliance.view'],
            str_contains($taskTitle, 'handbook') || str_contains($taskTitle, 'policies') => ['href' => url('/notices'), 'label' => 'Open company notices', 'permission' => 'notices.view'],
            default => null,
        };
        $canManageTask = $case->status === 'in_progress' && app(\App\Services\WorkspacePermissions::class)->has(auth()->user(), 'lifecycle.manage');
        $canOpenRelatedAction = $relatedAction && app(\App\Services\WorkspacePermissions::class)->has(auth()->user(), $relatedAction['permission']);
    @endphp
    <tr id="task-{{$task->id}}">
        <td><strong>{{$task->title}}</strong><small class="table-sub">{{ucfirst($task->category)}}</small>
            @if($canManageTask)
                <a class="task-action-link" href="{{$canOpenRelatedAction ? $relatedAction['href'] : '#task-action-'.$task->id}}">{{$canOpenRelatedAction ? $relatedAction['label'] : 'Add action notes'}}</a>
            @endif
        </td>
        <td>{{$task->due_date?\Carbon\Carbon::parse($task->due_date)->format('d M Y'):'—'}}</td>
        <td><span class="badge {{$task->status==='complete'?'success':($task->status==='blocked'?'inactive':'warning')}}">{{str_replace('_',' ',$task->status)}}</span></td>
        <td>
            @if($canManageTask)
                <form id="task-action-{{$task->id}}" method="post" action="{{url('/lifecycle/tasks/'.$task->id)}}" class="inline-form" data-change-save>@csrf
                    <select name="status" aria-label="Task status"><option value="pending" @selected($task->status==='pending')>Pending</option><option value="in_progress" @selected($task->status==='in_progress')>In progress</option><option value="blocked" @selected($task->status==='blocked')>Blocked</option><option value="complete" @selected($task->status==='complete')>Complete</option></select>
                    <input name="notes" value="{{$task->notes}}" placeholder="Optional note" aria-label="Task note"><button class="secondary" type="submit" data-change-save-button disabled>Save</button>
                </form>
            @elseif($task->notes){{$task->notes}}@else —@endif
        </td>
    </tr>
@endforeach
</tbody></table></div>
@if($case->status==='in_progress' && app(\App\Services\WorkspacePermissions::class)->has(auth()->user(),'lifecycle.manage'))<form method="post" action="{{url('/lifecycle/'.$case->id.'/complete')}}" data-confirm="Close this workflow? Completing offboarding will revoke the employee's access.">@csrf<button class="secondary">Complete workflow</button></form>@endif
</details>
@empty<div class="empty-state"><h3>No workflows yet</h3><p>Start an onboarding or offboarding checklist when you are ready.</p></div>@endforelse
</section>
@endsection

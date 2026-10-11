@php
    $selectedModuleCodes = old('module_codes', $selectedModuleCodes ?? ['employee_management', 'attendance_tracking', 'reports_analytics']);
@endphp
<div class="module-grid">
    @foreach($modules as $module)
        @php($checked = in_array($module->code, $selectedModuleCodes, true) || (bool) $module->is_core)
        <label class="module-option {{$module->available ? '' : 'module-planned'}}">
            @if($module->is_core)
                <input type="hidden" name="module_codes[]" value="{{$module->code}}">
                <input type="checkbox" checked disabled aria-label="{{$module->name}} is required">
            @elseif($module->available)
                <input type="checkbox" name="module_codes[]" value="{{$module->code}}" @checked($checked)>
            @else
                <input type="checkbox" disabled aria-label="{{$module->name}} is planned">
            @endif
            <span class="module-option-copy">
                <strong>{{$module->name}}</strong>
                <small>{{$module->description}}</small>
            </span>
            <span class="badge {{$module->available ? '':'inactive'}}">{{$module->is_core ? 'Required' : ($module->available ? 'Available' : 'Planned')}}</span>
        </label>
    @endforeach
</div>
<p class="caption">Planned modules are shown for roadmap visibility and cannot be enabled until their workflows are ready. Required modules keep the existing employee and clock features available.</p>

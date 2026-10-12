@php
$platform = request()->is('platform*');
$viewer = $platform ? auth('platform')->user() : auth()->user();
$workspaceName = $platform ? 'Mento Software' : (DB::table('businesses')->where('id',$viewer->business_id)->value('name') ?? 'Your workspace');
$workspaceLogo = !$platform ? DB::table('client_organisation_documents')->where('business_id',$viewer->business_id)->where('category','logo')->whereNull('archived_at')->value('id') : null;
$viewerRole = $platform ? 'Super administrator' : ($viewer->role === 'admin' ? 'Business administrator' : (DB::table('business_roles')->where('business_id',$viewer->business_id)->where('id',$viewer->business_role_id)->value('name') ?? 'Employee'));
$links = $platform ? [
 ['/platform','home','Platform overview',request()->is('platform')],
 ['/platform','building','Clients',request()->is('platform/clients*')],
 ['/platform/website','report','Website content',request()->is('platform/website*')],
 ['/platform/security','shield','Account security',request()->is('platform/security')],
] : [
 ['/','home','Overview',request()->is('/')],
 ['/people','people','Your people',request()->is('people','employees*')],
 ['/workplaces','shop','Workplaces',request()->is('workplaces','branches*')],
 ['/attendance','clock','Attendance',request()->is('attendance')],
 ['/reports','report','Reports',request()->is('reports*')],
 ['/leave','clock','Leave management',request()->is('leave*')],
 ['/calendar','report','Holiday calendar',request()->is('calendar*')],
 ['/rota','clock','Rota & shifts',request()->is('rota*')],
 ['/compliance','shield','Compliance & training',request()->is('compliance*')],
 ['/notices','report','Company notices',request()->is('notices*')],
 ['/lifecycle','people','Onboarding & offboarding',request()->is('lifecycle*')],
 ['/reminders','calendar','HR reminders',request()->is('reminders*')],
 ['/reports/hr','report','HR reports',request()->is('reports/hr*')],
 ['/roles','shield','Roles & permissions',request()->is('roles*')],
 ['/documents','report','Documents',request()->is('documents*')],
 ['/company','building','Organisation profile',request()->is('company')],
];
if (!$platform) {
    $moduleAccess = app(\App\Services\ModuleAccess::class);
    $requiredModules = ['/people'=>'employee_management','/workplaces'=>'employee_management','/attendance'=>'attendance_tracking','/reports'=>'reports_analytics','/leave'=>'leave_management','/calendar'=>'holiday_calendar','/rota'=>'rota_shifts','/compliance'=>'compliance_tracking','/notices'=>'company_notices','/lifecycle'=>'employee_lifecycle','/reminders'=>'hr_reminders','/reports/hr'=>'hr_reports','/roles'=>'role_permissions','/documents'=>'document_management'];
    $requiredPermissions = ['/people'=>'people.view','/workplaces'=>'people.view','/attendance'=>'attendance.view','/reports'=>'reports.view','/leave'=>'leave.view','/calendar'=>'leave.view','/rota'=>'rota.view','/compliance'=>'compliance.view','/notices'=>'notices.view','/lifecycle'=>'lifecycle.view','/reminders'=>'reminders.view','/reports/hr'=>'reports.view','/roles'=>'roles.manage','/documents'=>'documents.view','/company'=>'organisation.view'];
    $links = array_values(array_filter($links, fn ($link) => (!isset($requiredModules[$link[0]]) || $moduleAccess->enabled((int) $viewer->business_id, $requiredModules[$link[0]])) && (!isset($requiredPermissions[$link[0]]) || app(\App\Services\WorkspacePermissions::class)->has($viewer, $requiredPermissions[$link[0]]))));
}
$pageClass = $platform ? 'platform-page' : (request()->path() === '/' ? 'client-dashboard-page' : 'client-page');
@endphp
<!doctype html><html lang="en-GB"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>@yield('title','Workspace') · MentoClock</title><link rel="icon" href="{{asset('assets/app-icon.png')}}"><link rel="stylesheet" href="{{asset('assets/console.css')}}?v={{filemtime(public_path('assets/console.css'))}}"><script src="{{asset('assets/console.js')}}" defer></script></head><body class="{{$pageClass}}">
<a class="skip-link" href="#main">Skip to content</a><div class="console-shell"><aside class="sidebar" id="sidebar"><a class="logo" href="{{url($platform?'/platform':'/')}}">@if($workspaceLogo)<img src="{{url('/company/documents/'.$workspaceLogo.'/view')}}" alt="{{$workspaceName}} logo">@else<img src="{{asset('mento_logo.png')}}" alt="MentoClock">@endif</a><div class="workspace-badge"><span class="workspace-avatar">{{mb_substr($workspaceName,0,1)}}</span><div><strong>{{$workspaceName}}</strong><small>{{$platform?'Super admin console':'Client workspace'}}</small></div></div><span class="nav-label">{{$platform?'PLATFORM MANAGEMENT':'YOUR WORKSPACE'}}</span><nav aria-label="Workspace navigation">@foreach($links as [$href,$icon,$label,$current])<a href="{{url($href)}}" class="{{$current?'current':''}}" @if($current) aria-current="page" @endif>@include('partials.icon',['name'=>$icon]){{$label}}@if($current)<span class="nav-dot"></span>@endif</a>@endforeach</nav><div class="sidebar-bottom"><div class="support-note"><span class="eyebrow">Here to help</span><p>A little support for<br>a smoother workday.</p><a href="https://wa.me/447745975978?text=Hi%20Mento%20Software%2C%20I%20need%20help%20with%20MentoClock." target="_blank" rel="noopener">Talk to Mento Software ↗</a></div><a class="powered-by" href="https://www.mentosoftware.co.uk" target="_blank" rel="noopener">A Mento Software product ↗</a></div></aside>
<div class="workspace-content"><header class="topbar"><button class="menu-button" type="button" data-menu-toggle aria-controls="sidebar" aria-expanded="false" aria-label="Open workspace navigation">@include('partials.icon',['name'=>'menu'])</button><span class="breadcrumb">{{$platform?'Platform':'Workspace'}} <span>/</span> @yield('title','Overview')</span><div class="account-name"><span class="avatar">{{mb_substr($viewer->name,0,1)}}</span><div><strong>{{$viewer->name}}</strong><small>{{$viewerRole}}</small></div></div><form method="post" action="{{url($platform?'/platform/logout':'/logout')}}">@csrf<button class="icon-button" title="Sign out" aria-label="Sign out">@include('partials.icon',['name'=>'logout'])</button></form></header>
<main id="main">@include('partials.feedback')@yield('content')</main><footer class="console-footer"><span>© {{date('Y')}} MentoClock · Mento Software</span><span>{{$platform?'Manage with clarity.':'Your workplace. Your time.'}}</span></footer></div></div></body></html>

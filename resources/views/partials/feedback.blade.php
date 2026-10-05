@if(session('status'))<div class="alert success" role="status">@include('partials.icon',['name'=>'check'])<span>{{ session('status') }}</span></div>@endif
@if($errors->any())<div class="alert error" role="alert"><div><strong>Please check these details.</strong>@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div></div>@endif

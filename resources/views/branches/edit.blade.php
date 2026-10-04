@extends('layout')
@section('content')
<a href="/">← Back to dashboard</a><h1>Edit workplace</h1><div class="card" style="max-width:680px"><form method="post" action="/branches/{{$branch->id}}/edit">@csrf<label>Branch name</label><input name="name" value="{{$branch->name}}" required><label>Address</label><input name="address" value="{{$branch->address}}" required><label>Latitude</label><input name="latitude" type="number" step="any" value="{{$branch->latitude}}" required><label>Longitude</label><input name="longitude" type="number" step="any" value="{{$branch->longitude}}" required><label>Radius (metres)</label><input name="radius_m" type="number" min="50" max="1000" value="{{$branch->radius_m}}" required><button style="margin-top:24px">Save workplace</button></form></div>
@endsection

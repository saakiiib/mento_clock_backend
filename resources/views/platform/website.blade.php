@extends('layout')
@section('title','Website content')
@section('content')
<div class="page-heading"><div><span class="eyebrow">Public website</span><h1>Manage MentoClock.com</h1><p>Update the marketing pages. Review general enquiries and private trial requests in their dedicated menu tabs.</p></div><a class="button secondary" href="/" target="_blank" rel="noopener">Preview public site ↗</a></div>
<div class="stats"><article class="card stat"><div class="stat-head">PRIVATE TRIAL REQUESTS</div><div class="number">{{$stats['trials']}}</div><small><a href="{{url('/platform/trial-requests')}}">Open trial requests ↗</a></small></article><article class="card stat"><div class="stat-head">GENERAL ENQUIRIES</div><div class="number">{{$stats['general']}}</div><small><a href="{{url('/platform/general-enquiries')}}">Open general enquiries ↗</a></small></article><article class="card stat"><div class="stat-head">ALL WEBSITE REQUESTS</div><div class="number">{{$stats['total']}}</div><small>Stored in the application database</small></article><article class="card stat"><div class="stat-head">VISIBLE PACKAGES</div><div class="number">{{$stats['plans']}}</div><small>Shown on the pricing section</small></article></div>
<nav class="actions" aria-label="Website sections"><a class="button secondary" href="#settings">Page details</a><a class="button secondary" href="#packages">Packages</a><a class="button secondary" href="#features">Features</a><a class="button secondary" href="#faqs">FAQs</a><a class="button secondary" href="{{url('/platform/general-enquiries')}}">General enquiries</a><a class="button secondary" href="{{url('/platform/trial-requests')}}">Trial requests</a></nav>
<section class="card" id="settings"><div class="card-head"><div><h2>Website settings and page copy</h2><p>Use complete HTTPS URLs. Leave store or client portal links empty until they are ready.</p></div></div>
<form method="post" action="/platform/website/settings">@csrf
<h3>Landing page</h3>
<div class="form-two"><div><label>Website name</label><input name="site_name" value="{{old('site_name',$settings['site_name']??'MentoClock')}}" required maxlength="100"></div><div><label>Hero eyebrow</label><input name="hero_eyebrow" value="{{old('hero_eyebrow',$settings['hero_eyebrow']??'')}}" maxlength="100"></div></div>
<label>Hero headline · use \n for a line break</label><input name="hero_title" value="{{old('hero_title',$settings['hero_title']??'')}}" required maxlength="500">
<label>Hero description</label><textarea name="hero_description" rows="3" maxlength="1000" required>{{old('hero_description',$settings['hero_description']??'')}}</textarea>
<div class="form-two"><div><label>Primary button</label><input name="primary_cta" value="{{old('primary_cta',$settings['primary_cta']??'')}}" maxlength="100"></div><div><label>Workflow heading</label><input name="workflow_title" value="{{old('workflow_title',$settings['workflow_title']??'')}}" maxlength="200"></div></div>
<div class="form-two"><div><label>Features heading</label><input name="features_title" value="{{old('features_title',$settings['features_title']??'')}}" maxlength="200"></div><div><label>Features description</label><input name="features_description" value="{{old('features_description',$settings['features_description']??'')}}" maxlength="500"></div></div>
<div class="form-two"><div><label>Pricing heading</label><input name="pricing_title" value="{{old('pricing_title',$settings['pricing_title']??'')}}" maxlength="200"></div><div><label>Pricing description</label><input name="pricing_description" value="{{old('pricing_description',$settings['pricing_description']??'')}}" maxlength="500"></div></div>
<label>Pricing note</label><textarea name="pricing_note" rows="2" maxlength="1000">{{old('pricing_note',$settings['pricing_note']??'')}}</textarea>
<div class="form-two"><div><label>Contact heading</label><input name="contact_title" value="{{old('contact_title',$settings['contact_title']??'')}}" required maxlength="200"></div><div><label>Contact description</label><input name="contact_description" value="{{old('contact_description',$settings['contact_description']??'')}}" maxlength="500"></div></div>
<label>Footer text</label><input name="footer_text" value="{{old('footer_text',$settings['footer_text']??'')}}" maxlength="300">
<h3>Contact and links</h3><div class="form-two"><div><label>WhatsApp number · country code, digits only</label><input name="whatsapp_number" value="{{old('whatsapp_number',$settings['whatsapp_number']??'')}}" pattern="[1-9][0-9]{7,14}" required></div><div><label>Contact email</label><input name="contact_email" type="email" value="{{old('contact_email',$settings['contact_email']??'')}}" required maxlength="254"></div></div>
<div class="form-two"><div><label>Public website URL</label><input name="site_url" type="url" value="{{old('site_url',$settings['site_url']??'')}}" placeholder="https://mentoclock.com"></div><div><label>Client portal URL</label><input name="portal_url" type="url" value="{{old('portal_url',$settings['portal_url']??'')}}" placeholder="https://mentoclock.com/login"></div></div>
<p class="caption">The homepage app buttons and QR codes point to MentoClock’s published App Store and Google Play listings. If either store listing URL changes, update the matching link and regenerate its QR image in <code>public/marketing/assets</code>.</p>
<h3>Search and legal text</h3><div class="form-two"><div><label>SEO title</label><input name="seo_title" value="{{old('seo_title',$settings['seo_title']??'')}}" maxlength="200"></div><div><label>SEO description</label><textarea name="seo_description" rows="3" maxlength="500">{{old('seo_description',$settings['seo_description']??'')}}</textarea></div></div>
<label>Privacy notice</label><textarea name="privacy_text" rows="8" maxlength="12000">{{old('privacy_text',$settings['privacy_text']??'')}}</textarea>
<label>Website terms</label><textarea name="terms_text" rows="8" maxlength="12000">{{old('terms_text',$settings['terms_text']??'')}}</textarea>
<label>Cookie policy</label><textarea name="cookie_text" rows="8" maxlength="12000">{{old('cookie_text',$settings['cookie_text']??'')}}</textarea>
<div class="form-actions"><button>Save website settings</button></div></form></section>

@php $kindTitles=['plan'=>'Pricing packages','feature'=>'Homepage features','faq'=>'Frequently asked questions']; $anchors=['plan'=>'packages','feature'=>'features','faq'=>'faqs']; @endphp
@foreach($kindTitles as $kind=>$heading)
<section class="card" id="{{$anchors[$kind]}}"><div class="card-head"><div><h2>{{$heading}}</h2><p>Edit copy and display order. Hidden items remain saved.</p></div></div>
@foreach(($content[$kind]??collect()) as $item)
@php $extra=\App\Support\MarketingSite::extra((array)$item); @endphp
<details class="disclosure"><summary>{{$item->title}} <span class="badge {{$item->active?'':'inactive'}}">{{$item->active?'Visible':'Hidden'}}</span></summary>
<form method="post" action="/platform/website/content/{{$item->id}}">@csrf<input type="hidden" name="kind" value="{{$kind}}">
<div class="form-two"><div><label>Title</label><input name="title" value="{{$item->title}}" maxlength="150" required></div><div><label>Display order</label><input name="position" type="number" value="{{$item->position}}" min="-10000" max="10000" required></div></div>
<label>Description / answer</label><textarea name="body" rows="3" maxlength="3000" required>{{$item->body}}</textarea>
@if($kind==='plan')
<div class="form-two"><div><label>Audience label</label><input name="audience" value="{{$extra['audience']??''}}" maxlength="100"></div><div><label>Icon not used for packages</label><input value="—" disabled></div></div>
<div class="form-two"><div><label>Monthly price (£) · blank shows “Let’s talk”</label><input name="monthly" value="{{$extra['monthly']??''}}" inputmode="decimal"></div><div><label>Yearly total (£)</label><input name="annual" value="{{$extra['annual']??''}}" inputmode="decimal"></div></div>
<label>Package features · one per line</label><textarea name="features" rows="5">{{implode("\n",$extra['features']??[])}}</textarea><label class="check-row"><input type="checkbox" name="featured" value="1" @checked(!empty($extra['featured']))> Highlight this package</label>
@elseif($kind==='feature')
<label>Feature icon<select name="icon">@foreach(['clock','shop','pin','report','people','check'] as $icon)<option value="{{$icon}}" @selected(($extra['icon']??'check')===$icon)>{{$icon}}</option>@endforeach</select></label>
@endif
<label class="check-row"><input type="checkbox" name="active" value="1" @checked($item->active)> Show on the public website</label><div class="form-actions"><button class="secondary">Save item</button></div></form>
<form method="post" action="/platform/website/content/{{$item->id}}/delete" data-confirm="Permanently delete this website item?">@csrf<button class="danger">Delete item</button></form></details>
@endforeach
<details class="disclosure"><summary>Add a {{$kind==='plan'?'package':($kind==='feature'?'feature':'question')}}</summary><form method="post" action="/platform/website/content">@csrf<input type="hidden" name="kind" value="{{$kind}}">
<div class="form-two"><div><label>Title</label><input name="title" maxlength="150" required></div><div><label>Display order</label><input name="position" type="number" value="100" min="-10000" max="10000" required></div></div><label>Description / answer</label><textarea name="body" rows="3" maxlength="3000" required></textarea>
@if($kind==='plan')<div class="form-two"><div><label>Audience label</label><input name="audience" maxlength="100"></div><div><label>Monthly price (£)</label><input name="monthly" inputmode="decimal"></div></div><div class="form-two"><div><label>Yearly total (£)</label><input name="annual" inputmode="decimal"></div><div><label>Package features · one per line</label><textarea name="features" rows="3"></textarea></div></div><label class="check-row"><input type="checkbox" name="featured" value="1"> Highlight this package</label>
@elseif($kind==='feature')<label>Feature icon<select name="icon">@foreach(['clock','shop','pin','report','people','check'] as $icon)<option value="{{$icon}}">{{$icon}}</option>@endforeach</select></label>@endif
<label class="check-row"><input type="checkbox" name="active" value="1" checked> Show on the public website</label><div class="form-actions"><button>Add item</button></div></form></details>
</section>
@endforeach

<section class="card"><div class="card-head"><div><h2>Incoming website requests</h2><p>General enquiries and private trial requests have separate queues for easier follow-up.</p></div></div><div class="actions"><a class="button secondary" href="{{url('/platform/general-enquiries')}}">Open general enquiries ↗</a><a class="button secondary" href="{{url('/platform/trial-requests')}}">Open trial requests ↗</a></div></section>
<p class="caption">Website requests are retained for 90 days and then removed by the daily scheduled cleanup.</p>
@endsection

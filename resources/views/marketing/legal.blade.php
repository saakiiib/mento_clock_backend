@include('marketing.partials.header')
@php($legalTitles = ['privacy' => 'Privacy notice', 'terms' => 'Website terms', 'cookies' => 'Cookie policy'])
@php($settingKey = ['privacy' => 'privacy_text', 'terms' => 'terms_text', 'cookies' => 'cookie_text'][$page])
<main id="main" class="section"><article class="wrap legal"><span class="eyebrow">Clear information</span><h1>{{ $legalTitles[$page] }}</h1><div class="legal-copy">{!! nl2br(e($s[$settingKey] ?? '')) !!}</div><p>Contact: <a href="mailto:{{ e($s['contact_email']) }}">{{ e($s['contact_email']) }}</a></p></article></main>
@include('marketing.partials.footer')

@include('marketing.partials.header')
<main id="main" class="section"><article class="wrap legal"><span class="eyebrow">Clear information</span><h1>{{ $page === 'privacy' ? 'Privacy notice' : 'Website terms' }}</h1><div class="legal-copy">{!! nl2br(e($s[$page === 'privacy' ? 'privacy_text' : 'terms_text'])) !!}</div><p>Contact: <a href="mailto:{{ e($s['contact_email']) }}">{{ e($s['contact_email']) }}</a></p></article></main>
@include('marketing.partials.footer')

@include('marketing.partials.header')
<main id="main">
<section class="section trial-success-section"><div class="wrap"><article class="trial-success-card">
<span class="trial-success-mark" aria-hidden="true">✓</span>
<span class="eyebrow">Your request is safely recorded</span>
<h1>Thank you, {{ $firstName }}.</h1>
<p>We’ve saved your private trial request for the Mento Software team. To send us the details on WhatsApp as well, use the button below.</p>
<div class="trial-success-actions"><a class="button" href="{{ $whatsappUrl }}" target="_blank" rel="noopener">Open WhatsApp and send request <span aria-hidden="true">↗</span></a><a class="text-link" href="/">Back to MentoClock</a></div>
<p class="caption">WhatsApp will open with a prepared message. Please tap <strong>Send</strong> in WhatsApp to complete it. Your request is already saved if you choose to contact us later.</p>
</article></div></section>
</main>
@include('marketing.partials.footer')

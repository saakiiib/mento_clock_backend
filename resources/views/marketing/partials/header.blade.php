@php
$base = \App\Support\WebsiteSeo::base($s);
$seo = \App\Support\WebsiteSeo::settings();
$path = $canonicalPath ?? '/';
$metaTitle = $title ?? $s['seo_title'] ?? 'MentoClock';
$metaDescription = $description ?? $s['seo_description'] ?? '';
$canonical = ($canonicalOverride ?? null) ?: $base.$path;
$ogTitle = ($socialTitle ?? null) ?: ($path==='/' ? ($seo['seo_social_title']??'') : '') ?: $metaTitle;
$ogDescription = ($socialDescription ?? null) ?: ($path==='/' ? ($seo['seo_social_description']??'') : '') ?: $metaDescription;
$ogImage = $base.(($socialImage??null) ?: '/marketing/assets/social-card.jpg');
@endphp
<!doctype html><html lang="en-GB"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $metaTitle }}</title><meta name="description" content="{{ $metaDescription }}">
<meta name="robots" content="{{ !empty($noindex) ? 'noindex,nofollow' : 'index,follow,max-image-preview:large' }}">
@if(empty($noindex))<link rel="canonical" href="{{ $canonical }}">@endif
@if(!empty($seo['seo_verification']))<meta name="google-site-verification" content="{{ $seo['seo_verification'] }}">@endif
<meta name="theme-color" content="#F37021"><meta property="og:site_name" content="MentoClock"><meta property="og:locale" content="en_GB"><meta property="og:title" content="{{ $ogTitle }}"><meta property="og:description" content="{{ $ogDescription }}"><meta property="og:type" content="{{ isset($page) && is_object($page) && $page->kind==='blog' ? 'article' : 'website' }}"><meta property="og:url" content="{{ $canonical }}"><meta property="og:image" content="{{ $ogImage }}"><meta property="og:image:alt" content="MentoClock staff attendance software"><meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="{{ $ogTitle }}"><meta name="twitter:description" content="{{ $ogDescription }}"><meta name="twitter:image" content="{{ $ogImage }}">
<link rel="icon" href="/favicon.ico" sizes="any"><link rel="icon" type="image/png" sizes="48x48" href="/favicon-48.png"><link rel="icon" type="image/png" sizes="192x192" href="/favicon-192.png"><link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
@if(empty($noindex))<script type="application/ld+json">{!! \App\Support\WebsiteSeo::json(\App\Support\WebsiteSeo::schema($s,$path,$metaTitle,isset($page)&&is_object($page)?$page:null)) !!}</script>@endif
<link rel="stylesheet" href="/marketing/assets/site.css?v=20261124-seo"><link rel="stylesheet" href="/marketing/assets/seo.css?v=1"><script src="/marketing/assets/site.js?v=20261124-seo" defer></script></head><body><a class="skip-link" href="#main">Skip to content</a><header class="site-header"><div class="wrap nav"><a class="brand" href="/" aria-label="MentoClock home"><img src="/marketing/assets/logo.png" alt="MentoClock"></a><button class="menu-toggle" aria-label="Open navigation" aria-expanded="false" aria-controls="main-nav"><?=\App\Support\MarketingSite::icon('menu')?></button><nav id="main-nav" aria-label="Main navigation"><a href="/features">Features</a><a href="/industries">Industries</a><a href="/blog">Resources</a><a href="/#how-it-works">How it works</a><a href="/#pricing">Pricing</a><a href="/#faq">FAQs</a><?php if($s['portal_url']):?><a href="<?=e($s['portal_url'])?>" rel="noopener">Client login</a><?php endif;?><a class="button small" href="/#trial-request" data-open-trial>Request a trial <?=\App\Support\MarketingSite::icon('arrow')?></a></nav></div></header>

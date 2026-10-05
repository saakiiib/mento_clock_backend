@php
$paths = [
 'home'=>'<path d="m3 10 9-7 9 7v10H3zM9 20v-7h6v7"/>',
 'people'=>'<circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2M16 5a3 3 0 0 1 0 6M18 14a5 5 0 0 1 3 4v2"/>',
 'shop'=>'<path d="m3 10 2-6h14l2 6M4 10v10h16V10M9 20v-7h6v7M3 10c0 3 4 3 4 0 0 3 5 3 5 0 0 3 5 3 5 0 0 3 4 3 4 0"/>',
 'clock'=>'<circle cx="12" cy="12" r="8"/><path d="M12 7v5l3 2"/>',
 'report'=>'<path d="M6 3h9l4 4v14H6zM9 10h7M9 14h7M9 18h4"/>',
 'building'=>'<path d="M5 21V3h14v18M3 21h18M9 7h1M14 7h1M9 11h1M14 11h1M9 15h1M14 15h1M10 21v-3h4v3"/>',
 'arrow'=>'<path d="M5 12h14M14 7l5 5-5 5"/>',
 'plus'=>'<path d="M12 5v14M5 12h14"/>',
 'shield'=>'<path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6zM8 12l3 3 5-6"/>',
 'menu'=>'<path d="M4 6h16M4 12h16M4 18h16"/>',
 'logout'=>'<path d="M10 4H4v16h6M9 12h12M17 8l4 4-4 4"/>',
 'pin'=>'<path d="M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 1 1 14 0z"/><circle cx="12" cy="10" r="2"/>',
 'check'=>'<path d="m5 12 4 4L19 6"/>',
 'mail'=>'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/>',
 'eye'=>'<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
];
@endphp
<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $paths[$name] ?? $paths['check'] !!}</svg>

<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class MarketingSite
{
    public static function settings(): array
    {
        return DB::table('marketing_site_settings')->pluck('value', 'key')->all();
    }

    public static function items(string $kind, bool $all = false): array
    {
        $query = DB::table('marketing_site_content')->where('kind', $kind);
        if (!$all) $query->where('active', true);
        return $query->orderBy('position')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
    }

    public static function extra(array $row): array
    {
        $value = $row['extras'] ?? '{}';
        return is_array($value) ? $value : (json_decode((string) $value, true) ?: []);
    }

    public static function whatsapp(array $settings, string $message = ''): string
    {
        $number = preg_replace('/\D+/', '', $settings['whatsapp_number'] ?? '447745975978');
        return 'https://wa.me/'.$number.($message === '' ? '' : '?text='.rawurlencode($message));
    }

    public static function icon(string $name): string
    {
        $paths = [
            'clock' => '<circle cx="12" cy="12" r="8"/><path d="M12 7v5l3 2"/>',
            'shop' => '<path d="M3 10l2-6h14l2 6M4 10v10h16V10M9 20v-7h6v7"/><path d="M3 10c0 3 4 3 4 0 0 3 5 3 5 0 0 3 5 3 5 0 0 3 4 3 4 0"/>',
            'pin' => '<path d="M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 1114 0z"/><circle cx="12" cy="10" r="2"/>',
            'report' => '<path d="M6 3h9l4 4v14H6zM9 10h7M9 14h7M9 18h4"/>',
            'people' => '<circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0112 0v2M16 5a3 3 0 010 6M18 14a5 5 0 013 4v2"/>',
            'check' => '<path d="M5 12l4 4L19 6"/><path d="M20 13v7H4V4h10"/>',
            'arrow' => '<path d="M5 12h14M14 7l5 5-5 5"/>',
            'whatsapp' => '<path d="M20 11a8 8 0 01-12 7l-5 2 2-5a8 8 0 1115-4z"/><path d="M8 7c0 5 4 9 9 9l1-2-3-2-1 1-3-3 1-1-2-3z"/>',
            'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        ];
        return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name] ?? $paths['check']).'</svg>';
    }
}

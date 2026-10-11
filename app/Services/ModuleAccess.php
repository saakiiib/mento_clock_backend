<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ModuleAccess
{
    public function enabled(int $businessId, string $moduleCode): bool
    {
        return DB::table('business_modules as bm')
            ->join('modules as m', 'm.id', '=', 'bm.module_id')
            ->where('bm.business_id', $businessId)
            ->where('m.code', $moduleCode)
            ->where('m.available', true)
            ->where('bm.status', 'active')
            ->where(fn ($query) => $query->whereNull('bm.starts_at')->orWhere('bm.starts_at', '<=', now('UTC')))
            ->where(fn ($query) => $query->whereNull('bm.ends_at')->orWhere('bm.ends_at', '>', now('UTC')))
            ->exists();
    }
}

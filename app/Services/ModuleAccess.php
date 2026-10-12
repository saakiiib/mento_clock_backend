<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ModuleAccess
{
    public function enabled(int $businessId, string $moduleCode): bool
    {
        if ($this->directlyEnabled($businessId, $moduleCode)) return true;

        // The client-facing product is sold and enabled as two modules:
        // Rota includes its templates/availability tools, and Leave includes
        // the holiday calendar and closure management.
        $parentModule = match ($moduleCode) {
            'rota_advanced' => 'rota_shifts',
            'holiday_calendar' => 'leave_management',
            default => null,
        };

        return $parentModule !== null && $this->directlyEnabled($businessId, $parentModule);
    }

    private function directlyEnabled(int $businessId, string $moduleCode): bool
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

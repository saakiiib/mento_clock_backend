<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // V24 accounts may already contain businesses with shared ownership,
        // while older employee records still have a null account link.
        foreach (DB::table('businesses')->whereNotNull('client_account_id')->orderBy('id')
            ->get(['id', 'client_account_id']) as $business) {
            DB::table('users')->where('business_id', $business->id)->whereNull('client_account_id')
                ->update(['client_account_id' => $business->client_account_id]);
        }
    }

    public function down(): void
    {
        // Keep the populated ownership links; removing them would restore the
        // transfer bug and could erase intentional account associations.
    }
};

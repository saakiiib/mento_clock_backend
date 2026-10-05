<?php
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\DB;
Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::call(fn () => DB::table('marketing_site_enquiries')->where('created_at', '<', now('UTC')->subDays(90))->delete())->daily();

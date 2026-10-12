<?php
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::call(fn () => DB::table('marketing_site_enquiries')->where('created_at', '<', now('UTC')->subDays(90))->delete())->daily();
Artisan::command('hr:generate-reminders', function () {
    $created = app(\App\Services\HrReminderService::class)->run();
    $this->info($created.' new HR reminder(s) generated.');
})->purpose('Generate tenant-scoped HR reminders and send configured email digests.');
Schedule::command('hr:generate-reminders')->name('hr-generate-reminders')->dailyAt('07:00')->timezone('Europe/London')->withoutOverlapping();
Schedule::call(fn () => Log::info('Cron executed: schedule:run at ' . now()))->name('cron-check-log')->everyMinute();
Schedule::call(fn () => app(\App\Services\EmployeeLifecycle::class)->processDueOffboarding())->name('process-due-offboarding')->everyFifteenMinutes()->timezone('UTC')->withoutOverlapping();

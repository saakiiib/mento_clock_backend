<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Services\V39Notifications;
class V39PublishNotifications extends Command {
 protected $signature='v39:publish-due';
 protected $description='Publish due scheduled V39 announcements';
 public function handle(): int {$n=V39Notifications::publishDue();$this->info('V39 recipient rows: '.$n);return self::SUCCESS;}
}

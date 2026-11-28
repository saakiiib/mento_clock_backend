<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Services\V39Push;
class V39PushNotifications extends Command {
 protected $signature='v39:push-pending {--limit=100}';
 protected $description='Send opt-in Firebase pushes for saved V39 inbox announcements';
 public function handle(): int {$sent=V39Push::sendPending((int)$this->option('limit'));$this->info('Push recipients: '.$sent);return self::SUCCESS;}
}

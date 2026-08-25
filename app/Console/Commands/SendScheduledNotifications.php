<?php

namespace App\Console\Commands;

use App\Jobs\SendEmailNotificationJob;
use App\Models\Notifications\Notification;
use Illuminate\Console\Command;

class SendScheduledNotifications extends Command
{
    protected $signature = 'app:send-scheduled-notifications';

    protected $description = 'Envia notificaciones programadas';

    public function handle()
    {
        $today = now()->toDateString();
        $time = now()->format('H:i:s');

        $notifications = Notification::query()
            ->whereHas('channel', function ($channelQuery) {
                $channelQuery->where('code', 'email');
            })
            ->whereHas('status', function ($statusQuery) {
                $statusQuery->where('code', 'scheduled');
            })
            ->where(function ($query) use ($today, $time) {
                $query->where('scheduled_date', '<', $today)
                    ->orWhere(function ($sameDayQuery) use ($today, $time) {
                        $sameDayQuery->where('scheduled_date', $today)
                            ->where('scheduled_time', '<=', $time);
                    });
            })
            ->get();

        foreach ($notifications as $notification) {
            SendEmailNotificationJob::dispatchSync($notification->id);
        }

        return 0;
    }
}

<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Services\Notifications\FcmPushService;
use Illuminate\Notifications\Notification;

class PushNotificationChannel
{
    public function __construct(private readonly FcmPushService $push) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User || ! method_exists($notification, 'toPush')) {
            return;
        }

        $this->push->queueForUser($notifiable, $notification->toPush($notifiable));
    }
}

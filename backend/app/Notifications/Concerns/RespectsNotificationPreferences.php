<?php

namespace App\Notifications\Concerns;

use App\Models\NotificationPreference;
use App\Models\NotificationType;
use App\Models\User;
use App\Notifications\Channels\PushNotificationChannel;
use App\Services\Notifications\FcmPushService;

trait RespectsNotificationPreferences
{
    /** @return list<string> */
    protected function channelsFor(User $user, string $typeSlug): array
    {
        $type = NotificationType::query()->where('slug', $typeSlug)->first();
        if ($type === null) {
            return ['database'];
        }
        $preferences = NotificationPreference::query()->where('user_id', $user->id)
            ->where('notification_type_id', $type->id)->get()->keyBy('channel');
        $enabled = fn (string $channel): bool => $preferences->get($channel)?->enabled ?? $type->default_enabled;
        $channels = [];
        if ($enabled('in_app')) {
            $channels[] = 'database';
        }
        if ($enabled('email') && $user->hasVerifiedEmail()) {
            $channels[] = 'mail';
        }
        if ($enabled('push') && app(FcmPushService::class)->isConfigured() && $user->pushDevices()->exists()) {
            $channels[] = PushNotificationChannel::class;
        }

        return $channels;
    }
}

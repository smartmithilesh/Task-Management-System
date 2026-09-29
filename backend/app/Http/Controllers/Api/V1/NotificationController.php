<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use App\Models\NotificationType;
use App\Services\Notifications\FcmPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->notifications()->latest();
        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        return response()->json($query->paginate(30));
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $target = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $target->markAsRead();

        return response()->json(['success' => true]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function preferences(Request $request, FcmPushService $push): JsonResponse
    {
        $types = NotificationType::query()->orderBy('category')->orderBy('name')->get();
        $preferences = NotificationPreference::query()->where('user_id', $request->user()->id)->get()
            ->keyBy(fn (NotificationPreference $preference): string => $preference->notification_type_id.':'.$preference->channel);

        return response()->json(['data' => $types->map(fn (NotificationType $type): array => [
            'id' => $type->public_id,
            'slug' => $type->slug,
            'name' => $type->name,
            'category' => $type->category,
            'channels' => collect($this->availableChannels($push))->map(fn (string $channel): array => [
                'channel' => $channel,
                'enabled' => $preferences->get($type->id.':'.$channel)?->enabled ?? $type->default_enabled,
            ])->all(),
        ])]);
    }

    public function updatePreferences(Request $request, FcmPushService $push): JsonResponse
    {
        $channels = $this->availableChannels($push);
        $validated = $request->validate([
            'preferences' => ['required', 'array', 'min:1', 'max:100'],
            'preferences.*.type_id' => ['required', 'uuid', Rule::exists('notification_types', 'public_id')],
            'preferences.*.channel' => ['required', Rule::in($channels)],
            'preferences.*.enabled' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $validated): void {
            foreach ($validated['preferences'] as $preference) {
                $typeId = NotificationType::query()->where('public_id', $preference['type_id'])->value('id');
                NotificationPreference::query()->updateOrCreate(
                    ['user_id' => $request->user()->id, 'notification_type_id' => $typeId, 'channel' => $preference['channel']],
                    ['enabled' => $preference['enabled']],
                );
            }
        });

        return response()->json(['success' => true]);
    }

    /** @return list<string> */
    private function availableChannels(FcmPushService $push): array
    {
        return $push->isConfigured() ? ['in_app', 'email', 'push'] : ['in_app', 'email'];
    }
}

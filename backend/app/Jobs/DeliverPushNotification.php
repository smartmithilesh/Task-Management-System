<?php

namespace App\Jobs;

use App\Models\PushDevice;
use App\Services\Notifications\FcmPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeliverPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @param array{title: string, body: string, data?: array<string, scalar|null>} $payload */
    public function __construct(public readonly string $devicePublicId, public readonly array $payload) {}

    public int $tries = 4;

    /** @var list<int> */
    public array $backoff = [15, 60, 300];

    public function handle(FcmPushService $push): void
    {
        $device = PushDevice::query()->where('public_id', $this->devicePublicId)->first();
        if ($device === null) {
            return;
        }

        $push->deliver($device, $this->payload);
    }
}

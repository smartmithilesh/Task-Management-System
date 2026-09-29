<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PushDevice;
use App\Services\Notifications\FcmPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PushDeviceController extends Controller
{
    public function store(Request $request, FcmPushService $push): JsonResponse
    {
        abort_unless($push->isConfigured(), 503, 'Push notifications are not configured.');
        $validated = $request->validate([
            'device_id' => ['required', 'uuid'],
            'platform' => ['required', Rule::in(['android', 'ios'])],
            'token' => ['required', 'string', 'min:20', 'max:4096'],
        ]);
        $user = $request->user();
        $tokenHash = hash('sha256', $validated['token']);

        $device = DB::transaction(function () use ($user, $validated, $tokenHash): PushDevice {
            $sameToken = PushDevice::query()->where('token_hash', $tokenHash)->lockForUpdate()->first();
            $sameDevice = $user->pushDevices()->where('device_id', $validated['device_id'])->lockForUpdate()->first();
            if ($sameToken !== null && $sameDevice !== null && $sameToken->isNot($sameDevice)) {
                $sameToken->delete();
                $sameToken = null;
            }
            $device = $sameToken ?? $sameDevice ?? new PushDevice;
            $device->fill([
                'user_id' => $user->id,
                'device_id' => $validated['device_id'],
                'platform' => $validated['platform'],
                'token_hash' => $tokenHash,
                'encrypted_token' => $validated['token'],
                'last_seen_at' => now(),
            ])->save();

            return $device;
        });

        return response()->json(['data' => ['id' => $device->public_id, 'registered' => true]], 201);
    }

    public function destroy(Request $request, string $device): JsonResponse
    {
        validator(['device' => $device], ['device' => ['required', 'uuid']])->validate();
        $request->user()->pushDevices()->where('device_id', $device)->delete();

        return response()->json(['success' => true]);
    }
}

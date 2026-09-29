<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AuthorizesOrganizationRequests;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ActivityLogResource;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    use AuthorizesOrganizationRequests;

    public function task(Request $request, string $task): JsonResponse
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'tasks.view');
        $taskModel = Task::query()
            ->where('organization_id', $this->requireOrganization($actor))
            ->where('public_id', $task)
            ->firstOrFail();

        return ActivityLogResource::collection($taskModel->activityLogs()
            ->with('actor')
            ->orderByDesc('occurred_at')
            ->paginate(min(max($request->integer('per_page', 25), 1), 100)))
            ->response();
    }
}

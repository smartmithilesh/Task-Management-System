<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AuthorizesOrganizationRequests;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TimeEntryResource;
use App\Models\ActivityLog;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\Integrations\IntegrationEventDispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TimeEntryController extends Controller
{
    use AuthorizesOrganizationRequests;

    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        $organizationId = $this->requireOrganization($actor);
        $query = TimeEntry::query()->whereHas('task', fn (Builder $taskQuery) => $taskQuery->where('organization_id', $organizationId));

        if (! $request->boolean('all')) {
            $query->where('user_id', $actor->id);
        } else {
            $this->authorizePermission($actor, 'time_entries.view');
        }

        if ($request->filled('task_id')) {
            $taskId = Task::query()->where('organization_id', $organizationId)->where('public_id', $request->string('task_id'))->value('id');
            abort_if($taskId === null, 404);
            $query->where('task_id', $taskId);
        }

        return TimeEntryResource::collection($query->with(['task', 'user'])->orderByDesc('started_at')->paginate(50))->response();
    }

    public function start(Request $request, string $task): JsonResponse
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'tasks.edit');
        $taskModel = $this->organizationTask($task, $this->requireOrganization($actor));
        $validated = $request->validate(['description' => ['nullable', 'string', 'max:500']]);

        $entry = DB::transaction(function () use ($actor, $taskModel, $validated): TimeEntry {
            User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            abort_if(TimeEntry::query()->where('user_id', $actor->id)->whereNull('ended_at')->exists(), 409, 'Stop your running timer before starting another one.');

            $entry = TimeEntry::query()->create([
                'task_id' => $taskModel->id,
                'user_id' => $actor->id,
                'started_at' => now(),
                'description' => $validated['description'] ?? null,
            ]);
            $this->recordActivity($taskModel, $actor, 'timer_started', ['time_entry_id' => $entry->public_id]);

            return $entry;
        });

        return (new TimeEntryResource($entry->load(['task', 'user'])))->response()->setStatusCode(201);
    }

    public function stop(Request $request, string $task): TimeEntryResource
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'tasks.edit');
        $taskModel = $this->organizationTask($task, $this->requireOrganization($actor));
        $entry = TimeEntry::query()->where('task_id', $taskModel->id)
            ->where('user_id', $actor->id)
            ->whereNull('ended_at')
            ->firstOrFail();

        DB::transaction(function () use ($entry, $taskModel, $actor): void {
            $endedAt = now();
            $entry->update([
                'ended_at' => $endedAt,
                'duration_seconds' => max(0, Carbon::parse($entry->started_at)->diffInSeconds($endedAt, false)),
            ]);
            $this->recordActivity($taskModel, $actor, 'timer_stopped', ['time_entry_id' => $entry->public_id]);
        });

        return new TimeEntryResource($entry->load(['task', 'user']));
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'tasks.edit');
        $organizationId = $this->requireOrganization($actor);
        $validated = $request->validate([
            'task_id' => ['required', 'uuid', Rule::exists('tasks', 'public_id')->where('organization_id', $organizationId)],
            'started_at' => ['required', 'date'],
            'ended_at' => ['required', 'date', 'after:started_at'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);
        $task = Task::query()->where('organization_id', $organizationId)->where('public_id', $validated['task_id'])->firstOrFail();
        $startedAt = Carbon::parse($validated['started_at']);
        $endedAt = Carbon::parse($validated['ended_at']);
        $entry = $task->timeEntries()->create([
            'user_id' => $actor->id,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'duration_seconds' => max(0, $startedAt->diffInSeconds($endedAt, false)),
            'description' => $validated['description'] ?? null,
        ]);
        $this->recordActivity($task, $actor, 'time_logged', ['time_entry_id' => $entry->public_id]);

        return (new TimeEntryResource($entry->load(['task', 'user'])))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, string $entry): JsonResponse
    {
        $actor = $request->user();
        $organizationId = $this->requireOrganization($actor);
        $target = TimeEntry::query()->where('public_id', $entry)
            ->whereHas('task', fn (Builder $query) => $query->where('organization_id', $organizationId))
            ->firstOrFail();
        abort_unless($target->user_id === $actor->id || $actor->hasPermission('time_entries.manage'), 403);
        abort_if($target->ended_at === null, 409, 'Stop the running timer before deleting this entry.');
        $target->delete();

        return response()->json(['success' => true, 'message' => 'Time entry deleted.']);
    }

    private function organizationTask(string $publicId, int $organizationId): Task
    {
        return Task::query()->where('organization_id', $organizationId)->where('public_id', $publicId)->firstOrFail();
    }

    private function recordActivity(Task $task, User $actor, string $action, array $properties): void
    {
        ActivityLog::query()->create([
            'organization_id' => $task->organization_id,
            'actor_id' => $actor->id,
            'subject_type' => Task::class,
            'subject_id' => $task->id,
            'action' => $action,
            'properties' => $properties,
        ]);
        app(IntegrationEventDispatcher::class)->dispatch($task->organization_id, 'task.'.$action, [
            'task_id' => $task->public_id,
            'task_number' => $task->task_number,
            'actor' => ['id' => $actor->public_id, 'name' => $actor->name],
        ]);
    }
}

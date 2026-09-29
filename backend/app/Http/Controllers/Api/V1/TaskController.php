<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AuthorizesOrganizationRequests;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TaskResource;
use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use App\Services\Integrations\IntegrationEventDispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    use AuthorizesOrganizationRequests;

    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'tasks.view');
        $organizationId = $this->requireOrganization($actor);

        $tasks = Task::query()
            ->where('organization_id', $organizationId)
            ->with(['project', 'parent', 'status', 'priority', 'owner', 'assignees'])
            ->when($request->filled('project_id'), function (Builder $query) use ($request, $organizationId): void {
                $query->whereHas('project', fn (Builder $projectQuery) => $projectQuery
                    ->where('organization_id', $organizationId)
                    ->where('public_id', $request->string('project_id')->toString()));
            })
            ->when($request->filled('status'), function (Builder $query) use ($request): void {
                $status = $request->string('status')->toString();
                $query->whereHas('status', fn (Builder $statusQuery) => $statusQuery
                    ->where('slug', $status)->orWhere('public_id', $status));
            })
            ->when($request->boolean('assigned_to_me'), fn (Builder $query) => $query->whereHas('assignees', fn (Builder $assignees) => $assignees->where('users.id', $request->user()->id)))
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = '%'.$request->string('search')->trim()->toString().'%';
                $query->where(fn (Builder $query) => $query->where('title', 'like', $search)->orWhere('task_number', 'like', $search));
            })
            ->when($request->filled('due_before'), fn (Builder $query) => $query->whereDate('due_at', '<=', $request->date('due_before')))
            ->orderByRaw('due_at IS NULL')
            ->orderBy('due_at')
            ->orderByDesc('updated_at')
            ->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return TaskResource::collection($tasks)->response();
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'tasks.create');
        $organizationId = $this->requireOrganization($actor);
        $project = Project::query()->where('organization_id', $organizationId)
            ->where('public_id', $request->input('project_id'))
            ->firstOrFail();
        $validated = $request->validate([
            'project_id' => ['required', 'uuid'],
            'parent_task_id' => ['nullable', 'uuid', Rule::exists('tasks', 'public_id')->where('organization_id', $organizationId)->where('project_id', $project->id)],
            'title' => ['required', 'string', 'max:220'],
            'description' => ['nullable', 'string', 'max:60000'],
            'owner_id' => ['nullable', 'uuid', Rule::exists('users', 'public_id')->where('organization_id', $organizationId)],
            'status_id' => ['sometimes', 'uuid'],
            'priority_id' => ['nullable', 'uuid'],
            'category_id' => ['nullable', 'uuid', Rule::exists('task_categories', 'public_id')->where('organization_id', $organizationId)],
            'starts_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'progress' => ['sometimes', 'integer', 'between:0,100'],
            'assignee_ids' => ['sometimes', 'array'],
            'assignee_ids.*' => ['uuid', 'distinct', Rule::exists('users', 'public_id')->where('organization_id', $organizationId)],
        ]);
        $statusId = $this->resolveStatusId($validated['status_id'] ?? null, $organizationId);
        $priorityId = $this->resolvePriorityId($validated['priority_id'] ?? null, $organizationId);
        $categoryId = $this->resolveCategoryId($validated['category_id'] ?? null, $organizationId);

        $task = DB::transaction(function () use ($validated, $actor, $organizationId, $project, $statusId, $priorityId, $categoryId): Task {
            Organization::query()->whereKey($organizationId)->lockForUpdate()->firstOrFail();
            $task = Task::query()->create([
                'organization_id' => $organizationId,
                'project_id' => $project->id,
                'task_number' => $this->nextTaskNumber($organizationId),
                'title' => trim($validated['title']),
                'description' => $validated['description'] ?? null,
                'parent_task_id' => isset($validated['parent_task_id'])
                    ? Task::query()->where('public_id', $validated['parent_task_id'])->value('id')
                    : null,
                'created_by' => $actor->id,
                'owner_id' => isset($validated['owner_id']) ? User::query()->where('public_id', $validated['owner_id'])->value('id') : null,
                'status_id' => $statusId,
                'priority_id' => $priorityId,
                'category_id' => $categoryId,
                'starts_at' => $validated['starts_at'] ?? null,
                'due_at' => $validated['due_at'] ?? null,
                'estimated_hours' => $validated['estimated_hours'] ?? null,
                'progress' => $validated['progress'] ?? 0,
            ]);
            $this->syncAssignees($task, $validated['assignee_ids'] ?? [], $organizationId, $actor->id);
            $this->recordActivity($task, $actor, 'created', ['task_number' => $task->task_number, 'title' => $task->title]);

            return $task;
        });

        return (new TaskResource($this->loadTask($task)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, string $task): TaskResource
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'tasks.view');
        $target = $this->organizationTask($task, $this->requireOrganization($actor));

        return new TaskResource($this->loadTask($target));
    }

    public function update(Request $request, string $task): TaskResource
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'tasks.edit');
        $organizationId = $this->requireOrganization($actor);
        $target = $this->organizationTask($task, $organizationId);
        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:220'],
            'description' => ['sometimes', 'nullable', 'string', 'max:60000'],
            'parent_task_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('tasks', 'public_id')->where('organization_id', $organizationId)->where('project_id', $target->project_id)],
            'owner_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('users', 'public_id')->where('organization_id', $organizationId)],
            'status_id' => ['sometimes', 'uuid'],
            'priority_id' => ['sometimes', 'nullable', 'uuid'],
            'category_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('task_categories', 'public_id')->where('organization_id', $organizationId)],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'due_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'estimated_hours' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999.99'],
            'progress' => ['sometimes', 'integer', 'between:0,100'],
            'assignee_ids' => ['sometimes', 'array'],
            'assignee_ids.*' => ['uuid', 'distinct', Rule::exists('users', 'public_id')->where('organization_id', $organizationId)],
        ]);
        $attributes = $validated;
        $assigneePublicIds = $attributes['assignee_ids'] ?? null;
        unset($attributes['assignee_ids']);

        if (array_key_exists('parent_task_id', $attributes) && $attributes['parent_task_id'] !== null) {
            $ancestor = Task::query()->where('organization_id', $organizationId)
                ->where('project_id', $target->project_id)
                ->where('public_id', $attributes['parent_task_id'])
                ->firstOrFail();
            while ($ancestor !== null) {
                abort_if($ancestor->id === $target->id, 422, 'A task cannot be made its own ancestor.');
                $ancestor = $ancestor->parent_task_id === null ? null : Task::query()->find($ancestor->parent_task_id);
            }
        }

        foreach (['owner_id' => User::class, 'status_id' => TaskStatus::class, 'priority_id' => TaskPriority::class, 'category_id' => TaskCategory::class, 'parent_task_id' => Task::class] as $field => $model) {
            if (array_key_exists($field, $attributes)) {
                $attributes[$field] = $attributes[$field] === null ? null : $model::query()->where('public_id', $attributes[$field])->value('id');
            }
        }
        if (isset($attributes['status_id'])) {
            $attributes['status_id'] = $this->resolveStatusId($validated['status_id'], $organizationId);
        }
        if (array_key_exists('priority_id', $validated)) {
            $attributes['priority_id'] = $this->resolvePriorityId($validated['priority_id'], $organizationId);
        }
        if (array_key_exists('parent_task_id', $validated) && $attributes['parent_task_id'] === $target->id) {
            abort(422, 'A task cannot be its own parent.');
        }
        if (isset($attributes['title'])) {
            $attributes['title'] = trim($attributes['title']);
        }

        DB::transaction(function () use ($target, $attributes, $assigneePublicIds, $organizationId, $actor): void {
            $changes = collect($attributes)->mapWithKeys(fn (mixed $value, string $key): array => [
                $key => ['old' => $target->getAttribute($key), 'new' => $value],
            ])->all();
            $target->update($attributes);
            if ($assigneePublicIds !== null) {
                $this->syncAssignees($target, $assigneePublicIds, $organizationId, $actor->id);
            }
            $this->recordActivity($target, $actor, 'updated', $changes);
        });

        return new TaskResource($this->loadTask($target));
    }

    public function changeStatus(Request $request, string $task): TaskResource
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'tasks.change_status');
        $organizationId = $this->requireOrganization($actor);
        $target = $this->organizationTask($task, $organizationId);
        $validated = $request->validate([
            'status_id' => ['required', 'uuid'],
            'progress' => ['sometimes', 'integer', 'between:0,100'],
        ]);
        $statusId = $this->resolveStatusId($validated['status_id'], $organizationId);

        DB::transaction(function () use ($target, $statusId, $validated, $actor): void {
            $previousStatus = $target->status()->value('name');
            $target->update(['status_id' => $statusId, ...array_intersect_key($validated, ['progress' => true])]);
            $this->recordActivity($target, $actor, 'status_changed', [
                'from' => $previousStatus,
                'to' => $target->status()->value('name'),
            ]);
        });

        return new TaskResource($this->loadTask($target));
    }

    public function updateAssignees(Request $request, string $task): TaskResource
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'tasks.assign');
        $organizationId = $this->requireOrganization($actor);
        $target = $this->organizationTask($task, $organizationId);
        $validated = $request->validate([
            'assignee_ids' => ['required', 'array'],
            'assignee_ids.*' => ['required', 'uuid', 'distinct', Rule::exists('users', 'public_id')->where('organization_id', $organizationId)],
        ]);

        DB::transaction(function () use ($target, $validated, $organizationId, $actor): void {
            $this->syncAssignees($target, $validated['assignee_ids'], $organizationId, $actor->id);
            $this->recordActivity($target, $actor, 'assignees_updated', ['assignee_count' => count($validated['assignee_ids'])]);
        });

        return new TaskResource($this->loadTask($target));
    }

    public function destroy(Request $request, string $task): JsonResponse
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'tasks.delete');
        $target = $this->organizationTask($task, $this->requireOrganization($actor));

        DB::transaction(function () use ($target, $actor): void {
            $this->recordActivity($target, $actor, 'archived');
            $target->delete();
        });

        return response()->json(['success' => true, 'message' => 'Task archived.']);
    }

    private function organizationTask(string $publicId, int $organizationId): Task
    {
        return Task::query()->where('organization_id', $organizationId)->where('public_id', $publicId)->firstOrFail();
    }

    private function loadTask(Task $task): Task
    {
        return $task->load(['project', 'parent', 'status', 'priority', 'owner', 'assignees', 'tags', 'checklists.items']);
    }

    private function resolveStatusId(?string $publicId, int $organizationId): int
    {
        $status = TaskStatus::query()
            ->where(function (Builder $query) use ($organizationId): void {
                $query->whereNull('organization_id')->orWhere('organization_id', $organizationId);
            })
            ->when($publicId !== null, fn (Builder $query) => $query->where('public_id', $publicId), fn (Builder $query) => $query->where('is_default', true))
            ->orderByRaw('organization_id IS NULL')
            ->first();

        abort_if($status === null, 422, 'No task status is configured for this organization.');

        return $status->id;
    }

    private function resolvePriorityId(?string $publicId, int $organizationId): ?int
    {
        if ($publicId === null) {
            return null;
        }

        return TaskPriority::query()
            ->where('public_id', $publicId)
            ->where(fn (Builder $query) => $query->whereNull('organization_id')->orWhere('organization_id', $organizationId))
            ->value('id') ?? abort(422, 'The selected priority is not available to this organization.');
    }

    private function resolveCategoryId(?string $publicId, int $organizationId): ?int
    {
        if ($publicId === null) {
            return null;
        }

        return TaskCategory::query()->where('organization_id', $organizationId)->where('public_id', $publicId)->value('id')
            ?? abort(422, 'The selected category is not available to this organization.');
    }

    private function nextTaskNumber(int $organizationId): string
    {
        $number = Task::withTrashed()->where('organization_id', $organizationId)->count() + 1;
        do {
            $taskNumber = 'TASK-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT);
            $number++;
        } while (Task::withTrashed()->where('organization_id', $organizationId)->where('task_number', $taskNumber)->exists());

        return $taskNumber;
    }

    /** @param list<string> $publicIds */
    private function syncAssignees(Task $task, array $publicIds, int $organizationId, int $actorId): void
    {
        $userIds = User::query()->where('organization_id', $organizationId)->whereIn('public_id', $publicIds)->pluck('id');
        $existingUserIds = $task->assignees()->pluck('users.id');
        $task->assignees()->sync($userIds->mapWithKeys(fn (int $userId): array => [
            $userId => ['assigned_by' => $actorId, 'assigned_at' => now()],
        ])->all());
        $newAssigneeIds = $userIds->diff($existingUserIds)->reject(fn (int $userId): bool => $userId === $actorId);
        if ($newAssigneeIds->isEmpty()) {
            return;
        }

        $recipients = User::query()->whereIn('id', $newAssigneeIds)->get();
        $task->loadMissing('project');
        $assignedBy = User::query()->whereKey($actorId)->value('name') ?? 'A teammate';
        $payload = [
            'id' => $task->public_id,
            'task_number' => $task->task_number,
            'title' => $task->title,
            'project' => $task->project?->name ?? '',
            'assigned_by' => $assignedBy,
        ];
        foreach ($recipients as $recipient) {
            $recipient->notify(new TaskAssignedNotification($payload));
        }
    }

    /** @param array<string, mixed>|null $properties */
    private function recordActivity(Task $task, User $actor, string $action, ?array $properties = null): void
    {
        ActivityLog::query()->create([
            'organization_id' => $task->organization_id,
            'actor_id' => $actor->id,
            'subject_type' => Task::class,
            'subject_id' => $task->id,
            'action' => $action,
            'properties' => $properties,
        ]);
        $task->loadMissing('project');
        app(IntegrationEventDispatcher::class)->dispatch($task->organization_id, 'task.'.$action, [
            'task_id' => $task->public_id,
            'task_number' => $task->task_number,
            'title' => $task->title,
            'project' => $task->project?->name,
            'description' => $task->description,
            'starts_at' => $task->starts_at?->toIso8601String(),
            'due_at' => $task->due_at?->toIso8601String(),
            'actor' => ['id' => $actor->public_id, 'name' => $actor->name],
        ]);
    }
}

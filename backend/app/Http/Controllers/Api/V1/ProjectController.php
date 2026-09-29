<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AuthorizesOrganizationRequests;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProjectResource;
use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\Project;
use App\Models\TaskPriority;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    use AuthorizesOrganizationRequests;

    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'projects.view');
        $organizationId = $this->requireOrganization($actor);

        $projects = Project::query()
            ->where('organization_id', $organizationId)
            ->with(['department', 'manager', 'priority'])
            ->withCount('tasks')
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = '%'.$request->string('search')->trim()->toString().'%';
                $query->where(fn (Builder $query) => $query->where('name', 'like', $search)->orWhere('code', 'like', $search));
            })
            ->orderByDesc('updated_at')
            ->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return ProjectResource::collection($projects)->response();
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'projects.create');
        $organizationId = $this->requireOrganization($actor);
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('projects', 'code')->where('organization_id', $organizationId)],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:20000'],
            'client_name' => ['nullable', 'string', 'max:180'],
            'department_id' => ['nullable', 'uuid', Rule::exists('departments', 'public_id')->where('organization_id', $organizationId)],
            'manager_id' => ['nullable', 'uuid', Rule::exists('users', 'public_id')->where('organization_id', $organizationId)],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['sometimes', Rule::in(['planning', 'active', 'on_hold', 'completed', 'cancelled'])],
            'priority_id' => ['nullable', 'uuid'],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'budget_currency' => ['nullable', 'string', 'size:3', 'alpha:upper'],
            'member_ids' => ['sometimes', 'array'],
            'member_ids.*' => ['uuid', 'distinct', Rule::exists('users', 'public_id')->where('organization_id', $organizationId)],
        ]);
        $this->validatePriority($validated['priority_id'] ?? null, $organizationId);

        $project = DB::transaction(function () use ($validated, $actor, $organizationId): Project {
            $attributes = $validated;
            $memberPublicIds = $attributes['member_ids'] ?? [];
            unset($attributes['member_ids']);
            $attributes['department_id'] = $this->resolveDepartmentId($attributes['department_id'] ?? null, $organizationId);
            $attributes['manager_id'] = $this->resolveUserId($attributes['manager_id'] ?? null, $organizationId);
            $attributes['priority_id'] = $this->resolvePriorityId($attributes['priority_id'] ?? null, $organizationId);
            $attributes['organization_id'] = $organizationId;
            $attributes['created_by'] = $actor->id;

            $project = Project::query()->create($attributes);
            $this->syncMembers($project, $memberPublicIds, $actor->id, $organizationId);
            $this->recordActivity($project, $actor, 'created', ['code' => $project->code, 'name' => $project->name]);

            return $project;
        });

        return (new ProjectResource($project->load(['department', 'manager', 'priority', 'members'])->loadCount('tasks')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, string $project): ProjectResource
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'projects.view');
        $target = $this->organizationProject($project, $this->requireOrganization($actor));

        return new ProjectResource($target->load(['department', 'manager', 'priority', 'members'])->loadCount('tasks'));
    }

    public function update(Request $request, string $project): ProjectResource
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'projects.edit');
        $organizationId = $this->requireOrganization($actor);
        $target = $this->organizationProject($project, $organizationId);
        $validated = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('projects', 'code')->where('organization_id', $organizationId)->ignore($target->id)],
            'name' => ['sometimes', 'required', 'string', 'max:180'],
            'description' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'client_name' => ['sometimes', 'nullable', 'string', 'max:180'],
            'department_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('departments', 'public_id')->where('organization_id', $organizationId)],
            'manager_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('users', 'public_id')->where('organization_id', $organizationId)],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'due_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['sometimes', Rule::in(['planning', 'active', 'on_hold', 'completed', 'cancelled'])],
            'priority_id' => ['sometimes', 'nullable', 'uuid'],
            'progress' => ['sometimes', 'integer', 'between:0,100'],
            'budget' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'budget_currency' => ['sometimes', 'nullable', 'string', 'size:3', 'alpha:upper'],
        ]);
        if (array_key_exists('priority_id', $validated)) {
            $this->validatePriority($validated['priority_id'], $organizationId);
            $validated['priority_id'] = $this->resolvePriorityId($validated['priority_id'], $organizationId);
        }
        if (array_key_exists('department_id', $validated)) {
            $validated['department_id'] = $this->resolveDepartmentId($validated['department_id'], $organizationId);
        }
        if (array_key_exists('manager_id', $validated)) {
            $validated['manager_id'] = $this->resolveUserId($validated['manager_id'], $organizationId);
        }

        DB::transaction(function () use ($target, $validated, $actor): void {
            $changes = collect($validated)->mapWithKeys(fn (mixed $value, string $key): array => [
                $key => ['old' => $target->getAttribute($key), 'new' => $value],
            ])->all();
            $target->update($validated);
            $this->recordActivity($target, $actor, 'updated', $changes);
        });

        return new ProjectResource($target->load(['department', 'manager', 'priority', 'members'])->loadCount('tasks'));
    }

    public function updateMembers(Request $request, string $project): ProjectResource
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'projects.edit');
        $organizationId = $this->requireOrganization($actor);
        $target = $this->organizationProject($project, $organizationId);
        $validated = $request->validate([
            'member_ids' => ['required', 'array'],
            'member_ids.*' => ['required', 'uuid', 'distinct', Rule::exists('users', 'public_id')->where('organization_id', $organizationId)],
        ]);

        DB::transaction(function () use ($target, $validated, $actor, $organizationId): void {
            $this->syncMembers($target, $validated['member_ids'], $actor->id, $organizationId);
            $this->recordActivity($target, $actor, 'members_updated', ['member_count' => count($validated['member_ids'])]);
        });

        return new ProjectResource($target->load(['department', 'manager', 'priority', 'members'])->loadCount('tasks'));
    }

    public function destroy(Request $request, string $project): JsonResponse
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'projects.delete');
        $target = $this->organizationProject($project, $this->requireOrganization($actor));
        abort_if($target->tasks()->exists(), 409, 'Archive or move this project’s tasks before archiving the project.');

        DB::transaction(function () use ($target, $actor): void {
            $this->recordActivity($target, $actor, 'archived');
            $target->delete();
        });

        return response()->json(['success' => true, 'message' => 'Project archived.']);
    }

    private function organizationProject(string $publicId, int $organizationId): Project
    {
        return Project::query()->where('organization_id', $organizationId)->where('public_id', $publicId)->firstOrFail();
    }

    private function resolveDepartmentId(?string $publicId, int $organizationId): ?int
    {
        return $publicId === null ? null : Department::query()
            ->where('organization_id', $organizationId)
            ->where('public_id', $publicId)
            ->value('id');
    }

    private function resolveUserId(?string $publicId, int $organizationId): ?int
    {
        return $publicId === null ? null : User::query()
            ->where('organization_id', $organizationId)
            ->where('public_id', $publicId)
            ->value('id');
    }

    private function validatePriority(?string $publicId, int $organizationId): void
    {
        if ($publicId === null) {
            return;
        }

        $exists = TaskPriority::query()
            ->where('public_id', $publicId)
            ->where(function (Builder $query) use ($organizationId): void {
                $query->whereNull('organization_id')->orWhere('organization_id', $organizationId);
            })
            ->exists();

        abort_unless($exists, 422, 'The selected priority is not available to this organization.');
    }

    private function resolvePriorityId(?string $publicId, int $organizationId): ?int
    {
        return $publicId === null ? null : TaskPriority::query()
            ->where('public_id', $publicId)
            ->where(function (Builder $query) use ($organizationId): void {
                $query->whereNull('organization_id')->orWhere('organization_id', $organizationId);
            })
            ->value('id');
    }

    /** @param list<string> $memberPublicIds */
    private function syncMembers(Project $project, array $memberPublicIds, int $actorId, int $organizationId): void
    {
        $members = User::query()->where('organization_id', $organizationId)->whereIn('public_id', $memberPublicIds)->pluck('id');
        $project->members()->sync($members->mapWithKeys(fn (int $memberId): array => [
            $memberId => ['invited_by' => $actorId, 'joined_at' => now()],
        ])->all());
    }

    /** @param array<string, mixed>|null $properties */
    private function recordActivity(Project $project, User $actor, string $action, ?array $properties = null): void
    {
        ActivityLog::query()->create([
            'organization_id' => $project->organization_id,
            'actor_id' => $actor->id,
            'subject_type' => Project::class,
            'subject_id' => $project->id,
            'action' => $action,
            'properties' => $properties,
        ]);
    }
}

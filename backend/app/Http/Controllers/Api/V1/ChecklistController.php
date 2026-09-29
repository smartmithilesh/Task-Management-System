<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AuthorizesOrganizationRequests;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ChecklistResource;
use App\Models\ActivityLog;
use App\Models\Checklist;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChecklistController extends Controller
{
    use AuthorizesOrganizationRequests;

    public function store(Request $request, string $task): JsonResponse
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'tasks.edit');
        $taskModel = $this->organizationTask($task, $this->requireOrganization($actor));
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'items' => ['sometimes', 'array', 'max:100'],
            'items.*.content' => ['required', 'string', 'max:500'],
        ]);

        $checklist = DB::transaction(function () use ($validated, $taskModel, $actor): Checklist {
            $checklist = $taskModel->checklists()->create([
                'title' => trim($validated['title']),
                'position' => (int) $taskModel->checklists()->max('position') + 1,
                'created_by' => $actor->id,
            ]);

            foreach ($validated['items'] ?? [] as $position => $item) {
                $checklist->items()->create(['content' => trim($item['content']), 'position' => $position + 1]);
            }

            $this->recordActivity($taskModel, $actor, 'checklist_created', ['title' => $checklist->title]);

            return $checklist;
        });

        return (new ChecklistResource($checklist->load(['items.completedBy'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, string $checklist): ChecklistResource
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'tasks.edit');
        $target = $this->organizationChecklist($checklist, $this->requireOrganization($actor));
        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:160'],
            'position' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ]);
        $target->update($validated);
        $this->recordActivity($target->task, $actor, 'checklist_updated', ['title' => $target->title]);

        return new ChecklistResource($target->load(['items.completedBy']));
    }

    public function destroy(Request $request, string $checklist): JsonResponse
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'tasks.edit');
        $target = $this->organizationChecklist($checklist, $this->requireOrganization($actor));
        DB::transaction(function () use ($target, $actor): void {
            $this->recordActivity($target->task, $actor, 'checklist_deleted', ['title' => $target->title]);
            $target->delete();
        });

        return response()->json(['success' => true, 'message' => 'Checklist deleted.']);
    }

    private function organizationTask(string $publicId, int $organizationId): Task
    {
        return Task::query()->where('organization_id', $organizationId)->where('public_id', $publicId)->firstOrFail();
    }

    private function organizationChecklist(string $publicId, int $organizationId): Checklist
    {
        return Checklist::query()->where('public_id', $publicId)
            ->whereHas('task', fn ($query) => $query->where('organization_id', $organizationId))
            ->firstOrFail();
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
    }
}

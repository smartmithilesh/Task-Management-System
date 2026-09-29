<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AuthorizesOrganizationRequests;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ChecklistItemResource;
use App\Models\ActivityLog;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChecklistItemController extends Controller
{
    use AuthorizesOrganizationRequests;

    public function store(Request $request, string $checklist): JsonResponse
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'tasks.edit');
        $parent = $this->organizationChecklist($checklist, $this->requireOrganization($actor));
        $validated = $request->validate(['content' => ['required', 'string', 'max:500']]);
        $item = $parent->items()->create([
            'content' => trim($validated['content']),
            'position' => (int) $parent->items()->max('position') + 1,
        ]);
        $this->recordActivity($parent->task, $actor, 'checklist_item_added', ['checklist' => $parent->title]);

        return (new ChecklistItemResource($item))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, string $item): ChecklistItemResource
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'tasks.edit');
        $target = $this->organizationItem($item, $this->requireOrganization($actor));
        $validated = $request->validate([
            'content' => ['sometimes', 'required', 'string', 'max:500'],
            'position' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'is_completed' => ['sometimes', 'boolean'],
        ]);
        if (array_key_exists('is_completed', $validated)) {
            $validated['completed_by'] = $validated['is_completed'] ? $actor->id : null;
            $validated['completed_at'] = $validated['is_completed'] ? now() : null;
        }
        $target->update($validated);
        $this->recordActivity($target->checklist->task, $actor, $target->is_completed ? 'checklist_item_completed' : 'checklist_item_updated', [
            'checklist' => $target->checklist->title,
            'content' => $target->content,
        ]);

        return new ChecklistItemResource($target->load('completedBy'));
    }

    public function destroy(Request $request, string $item): JsonResponse
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'tasks.edit');
        $target = $this->organizationItem($item, $this->requireOrganization($actor));
        DB::transaction(function () use ($target, $actor): void {
            $this->recordActivity($target->checklist->task, $actor, 'checklist_item_deleted', ['content' => $target->content]);
            $target->delete();
        });

        return response()->json(['success' => true, 'message' => 'Checklist item deleted.']);
    }

    private function organizationChecklist(string $publicId, int $organizationId): Checklist
    {
        return Checklist::query()->where('public_id', $publicId)
            ->whereHas('task', fn ($query) => $query->where('organization_id', $organizationId))
            ->firstOrFail();
    }

    private function organizationItem(string $publicId, int $organizationId): ChecklistItem
    {
        return ChecklistItem::query()->where('public_id', $publicId)
            ->whereHas('checklist.task', fn ($query) => $query->where('organization_id', $organizationId))
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

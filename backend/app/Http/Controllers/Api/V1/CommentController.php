<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AuthorizesOrganizationRequests;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CommentResource;
use App\Models\ActivityLog;
use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskMentionedNotification;
use App\Services\Integrations\IntegrationEventDispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CommentController extends Controller
{
    use AuthorizesOrganizationRequests;

    public function index(Request $request, string $task): JsonResponse
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'tasks.view');
        $taskModel = $this->organizationTask($task, $this->requireOrganization($actor));

        return CommentResource::collection($taskModel->comments()->with(['author', 'mentionedUsers'])->latest()->paginate(50))->response();
    }

    public function store(Request $request, string $task): JsonResponse
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'tasks.edit');
        $taskModel = $this->organizationTask($task, $this->requireOrganization($actor));
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:20000'],
            'mention_ids' => ['sometimes', 'array', 'max:50'],
            'mention_ids.*' => ['required', 'uuid', 'distinct', Rule::exists('users', 'public_id')->where('organization_id', $taskModel->organization_id)],
        ]);

        $comment = DB::transaction(function () use ($validated, $taskModel, $actor): Comment {
            $comment = $taskModel->comments()->create([
                'user_id' => $actor->id,
                'body' => trim($validated['body']),
            ]);
            $mentionedIds = User::query()->where('organization_id', $taskModel->organization_id)
                ->whereIn('public_id', $validated['mention_ids'] ?? [])
                ->pluck('id');
            $comment->mentionedUsers()->sync($mentionedIds->all());
            $this->recordActivity($taskModel, $actor, 'comment_added', ['comment_id' => $comment->public_id]);
            $recipients = $mentionedIds->reject(fn (int $id): bool => $id === $actor->id);
            $notice = new TaskMentionedNotification([
                'id' => $taskModel->public_id,
                'task_number' => $taskModel->task_number,
                'title' => $taskModel->title,
                'comment_id' => $comment->public_id,
                'mentioned_by' => $actor->name,
            ]);
            User::query()->whereIn('id', $recipients)->get()->each->notify($notice);

            return $comment;
        });

        return (new CommentResource($comment->load(['author', 'mentionedUsers'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, string $comment): CommentResource
    {
        $actor = $request->user();
        $target = $this->organizationComment($comment, $this->requireOrganization($actor));
        abort_unless($target->user_id === $actor->id || $actor->hasPermission('tasks.edit'), 403);
        $validated = $request->validate(['body' => ['required', 'string', 'max:20000']]);
        $target->update(['body' => trim($validated['body']), 'edited_at' => now()]);
        $this->recordActivity($target->task, $actor, 'comment_updated', ['comment_id' => $target->public_id]);

        return new CommentResource($target->load(['author', 'mentionedUsers']));
    }

    public function destroy(Request $request, string $comment): JsonResponse
    {
        $actor = $request->user();
        $target = $this->organizationComment($comment, $this->requireOrganization($actor));
        abort_unless($target->user_id === $actor->id || $actor->hasPermission('tasks.delete'), 403);
        $this->recordActivity($target->task, $actor, 'comment_deleted', ['comment_id' => $target->public_id]);
        $target->delete();

        return response()->json(['success' => true, 'message' => 'Comment deleted.']);
    }

    private function organizationTask(string $publicId, int $organizationId): Task
    {
        return Task::query()->where('organization_id', $organizationId)->where('public_id', $publicId)->firstOrFail();
    }

    private function organizationComment(string $publicId, int $organizationId): Comment
    {
        return Comment::query()->where('public_id', $publicId)
            ->whereHas('task', fn (Builder $query) => $query->where('organization_id', $organizationId))
            ->firstOrFail();
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
            'comment_id' => $properties['comment_id'] ?? null,
        ]);
    }
}

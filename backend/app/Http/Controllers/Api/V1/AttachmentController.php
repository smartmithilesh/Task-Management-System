<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AuthorizesOrganizationRequests;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AttachmentResource;
use App\Models\ActivityLog;
use App\Models\Attachment;
use App\Models\Task;
use App\Services\Integrations\IntegrationEventDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    use AuthorizesOrganizationRequests;

    public function index(Request $request, string $task): AnonymousResourceCollection
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'tasks.view');
        $taskModel = $this->organizationTask($task, $this->requireOrganization($actor));

        return AttachmentResource::collection($taskModel->attachments()->with('uploader')->latest()->get());
    }

    public function store(Request $request, string $task): JsonResponse
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'tasks.edit');
        $taskModel = $this->organizationTask($task, $this->requireOrganization($actor));
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,csv,txt'],
        ]);
        $file = $validated['file'];
        $path = $file->storeAs('task-attachments/'.$taskModel->public_id, Str::uuid().'.'.$file->guessExtension(), 'local');
        if ($path === false) {
            throw ValidationException::withMessages(['file' => ['The file could not be stored. Check private storage permissions.']]);
        }

        try {
            $attachment = DB::transaction(function () use ($taskModel, $actor, $file, $path): Attachment {
                $attachment = $taskModel->attachments()->create([
                    'uploaded_by' => $actor->id,
                    'disk' => 'local',
                    'path' => $path,
                    'original_name' => basename($file->getClientOriginalName()),
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'size_bytes' => $file->getSize(),
                    'sha256' => hash_file('sha256', $file->getRealPath()),
                ]);
                ActivityLog::query()->create([
                    'organization_id' => $taskModel->organization_id,
                    'actor_id' => $actor->id,
                    'subject_type' => Task::class,
                    'subject_id' => $taskModel->id,
                    'action' => 'attachment_added',
                    'properties' => ['attachment_id' => $attachment->public_id],
                ]);
                app(IntegrationEventDispatcher::class)->dispatch($taskModel->organization_id, 'task.attachment_added', [
                    'task_id' => $taskModel->public_id,
                    'task_number' => $taskModel->task_number,
                    'attachment_id' => $attachment->public_id,
                    'actor' => ['id' => $actor->public_id, 'name' => $actor->name],
                ]);

                return $attachment;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return (new AttachmentResource($attachment->load('uploader')))
            ->response()
            ->setStatusCode(201);
    }

    public function download(Request $request, string $attachment): StreamedResponse
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'tasks.view');
        $target = Attachment::query()->where('public_id', $attachment)
            ->whereHas('task', fn ($query) => $query->where('organization_id', $this->requireOrganization($actor)))
            ->firstOrFail();
        abort_unless(Storage::disk($target->disk)->exists($target->path), 404);

        return Storage::disk($target->disk)->download($target->path, basename($target->original_name));
    }

    public function destroy(Request $request, string $attachment): JsonResponse
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'tasks.edit');
        $target = Attachment::query()->where('public_id', $attachment)
            ->whereHas('task', fn ($query) => $query->where('organization_id', $this->requireOrganization($actor)))
            ->firstOrFail();
        Storage::disk($target->disk)->delete($target->path);
        $target->delete();

        return response()->json(['success' => true, 'message' => 'Attachment deleted.']);
    }

    private function organizationTask(string $publicId, int $organizationId): Task
    {
        return Task::query()->where('organization_id', $organizationId)->where('public_id', $publicId)->firstOrFail();
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AuthorizesOrganizationRequests;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TagResource;
use App\Models\Tag;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TagController extends Controller
{
    use AuthorizesOrganizationRequests;

    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'tasks.view');
        $tags = Tag::query()->where('organization_id', $this->requireOrganization($actor))->orderBy('name')->get();

        return TagResource::collection($tags)->response();
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'tasks.edit');
        $organizationId = $this->requireOrganization($actor);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'color' => ['sometimes', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);
        $slug = Str::slug($validated['name']);
        abort_if($slug === '', 422, 'Enter a tag name that includes letters or numbers.');
        abort_if(Tag::query()->where('organization_id', $organizationId)->where('slug', $slug)->exists(), 422, 'That tag already exists.');

        $tag = Tag::query()->create([
            'organization_id' => $organizationId,
            'name' => trim($validated['name']),
            'slug' => $slug,
            'color' => $validated['color'] ?? '#64748b',
        ]);

        return (new TagResource($tag))->response()->setStatusCode(201);
    }

    public function updateTaskTags(Request $request, string $task): JsonResponse
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'tasks.edit');
        $organizationId = $this->requireOrganization($actor);
        $taskModel = Task::query()->where('organization_id', $organizationId)->where('public_id', $task)->firstOrFail();
        $validated = $request->validate([
            'tag_ids' => ['required', 'array'],
            'tag_ids.*' => ['required', 'uuid', 'distinct', Rule::exists('tags', 'public_id')->where('organization_id', $organizationId)],
        ]);
        $tagIds = Tag::query()->where('organization_id', $organizationId)->whereIn('public_id', $validated['tag_ids'])->pluck('id');

        DB::transaction(fn () => $taskModel->tags()->sync($tagIds->mapWithKeys(fn (int $tagId): array => [
            $tagId => ['tagged_by' => $actor->id],
        ])->all()));

        return TagResource::collection($taskModel->load('tags')->tags)->response();
    }
}

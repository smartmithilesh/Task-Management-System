<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AuthorizesOrganizationRequests;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TeamResource;
use App\Models\Department;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    use AuthorizesOrganizationRequests;

    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'teams.view');
        $organizationId = $this->requireOrganization($actor);

        $teams = Team::query()
            ->where('organization_id', $organizationId)
            ->with(['department', 'lead'])
            ->withCount('users')
            ->when($request->filled('department_id'), function (Builder $query) use ($request, $organizationId): void {
                $query->whereHas('department', function (Builder $query) use ($request, $organizationId): void {
                    $query->where('organization_id', $organizationId)
                        ->where('public_id', $request->string('department_id')->toString());
                });
            })
            ->orderBy('name')
            ->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return TeamResource::collection($teams)->response();
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'teams.manage');
        $organizationId = $this->requireOrganization($actor);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:140', 'alpha_dash'],
            'description' => ['nullable', 'string', 'max:5000'],
            'department_id' => ['nullable', 'uuid', Rule::exists('departments', 'public_id')->where('organization_id', $organizationId)],
            'lead_id' => ['nullable', 'uuid', Rule::exists('users', 'public_id')->where('organization_id', $organizationId)],
        ]);

        $name = trim($validated['name']);
        $slug = $validated['slug'] ?? Str::slug($name);
        $slug = $slug !== '' ? $slug : Str::lower(Str::random(8));
        $this->ensureUniqueSlug($organizationId, $slug);
        $validated['slug'] = $slug;
        $validated['organization_id'] = $organizationId;
        $validated['department_id'] = isset($validated['department_id'])
            ? Department::query()->where('public_id', $validated['department_id'])->value('id')
            : null;
        $validated['lead_id'] = isset($validated['lead_id'])
            ? User::query()->where('public_id', $validated['lead_id'])->value('id')
            : null;

        $team = Team::query()->create($validated);

        return (new TeamResource($team->load(['department', 'lead', 'users'])->loadCount('users')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, string $team): TeamResource
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'teams.view');

        return new TeamResource($this->organizationTeam($team, $this->requireOrganization($actor))
            ->load(['department', 'lead', 'users'])
            ->loadCount('users'));
    }

    public function update(Request $request, string $team): TeamResource
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'teams.manage');
        $organizationId = $this->requireOrganization($actor);
        $target = $this->organizationTeam($team, $organizationId);
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'slug' => ['sometimes', 'required', 'string', 'max:140', 'alpha_dash', Rule::unique('teams', 'slug')->where('organization_id', $organizationId)->ignore($target->id)],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'department_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('departments', 'public_id')->where('organization_id', $organizationId)],
            'lead_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('users', 'public_id')->where('organization_id', $organizationId)],
        ]);
        if (array_key_exists('name', $validated)) {
            $validated['name'] = trim($validated['name']);
        }
        if (array_key_exists('department_id', $validated)) {
            $validated['department_id'] = $validated['department_id'] === null
                ? null
                : Department::query()->where('public_id', $validated['department_id'])->value('id');
        }
        if (array_key_exists('lead_id', $validated)) {
            $validated['lead_id'] = $validated['lead_id'] === null
                ? null
                : User::query()->where('public_id', $validated['lead_id'])->value('id');
        }
        $target->update($validated);

        return new TeamResource($target->load(['department', 'lead', 'users'])->loadCount('users'));
    }

    public function updateMembers(Request $request, string $team): TeamResource
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'teams.manage');
        $organizationId = $this->requireOrganization($actor);
        $target = $this->organizationTeam($team, $organizationId);
        $validated = $request->validate([
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['required', 'uuid', 'distinct', Rule::exists('users', 'public_id')->where('organization_id', $organizationId)],
        ]);
        $userIds = User::query()->where('organization_id', $organizationId)
            ->whereIn('public_id', $validated['user_ids'])
            ->pluck('id')
            ->all();

        DB::transaction(function () use ($target, $userIds, $actor): void {
            $target->users()->sync(collect($userIds)->mapWithKeys(fn (int $userId): array => [
                $userId => ['added_by' => $actor->id],
            ])->all());
        });

        return new TeamResource($target->load(['department', 'lead', 'users'])->loadCount('users'));
    }

    public function destroy(Request $request, string $team): JsonResponse
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'teams.manage');
        $target = $this->organizationTeam($team, $this->requireOrganization($actor));
        $target->delete();

        return response()->json(['success' => true, 'message' => 'Team archived.']);
    }

    private function organizationTeam(string $publicId, int $organizationId): Team
    {
        return Team::query()
            ->where('organization_id', $organizationId)
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    private function ensureUniqueSlug(int $organizationId, string $slug): void
    {
        abort_if(Team::query()->where('organization_id', $organizationId)->where('slug', $slug)->exists(), 422, 'That team URL is already in use.');
    }
}

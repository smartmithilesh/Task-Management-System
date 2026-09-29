<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AuthorizesOrganizationRequests;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DepartmentResource;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    use AuthorizesOrganizationRequests;

    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'departments.view');
        $organizationId = $this->requireOrganization($actor);

        $departments = Department::query()
            ->where('organization_id', $organizationId)
            ->with('manager')
            ->withCount(['users', 'teams'])
            ->orderBy('name')
            ->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return DepartmentResource::collection($departments)->response();
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'departments.manage');
        $organizationId = $this->requireOrganization($actor);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:140', 'alpha_dash'],
            'description' => ['nullable', 'string', 'max:5000'],
            'manager_id' => ['nullable', 'uuid', Rule::exists('users', 'public_id')->where('organization_id', $organizationId)],
        ]);

        $name = trim($validated['name']);
        $slug = $validated['slug'] ?? Str::slug($name);
        $slug = $slug !== '' ? $slug : Str::lower(Str::random(8));
        $this->ensureUniqueSlug($organizationId, $slug);
        $validated['slug'] = $slug;
        $validated['manager_id'] = isset($validated['manager_id'])
            ? User::query()->where('public_id', $validated['manager_id'])->value('id')
            : null;
        $validated['organization_id'] = $organizationId;

        $department = Department::query()->create($validated);

        return (new DepartmentResource($department->load('manager')->loadCount(['users', 'teams'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, string $department): DepartmentResource
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'departments.view');

        return new DepartmentResource($this->organizationDepartment($department, $this->requireOrganization($actor))
            ->load('manager')
            ->loadCount(['users', 'teams']));
    }

    public function update(Request $request, string $department): DepartmentResource
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'departments.manage');
        $organizationId = $this->requireOrganization($actor);
        $target = $this->organizationDepartment($department, $organizationId);
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'slug' => ['sometimes', 'required', 'string', 'max:140', 'alpha_dash', Rule::unique('departments', 'slug')->where('organization_id', $organizationId)->ignore($target->id)],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'manager_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('users', 'public_id')->where('organization_id', $organizationId)],
        ]);

        if (array_key_exists('name', $validated)) {
            $validated['name'] = trim($validated['name']);
        }
        if (array_key_exists('manager_id', $validated)) {
            $validated['manager_id'] = $validated['manager_id'] === null
                ? null
                : User::query()->where('public_id', $validated['manager_id'])->value('id');
        }
        $target->update($validated);

        return new DepartmentResource($target->load('manager')->loadCount(['users', 'teams']));
    }

    public function destroy(Request $request, string $department): JsonResponse
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'departments.manage');
        $target = $this->organizationDepartment($department, $this->requireOrganization($actor));
        abort_if($target->teams()->exists(), 409, 'Move or remove this department’s teams before archiving it.');

        $target->delete();

        return response()->json(['success' => true, 'message' => 'Department archived.']);
    }

    private function organizationDepartment(string $publicId, int $organizationId): Department
    {
        return Department::query()
            ->where('organization_id', $organizationId)
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    private function ensureUniqueSlug(int $organizationId, string $slug): void
    {
        abort_if(Department::query()->where('organization_id', $organizationId)->where('slug', $slug)->exists(), 422, 'That department URL is already in use.');
    }
}

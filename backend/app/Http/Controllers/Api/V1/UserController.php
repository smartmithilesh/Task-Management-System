<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AuthorizesOrganizationRequests;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    use AuthorizesOrganizationRequests;

    public function index(Request $request): JsonResponse
    {
        $this->authorizeOrganizationPermission($request, 'users.view');

        $users = User::query()
            ->with(['organization', 'department'])
            ->where('organization_id', $this->requireOrganization($request->user()))
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = '%'.$request->string('search')->trim()->toString().'%';
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', $search)->orWhere('email', 'like', $search);
                });
            })
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->orderBy('name')
            ->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return UserResource::collection($users)->response();
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeOrganizationPermission($request, 'users.create');
        $request->merge(['email' => Str::lower($request->string('email')->toString())]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
            'phone' => ['nullable', 'string', 'max:40'],
            'employee_number' => ['nullable', 'string', 'max:80', Rule::unique('users', 'employee_number')->where('organization_id', $this->requireOrganization($request->user()))],
            'department_id' => ['nullable', 'uuid', Rule::exists('departments', 'public_id')->where('organization_id', $this->requireOrganization($request->user()))],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'timezone' => ['sometimes', 'string', 'timezone'],
            'language' => ['sometimes', 'string', 'max:10'],
        ]);

        $user = User::query()->create([
            ...$validated,
            'email' => Str::lower($validated['email']),
            'organization_id' => $this->requireOrganization($request->user()),
            'department_id' => isset($validated['department_id'])
                ? Department::query()->where('public_id', $validated['department_id'])->value('id')
                : null,
        ]);

        return (new UserResource($user->load(['organization', 'department'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, string $user): UserResource
    {
        $this->authorizeOrganizationPermission($request, 'users.view');

        return new UserResource($this->organizationUser($request, $user)->load(['organization', 'department', 'roles.permissions']));
    }

    public function update(Request $request, string $user): UserResource
    {
        $this->authorizeOrganizationPermission($request, 'users.edit');
        $target = $this->organizationUser($request, $user);

        if ($request->has('email') && is_string($request->input('email'))) {
            $request->merge(['email' => Str::lower($request->string('email')->toString())]);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'employee_number' => ['sometimes', 'nullable', 'string', 'max:80', Rule::unique('users', 'employee_number')->where('organization_id', $this->requireOrganization($request->user()))->ignore($target->id)],
            'department_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('departments', 'public_id')->where('organization_id', $this->requireOrganization($request->user()))],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'timezone' => ['sometimes', 'string', 'timezone'],
            'language' => ['sometimes', 'string', 'max:10'],
        ]);

        if (array_key_exists('email', $validated)) {
            $validated['email'] = Str::lower($validated['email']);
            $validated['email_verified_at'] = null;
        }

        if (array_key_exists('department_id', $validated)) {
            $validated['department_id'] = $validated['department_id'] === null
                ? null
                : Department::query()->where('public_id', $validated['department_id'])->value('id');
        }

        $target->forceFill($validated)->save();

        return new UserResource($target->load(['organization', 'department', 'roles.permissions']));
    }

    public function assignRoles(Request $request, string $user): UserResource
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'roles.edit');
        $organizationId = $this->requireOrganization($actor);
        $target = $this->organizationUser($request, $user);
        $validated = $request->validate([
            'role_ids' => ['required', 'array'],
            'role_ids.*' => ['required', 'uuid', 'distinct', Rule::exists('roles', 'public_id')->where('organization_id', $organizationId)],
        ]);
        $roleIds = Role::query()->where('organization_id', $organizationId)
            ->whereIn('public_id', $validated['role_ids'])
            ->pluck('id')
            ->all();

        DB::transaction(function () use ($target, $organizationId, $roleIds, $actor): void {
            $existingOrganizationRoleIds = $target->roles()
                ->where('roles.organization_id', $organizationId)
                ->pluck('roles.id')
                ->all();

            $target->roles()->detach($existingOrganizationRoleIds);
            $target->roles()->attach(array_fill_keys($roleIds, ['assigned_by' => $actor->id]));
        });

        return new UserResource($target->load(['organization', 'department', 'roles.permissions']));
    }

    public function profile(Request $request): UserResource
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'timezone' => ['sometimes', 'string', 'timezone'],
            'language' => ['sometimes', 'string', 'max:10'],
        ]);

        $request->user()->update($validated);

        return new UserResource($request->user()->load(['organization', 'department']));
    }

    private function authorizeOrganizationPermission(Request $request, string $permission): void
    {
        $actor = $request->user();
        $this->authorizePermission($actor, $permission);
        $this->requireOrganization($actor);
    }

    private function organizationUser(Request $request, string $publicId): User
    {
        return User::query()
            ->where('organization_id', $this->requireOrganization($request->user()))
            ->where('public_id', $publicId)
            ->firstOrFail();
    }
}

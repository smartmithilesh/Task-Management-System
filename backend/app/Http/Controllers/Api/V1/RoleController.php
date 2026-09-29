<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AuthorizesOrganizationRequests;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PermissionResource;
use App\Http\Resources\Api\V1\RoleResource;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    use AuthorizesOrganizationRequests;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authorizePermission($user, 'roles.view');
        $organizationId = $this->requireOrganization($user);

        $roles = Role::query()
            ->where(function (Builder $query) use ($organizationId): void {
                $query->whereNull('organization_id')->orWhere('organization_id', $organizationId);
            })
            ->with('permissions')
            ->withCount('users')
            ->orderBy('is_system', 'desc')
            ->orderBy('name')
            ->get();

        return RoleResource::collection($roles)->response();
    }

    public function permissions(Request $request): JsonResponse
    {
        $this->authorizePermission($request->user(), 'roles.view');

        return PermissionResource::collection(Permission::query()->orderBy('group_name')->orderBy('label')->get())
            ->response();
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authorizePermission($user, 'roles.create');
        $organizationId = $this->requireOrganization($user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:120', 'alpha_dash'],
            'description' => ['nullable', 'string', 'max:2000'],
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => ['uuid', 'distinct', Rule::exists('permissions', 'public_id')],
        ]);

        $slug = $validated['slug'] ?? Str::slug($validated['name']);
        $slug = $slug !== '' ? $slug : Str::lower(Str::random(8));
        $scopeKey = 'organization:'.$organizationId.':'.$slug;
        validator(['scope_key' => $scopeKey], [
            'scope_key' => [Rule::unique('roles', 'scope_key')],
        ])->validate();

        $role = DB::transaction(function () use ($validated, $organizationId, $slug, $scopeKey): Role {
            $role = Role::query()->create([
                'organization_id' => $organizationId,
                'name' => $validated['name'],
                'slug' => $slug,
                'scope_key' => $scopeKey,
                'description' => $validated['description'] ?? null,
                'is_system' => false,
            ]);

            $this->syncPermissions($role, $validated['permission_ids'] ?? []);

            return $role;
        });

        return (new RoleResource($role->load('permissions')->loadCount('users')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, string $role): RoleResource
    {
        $user = $request->user();
        $this->authorizePermission($user, 'roles.edit');
        $organizationId = $this->requireOrganization($user);
        $target = $this->organizationRole($role, $organizationId);
        abort_if($target->is_system, 403, 'System roles cannot be changed.');

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'slug' => ['sometimes', 'required', 'string', 'max:120', 'alpha_dash'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => ['uuid', 'distinct', Rule::exists('permissions', 'public_id')],
        ]);

        if (array_key_exists('slug', $validated)) {
            $scopeKey = 'organization:'.$organizationId.':'.$validated['slug'];
            validator(['scope_key' => $scopeKey], [
                'scope_key' => [Rule::unique('roles', 'scope_key')->ignore($target->id)],
            ])->validate();
            $validated['scope_key'] = $scopeKey;
        }

        $permissionIds = $validated['permission_ids'] ?? null;
        unset($validated['permission_ids']);
        DB::transaction(function () use ($target, $validated, $permissionIds): void {
            $target->update($validated);

            if ($permissionIds !== null) {
                $this->syncPermissions($target, $permissionIds);
            }
        });

        return new RoleResource($target->load('permissions')->loadCount('users'));
    }

    public function destroy(Request $request, string $role): JsonResponse
    {
        $user = $request->user();
        $this->authorizePermission($user, 'roles.delete');
        $organizationId = $this->requireOrganization($user);
        $target = $this->organizationRole($role, $organizationId);
        abort_if($target->is_system, 403, 'System roles cannot be deleted.');
        abort_if($target->users()->exists(), 409, 'Reassign users before deleting this role.');

        $target->delete();

        return response()->json(['success' => true, 'message' => 'Role deleted.']);
    }

    private function organizationRole(string $publicId, int $organizationId): Role
    {
        return Role::query()
            ->where('organization_id', $organizationId)
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    /** @param list<string> $publicIds */
    private function syncPermissions(Role $role, array $publicIds): void
    {
        $permissionIds = Permission::query()->whereIn('public_id', $publicIds)->pluck('id')->all();
        $role->permissions()->sync($permissionIds);
    }
}

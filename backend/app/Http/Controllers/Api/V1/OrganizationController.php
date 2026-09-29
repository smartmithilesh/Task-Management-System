<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AuthorizesOrganizationRequests;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OrganizationResource;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OrganizationController extends Controller
{
    use AuthorizesOrganizationRequests;

    public function show(Request $request): OrganizationResource
    {
        $user = $request->user();
        $this->authorizePermission($user, 'settings.manage');
        $organizationId = $this->requireOrganization($user);

        return new OrganizationResource(Organization::query()
            ->whereKey($organizationId)
            ->with('owner')
            ->firstOrFail());
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authorizePermission($user, 'settings.manage');
        abort_if($user->organization_id !== null, 409, 'Your account already belongs to an organization.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:180', 'alpha_dash', 'unique:organizations,slug'],
            'website' => ['nullable', 'url', 'max:255'],
            'timezone' => ['sometimes', 'required', 'timezone'],
            'language' => ['sometimes', 'required', 'string', 'max:10'],
        ]);

        $organization = DB::transaction(function () use ($validated, $user): Organization {
            $name = trim($validated['name']);
            $slug = $validated['slug'] ?? Str::slug($name);
            if ($slug === '') {
                $slug = 'workspace-'.Str::lower(Str::random(8));
            }

            if (! array_key_exists('slug', $validated) || $validated['slug'] === null) {
                $baseSlug = $slug;
                $suffix = 2;
                while (Organization::query()->where('slug', $slug)->exists()) {
                    $slug = $baseSlug.'-'.$suffix;
                    $suffix++;
                }
            }

            $organization = Organization::query()->create([
                ...$validated,
                'name' => $name,
                'slug' => $slug,
                'owner_id' => $user->id,
            ]);

            $user->forceFill(['organization_id' => $organization->id])->save();

            return $organization;
        });

        return (new OrganizationResource($organization->load('owner')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request): OrganizationResource
    {
        $user = $request->user();
        $this->authorizePermission($user, 'settings.manage');
        $organizationId = $this->requireOrganization($user);
        $organization = Organization::query()->whereKey($organizationId)->firstOrFail();

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:160'],
            'slug' => ['sometimes', 'required', 'string', 'max:180', 'alpha_dash', Rule::unique('organizations', 'slug')->ignore($organization->id)],
            'website' => ['sometimes', 'nullable', 'url', 'max:255'],
            'timezone' => ['sometimes', 'required', 'timezone'],
            'language' => ['sometimes', 'required', 'string', 'max:10'],
        ]);

        $organization->update($validated);

        return new OrganizationResource($organization->load('owner'));
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class OrganizationController extends Controller
{
    private function removeSensitiveData($organization)
    {
        $organization->makeHidden([
            'pivot',
            'metadata',
        ]);

        return serializeApiResponse($organization);
    }

    #[OA\Get(
        summary: 'List',
        description: 'Get all organizations for the current user.',
        path: '/organizations',
        operationId: 'list-organizations',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Organizations'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of organizations.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Organization')
                        )
                    ),
                ]),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
            new OA\Response(
                response: 400,
                ref: '#/components/responses/400',
            ),
        ]
    )]
    public function index(Request $request)
    {
        $organizations = auth()->user()->organizations()->with('owner')->get()->sortBy('id');
        $organizations = $organizations->map(function ($organization) {
            return $this->removeSensitiveData($organization);
        });

        return response()->json($organizations);
    }

    #[OA\Get(
        summary: 'Get',
        description: 'Get organization by ID.',
        path: '/organizations/{id}',
        operationId: 'get-organization-by-id',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Organizations'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Organization ID', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Organization details.',
                content: new OA\JsonContent(ref: '#/components/schemas/Organization')
            ),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
            new OA\Response(
                response: 404,
                ref: '#/components/responses/404',
            ),
        ]
    )]
    public function show(Request $request, $id)
    {
        $organization = auth()->user()->organizations()->with('owner', 'teams')->find($id);

        if (is_null($organization)) {
            return response()->json(['message' => 'Organization not found.'], 404);
        }

        return response()->json($this->removeSensitiveData($organization));
    }

    #[OA\Post(
        summary: 'Create',
        description: 'Create a new organization.',
        path: '/organizations',
        operationId: 'create-organization',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Organizations'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', description: 'The name of the organization.'),
                    new OA\Property(property: 'description', type: 'string', description: 'The description of the organization.'),
                    new OA\Property(property: 'slug', type: 'string', description: 'The unique slug (auto-generated if empty).'),
                    new OA\Property(property: 'billing_email', type: 'string', description: 'The billing email.'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Organization created.',
                content: new OA\JsonContent(ref: '#/components/schemas/Organization')
            ),
            new OA\Response(
                response: 400,
                ref: '#/components/responses/400',
            ),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
        ]
    )]
    public function store(Request $request)
    {
        $incoming = validateIncomingRequest($request);
        if ($incoming) {
            return $incoming;
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('organizations', 'slug')],
            'billing_email' => 'nullable|email|max:255',
        ]);

        $organization = Organization::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? null,
            'description' => $validated['description'] ?? null,
            'billing_email' => $validated['billing_email'] ?? null,
            'owner_id' => auth()->id(),
        ]);

        $organization->members()->attach(auth()->id(), ['role' => 'owner']);

        $organization->load('owner');

        audit('organization.created', "Organization '{$organization->name}' created.", organizationId: $organization->id);

        return response()->json($this->removeSensitiveData($organization), 201);
    }

    #[OA\Patch(
        summary: 'Update',
        description: 'Update an organization.',
        path: '/organizations/{id}',
        operationId: 'update-organization',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Organizations'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Organization ID', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'billing_email', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Organization updated.',
                content: new OA\JsonContent(ref: '#/components/schemas/Organization')
            ),
            new OA\Response(
                response: 400,
                ref: '#/components/responses/400',
            ),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
            new OA\Response(
                response: 403,
                description: 'Forbidden. Only owner or admin can update.',
            ),
            new OA\Response(
                response: 404,
                ref: '#/components/responses/404',
            ),
        ]
    )]
    public function update(Request $request, $id)
    {
        $organization = auth()->user()->organizations()->find($id);

        if (is_null($organization)) {
            return response()->json(['message' => 'Organization not found.'], 404);
        }

        $membership = $organization->members()->where('user_id', auth()->id())->first();
        $role = data_get($membership, 'pivot.role');

        if (! in_array($role, ['owner', 'admin'])) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'billing_email' => 'nullable|email|max:255',
        ]);

        $organization->update($validated);

        audit('organization.updated', "Organization '{$organization->name}' updated.", organizationId: $organization->id);

        return response()->json($this->removeSensitiveData($organization->fresh()));
    }

    #[OA\Delete(
        summary: 'Delete',
        description: 'Delete an organization.',
        path: '/organizations/{id}',
        operationId: 'delete-organization',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Organizations'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Organization ID', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Organization deleted.',
            ),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
            new OA\Response(
                response: 403,
                description: 'Forbidden. Only owner can delete.',
            ),
            new OA\Response(
                response: 404,
                ref: '#/components/responses/404',
            ),
        ]
    )]
    public function destroy(Request $request, $id)
    {
        $organization = auth()->user()->organizations()->find($id);

        if (is_null($organization)) {
            return response()->json(['message' => 'Organization not found.'], 404);
        }

        if ($organization->owner_id !== auth()->id()) {
            return response()->json(['message' => 'Forbidden. Only the owner can delete the organization.'], 403);
        }

        $organization->delete();

        audit('organization.deleted', "Organization '{$organization->name}' deleted.", organizationId: $organization->id);

        return response()->json(['message' => 'Organization deleted.'], 200);
    }

    #[OA\Get(
        summary: 'Members',
        description: 'Get organization members.',
        path: '/organizations/{id}/members',
        operationId: 'get-organization-members',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Organizations'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Organization ID', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of members.',
            ),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
            new OA\Response(
                response: 404,
                ref: '#/components/responses/404',
            ),
        ]
    )]
    public function members(Request $request, $id)
    {
        $organization = auth()->user()->organizations()->find($id);

        if (is_null($organization)) {
            return response()->json(['message' => 'Organization not found.'], 404);
        }

        $members = $organization->members()->get()->makeHidden([
            'pivot',
            'email_change_code',
            'email_change_code_expires_at',
        ]);

        return response()->json(serializeApiResponse($members));
    }

    #[OA\Post(
        summary: 'Invite Member',
        description: 'Invite a member to the organization.',
        path: '/organizations/{id}/members',
        operationId: 'invite-organization-member',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Organizations'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Organization ID', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'role'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'role', type: 'string', enum: ['member', 'admin']),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Member invited.',
            ),
            new OA\Response(
                response: 403,
                description: 'Forbidden.',
            ),
            new OA\Response(
                response: 404,
                ref: '#/components/responses/404',
            ),
        ]
    )]
    public function inviteMember(Request $request, $id)
    {
        $organization = auth()->user()->organizations()->find($id);

        if (is_null($organization)) {
            return response()->json(['message' => 'Organization not found.'], 404);
        }

        $membership = $organization->members()->where('user_id', auth()->id())->first();
        $role = data_get($membership, 'pivot.role');

        if (! in_array($role, ['owner', 'admin'])) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
            'role' => 'required|string|in:member,admin',
        ]);

        $user = \App\Models\User::where('email', $validated['email'])->first();

        if ($organization->members()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'User is already a member.'], 409);
        }

        $organization->members()->attach($user->id, ['role' => $validated['role']]);

        audit('organization.member.added', "User '{$user->email}' added as {$validated['role']}.", organizationId: $organization->id);

        return response()->json(['message' => 'Member added.'], 200);
    }

    #[OA\Delete(
        summary: 'Remove Member',
        description: 'Remove a member from the organization.',
        path: '/organizations/{id}/members/{userId}',
        operationId: 'remove-organization-member',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Organizations'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Organization ID', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'userId', in: 'path', required: true, description: 'User ID', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Member removed.',
            ),
            new OA\Response(
                response: 403,
                description: 'Forbidden.',
            ),
            new OA\Response(
                response: 404,
                ref: '#/components/responses/404',
            ),
        ]
    )]
    public function removeMember(Request $request, $id, $userId)
    {
        $organization = auth()->user()->organizations()->find($id);

        if (is_null($organization)) {
            return response()->json(['message' => 'Organization not found.'], 404);
        }

        if ($organization->owner_id === (int) $userId) {
            return response()->json(['message' => 'Cannot remove the owner.'], 403);
        }

        $membership = $organization->members()->where('user_id', auth()->id())->first();
        $role = data_get($membership, 'pivot.role');

        if (! in_array($role, ['owner', 'admin'])) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $organization->members()->detach($userId);

        audit('organization.member.removed', "User #{$userId} removed from organization.", organizationId: $organization->id);

        return response()->json(['message' => 'Member removed.'], 200);
    }
}

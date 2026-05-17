<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AutomationHook;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AutomationHookController extends Controller
{
    public function index(Request $request)
    {
        $query = AutomationHook::where('user_id', auth()->id());

        $organizationId = $request->get('organization_id');
        $event = $request->get('event');

        if ($organizationId) {
            $org = auth()->user()->organizations()->find($organizationId);
            if (! $org) {
                return response()->json(['message' => 'Organization not found.'], 404);
            }
            $query->where('organization_id', $organizationId);
        }

        if ($event) {
            $query->forEvent($event);
        }

        return response()->json(serializeApiResponse($query->get()));
    }

    public function store(Request $request)
    {
        $incoming = validateIncomingRequest($request);
        if ($incoming) {
            return $incoming;
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'event' => 'required|string|max:255',
            'url' => 'required|url|max:2048',
            'secret' => 'nullable|string|max:255',
            'organization_id' => 'nullable|integer|exists:organizations,id',
            'team_id' => 'nullable|integer|exists:teams,id',
        ]);

        if (isset($validated['organization_id'])) {
            $org = auth()->user()->organizations()->find($validated['organization_id']);
            if (! $org) {
                return response()->json(['message' => 'Organization not found.'], 404);
            }
        }

        if (isset($validated['team_id'])) {
            if (! auth()->user()->teams->contains('id', $validated['team_id'])) {
                return response()->json(['message' => 'Team not found.'], 404);
            }
        }

        $hook = AutomationHook::create([
            'organization_id' => $validated['organization_id'] ?? null,
            'team_id' => $validated['team_id'] ?? null,
            'user_id' => auth()->id(),
            'name' => $validated['name'],
            'event' => $validated['event'],
            'url' => $validated['url'],
            'secret' => $validated['secret'] ?? null,
        ]);

        return response()->json(serializeApiResponse($hook), 201);
    }

    public function destroy(Request $request, $id)
    {
        $hook = AutomationHook::where('user_id', auth()->id())->find($id);

        if (is_null($hook)) {
            return response()->json(['message' => 'Hook not found.'], 404);
        }

        $hook->delete();

        return response()->json(['message' => 'Hook deleted.'], 200);
    }
}

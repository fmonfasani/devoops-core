<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AuditLogController extends Controller
{
    #[OA\Get(
        summary: 'List audit logs',
        description: 'Get audit logs, filtered by organization or team.',
        path: '/audit-logs',
        operationId: 'list-audit-logs',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Audit Logs'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of audit logs.',
            ),
        ]
    )]
    public function index(Request $request)
    {
        $query = AuditLog::recent();

        $organizationId = $request->get('organization_id');
        $teamId = $request->get('team_id');
        $event = $request->get('event');
        $perPage = min((int) $request->get('per_page', 50), 100);
        $auditableType = $request->get('auditable_type');
        $auditableId = $request->get('auditable_id');

        if ($organizationId) {
            $org = auth()->user()->organizations()->find($organizationId);
            if (! $org) {
                return response()->json(['message' => 'Organization not found.'], 404);
            }
            $query->forOrganization($organizationId);
        }

        if ($teamId) {
            if (! auth()->user()->teams->contains('id', $teamId)) {
                return response()->json(['message' => 'Team not found.'], 404);
            }
            $query->forTeam($teamId);
        }

        if ($event) {
            $query->forEvent($event);
        }

        if ($auditableType && $auditableId) {
            $query->where('auditable_type', $auditableType)
                ->where('auditable_id', $auditableId);
        }

        if (! $organizationId && ! $teamId) {
            $teamIds = auth()->user()->teams->pluck('id');
            $orgIds = auth()->user()->organizations->pluck('id');
            $query->where(function ($q) use ($teamIds, $orgIds) {
                $q->whereIn('team_id', $teamIds)
                  ->orWhereIn('organization_id', $orgIds)
                  ->orWhere('user_id', auth()->id());
            });
        }

        $logs = $query->paginate($perPage);

        return response()->json(serializeApiResponse($logs));
    }

    #[OA\Get(
        summary: 'Get audit log',
        description: 'Get a single audit log entry.',
        path: '/audit-logs/{id}',
        operationId: 'get-audit-log',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Audit Logs'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Audit log entry.'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
        ]
    )]
    public function show(Request $request, $id)
    {
        $log = AuditLog::findOrFail($id);

        $accessible = false;
        if ($log->organization_id && auth()->user()->organizations->contains('id', $log->organization_id)) {
            $accessible = true;
        }
        if ($log->team_id && auth()->user()->teams->contains('id', $log->team_id)) {
            $accessible = true;
        }
        if ($log->user_id === auth()->id()) {
            $accessible = true;
        }

        if (! $accessible) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return response()->json(serializeApiResponse($log));
    }
}

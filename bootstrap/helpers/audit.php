<?php

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

function audit(string $event, ?string $description = null, ?array $oldValues = null, ?array $newValues = null, $auditable = null, ?int $organizationId = null, ?int $teamId = null): AuditLog
{
    $user = Auth::user();

    if (is_null($organizationId) && $auditable && method_exists($auditable, 'organization') && $auditable->organization) {
        $organizationId = $auditable->organization->id;
    }

    if (is_null($teamId) && $auditable && method_exists($auditable, 'team') && $auditable->team) {
        $teamId = $auditable->team->id;
    }

    if (is_null($teamId) && function_exists('currentTeam')) {
        $currentTeam = currentTeam();
        if ($currentTeam) {
            $teamId = $currentTeam->id;
        }
    }

    if (is_null($teamId) && session('currentTeam')) {
        $teamId = data_get(session('currentTeam'), 'id');
    }

    return AuditLog::create([
        'organization_id' => $organizationId,
        'team_id' => $teamId,
        'user_id' => $user?->id,
        'event' => $event,
        'auditable_type' => $auditable ? get_class($auditable) : null,
        'auditable_id' => $auditable ? $auditable->id : null,
        'description' => $description,
        'old_values' => $oldValues,
        'new_values' => $newValues,
        'ip_address' => Request::ip(),
        'user_agent' => Request::userAgent(),
    ]);
}

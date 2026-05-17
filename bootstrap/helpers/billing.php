<?php

use App\Models\UsageRecord;

function track_usage(array $params): UsageRecord
{
    $teamId = $params['team_id'] ?? null;
    $organizationId = $params['organization_id'] ?? null;

    if (is_null($teamId) && function_exists('currentTeam')) {
        $currentTeam = currentTeam();
        if ($currentTeam) {
            $teamId = $currentTeam->id;
        }
    }

    if (is_null($teamId) && session('currentTeam')) {
        $teamId = data_get(session('currentTeam'), 'id');
    }

    return UsageRecord::create([
        'organization_id' => $organizationId,
        'team_id' => $teamId,
        'meter' => $params['meter'],
        'quantity' => $params['quantity'],
        'unit' => $params['unit'] ?? 'count',
        'description' => $params['description'] ?? null,
        'metadata' => $params['metadata'] ?? null,
        'recorded_at' => $params['recorded_at'] ?? now(),
    ]);
}

<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait Auditable
{
    protected static function bootAuditable()
    {
        static::created(function ($model) {
            static::logAudit($model, 'created', null, $model->toArray());
        });

        static::updated(function ($model) {
            $old = $model->getOriginal();
            $new = $model->getChanges();
            if (! empty($new)) {
                static::logAudit($model, 'updated', $old, $new);
            }
        });

        static::deleted(function ($model) {
            static::logAudit($model, 'deleted', $model->toArray(), null);
        });
    }

    protected static function logAudit($model, string $event, ?array $oldValues, ?array $newValues): void
    {
        $user = Auth::user();

        $organizationId = null;
        $teamId = null;

        if (method_exists($model, 'organization') && $model->organization) {
            $organizationId = $model->organization->id;
        } elseif (property_exists($model, 'organization_id') && $model->organization_id) {
            $organizationId = $model->organization_id;
        }

        if (method_exists($model, 'team') && $model->team) {
            $teamId = $model->team->id;
        } elseif (property_exists($model, 'team_id') && $model->team_id) {
            $teamId = $model->team_id;
        }

        if (function_exists('currentTeam') && ! $teamId) {
            $currentTeam = currentTeam();
            if ($currentTeam) {
                $teamId = $currentTeam->id;
            }
        }

        AuditLog::create([
            'organization_id' => $organizationId,
            'team_id' => $teamId,
            'user_id' => $user?->id,
            'event' => class_basename($model).'.'.$event,
            'auditable_type' => get_class($model),
            'auditable_id' => $model->id,
            'description' => static::getAuditDescription($model, $event),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }

    protected static function getAuditDescription($model, string $event): string
    {
        $name = method_exists($model, 'auditName') ? $model->auditName() : ($model->name ?? $model->id);

        return match ($event) {
            'created' => class_basename($model)." '{$name}' created.",
            'updated' => class_basename($model)." '{$name}' updated.",
            'deleted' => class_basename($model)." '{$name}' deleted.",
            default => class_basename($model)." '{$name}' {$event}.",
        };
    }
}

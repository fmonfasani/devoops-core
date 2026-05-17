<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UsageRecord;
use Illuminate\Http\Request;

class UsageRecordController extends Controller
{
    public function index(Request $request)
    {
        $query = UsageRecord::query();

        $teamId = $request->get('team_id');
        $organizationId = $request->get('organization_id');
        $meter = $request->get('meter');
        $from = $request->get('from');
        $to = $request->get('to');
        $perPage = min((int) $request->get('per_page', 50), 100);

        if ($organizationId) {
            $org = auth()->user()->organizations()->find($organizationId);
            if (! $org) {
                return response()->json(['message' => 'Organization not found.'], 404);
            }
            $query->where('organization_id', $organizationId);
        }

        if ($teamId) {
            if (! auth()->user()->teams->contains('id', $teamId)) {
                return response()->json(['message' => 'Team not found.'], 404);
            }
            $query->where('team_id', $teamId);
        }

        if (! $organizationId && ! $teamId) {
            $teamIds = auth()->user()->teams->pluck('id');
            $orgIds = auth()->user()->organizations->pluck('id');
            $query->where(function ($q) use ($teamIds, $orgIds) {
                $q->whereIn('team_id', $teamIds)
                  ->orWhereIn('organization_id', $orgIds);
            });
        }

        if ($meter) {
            $query->byMeter($meter);
        }

        if ($from && $to) {
            $query->between($from, $to);
        }

        return response()->json(serializeApiResponse($query->orderBy('recorded_at', 'desc')->paginate($perPage)));
    }

    public function summary(Request $request)
    {
        $teamId = $request->get('team_id');
        $organizationId = $request->get('organization_id');
        $from = $request->get('from', now()->startOfMonth()->toDateTimeString());
        $to = $request->get('to', now()->toDateTimeString());

        $query = UsageRecord::query();

        if ($organizationId) {
            $query->where('organization_id', $organizationId);
        }
        if ($teamId) {
            $query->where('team_id', $teamId);
        }

        $query->between($from, $to);

        $summary = $query->get()->groupBy('meter')->map(function ($records) {
            return [
                'total_quantity' => $records->sum('quantity'),
                'unit' => $records->first()->unit,
                'count' => $records->count(),
            ];
        });

        return response()->json($summary);
    }
}

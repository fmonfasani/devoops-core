<?php

use App\Models\AutomationHook;
use Illuminate\Support\Facades\Http;

function dispatch_automation_hooks(string $event, array $payload = []): void
{
    $hooks = AutomationHook::active()->forEvent($event)->get();

    foreach ($hooks as $hook) {
        try {
            $headers = [
                'Content-Type' => 'application/json',
                'X-DevOops-Event' => $event,
            ];

            $body = [
                'event' => $event,
                'data' => $payload,
                'timestamp' => now()->toIso8601String(),
            ];

            if ($hook->secret) {
                $body['signature'] = hash_hmac('sha256', json_encode($body), $hook->secret);
                $headers['X-DevOops-Signature'] = $body['signature'];
            }

            Http::timeout(10)->withHeaders($headers)->post($hook->url, $body);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Automation hook dispatch failed', [
                'hook_id' => $hook->id,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

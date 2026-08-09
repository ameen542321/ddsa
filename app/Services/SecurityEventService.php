<?php

namespace App\Services;

use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SecurityEventService
{
    private const SENSITIVE_KEYS = ['password', 'password_confirmation', 'token', '_token', 'authorization', 'cookie', 'secret', 'api_key'];

    public function record(string $code, string $category, string $severity, string $title, array $context = []): SecurityEvent
    {
        $request = app()->bound('request') ? request() : null;
        $sourceIp = $context['source_ip'] ?? $request?->ip();
        $route = $context['route'] ?? $request?->route()?->uri();
        $fingerprint = hash('sha256', implode('|', [$code, $sourceIp, $route, $context['subject'] ?? '']));
        $now = now();

        return DB::transaction(function () use ($code, $category, $severity, $title, $context, $request, $sourceIp, $route, $fingerprint, $now) {
            $event = SecurityEvent::query()
                ->where('fingerprint', $fingerprint)
                ->open()
                ->where('last_seen_at', '>=', $now->copy()->subHours(24))
                ->lockForUpdate()
                ->latest('id')
                ->first();

            $evidence = $this->redact($context['evidence'] ?? []);

            if ($event) {
                $event->update([
                    'occurrences' => $event->occurrences + 1,
                    'last_seen_at' => $now,
                    'severity' => $this->highestSeverity($event->severity, $severity),
                    'confidence' => max($event->confidence, (int) ($context['confidence'] ?? 50)),
                    'evidence' => array_replace_recursive($event->evidence ?? [], $evidence),
                ]);

                return $event->refresh();
            }

            $actor = $context['actor'] ?? null;
            $target = $context['target'] ?? null;

            return SecurityEvent::create([
                'event_code' => $code,
                'category' => $category,
                'severity' => $severity,
                'confidence' => min(100, max(0, (int) ($context['confidence'] ?? 50))),
                'status' => 'new',
                'title' => $title,
                'description' => $context['description'] ?? null,
                'source_ip' => $sourceIp,
                'user_agent' => Str::limit((string) ($context['user_agent'] ?? $request?->userAgent()), 1000, ''),
                'route' => $route,
                'http_method' => $context['http_method'] ?? $request?->method(),
                'actor_type' => $actor instanceof Model ? $actor::class : null,
                'actor_id' => $actor instanceof Model ? $actor->getKey() : null,
                'target_type' => $target instanceof Model ? $target::class : null,
                'target_id' => $target instanceof Model ? $target->getKey() : null,
                'fingerprint' => $fingerprint,
                'evidence' => $evidence,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
                'detected_at' => $now,
            ]);
        });
    }

    public function transition(SecurityEvent $event, User $admin, string $action, ?string $note = null): SecurityEvent
    {
        $transitions = [
            'acknowledge' => 'investigating',
            'contain' => 'contained',
            'resolve' => 'resolved',
            'false_positive' => 'false_positive',
        ];
        $to = $transitions[$action] ?? $event->status;

        return DB::transaction(function () use ($event, $admin, $action, $note, $to) {
            $from = $event->status;
            $changes = ['status' => $to];

            if ($action === 'acknowledge') {
                $changes += ['acknowledged_by' => $admin->id, 'assigned_to' => $admin->id, 'acknowledged_at' => now()];
            } elseif ($action === 'contain') {
                $changes['contained_at'] = now();
            } elseif (in_array($action, ['resolve', 'false_positive'], true)) {
                $changes += ['resolved_at' => now(), 'resolution' => $note];
            } elseif ($action === 'block_source') {
                abort_if(! $event->source_ip, 422, 'لا يوجد عنوان مصدر صالح للتقييد.');
                abort_if(hash_equals($event->source_ip, (string) request()->ip()), 422, 'لا يمكن للمدير تقييد عنوان اتصاله الحالي.');
                Cache::put($this->blockKey($event->source_ip), true, now()->addMinutes(30));
                $changes += ['status' => 'contained', 'contained_at' => now()];
            }

            $event->update($changes);
            $event->activities()->create([
                'user_id' => $admin->id,
                'action' => $action,
                'from_status' => $from,
                'to_status' => $changes['status'] ?? $to,
                'note' => $note,
                'ip_address' => request()->ip(),
            ]);

            return $event->refresh();
        });
    }

    public function isBlocked(?string $ip): bool
    {
        return $ip ? Cache::has($this->blockKey($ip)) : false;
    }

    private function blockKey(string $ip): string { return 'security:block:'.hash('sha256', $ip); }

    private function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array(Str::lower((string) $key), self::SENSITIVE_KEYS, true)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->redact($value);
            } elseif (is_string($value)) {
                $data[$key] = Str::limit($value, 2000, '…');
            }
        }

        return Arr::undot(Arr::dot($data));
    }

    private function highestSeverity(string $current, string $incoming): string
    {
        $weights = ['info' => 0, 'low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];
        return ($weights[$incoming] ?? 0) > ($weights[$current] ?? 0) ? $incoming : $current;
    }
}

<?php

namespace Tests\Feature;

use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\SecurityEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityCommandCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_open_security_command_center(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->saveQuietly();

        $this->get(route('admin.security.index'))->assertRedirect(route('login'));
        $this->actingAs($owner)->get(route('admin.security.index'))->assertRedirect(route('no.access'));
        $this->actingAs($admin)->get(route('admin.security.index'))->assertOk()->assertSee('مركز القيادة الأمنية');
    }

    public function test_service_groups_events_and_redacts_secrets(): void
    {
        $service = app(SecurityEventService::class);
        $first = $service->record('TEST.EVENT', 'testing', 'low', 'بلاغ اختباري', [
            'source_ip' => '192.0.2.5',
            'subject' => 'same',
            'evidence' => ['password' => 'never-store-me', 'safe' => 'visible'],
        ]);
        $second = $service->record('TEST.EVENT', 'testing', 'high', 'بلاغ اختباري', [
            'source_ip' => '192.0.2.5',
            'subject' => 'same',
        ]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(2, $second->occurrences);
        $this->assertSame('high', $second->severity);
        $this->assertSame('[REDACTED]', $second->evidence['password']);
        $this->assertStringNotContainsString('never-store-me', (string) $second->getRawOriginal('evidence'));
    }

    public function test_admin_action_is_validated_and_audited(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->saveQuietly();
        $event = app(SecurityEventService::class)->record('TEST.ACTION', 'testing', 'medium', 'بلاغ يحتاج أمرًا');

        $this->actingAs($admin)->patch(route('admin.security.action', $event), [
            'action' => 'resolve',
            'note' => 'تم التحقق من توقف السبب وإغلاق البلاغ.',
        ])->assertRedirect();

        $this->assertDatabaseHas('security_events', ['id' => $event->id, 'status' => 'resolved', 'acknowledged_by' => null]);
        $this->assertDatabaseHas('security_event_activities', ['security_event_id' => $event->id, 'user_id' => $admin->id, 'action' => 'resolve']);
    }

    public function test_failed_logins_are_limited_without_suspending_victim(): void
    {
        $user = User::factory()->create(['email' => 'victim@example.test', 'password' => Hash::make('correct-password')]);

        foreach (range(1, 5) as $attempt) {
            $this->post(route('login.submit'), ['email' => $user->email, 'password' => 'wrong-password'])->assertSessionHasErrors('email');
        }

        $this->post(route('login.submit'), ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['email' => 'تم تجاوز عدد المحاولات المسموح. حاول مرة أخرى لاحقًا.']);

        $this->assertSame('active', $user->refresh()->status);
        $this->assertDatabaseHas('security_events', ['event_code' => 'AUTH.LOGIN_FAILED', 'occurrences' => 5]);
        $this->assertDatabaseHas('security_events', ['event_code' => 'AUTH.RATE_LIMITED']);
    }
}

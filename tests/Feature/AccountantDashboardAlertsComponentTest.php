<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class AccountantDashboardAlertsComponentTest extends TestCase
{
    public function test_active_reference_alert_displays_the_arabic_weekday_name(): void
    {
        $html = Blade::render(
            '<x-accountant-dashboard-alerts active-reference-date="2026-08-09" />'
        );

        $this->assertStringContainsString('يوم الأحد، تاريخ 2026-08-09', $html);
    }
}

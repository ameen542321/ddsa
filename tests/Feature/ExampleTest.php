<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('من نحن')
            ->assertSee('الخدمات')
            ->assertSee('الشروط والأحكام')
            ->assertSee('سياسة الخصوصية')
            ->assertSee('تواصل معنا');
    }
}

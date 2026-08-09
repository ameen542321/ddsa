<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
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

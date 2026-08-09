<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailySalesProfitDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_sales_page_displays_profit_based_on_collected_amount(): void
    {
        $owner = User::factory()->create([
            'role' => 'user',
            'status' => 'active',
            'subscription_end_at' => now()->addDays(30),
        ]);

        $store = Store::factory()->create([
            'user_id' => $owner->id,
            'status' => 'active',
        ]);

        $product = Product::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'category_id' => null,
            'name' => 'Collected Profit Product',
            'slug' => 'collected-profit-product',
            'price' => 20,
            'cost_price' => 12,
            'quantity' => 100,
            'status' => 'active',
            'product_type' => 'standard',
            'roll_length' => 0,
            'is_splittable' => false,
            'items_per_unit' => 1,
            'piece_price' => 0,
            'min_stock' => 1,
        ]);

        $sale = Sale::create([
            'store_id' => $store->id,
            'employee_id' => null,
            'accountant_id' => null,
            'sale_type' => 'cash',
            'products_total' => 20,
            'tax_rate' => 0,
            'labor_total' => 0,
            'final_total' => 20,
            'paid_amount' => 40,
            'cash_amount' => 40,
            'card_amount' => 0,
            'remaining_amount' => 0,
            'has_partial_credit' => false,
            'profit' => 8,
            'total' => 20,
            'has_invoice' => false,
            'description' => 'Collected amount is sale basis',
            'created_at' => now()->startOfDay()->addHours(10),
            'updated_at' => now()->startOfDay()->addHours(10),
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 20,
            'total' => 20,
        ]);

        $response = $this
            ->actingAs($owner, 'web')
            ->get(route('user.stores.daily', $store->id));

        $response->assertOk();
        $response->assertSee('>28.00<', false);
    }
}

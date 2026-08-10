<?php

namespace Tests\Feature;

use App\Models\CreditSale;
use App\Models\Employee;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailySalesCreditIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_credit_collection_syncs_linked_daily_sale_paid_and_remaining_amounts(): void
    {
        $owner = User::factory()->create(['role' => 'user', 'status' => 'active']);
        $store = Store::factory()->create(['user_id' => $owner->id, 'status' => 'active']);
        $employee = Employee::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'name' => 'موظف آجل',
            'phone' => '0500000003',
            'salary' => 1000,
            'status' => 'active',
        ]);

        $sale = Sale::create([
            'store_id' => $store->id,
            'employee_id' => $employee->id,
            'sale_type' => 'credit',
            'products_total' => 80,
            'tax_rate' => 0,
            'labor_total' => 20,
            'final_total' => 100,
            'paid_amount' => 0,
            'cash_amount' => 0,
            'card_amount' => 0,
            'remaining_amount' => 100,
            'has_partial_credit' => false,
            'profit' => 30,
            'total' => 100,
            'has_invoice' => false,
            'description' => 'بيع آجل كامل',
            'business_date' => '2026-07-18',
        ]);

        $creditSale = CreditSale::create([
            'store_id' => $store->id,
            'person_id' => $employee->id,
            'person_type' => Employee::class,
            'amount' => 100,
            'remaining_amount' => 40,
            'sale_id' => $sale->id,
            'description' => 'وصف مستقل بدون رقم العملية',
            'credit_note' => 'عملية آجل يومية',
            'date' => '2026-07-18',
            'status' => CreditSale::STATUS_PENDING,
            'month' => '2026-07',
            'added_by' => $owner->id,
        ]);

        $this->assertSame($sale->id, $creditSale->resolveLinkedSaleId());

        $creditSale->collections()->create([
            'store_id' => $store->id,
            'sale_id' => $sale->id,
            'person_id' => $employee->id,
            'person_type' => Employee::class,
            'amount' => 60,
            'payment_method' => 'cash',
            'payment_method_label' => 'كاش',
            'cash_amount' => 60,
            'card_amount' => 0,
            'collection_date' => '2026-07-18',
            'collected_by' => $owner->id,
        ]);

        $creditSale->syncLinkedSaleCollectionState();
        $sale->refresh();

        $this->assertSame(60.0, (float) $sale->paid_amount);
        $this->assertSame(40.0, (float) $sale->remaining_amount);
    }
}

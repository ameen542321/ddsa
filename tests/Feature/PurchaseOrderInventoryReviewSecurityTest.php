<?php

namespace Tests\Feature;

use App\Models\Accountant;
use App\Models\Employee;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Modules\PurchaseOrders\Models\StorePurchaseOrder;
use App\Modules\PurchaseOrders\Models\StorePurchaseOrderItem;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderInventoryReviewSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_accountant_create_page_does_not_render_stock_or_cost_values(): void
    {
        [$owner, $store, $accountant] = $this->ownerStoreAndAccountant();
        Product::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'name' => 'كشاف اختبار حساس',
            'price' => 7654.32,
            'cost_price' => 8765.43,
            'quantity' => 98765.432,
            'status' => 'active',
            'usage_type' => Product::USAGE_TYPE_SALE,
        ]);

        $response = $this->actingAs($accountant, 'accountant')
            ->get(route('accountant.purchase-orders.create'));

        $response->assertOk();

        $decodedHtml = html_entity_decode($response->getContent(), ENT_QUOTES | ENT_HTML5);
        $this->assertStringContainsString(
            json_encode('كشاف اختبار حساس', JSON_THROW_ON_ERROR),
            $decodedHtml
        );
        $response->assertDontSee('98765.432', false);
        $response->assertDontSee('8765.43', false);
        $response->assertDontSee('7654.32', false);
        $response->assertDontSee('الكمية الموجودة', false);
    }

    public function test_accountant_cannot_open_another_accountants_purchase_order(): void
    {
        [$owner, $store, $accountant] = $this->ownerStoreAndAccountant();
        $otherAccountant = $this->createAccountant($owner, $store, 'محاسب آخر');
        $order = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'accountant_id' => $otherAccountant->id,
            'supplier_name' => 'مورد الاختبار',
            'status' => 'draft',
        ]);

        $this->actingAs($accountant, 'accountant')
            ->get(route('accountant.purchase-orders.show', $order->id))
            ->assertForbidden();
    }

    public function test_accountant_cannot_download_another_accountants_receipt_pdf(): void
    {
        [$owner, $store, $accountant] = $this->ownerStoreAndAccountant();
        $otherAccountant = $this->createAccountant($owner, $store, 'محاسب PDF آخر');
        $order = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'accountant_id' => $otherAccountant->id,
            'supplier_name' => 'مورد PDF',
            'status' => 'sent',
            'workflow_status' => 'pending_receipt_confirmation',
        ]);

        $this->actingAs($accountant, 'accountant')
            ->get(route('accountant.purchase-orders.receipt.pdf', $order))
            ->assertForbidden();
    }

    public function test_accountant_cannot_mutate_or_count_another_accountants_order(): void
    {
        [$owner, $store, $accountant] = $this->ownerStoreAndAccountant();
        $otherAccountant = $this->createAccountant($owner, $store, 'محاسب مسارات آخر');
        $order = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'accountant_id' => $otherAccountant->id,
            'status' => 'sent',
            'workflow_status' => 'pending_receipt_confirmation',
            'inventory_review_status' => 'returned_to_accountant',
        ]);

        $this->actingAs($accountant, 'accountant');
        $this->get(route('accountant.purchase-orders.edit', $order))->assertForbidden();
        $this->put(route('accountant.purchase-orders.update', $order), [])->assertForbidden();
        $this->get(route('accountant.purchase-orders.inventory-count', $order))->assertForbidden();
        $this->post(route('accountant.purchase-orders.inventory-count.save', $order), [])->assertForbidden();
        $this->get(route('accountant.purchase-orders.inventory-count.pdf', $order))->assertForbidden();
        $this->post(route('accountant.purchase-orders.receive', $order), [])->assertForbidden();
    }

    public function test_owner_pdf_endpoint_rejects_document_outside_its_stage(): void
    {
        [$owner, $store] = $this->ownerStoreAndAccountant();
        $order = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'supplier_name' => 'مورد مستند خاطئ',
            'status' => 'received',
            'workflow_status' => 'pending_inventory_approval',
        ]);

        $this->actingAs($owner)
            ->get(route('user.stores.purchase-orders.pdf', [$store, $order, 'type' => 'order']))
            ->assertForbidden();
    }

    public function test_owner_can_filter_orders_by_the_operational_workflow_status(): void
    {
        [$owner, $store] = $this->ownerStoreAndAccountant();
        StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'supplier_name' => 'مورد مراجعة الاستلام',
            'status' => 'received',
            'workflow_status' => 'pending_owner_receipt_review',
        ]);
        StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'supplier_name' => 'مورد الاعتماد المخزني',
            'status' => 'received',
            'workflow_status' => 'pending_inventory_approval',
        ]);

        $this->actingAs($owner)
            ->get(route('user.stores.purchase-orders.index', [
                $store,
                'workflow_status' => 'pending_owner_receipt_review',
            ]))
            ->assertOk()
            ->assertSee('مورد مراجعة الاستلام')
            ->assertDontSee('مورد الاعتماد المخزني');
    }

    public function test_opening_a_returned_order_flashes_its_arabic_status_and_note(): void
    {
        [$owner, $store] = $this->ownerStoreAndAccountant();
        $order = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'status' => 'draft',
            'workflow_status' => 'returned_for_edit',
            'inventory_review_status' => 'returned_for_edit',
            'inventory_review_note' => 'عدّل كمية المنتج',
        ]);

        $this->actingAs($owner)
            ->get(route('user.stores.purchase-orders.show', [$store, $order]))
            ->assertOk()
            ->assertSee('حالة الطلبية: معادة للتعديل — عدّل كمية المنتج', false);
    }

    public function test_owner_sees_inventory_difference_but_accountant_show_page_does_not(): void
    {
        [$owner, $store, $accountant] = $this->ownerStoreAndAccountant();
        $product = Product::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'name' => 'منتج فرق الجرد',
            'price' => 20,
            'cost_price' => 10,
            'quantity' => 10,
            'status' => 'active',
            'usage_type' => Product::USAGE_TYPE_SALE,
        ]);
        $order = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'accountant_id' => $accountant->id,
            'supplier_name' => 'مورد الاختبار',
            'status' => 'sent',
            'workflow_status' => 'returned_after_count',
            'inventory_review_status' => 'pending_owner_after_count',
            'inventory_submitted_at' => now(),
        ]);
        StorePurchaseOrderItem::create([
            'store_purchase_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity_requested' => 3,
            'unit_type' => 'unit',
            'cost_price_at_order' => 10,
            'inventory_count_quantity' => 8,
            'inventory_count_unit' => 'unit',
            'system_quantity_snapshot' => 10,
            'inventory_snapshot_at' => now(),
        ]);

        $this->actingAs($owner)
            ->get(route('user.stores.purchase-orders.show', [$store->id, $order->id]))
            ->assertOk()
            ->assertSee('نقص', false)
            ->assertSee('10.000', false);

        $this->actingAs($accountant, 'accountant')
            ->get(route('accountant.purchase-orders.show', $order->id))
            ->assertOk()
            ->assertDontSee('نقص', false)
            ->assertDontSee('10.000', false);
    }

    public function test_inventory_count_shows_unit_choices_only_for_convertible_products(): void
    {
        [$owner, $store, $accountant] = $this->ownerStoreAndAccountant();
        $products = collect([
            ['name' => 'منتج قطعة واحدة', 'product_type' => 'standard', 'is_splittable' => false],
            ['name' => 'منتج طقم', 'product_type' => 'standard', 'is_splittable' => true, 'items_per_unit' => 2],
            ['name' => 'منتج رول', 'product_type' => 'fractional', 'roll_length' => 30],
        ])->map(fn (array $attributes) => Product::create(array_merge([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'price' => 20,
            'cost_price' => 10,
            'quantity' => 10,
            'status' => 'active',
            'usage_type' => Product::USAGE_TYPE_SALE,
        ], $attributes)));
        $order = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'accountant_id' => $accountant->id,
            'status' => 'draft',
            'workflow_status' => 'returned_for_count',
            'inventory_review_status' => 'returned_to_accountant',
        ]);

        foreach ($products as $product) {
            StorePurchaseOrderItem::create([
                'store_purchase_order_id' => $order->id,
                'product_id' => $product->id,
                'quantity_requested' => 1,
                'unit_type' => 'unit',
                'cost_price_at_order' => 10,
                'inventory_count_required' => true,
            ]);
        }

        $this->actingAs($accountant, 'accountant')
            ->get(route('accountant.purchase-orders.inventory-count', $order))
            ->assertOk()
            ->assertSee('الطقم = 2 حبة', false)
            ->assertSee('الرول = 30.00 متر', false)
            ->assertSee('name="items['.$order->items->firstWhere('product_id', $products[0]->id)->id.'][inventory_count_unit]" value="unit"', false)
            ->assertSee('منتج قطعة واحدة')
            ->assertSee('منتج طقم')
            ->assertSee('منتج رول');

        $this->assertSame(2, substr_count($response->getContent(), 'وحدة الجرد'));
    }

    public function test_inventory_paper_view_does_not_include_system_snapshot_or_difference(): void
    {
        [$owner, $store, $accountant] = $this->ownerStoreAndAccountant();
        $product = Product::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'name' => 'منتج PDF الجرد',
            'price' => 20,
            'cost_price' => 10,
            'quantity' => 77,
            'status' => 'active',
            'usage_type' => Product::USAGE_TYPE_SALE,
        ]);
        $order = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'accountant_id' => $accountant->id,
            'supplier_name' => 'مورد الاختبار',
            'status' => 'draft',
            'workflow_status' => 'returned_for_count',
            'inventory_review_status' => 'returned_to_accountant',
        ]);
        StorePurchaseOrderItem::create([
            'store_purchase_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity_requested' => 4,
            'unit_type' => 'unit',
            'cost_price_at_order' => 10,
            'inventory_count_quantity' => 8,
            'inventory_count_unit' => 'unit',
            'system_quantity_snapshot' => 77,
            'inventory_snapshot_at' => now(),
        ]);

        $order->load(['items.product', 'store.user', 'accountant']);
        $store = $order->store;
        $html = view('modules.purchase-orders.inventory-count-pdf', compact('order', 'store'))->render();

        $this->assertStringContainsString('منتج PDF الجرد', $html);
        $this->assertStringContainsString('كمية الجرد', $html);
        $this->assertStringNotContainsString('لقطة النظام', $html);
        $this->assertStringNotContainsString('الفرق', $html);
        $this->assertStringNotContainsString('77', $html);
    }

    private function ownerStoreAndAccountant(): array
    {
        $owner = User::factory()->create([
            'role' => 'user',
            'status' => 'active',
            'welcome_shown' => true,
            'subscription_end_at' => now()->addDays(30),
        ]);
        $store = Store::factory()->create([
            'user_id' => $owner->id,
            'status' => 'active',
        ]);
        $accountant = $this->createAccountant($owner, $store, 'محاسب الاختبار');

        return [$owner, $store, $accountant];
    }

    private function createAccountant(User $owner, Store $store, string $name): Accountant
    {
        $employee = Employee::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'name' => $name,
            'phone' => '0500000000',
            'salary' => 0,
            'status' => 'active',
        ]);

        return Accountant::create([
            'employee_id' => $employee->id,
            'user_id' => $owner->id,
            'store_id' => $store->id,
            'name' => $name,
            'email' => 'accountant-' . uniqid() . '@example.test',
            'password' => 'password',
            'status' => 'active',
        ]);
    }
}

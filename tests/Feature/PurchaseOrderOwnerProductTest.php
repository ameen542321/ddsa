<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Modules\PurchaseOrders\Models\StorePurchaseOrder;
use App\Modules\PurchaseOrders\Models\StorePurchaseOrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderOwnerProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_save_missing_owner_purchase_item_as_a_non_sellable_product(): void
    {
        $owner = User::factory()->create([
            'role' => 'user',
            'status' => 'active',
            'welcome_shown' => true,
            'subscription_end_at' => now()->addDays(30),
        ]);
        $store = $owner->stores()->firstOrFail();
        $store->update(['status' => 'active']);
        $category = Category::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'name' => 'مشتريات المالك',
            'status' => 'active',
        ]);
        $order = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'supplier_name' => 'مورد الاختبار',
            'status' => 'sent',
            'sent_at' => now(),
        ]);
        $item = StorePurchaseOrderItem::create([
            'store_purchase_order_id' => $order->id,
            'custom_product_name' => 'طقم ضيافة المالك',
            'quantity_requested' => 2,
            'unit_type' => 'kit',
            'items_per_unit' => 6,
            'cost_price_at_order' => 100,
            'add_to_owner_purchases' => true,
        ]);

        $response = $this->actingAs($owner)->postJson(
            route('user.stores.purchase-orders.items.owner-product.store', [$store, $order, $item]),
            [
                'name' => 'طقم ضيافة المالك',
                'category_id' => $category->id,
                'owner_unit_type' => 'kit',
                'receipt_total_cost' => 120,
                'received_quantity' => 2,
                'items_per_unit' => 12,
                'usage_type' => Product::USAGE_TYPE_OWNER_PURCHASE,
            ]
        );

        $response->assertCreated();
        $product = Product::where('name', 'طقم ضيافة المالك')->firstOrFail();
        $this->assertSame(Product::USAGE_TYPE_OWNER_PURCHASE, $product->usage_type);
        $this->assertSame(0.0, (float) $product->quantity);
        $this->assertSame(0.0, (float) $product->min_stock);
        $this->assertSame(0.0, (float) $product->price);
        $this->assertSame(60.0, (float) $product->cost_price);
        $this->assertTrue((bool) $product->is_splittable);
        $this->assertSame(12, (int) $product->items_per_unit);
        $this->assertSame(5.0, round((float) $product->cost_price / (int) $product->items_per_unit, 2));
        $this->assertFalse(Product::sellable()->whereKey($product->id)->exists());
        $this->assertDatabaseHas('store_purchase_order_items', [
            'id' => $item->id,
            'product_id' => $product->id,
            'add_to_owner_purchases' => true,
        ]);
    }

    public function test_owner_can_save_item_as_a_sale_product_after_receipt_review(): void
    {
        $owner = User::factory()->create([
            'role' => 'user',
            'status' => 'active',
            'welcome_shown' => true,
            'subscription_end_at' => now()->addDays(30),
        ]);
        $store = $owner->stores()->firstOrFail();
        $store->update(['status' => 'active']);
        $category = Category::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'name' => 'منتجات البيع',
            'status' => 'active',
        ]);
        $order = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'supplier_name' => 'مورد الاختبار',
            'status' => 'received',
            'workflow_status' => 'pending_owner_receipt_review',
            'received_at' => now(),
        ]);
        $item = StorePurchaseOrderItem::create([
            'store_purchase_order_id' => $order->id,
            'custom_product_name' => 'منتج بيع جديد',
            'quantity_requested' => 3,
            'quantity_received' => 3,
            'unit_type' => 'piece',
            'cost_price_at_receipt' => 90,
            'add_to_owner_purchases' => true,
        ]);

        $response = $this->actingAs($owner)->postJson(
            route('user.stores.purchase-orders.items.owner-product.store', [$store, $order, $item]),
            [
                'name' => 'منتج بيع جديد',
                'category_id' => $category->id,
                'owner_unit_type' => 'piece',
                'receipt_total_cost' => 90,
                'received_quantity' => 3,
                'usage_type' => Product::USAGE_TYPE_SALE,
                'selling_price' => 45,
                'min_stock' => 2,
                'carton_qty' => 12,
                'waste_percentage' => 3.5,
                'quick_sale_default_unit' => 'piece',
            ]
        );

        $response->assertCreated();
        $product = Product::where('name', 'منتج بيع جديد')->firstOrFail();
        $this->assertSame(Product::USAGE_TYPE_SALE, $product->usage_type);
        $this->assertSame(45.0, (float) $product->price);
        $this->assertSame(2.0, (float) $product->min_stock);
        $this->assertSame(12, (int) $product->carton_qty);
        $this->assertSame(3.5, (float) $product->waste_percentage);
        $this->assertTrue(Product::sellable()->whereKey($product->id)->exists());
        $this->assertDatabaseHas('store_purchase_order_items', [
            'id' => $item->id,
            'product_id' => $product->id,
            'add_to_owner_purchases' => false,
        ]);
    }

    public function test_owner_can_link_an_existing_store_product_from_the_single_product_dialog(): void
    {
        $owner = User::factory()->create([
            'role' => 'user',
            'status' => 'active',
            'welcome_shown' => true,
            'subscription_end_at' => now()->addDays(30),
        ]);
        $store = $owner->stores()->firstOrFail();
        $store->update(['status' => 'active']);
        $category = Category::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'name' => 'منتجات البيع',
            'status' => 'active',
        ]);
        $product = Product::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'name' => 'منتج موجود',
            'slug' => 'existing-product-'.$store->id,
            'price' => 20,
            'cost_price' => 10,
            'quantity' => 5,
            'min_stock' => 1,
            'status' => 'active',
            'product_type' => 'standard',
            'usage_type' => Product::USAGE_TYPE_SALE,
        ]);
        $order = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'supplier_name' => 'مورد الاختبار',
            'status' => 'received',
            'workflow_status' => 'pending_owner_receipt_review',
            'received_at' => now(),
        ]);
        $item = StorePurchaseOrderItem::create([
            'store_purchase_order_id' => $order->id,
            'custom_product_name' => 'بند غير مربوط',
            'quantity_requested' => 2,
            'quantity_received' => 2,
            'unit_type' => 'piece',
            'cost_price_at_receipt' => 20,
        ]);

        $response = $this->actingAs($owner)->postJson(
            route('user.stores.purchase-orders.items.owner-product.store', [$store, $order, $item]),
            [
                'existing_product_id' => $product->id,
                'owner_unit_type' => 'piece',
                'usage_type' => Product::USAGE_TYPE_SALE,
            ]
        );

        $response->assertOk()->assertJsonPath('product.id', $product->id);
        $this->assertDatabaseHas('store_purchase_order_items', [
            'id' => $item->id,
            'product_id' => $product->id,
            'matched_product_id' => null,
            'add_to_owner_purchases' => false,
        ]);
    }
}

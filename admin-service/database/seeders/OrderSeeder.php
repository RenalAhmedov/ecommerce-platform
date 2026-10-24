<?php

namespace Database\Seeders;

use App\Enums\InventoryMovementReason;
use App\Enums\OrderStatus;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Orders over the last 30 days in every status the shop produces, so the
 * dashboard, reports and order screens have history to show. Each one is
 * written like the api-gateway's checkout (stock locked and taken, "sale"
 * movements, price snapshots); cancelled and declined orders then give their
 * stock back through the same InventoryService the panel uses.
 */
class OrderSeeder extends Seeder
{
    /** [customer, days ago, hour, status, [sku => quantity]] */
    private const ORDERS = [
        ['ada.lovelace', 29, 10, OrderStatus::Delivered, ['AUD-HP-01' => 1, 'KIT-MUG-01' => 2]],
        ['grace.hopper', 27, 14, OrderStatus::Delivered, ['LAP-14-01' => 1]],
        ['alan.turing', 25, 9, OrderStatus::Delivered, ['ACC-KEY-01' => 1, 'ACC-MOU-01' => 1]],
        ['margaret.hamilton', 23, 16, OrderStatus::Delivered, ['BOK-ALG-01' => 1]],
        ['katherine.johnson', 21, 11, OrderStatus::Cancelled, ['KIT-PAN-01' => 1]],
        ['barbara.liskov', 20, 13, OrderStatus::Delivered, ['CLO-TSH-01' => 3, 'CLO-HOD-01' => 1]],
        ['edsger.dijkstra', 18, 15, OrderStatus::Delivered, ['DEC-LMP-01' => 1, 'DEC-PLT-01' => 2]],
        ['donald.knuth', 16, 10, OrderStatus::Delivered, ['BOK-CLN-01' => 1, 'BOK-ALG-01' => 1]],
        ['ada.lovelace', 14, 12, OrderStatus::Delivered, ['AUD-SPK-01' => 1]],
        ['grace.hopper', 12, 17, OrderStatus::PaymentFailed, ['LAP-16-01' => 1]],
        ['grace.hopper', 12, 18, OrderStatus::Delivered, ['LAP-16-01' => 1]],
        ['alan.turing', 10, 9, OrderStatus::Shipped, ['KIT-KNF-01' => 1, 'KIT-KTL-01' => 1]],
        ['margaret.hamilton', 9, 14, OrderStatus::Shipped, ['ACC-HUB-01' => 1, 'ACC-MOU-01' => 1]],
        ['katherine.johnson', 7, 11, OrderStatus::Shipped, ['AUD-EAR-01' => 1]],
        ['barbara.liskov', 6, 15, OrderStatus::Processing, ['KIT-MUG-01' => 4]],
        ['edsger.dijkstra', 5, 10, OrderStatus::Processing, ['CLO-HOD-01' => 2]],
        ['donald.knuth', 4, 13, OrderStatus::Processing, ['ACC-KEY-01' => 1]],
        ['ada.lovelace', 3, 16, OrderStatus::Paid, ['DEC-PLT-01' => 1, 'KIT-MUG-01' => 1]],
        ['alan.turing', 2, 9, OrderStatus::Cancelled, ['AUD-HP-01' => 1]],
        ['margaret.hamilton', 2, 14, OrderStatus::Paid, ['LAP-14-01' => 1, 'ACC-MOU-01' => 1]],
        ['katherine.johnson', 1, 11, OrderStatus::Paid, ['CLO-TSH-01' => 2]],
        ['barbara.liskov', 1, 17, OrderStatus::Paid, ['AUD-SPK-01' => 2]],
        ['edsger.dijkstra', 0, 9, OrderStatus::Paid, ['KIT-KNF-01' => 1]],
        ['donald.knuth', 0, 10, OrderStatus::PaymentFailed, ['BOK-ALG-01' => 1]],
    ];

    private const CITIES = [
        ['Berlin', '10115'], ['Hamburg', '20095'], ['Munich', '80331'], ['Cologne', '50667'],
        ['Frankfurt', '60311'], ['Stuttgart', '70173'], ['Leipzig', '04109'], ['Dresden', '01067'],
    ];

    public function run(InventoryService $inventory): void
    {
        $admin = User::where('email', UserSeeder::ADMIN_EMAIL)->firstOrFail();

        foreach (self::ORDERS as $index => [$customer, $daysAgo, $hour, $status, $items]) {
            $orderNumber = sprintf('ORD-DEMO-%04d', $index + 1);

            if (Order::where('order_number', $orderNumber)->exists()) {
                continue;
            }

            $user = User::where('email', "{$customer}@example.com")->firstOrFail();
            // Never in the future, and over an hour old, so the api-gateway's
            // confirmation-email sweep doesn't pick today's orders up.
            $placedAt = CarbonImmutable::now()->subDays($daysAgo)->setTime($hour, 15)->min(CarbonImmutable::now()->subHours(2));

            DB::transaction(function () use ($orderNumber, $user, $status, $items, $placedAt, $index, $inventory, $admin) {
                $order = $this->placeOrder($orderNumber, $user, $status, $items, $placedAt, $index);

                if ($status === OrderStatus::Cancelled || $status === OrderStatus::PaymentFailed) {
                    $quantities = $order->items->mapWithKeys(fn ($item) => [$item->product_id => $item->quantity])->all();
                    $inventory->release($quantities, $order->id, $status === OrderStatus::Cancelled ? $admin : null);
                }
            });
        }
    }

    /** @param  array<string, int>  $items  sku => quantity */
    private function placeOrder(string $orderNumber, User $user, OrderStatus $status, array $items, CarbonImmutable $placedAt, int $index): Order
    {
        $products = Product::whereIn('sku', array_keys($items))->get()->keyBy('sku');
        $stock = Inventory::whereIn('product_id', $products->pluck('id'))->orderBy('product_id')->lockForUpdate()->pluck('quantity', 'product_id');

        $lines = [];
        $total = '0.00';
        foreach ($items as $sku => $quantity) {
            $product = $products[$sku] ?? throw new LogicException("Unknown product {$sku} in the demo orders.");
            if ($stock[$product->id] < $quantity) {
                throw new LogicException("Not enough {$sku} in stock for {$orderNumber}.");
            }

            $lineTotal = bcmul($product->price, (string) $quantity, 2);
            $total = bcadd($total, $lineTotal, 2);
            $lines[] = ['product_id' => $product->id, 'product_name' => $product->name, 'unit_price' => $product->price, 'quantity' => $quantity, 'line_total' => $lineTotal];
        }

        $paid = $status !== OrderStatus::PaymentFailed;
        [$city, $postalCode] = self::CITIES[$index % count(self::CITIES)];

        $order = new Order([
            'order_number' => $orderNumber,
            'user_id' => $user->id,
            'status' => $status,
            'subtotal' => $total,
            'total' => $total,
            'payment_reference' => $paid ? sprintf('mock_demo_%04d', $index + 1) : null,
            'shipping_address' => ['fullName' => $user->name, 'line1' => ($index + 1).' Main Street', 'city' => $city, 'postalCode' => $postalCode, 'country' => 'DE'],
            'paid_at' => $paid ? $placedAt->addMinute() : null,
        ]);
        $order->created_at = $placedAt;
        $order->updated_at = $placedAt;
        $order->save();

        foreach ($lines as $line) {
            $order->items()->create($line);
            Inventory::where('product_id', $line['product_id'])->decrement('quantity', $line['quantity']);
            InventoryMovement::create([
                'product_id' => $line['product_id'],
                'order_id' => $order->id,
                'user_id' => $user->id,
                'change' => -$line['quantity'],
                'reason' => InventoryMovementReason::Sale,
            ]);
        }

        return $order->load('items');
    }
}

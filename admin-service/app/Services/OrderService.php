<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Events\OrderPaid;
use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Models\User;
use App\Repositories\OrderRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly InventoryService $inventory,
        private readonly CatalogNotifier $catalog,
    ) {}

    /**
     * @param  array{search?: ?string, status?: ?string, date_from?: ?string, date_to?: ?string}  $filters
     * @return LengthAwarePaginator<int, Order>
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->orders->paginate($filters, $perPage);
    }

    public function withDetails(Order $order): Order
    {
        return $this->orders->loadDetails($order);
    }

    /**
     * Locks the order row first and the stock rows second, the same order as
     * the Nest api-gateway, and re-checks the status under the lock, so a
     * double click, two admins or a Nest job running at the same moment can
     * never apply a change twice (e.g. return the stock of a cancelled order twice).
     *
     * @throws ValidationException when the change is not allowed from the current status
     */
    public function changeStatus(Order $order, OrderStatus $status, User $admin): Order
    {
        $order = DB::transaction(function () use ($order, $status, $admin) {
            $order = $this->orders->lockById($order->id);

            if (! $order->status->canMoveTo($status)) {
                throw ValidationException::withMessages([
                    'status' => "Order {$order->order_number} is {$order->status->label()} and cannot be changed to {$status->label()}.",
                ]);
            }

            if ($status === OrderStatus::Cancelled) {
                $this->inventory->release($this->orders->quantitiesByProduct($order), $order->id, $admin);
            }

            $previous = $order->status;
            $order->status = $status;
            $this->orders->save($order);

            Log::info('Order status changed', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'from' => $previous->value,
                'to' => $status->value,
                'stock_returned' => $status === OrderStatus::Cancelled,
            ]);

            return $order;
        });

        // Returned stock changes the products' "stock" in the shop's cached responses.
        if ($status === OrderStatus::Cancelled) {
            $this->catalog->catalogChanged('order cancelled, stock returned');
        }

        OrderStatusChanged::dispatch($order, $admin);

        return $order;
    }

    /** The api-gateway took a payment: tells every admin with the panel open. */
    public function announcePaid(int $orderId): void
    {
        $order = $this->orders->findWithCustomer($orderId);

        if ($order === null) {
            Log::warning('Paid order announced by the api-gateway does not exist', ['order_id' => $orderId]);

            return;
        }

        OrderPaid::dispatch($order);

        Log::info('Admins notified of a paid shop order', ['order_id' => $order->id, 'order_number' => $order->order_number]);
    }
}

<?php

namespace App\Repositories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\LikePattern;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class OrderRepository
{
    /**
     * Customers who were soft-deleted still show on their orders.
     *
     * @param  array{search?: ?string, status?: ?string, date_from?: ?string, date_to?: ?string}  $filters
     * @return LengthAwarePaginator<int, Order>
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return Order::query()
            ->with(['user' => fn ($query) => $query->withTrashed()])
            ->withCount('items')
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $pattern = LikePattern::contains($search);
                $query->where(fn (Builder $query) => $query
                    ->whereLike('order_number', $pattern)
                    ->orWhereHas('user', fn (Builder $user) => $user
                        ->withTrashed()
                        ->where(fn (Builder $user) => $user->whereLike('email', $pattern)->orWhereLike('name', $pattern))));
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', OrderStatus::from($status)))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->where('created_at', '>=', CarbonImmutable::parse($date)->startOfDay()))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->where('created_at', '<', CarbonImmutable::parse($date)->addDay()->startOfDay()))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function loadDetails(Order $order): Order
    {
        return $order->load([
            'user' => fn ($query) => $query->withTrashed(),
            'items.product' => fn ($query) => $query->withTrashed(),
            'inventoryMovements' => fn ($query) => $query->with('user')->orderBy('id'),
        ]);
    }

    /** Re-reads the row with SELECT … FOR UPDATE, as the Nest api-gateway does before any status change. */
    public function lockById(int $id): Order
    {
        return Order::whereKey($id)->lockForUpdate()->firstOrFail();
    }

    /** @return array<int, int> product id => quantity */
    public function quantitiesByProduct(Order $order): array
    {
        return $order->items()->pluck('quantity', 'product_id')->all();
    }

    public function save(Order $order): void
    {
        $order->save();
    }
}

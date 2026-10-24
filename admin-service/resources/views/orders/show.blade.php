<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight font-mono">{{ $order->order_number }}</h2>
            <x-order-status :status="$order->status" :order="$order->id" />
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-input-error :messages="$errors->all()" />

            <div class="grid gap-6 md:grid-cols-3">
                <section class="bg-white shadow-sm sm:rounded-lg p-5 text-sm space-y-1">
                    <h3 class="mb-2 font-medium text-gray-900">{{ __('Customer') }}</h3>
                    <p>{{ $order->user->name }}{{ $order->user->trashed() ? ' ('.__('deleted').')' : '' }}</p>
                    <p class="text-gray-600">{{ $order->user->email }}</p>
                </section>

                <section class="bg-white shadow-sm sm:rounded-lg p-5 text-sm space-y-1">
                    <h3 class="mb-2 font-medium text-gray-900">{{ __('Shipping address') }}</h3>
                    @forelse ($order->shipping_address ?? [] as $field => $value)
                        <p><span class="text-gray-500">{{ $field }}:</span> {{ $value }}</p>
                    @empty
                        <p class="text-gray-500">—</p>
                    @endforelse
                </section>

                <section class="bg-white shadow-sm sm:rounded-lg p-5 text-sm space-y-1">
                    <h3 class="mb-2 font-medium text-gray-900">{{ __('Payment') }}</h3>
                    <p><span class="text-gray-500">{{ __('Placed') }}:</span> {{ $order->created_at->format('Y-m-d H:i') }}</p>
                    <p><span class="text-gray-500">{{ __('Paid') }}:</span> {{ $order->paid_at?->format('Y-m-d H:i') ?? '—' }}</p>
                    <p class="break-all"><span class="text-gray-500">{{ __('Reference') }}:</span> {{ $order->payment_reference ?? '—' }}</p>
                </section>
            </div>

            <section class="bg-white shadow-sm sm:rounded-lg p-5">
                <h3 class="mb-3 font-medium text-gray-900">{{ __('Status') }}</h3>
                @if ($order->status->adminTransitions() === [])
                    <p class="text-sm text-gray-600">
                        @if ($order->status === \App\Enums\OrderStatus::Pending)
                            {{ __('Waiting for payment. The shop finishes or expires pending orders on its own, so they cannot be changed here.') }}
                        @else
                            {{ __('This order is :status; there is no further step.', ['status' => strtolower($order->status->label())]) }}
                        @endif
                    </p>
                @else
                    <div class="flex flex-wrap gap-3">
                        @foreach ($order->status->adminTransitions() as $next)
                            <form method="POST" action="{{ route('orders.status', $order) }}"
                                @if ($next === \App\Enums\OrderStatus::Cancelled) onsubmit="return confirm('Cancel this order? Its items go back into stock.')" @endif>
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="{{ $next->value }}">
                                <button class="rounded-md px-4 py-2 text-xs font-semibold uppercase tracking-widest {{ $next === \App\Enums\OrderStatus::Cancelled ? 'bg-white text-red-700 ring-1 ring-red-300 hover:bg-red-50' : 'bg-gray-800 text-white hover:bg-gray-700' }}">
                                    {{ __('Mark as') }} {{ strtolower($next->label()) }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3">{{ __('Product') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Unit price') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Qty') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Line total') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($order->items as $item)
                            <tr>
                                <td class="px-4 py-3">
                                    {{ $item->product_name }}
                                    <span class="font-mono text-xs text-gray-500">{{ $item->product->sku }}</span>
                                </td>
                                <td class="px-4 py-3 text-right">{{ $item->unit_price }}</td>
                                <td class="px-4 py-3 text-right">{{ $item->quantity }}</td>
                                <td class="px-4 py-3 text-right">{{ $item->line_total }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="text-sm">
                        <tr><td colspan="3" class="px-4 py-2 text-right text-gray-500">{{ __('Subtotal') }}</td><td class="px-4 py-2 text-right">{{ $order->subtotal }}</td></tr>
                        <tr><td colspan="3" class="px-4 py-2 text-right font-medium">{{ __('Total') }}</td><td class="px-4 py-2 text-right font-medium">{{ $order->total }}</td></tr>
                    </tfoot>
                </table>
            </section>

            @if ($order->inventoryMovements->isNotEmpty())
                <section class="bg-white shadow-sm sm:rounded-lg p-5">
                    <h3 class="mb-3 font-medium text-gray-900">{{ __('Stock movements') }}</h3>
                    <ul class="space-y-1 text-sm">
                        @foreach ($order->inventoryMovements as $movement)
                            <li>
                                <span class="text-gray-500">{{ $movement->created_at->format('Y-m-d H:i') }}</span>
                                {{ ucfirst($movement->reason->value) }}:
                                <span class="font-mono {{ $movement->change < 0 ? 'text-red-700' : 'text-green-700' }}">{{ $movement->change > 0 ? '+' : '' }}{{ $movement->change }}</span>
                                × {{ $order->items->firstWhere('product_id', $movement->product_id)?->product_name ?? '#'.$movement->product_id }}
                                @if ($movement->user)
                                    <span class="text-gray-500">({{ $movement->user->name }})</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <a href="{{ route('orders.index') }}" class="text-sm text-gray-600 underline">{{ __('Back to orders') }}</a>
        </div>
    </div>
</x-app-layout>

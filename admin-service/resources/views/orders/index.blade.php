<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Orders') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <form method="GET" action="{{ route('orders.index') }}" class="bg-white shadow-sm sm:rounded-lg p-4 flex flex-wrap items-end gap-3">
                <div>
                    <x-input-label for="search" :value="__('Order number or customer')" />
                    <x-text-input id="search" name="search" type="search" class="mt-1" :value="request('search')" />
                </div>
                <div>
                    <x-input-label for="status" :value="__('Status')" />
                    <x-select-input id="status" name="status" class="mt-1" :selected="request('status')" placeholder="All"
                        :options="collect($statuses)->mapWithKeys(fn ($status) => [$status->value => $status->label()])" />
                </div>
                <div>
                    <x-input-label for="date_from" :value="__('From')" />
                    <x-text-input id="date_from" name="date_from" type="date" class="mt-1" :value="request('date_from')" />
                </div>
                <div>
                    <x-input-label for="date_to" :value="__('To')" />
                    <x-text-input id="date_to" name="date_to" type="date" class="mt-1" :value="request('date_to')" />
                </div>
                <x-primary-button>{{ __('Filter') }}</x-primary-button>
                <a href="{{ route('orders.index') }}" class="text-sm text-gray-600 underline">{{ __('Reset') }}</a>
            </form>

            <x-input-error :messages="$errors->all()" />

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3">{{ __('Order') }}</th>
                            <th class="px-4 py-3">{{ __('Customer') }}</th>
                            <th class="px-4 py-3">{{ __('Placed') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Items') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Total') }}</th>
                            <th class="px-4 py-3">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($orders as $order)
                            <tr>
                                <td class="px-4 py-3 font-mono">
                                    <a href="{{ route('orders.show', $order) }}" class="text-indigo-600 hover:underline">{{ $order->order_number }}</a>
                                </td>
                                <td class="px-4 py-3">
                                    {{ $order->user->name }}
                                    <div class="text-xs text-gray-500">{{ $order->user->email }}</div>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $order->created_at->format('Y-m-d H:i') }}</td>
                                <td class="px-4 py-3 text-right">{{ $order->items_count }}</td>
                                <td class="px-4 py-3 text-right">{{ $order->total }}</td>
                                <td class="px-4 py-3"><x-order-status :status="$order->status" /></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-gray-500">{{ __('No orders found.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $orders->links() }}
        </div>
    </div>
</x-app-layout>

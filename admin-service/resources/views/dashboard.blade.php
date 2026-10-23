@php
    use App\Enums\OrderStatus;

    $statuses = $metrics['ordersByStatus'];
    $maxDay = max(array_map(fn ($day) => (float) $day['revenue'], $metrics['dailyRevenue'])) ?: 1;
    $periods = ['today' => __('Today'), 'week' => __('This week'), 'month' => __('This month')];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Dashboard') }}</h2>
            <span class="text-xs text-gray-500">{{ __('Figures as of') }} {{ \Illuminate\Support\Carbon::parse($metrics['generatedAt'])->format('H:i:s') }} UTC</span>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($periods as $key => $label)
                    <div class="bg-white shadow-sm sm:rounded-lg p-5">
                        <p class="text-xs uppercase tracking-wider text-gray-500">{{ __('Revenue') }} · {{ $label }}</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $metrics['revenue'][$key]['revenue'] }}</p>
                        <p class="mt-1 text-xs text-gray-500">{{ $metrics['revenue'][$key]['orders'] }} {{ __('orders') }} · {{ __('avg') }} {{ $metrics['revenue'][$key]['average'] }}</p>
                    </div>
                @endforeach
                <div class="bg-white shadow-sm sm:rounded-lg p-5">
                    <p class="text-xs uppercase tracking-wider text-gray-500">{{ __('Customers') }}</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $metrics['customers']['total'] }}</p>
                    <p class="mt-1 text-xs text-gray-500">+{{ $metrics['customers']['newSince'] }} {{ __('this month') }}</p>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <a href="{{ route('orders.index', ['status' => 'paid']) }}" class="bg-white shadow-sm sm:rounded-lg p-5 hover:ring-2 hover:ring-indigo-200">
                    <p class="text-xs uppercase tracking-wider text-gray-500">{{ __('To process') }}</p>
                    <p class="mt-2 text-2xl font-semibold text-blue-700">{{ $statuses['paid'] }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ __('paid, not yet processing') }}</p>
                </a>
                <a href="{{ route('orders.index', ['status' => 'processing']) }}" class="bg-white shadow-sm sm:rounded-lg p-5 hover:ring-2 hover:ring-indigo-200">
                    <p class="text-xs uppercase tracking-wider text-gray-500">{{ __('To ship') }}</p>
                    <p class="mt-2 text-2xl font-semibold text-amber-700">{{ $statuses['processing'] }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ __('processing') }}</p>
                </a>
                <a href="{{ route('orders.index', ['status' => 'pending']) }}" class="bg-white shadow-sm sm:rounded-lg p-5 hover:ring-2 hover:ring-indigo-200">
                    <p class="text-xs uppercase tracking-wider text-gray-500">{{ __('Awaiting payment') }}</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-700">{{ $statuses['pending'] }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ __('pending in the shop') }}</p>
                </a>
            </div>

            <section class="bg-white shadow-sm sm:rounded-lg p-5">
                <h3 class="mb-4 font-medium text-gray-900">{{ __('Revenue, last 14 days') }}</h3>
                <div class="flex h-40 items-end gap-1">
                    @foreach ($metrics['dailyRevenue'] as $day)
                        <div class="flex flex-1 flex-col items-center gap-1" title="{{ $day['day'] }}: {{ $day['revenue'] }}">
                            <div class="w-full rounded-t bg-indigo-400" style="height: {{ max(2, round((float) $day['revenue'] / $maxDay * 100)) }}%"></div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-1 flex gap-1 text-[10px] text-gray-500">
                    @foreach ($metrics['dailyRevenue'] as $day)
                        <span class="flex-1 text-center">{{ \Illuminate\Support\Carbon::parse($day['day'])->format('d') }}</span>
                    @endforeach
                </div>
            </section>

            <div class="grid gap-6 lg:grid-cols-2">
                <section class="bg-white shadow-sm sm:rounded-lg p-5">
                    <h3 class="mb-3 font-medium text-gray-900">{{ __('Top sellers, last 30 days') }}</h3>
                    <table class="w-full text-sm">
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($metrics['topSellers'] as $product)
                                <tr>
                                    <td class="py-2">{{ $product['name'] }} <span class="font-mono text-xs text-gray-500">{{ $product['sku'] }}</span></td>
                                    <td class="py-2 text-right">{{ $product['units'] }} {{ __('sold') }}</td>
                                    <td class="py-2 text-right">{{ $product['revenue'] }}</td>
                                </tr>
                            @empty
                                <tr><td class="py-2 text-gray-500">{{ __('No sales yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </section>

                <section class="bg-white shadow-sm sm:rounded-lg p-5">
                    <h3 class="mb-3 font-medium text-gray-900">{{ __('Low stock') }}</h3>
                    <table class="w-full text-sm">
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($metrics['lowStock'] as $product)
                                <tr>
                                    <td class="py-2">
                                        <a href="{{ route('products.edit', $product['id']) }}" class="text-indigo-600 hover:underline">{{ $product['name'] }}</a>
                                        <span class="font-mono text-xs text-gray-500">{{ $product['sku'] }}</span>
                                    </td>
                                    <td class="py-2 text-right {{ $product['quantity'] === 0 ? 'text-red-700 font-medium' : 'text-amber-700' }}">
                                        {{ $product['quantity'] }} {{ __('left') }} <span class="text-xs text-gray-500">({{ __('alert at') }} {{ $product['threshold'] }})</span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td class="py-2 text-gray-500">{{ __('All products are well stocked.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </section>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <section class="bg-white shadow-sm sm:rounded-lg p-5 lg:col-span-2">
                    <h3 class="mb-3 font-medium text-gray-900">{{ __('Recent orders') }}</h3>
                    <table class="w-full text-sm">
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($metrics['recentOrders'] as $order)
                                <tr>
                                    <td class="py-2 font-mono"><a href="{{ route('orders.show', $order['id']) }}" class="text-indigo-600 hover:underline">{{ $order['orderNumber'] }}</a></td>
                                    <td class="py-2">{{ $order['customer'] }}</td>
                                    <td class="py-2 text-right">{{ $order['total'] }}</td>
                                    <td class="py-2 text-right"><x-order-status :status="OrderStatus::from($order['status'])" /></td>
                                </tr>
                            @empty
                                <tr><td class="py-2 text-gray-500">{{ __('No orders yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </section>

                <section class="bg-white shadow-sm sm:rounded-lg p-5">
                    <h3 class="mb-3 font-medium text-gray-900">{{ __('Orders by status') }}</h3>
                    <ul class="space-y-2 text-sm">
                        @foreach (OrderStatus::cases() as $status)
                            <li class="flex items-center justify-between">
                                <a href="{{ route('orders.index', ['status' => $status->value]) }}"><x-order-status :status="$status" /></a>
                                <span>{{ $statuses[$status->value] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>

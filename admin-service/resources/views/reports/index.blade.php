@php
    $query = ['period' => $report->period->value, 'date_from' => $report->from->toDateString(), 'date_to' => $report->lastDay()->toDateString()];
    $maxRevenue = $report->maxRevenue() ?: 1;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Sales report') }}</h2>
            <div class="flex items-center gap-4 text-sm">
                <a href="{{ route('reports.sales.csv', $query) }}" class="text-indigo-600 hover:underline">{{ __('Download CSV') }}</a>
                <a href="{{ route('reports.sales.pdf', $query) }}" class="text-indigo-600 hover:underline">{{ __('Download PDF') }}</a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <form method="GET" action="{{ route('reports.sales') }}" class="bg-white shadow-sm sm:rounded-lg p-4 flex flex-wrap items-end gap-3">
                <div>
                    <x-input-label for="period" :value="__('Group by')" />
                    <x-select-input id="period" name="period" class="mt-1" :selected="$report->period->value"
                        :options="collect($periods)->mapWithKeys(fn ($period) => [$period->value => $period->label()])" />
                </div>
                <div>
                    <x-input-label for="date_from" :value="__('From')" />
                    <x-text-input id="date_from" name="date_from" type="date" class="mt-1" :value="request('date_from', $report->from->toDateString())" />
                </div>
                <div>
                    <x-input-label for="date_to" :value="__('To')" />
                    <x-text-input id="date_to" name="date_to" type="date" class="mt-1" :value="request('date_to', $report->lastDay()->toDateString())" />
                </div>
                <x-primary-button>{{ __('Show') }}</x-primary-button>
            </form>

            <x-input-error :messages="$errors->all()" />

            <p class="text-sm text-gray-600">
                {{ $report->period->label() }} {{ __('sales from') }} <strong>{{ $report->from->format('j M Y') }}</strong> {{ __('to') }} <strong>{{ $report->lastDay()->format('j M Y') }}</strong>
                ({{ __('whole periods, UTC; paid orders that were not cancelled') }}).
            </p>

            <div class="grid gap-4 sm:grid-cols-4">
                @foreach (['revenue' => __('Revenue'), 'orders' => __('Orders'), 'units' => __('Units sold'), 'average' => __('Average order')] as $key => $label)
                    <div class="bg-white shadow-sm sm:rounded-lg p-5">
                        <p class="text-xs uppercase tracking-wider text-gray-500">{{ $label }}</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $report->totals[$key] }}</p>
                    </div>
                @endforeach
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3">{{ __('Period') }}</th>
                            <th class="px-4 py-3 w-1/3"></th>
                            <th class="px-4 py-3 text-right">{{ __('Orders') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Units') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Revenue') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Average') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($report->rows as $row)
                            <tr>
                                <td class="px-4 py-2 whitespace-nowrap">{{ $report->rowLabel($row) }}</td>
                                <td class="px-4 py-2">
                                    <div class="h-2 rounded bg-indigo-400" style="width: {{ round((float) $row['revenue'] / $maxRevenue * 100) }}%"></div>
                                </td>
                                <td class="px-4 py-2 text-right">{{ $row['orders'] }}</td>
                                <td class="px-4 py-2 text-right">{{ $row['units'] }}</td>
                                <td class="px-4 py-2 text-right">{{ $row['revenue'] }}</td>
                                <td class="px-4 py-2 text-right">{{ $row['average'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 font-medium">
                        <tr>
                            <td class="px-4 py-3">{{ __('Total') }}</td>
                            <td></td>
                            <td class="px-4 py-3 text-right">{{ $report->totals['orders'] }}</td>
                            <td class="px-4 py-3 text-right">{{ $report->totals['units'] }}</td>
                            <td class="px-4 py-3 text-right">{{ $report->totals['revenue'] }}</td>
                            <td class="px-4 py-3 text-right">{{ $report->totals['average'] }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>

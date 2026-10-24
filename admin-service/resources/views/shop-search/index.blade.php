<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Shop search') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <p class="text-sm text-gray-600">
                {{ __('What customers get when they search the shop: typos are forgiven and the best matches come first. The results come live from the shop API (GraphQL), so a product you just changed shows up once the search index has caught up, usually within seconds.') }}
            </p>

            <form method="GET" action="{{ route('shop-search') }}" class="bg-white shadow-sm sm:rounded-lg p-4 flex flex-wrap items-end gap-3">
                <div class="grow">
                    <x-input-label for="q" :value="__('Search')" />
                    <x-text-input id="q" name="q" type="search" class="mt-1 w-full" :value="$filters['q']" placeholder="headphnes" autofocus />
                </div>
                <div>
                    <x-input-label for="sort" :value="__('Sort by')" />
                    <x-select-input id="sort" name="sort" class="mt-1" :selected="$filters['sort']"
                        :options="collect($sorts)->mapWithKeys(fn ($sort) => [$sort => ucfirst($sort)])" />
                </div>
                <div>
                    <x-input-label for="order" :value="__('Order')" />
                    <x-select-input id="order" name="order" class="mt-1" :selected="$filters['order']" :options="['desc' => 'Descending', 'asc' => 'Ascending']" />
                </div>
                <div>
                    <x-input-label for="category_id" :value="__('Category')" />
                    <x-select-input id="category_id" name="category_id" class="mt-1" :options="$categories" :selected="$filters['category_id']" placeholder="All" />
                </div>
                <div>
                    <x-input-label for="min_price" :value="__('Min price')" />
                    <x-text-input id="min_price" name="min_price" type="number" step="0.01" min="0" class="mt-1 w-28" :value="$filters['min_price']" />
                </div>
                <div>
                    <x-input-label for="max_price" :value="__('Max price')" />
                    <x-text-input id="max_price" name="max_price" type="number" step="0.01" min="0" class="mt-1 w-28" :value="$filters['max_price']" />
                </div>
                <x-primary-button>{{ __('Search') }}</x-primary-button>
            </form>

            <x-input-error :messages="$errors->all()" />

            @if ($results === null)
                <p class="text-sm text-gray-500">{{ __('Type what a customer would type, mistakes included.') }}</p>
            @elseif (! $results['available'])
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">{{ __('The shop API is not reachable right now, so the shop search cannot be shown.') }}</div>
            @else
                @if ($results['searchEngine'] !== 'up')
                    <div class="rounded-md bg-amber-50 p-4 text-sm text-amber-800">
                        {{ __('Elasticsearch is :status, so the shop is using its plain search: exact text only, no typo tolerance or relevance.', ['status' => $results['searchEngine']]) }}
                    </div>
                @endif

                <p class="text-sm text-gray-600">{{ trans_choice(':count product matches|:count products match', $results['total']) }}@if ($results['total'] > count($results['products'])) {{ __('(first :shown shown)', ['shown' => count($results['products'])]) }}@endif.</p>

                <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-4 py-3 w-12">#</th>
                                <th class="px-4 py-3"></th>
                                <th class="px-4 py-3">{{ __('Product') }}</th>
                                <th class="px-4 py-3">{{ __('Category') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Price') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Stock') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($results['products'] as $product)
                                <tr>
                                    <td class="px-4 py-3 text-gray-400">{{ $loop->iteration }}</td>
                                    <td class="px-4 py-2">
                                        @if ($product['thumbnailUrl'])
                                            <img src="{{ $product['thumbnailUrl'] }}" alt="" class="h-10 w-10 rounded object-cover">
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <a href="{{ route('products.edit', $product['id']) }}" class="font-medium text-indigo-600 hover:underline">{{ $product['name'] }}</a>
                                        <div class="font-mono text-xs text-gray-500">{{ $product['sku'] }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">{{ $product['category'] ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right">{{ $product['price'] }}</td>
                                    <td class="px-4 py-3 text-right {{ $product['stock'] === 0 ? 'text-red-600' : '' }}">{{ $product['stock'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">{{ __('Customers would find nothing for this search.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>

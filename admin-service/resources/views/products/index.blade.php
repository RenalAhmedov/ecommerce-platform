<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Products') }}</h2>
            <a href="{{ route('products.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                {{ __('New product') }}
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <form method="GET" action="{{ route('products.index') }}" class="bg-white shadow-sm sm:rounded-lg p-4 flex flex-wrap items-end gap-3">
                <div>
                    <x-input-label for="search" :value="__('Search name or SKU')" />
                    <x-text-input id="search" name="search" type="search" class="mt-1" :value="request('search')" />
                </div>
                <div>
                    <x-input-label for="category_id" :value="__('Category')" />
                    <x-select-input id="category_id" name="category_id" class="mt-1" :options="$categories" :selected="request('category_id')" placeholder="All" />
                </div>
                <div>
                    <x-input-label for="status" :value="__('Status')" />
                    <x-select-input id="status" name="status" class="mt-1" :selected="request('status')" placeholder="All"
                        :options="collect($statuses)->mapWithKeys(fn ($status) => [$status->value => ucfirst($status->value)])" />
                </div>
                <x-primary-button>{{ __('Filter') }}</x-primary-button>
                <a href="{{ route('products.index') }}" class="text-sm text-gray-600 underline">{{ __('Reset') }}</a>
            </form>

            <x-input-error :messages="$errors->all()" />

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3"></th>
                            <th class="px-4 py-3">{{ __('SKU') }}</th>
                            <th class="px-4 py-3">{{ __('Name') }}</th>
                            <th class="px-4 py-3">{{ __('Category') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Price') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Stock') }}</th>
                            <th class="px-4 py-3">{{ __('Status') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($products as $product)
                            <tr>
                                <td class="px-4 py-2">
                                    @if ($product->thumbnail_url)
                                        <img src="{{ $product->thumbnail_url }}" alt="" class="h-10 w-10 rounded object-cover">
                                    @else
                                        <div class="h-10 w-10 rounded bg-gray-100"></div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 font-mono">{{ $product->sku }}</td>
                                <td class="px-4 py-3">{{ $product->name }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $product->category?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-right">{{ $product->price }}</td>
                                <td class="px-4 py-3 text-right">
                                    {{ $product->inventory?->quantity ?? 0 }}
                                    @if ($product->inventory?->isLowStock())
                                        <span class="ms-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800">{{ __('low') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if ($product->trashed())
                                        <span class="rounded bg-red-100 px-2 py-0.5 text-xs text-red-800">{{ __('Trashed') }}</span>
                                    @elseif ($product->is_active)
                                        <span class="rounded bg-green-100 px-2 py-0.5 text-xs text-green-800">{{ __('Active') }}</span>
                                    @else
                                        <span class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-700">{{ __('Inactive') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    @if ($product->trashed())
                                        <form method="POST" action="{{ route('products.restore', $product) }}" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button class="text-indigo-600 hover:underline">{{ __('Restore') }}</button>
                                        </form>
                                    @else
                                        <a href="{{ route('products.edit', $product) }}" class="text-indigo-600 hover:underline">{{ __('Edit') }}</a>
                                        <form method="POST" action="{{ route('products.destroy', $product) }}" class="inline ms-3"
                                            onsubmit="return confirm('Move {{ $product->sku }} to trash?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="text-red-600 hover:underline">{{ __('Delete') }}</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-6 text-center text-gray-500">{{ __('No products found.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $products->links() }}
        </div>
    </div>
</x-app-layout>

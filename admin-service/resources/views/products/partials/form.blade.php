@php($product ??= null)

<div class="grid gap-6 sm:grid-cols-2">
    <div>
        <x-input-label for="name" :value="__('Name')" />
        <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $product?->name)" required maxlength="255" />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="slug" :value="__('Slug')" />
        <x-text-input id="slug" name="slug" class="mt-1 block w-full" :value="old('slug', $product?->slug)" maxlength="255" placeholder="generated from the name" />
        <x-input-error :messages="$errors->get('slug')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="sku" :value="__('SKU')" />
        <x-text-input id="sku" name="sku" class="mt-1 block w-full font-mono uppercase" :value="old('sku', $product?->sku)" required maxlength="64" />
        <x-input-error :messages="$errors->get('sku')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="price" :value="__('Price')" />
        <x-text-input id="price" name="price" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('price', $product?->price)" required />
        <x-input-error :messages="$errors->get('price')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="category_id" :value="__('Category')" />
        <x-select-input id="category_id" name="category_id" class="mt-1 block w-full" :options="$categories" :selected="old('category_id', $product?->category_id)" placeholder="No category" />
        <x-input-error :messages="$errors->get('category_id')" class="mt-2" />
    </div>

    @if ($product === null)
        <div>
            <x-input-label for="initial_stock" :value="__('Initial stock')" />
            <x-text-input id="initial_stock" name="initial_stock" type="number" min="0" step="1" class="mt-1 block w-full" :value="old('initial_stock', 0)" />
            <x-input-error :messages="$errors->get('initial_stock')" class="mt-2" />
        </div>
    @else
        <div>
            <x-input-label :value="__('Stock')" />
            <p class="mt-3 text-sm text-gray-700">{{ $product->inventory?->quantity ?? 0 }} {{ __('in stock') }}</p>
        </div>
    @endif

    <div class="sm:col-span-2">
        <x-input-label for="description" :value="__('Description')" />
        <textarea id="description" name="description" rows="4" maxlength="5000" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-xs">{{ old('description', $product?->description) }}</textarea>
        <x-input-error :messages="$errors->get('description')" class="mt-2" />
    </div>

    <div class="sm:col-span-2">
        <input type="hidden" name="is_active" value="0">
        <label for="is_active" class="inline-flex items-center">
            <input id="is_active" name="is_active" type="checkbox" value="1" class="rounded border-gray-300 text-indigo-600 shadow-xs focus:ring-indigo-500"
                @checked(old('is_active', $product?->is_active ?? true))>
            <span class="ms-2 text-sm text-gray-700">{{ __('Active (visible in the shop)') }}</span>
        </label>
        <x-input-error :messages="$errors->get('is_active')" class="mt-2" />
    </div>
</div>

@php($category ??= null)

<div class="grid gap-6 sm:grid-cols-2">
    <div>
        <x-input-label for="name" :value="__('Name')" />
        <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $category?->name)" required maxlength="255" />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="slug" :value="__('Slug')" />
        <x-text-input id="slug" name="slug" class="mt-1 block w-full" :value="old('slug', $category?->slug)" maxlength="255" placeholder="generated from the name" />
        <x-input-error :messages="$errors->get('slug')" class="mt-2" />
    </div>

    <div class="sm:col-span-2">
        <x-input-label for="parent_id" :value="__('Parent category')" />
        <x-select-input id="parent_id" name="parent_id" class="mt-1 block w-full" :options="$parents" :selected="old('parent_id', $category?->parent_id)" placeholder="None (top level)" />
        <x-input-error :messages="$errors->get('parent_id')" class="mt-2" />
    </div>

    <div class="sm:col-span-2">
        <x-input-label for="description" :value="__('Description')" />
        <textarea id="description" name="description" rows="3" maxlength="5000" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-xs">{{ old('description', $category?->description) }}</textarea>
        <x-input-error :messages="$errors->get('description')" class="mt-2" />
    </div>
</div>

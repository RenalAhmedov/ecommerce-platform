<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Categories') }}</h2>
            <a href="{{ route('categories.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                {{ __('New category') }}
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-input-error :messages="$errors->all()" />

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3">{{ __('Name') }}</th>
                            <th class="px-4 py-3">{{ __('Slug') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Products') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($tree as ['category' => $category, 'depth' => $depth])
                            <tr>
                                <td class="px-4 py-3" style="padding-left: {{ 1 + $depth * 1.5 }}rem">
                                    @if ($depth > 0)
                                        <span class="text-gray-400">└</span>
                                    @endif
                                    {{ $category->name }}
                                </td>
                                <td class="px-4 py-3 font-mono text-gray-600">{{ $category->slug }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('products.index', ['category_id' => $category->id]) }}" class="text-indigo-600 hover:underline">{{ $category->products_count }}</a>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('categories.edit', $category) }}" class="text-indigo-600 hover:underline">{{ __('Edit') }}</a>
                                    @if ($category->children_count === 0)
                                        <form method="POST" action="{{ route('categories.destroy', $category) }}" class="inline ms-3"
                                            onsubmit="return confirm('Move this category to trash? Its {{ $category->products_count }} product(s) will show as uncategorised until it is restored.')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="text-red-600 hover:underline">{{ __('Delete') }}</button>
                                        </form>
                                    @else
                                        <span class="ms-3 text-gray-400" title="{{ __('Move or delete its subcategories first') }}">{{ __('Delete') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-gray-500">{{ __('No categories yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($trashed->isNotEmpty())
                <section class="bg-white shadow-sm sm:rounded-lg p-4">
                    <h3 class="mb-3 text-sm font-medium uppercase tracking-wider text-gray-500">{{ __('Trash') }}</h3>
                    <ul class="divide-y divide-gray-100 text-sm">
                        @foreach ($trashed as $category)
                            <li class="flex items-center justify-between py-2">
                                <span>
                                    {{ $category->name }}
                                    @if ($category->parent)
                                        <span class="text-gray-500">{{ __('in') }} {{ $category->parent->name }}{{ $category->parent->trashed() ? ' ('.__('trashed').')' : '' }}</span>
                                    @endif
                                </span>
                                <form method="POST" action="{{ route('categories.restore', $category) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="text-indigo-600 hover:underline">{{ __('Restore') }}</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>

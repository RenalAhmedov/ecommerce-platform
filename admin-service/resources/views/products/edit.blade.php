<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Edit') }} {{ $product->sku }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <form method="POST" action="{{ route('products.update', $product) }}" class="bg-white shadow-sm sm:rounded-lg p-6 space-y-6">
                @csrf
                @method('PUT')
                @include('products.partials.form')

                <div class="flex items-center gap-4">
                    <x-primary-button>{{ __('Save') }}</x-primary-button>
                    <a href="{{ route('products.index') }}" class="text-sm text-gray-600 underline">{{ __('Back to list') }}</a>
                </div>
            </form>

            <section class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                <h3 class="text-lg font-medium text-gray-900">{{ __('Image') }}</h3>

                <div class="flex items-start gap-6">
                    @if ($product->image_url)
                        <a href="{{ $product->image_url }}" target="_blank" rel="noopener">
                            <img src="{{ $product->thumbnail_url }}" alt="{{ $product->name }}" class="h-32 w-32 rounded-md object-cover">
                        </a>
                    @else
                        <div class="flex h-32 w-32 items-center justify-center rounded-md bg-gray-100 text-xs text-gray-500">{{ __('No image') }}</div>
                    @endif

                    <div class="space-y-3">
                        @if ($imageProcessing)
                            <p class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800">{{ __('A new image is being processed. Refresh the page in a moment.') }}</p>
                        @endif

                        <form method="POST" action="{{ route('products.image.store', $product) }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
                            @csrf
                            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" required class="text-sm">
                            <x-primary-button>{{ $product->image_path ? __('Replace') : __('Upload') }}</x-primary-button>
                        </form>
                        <p class="text-xs text-gray-500">{{ __('JPEG, PNG or WebP, up to 5 MB. Saved as a 1200 px WebP plus a 300 px thumbnail.') }}</p>
                        <x-input-error :messages="$errors->get('image')" />

                        @if ($product->image_path)
                            <form method="POST" action="{{ route('products.image.destroy', $product) }}" onsubmit="return confirm('Remove this image?')">
                                @csrf
                                @method('DELETE')
                                <button class="text-sm text-red-600 hover:underline">{{ __('Remove image') }}</button>
                            </form>
                        @endif
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>

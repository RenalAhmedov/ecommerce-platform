<x-app-layout>
    @if ($imports->contains(fn ($import) => ! $import->status->isFinished()))
        <x-slot name="head">
            <meta http-equiv="refresh" content="3">
        </x-slot>
    @endif

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Import products') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <section class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                <form method="POST" action="{{ route('products.import.store') }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
                    @csrf
                    <input type="file" name="file" accept=".csv,text/csv" required class="text-sm">
                    <x-primary-button>{{ __('Import CSV') }}</x-primary-button>
                    <a href="{{ route('products.export') }}" class="text-sm text-indigo-600 hover:underline">{{ __('Download all products as a starting file') }}</a>
                </form>
                <x-input-error :messages="$errors->get('file')" />

                <div class="text-sm text-gray-600 space-y-1">
                    <p>{{ __('Columns') }}: <code class="font-mono">{{ implode(', ', $columns) }}</code> — {{ __('required') }}: <code class="font-mono">{{ implode(', ', $requiredColumns) }}</code>. {{ __('Up to :rows rows per file.', ['rows' => $maxRows]) }}</p>
                    <p>{{ __('Rows are matched by SKU: a new SKU creates a product (with "stock" as opening stock), an existing SKU updates it. Stock of existing products is not changed. Leave slug empty to generate it; category_slug must be an existing category.') }}</p>
                </div>
            </section>

            <section class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3">{{ __('File') }}</th>
                            <th class="px-4 py-3">{{ __('By') }}</th>
                            <th class="px-4 py-3">{{ __('Status') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Rows') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Created') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Updated') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Failed') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($imports as $import)
                            <tr>
                                <td class="px-4 py-3">
                                    {{ $import->original_name }}
                                    <div class="text-xs text-gray-500">{{ $import->created_at->diffForHumans() }}</div>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $import->user?->name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    @php($colors = ['queued' => 'bg-gray-100 text-gray-700', 'processing' => 'bg-amber-100 text-amber-800', 'completed' => 'bg-green-100 text-green-800', 'failed' => 'bg-red-100 text-red-800'])
                                    <span class="rounded px-2 py-0.5 text-xs {{ $colors[$import->status->value] }}">{{ ucfirst($import->status->value) }}</span>
                                </td>
                                <td class="px-4 py-3 text-right">{{ $import->processed_rows }} / {{ $import->total_rows }}</td>
                                <td class="px-4 py-3 text-right">{{ $import->created_count }}</td>
                                <td class="px-4 py-3 text-right">{{ $import->updated_count }}</td>
                                <td class="px-4 py-3 text-right">{{ $import->failed_count }}</td>
                            </tr>
                            @if ($import->errors !== [])
                                <tr>
                                    <td colspan="7" class="px-4 pb-3">
                                        <details class="text-xs">
                                            <summary class="cursor-pointer text-red-700">{{ __('Show problems') }} ({{ count($import->errors) }}{{ $import->failed_count > count($import->errors) ? '+' : '' }})</summary>
                                            <ul class="mt-2 space-y-1 text-gray-700">
                                                @foreach ($import->errors as $error)
                                                    <li>
                                                        @if ($error['row'])
                                                            <span class="font-mono">{{ __('Row') }} {{ $error['row'] }}{{ $error['sku'] ? ' ('.$error['sku'].')' : '' }}:</span>
                                                        @endif
                                                        {{ implode(' ', $error['messages']) }}
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </details>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-6 text-center text-gray-500">{{ __('No imports yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        </div>
    </div>
</x-app-layout>

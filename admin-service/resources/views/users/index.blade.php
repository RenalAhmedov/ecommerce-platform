<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Users') }}</h2>
            <a href="{{ route('users.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                {{ __('New user') }}
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <form method="GET" action="{{ route('users.index') }}" class="bg-white shadow-sm sm:rounded-lg p-4 flex flex-wrap items-end gap-3">
                <div>
                    <x-input-label for="search" :value="__('Name or email')" />
                    <x-text-input id="search" name="search" type="search" class="mt-1" :value="request('search')" />
                </div>
                <div>
                    <x-input-label for="role" :value="__('Role')" />
                    <x-select-input id="role" name="role" class="mt-1" :selected="request('role')" placeholder="All"
                        :options="collect($roles)->mapWithKeys(fn ($role) => [$role->value => ucfirst($role->value)])" />
                </div>
                <label class="inline-flex items-center pb-2 text-sm text-gray-700">
                    <input type="checkbox" name="trashed" value="1" class="rounded border-gray-300 text-indigo-600" @checked(request()->boolean('trashed'))>
                    <span class="ms-2">{{ __('Deleted users') }}</span>
                </label>
                <x-primary-button>{{ __('Filter') }}</x-primary-button>
                <a href="{{ route('users.index') }}" class="text-sm text-gray-600 underline">{{ __('Reset') }}</a>
            </form>

            <x-input-error :messages="$errors->all()" />

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3">{{ __('Name') }}</th>
                            <th class="px-4 py-3">{{ __('Email') }}</th>
                            <th class="px-4 py-3">{{ __('Role') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Orders') }}</th>
                            <th class="px-4 py-3">{{ __('Joined') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($users as $user)
                            <tr>
                                <td class="px-4 py-3">
                                    {{ $user->name }}
                                    @if ($user->is(auth()->user()))
                                        <span class="ms-1 text-xs text-gray-500">({{ __('you') }})</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $user->email }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded px-2 py-0.5 text-xs {{ $user->isAdmin() ? 'bg-indigo-100 text-indigo-800' : 'bg-gray-100 text-gray-700' }}">{{ ucfirst($user->role->value) }}</span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('orders.index', ['search' => $user->email]) }}" class="text-indigo-600 hover:underline">{{ $user->orders_count }}</a>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $user->created_at->format('Y-m-d') }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    @if ($user->trashed())
                                        <form method="POST" action="{{ route('users.restore', $user) }}" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button class="text-indigo-600 hover:underline">{{ __('Restore') }}</button>
                                        </form>
                                    @else
                                        <a href="{{ route('users.edit', $user) }}" class="text-indigo-600 hover:underline">{{ __('Edit') }}</a>
                                        @unless ($user->is(auth()->user()))
                                            <form method="POST" action="{{ route('users.destroy', $user) }}" class="inline ms-3"
                                                onsubmit="return confirm('Delete this user? They can no longer log in; their orders are kept.')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="text-red-600 hover:underline">{{ __('Delete') }}</button>
                                            </form>
                                        @endunless
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-gray-500">{{ __('No users found.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $users->links() }}
        </div>
    </div>
</x-app-layout>

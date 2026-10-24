<x-guest-layout>
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded-sm border-gray-300 text-indigo-600 shadow-xs focus:ring-indigo-500" name="remember">
                <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <x-primary-button class="ms-3">
                {{ __('Log in') }}
            </x-primary-button>
        </div>
    </form>

    <details class="mt-6 border-t border-gray-200 pt-4" @if ($errors->has('token')) open @endif>
        <summary class="cursor-pointer text-sm text-gray-600 hover:text-gray-900">{{ __('Signed in to the shop API? Use your access token') }}</summary>

        <form method="POST" action="{{ route('login.token') }}" class="mt-4">
            @csrf

            <x-input-label for="token" :value="__('Access token')" />
            <textarea id="token" name="token" rows="3" required autocomplete="off" spellcheck="false"
                      class="block mt-1 w-full rounded-md border-gray-300 font-mono text-xs shadow-xs focus:border-indigo-500 focus:ring-indigo-500"></textarea>
            <x-input-error :messages="$errors->get('token')" class="mt-2" />

            <div class="flex justify-end mt-4">
                <x-primary-button>{{ __('Log in with token') }}</x-primary-button>
            </div>
        </form>
    </details>
</x-guest-layout>

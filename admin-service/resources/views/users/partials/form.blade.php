@php($user ??= null)

<div class="grid gap-6 sm:grid-cols-2">
    <div>
        <x-input-label for="name" :value="__('Name')" />
        <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $user?->name)" required maxlength="255" />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="email" :value="__('Email')" />
        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user?->email)" required maxlength="255" autocomplete="off" />
        <x-input-error :messages="$errors->get('email')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="password" :value="$user ? __('New password (leave empty to keep)') : __('Password')" />
        <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" :required="$user === null" minlength="8" maxlength="72" autocomplete="new-password" />
        <x-input-error :messages="$errors->get('password')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="role" :value="__('Role')" />
        <x-select-input id="role" name="role" class="mt-1 block w-full" :selected="old('role', $user?->role->value ?? 'customer')"
            :options="collect($roles)->mapWithKeys(fn ($role) => [$role->value => ucfirst($role->value)])" />
        <p class="mt-1 text-xs text-gray-500">{{ __('Admins can sign in to this panel; customers only to the shop.') }}</p>
        <x-input-error :messages="$errors->get('role')" class="mt-2" />
    </div>
</div>

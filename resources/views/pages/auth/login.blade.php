<x-layouts::auth :title="__('Log in - Teamate')">
    <div class="flex flex-col gap-6">
        <!-- Auth Card Heading -->
        <div class="text-center">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                {{ __('Welcome back') }}
            </h1>
            <p class="mt-1.5 text-sm text-slate-500 dark:text-neutral-400">
                {{ __('Enter your credentials to access your workspace') }}
            </p>
        </div>

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <!-- WebAuthn Passkeys -->
        <x-passkey-verify />

        <!-- Login Form -->
        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-5">
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Work Email')"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="name@company.com"
            />

            <!-- Password -->
            <div class="relative">
                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Enter your password')"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link
                        class="absolute top-0 end-0 text-xs font-semibold text-emerald-600 hover:text-emerald-700 dark:text-emerald-400"
                        :href="route('password.request')"
                        wire:navigate
                    >
                        {{ __('Forgot password?') }}
                    </flux:link>
                @endif
            </div>

            <!-- Remember Me -->
            <flux:checkbox
                name="remember"
                :label="__('Keep me signed in on this device')"
                :checked="old('remember')"
            />

            <!-- Submit Button -->
            <div class="pt-1">
                <flux:button
                    variant="primary"
                    type="submit"
                    class="w-full !bg-emerald-600 hover:!bg-emerald-700 !text-white shadow-md font-semibold rounded-xl transition duration-150"
                    data-test="login-button"
                >
                    {{ __('Sign in') }}
                </flux:button>
            </div>
        </form>

        <!-- Divider & Switch to Register -->
        <div class="pt-2 text-center text-sm text-slate-500 dark:text-neutral-400">
            <span>{{ __('Don\'t have an account yet?') }}</span>
            <flux:link
                :href="route('register')"
                class="font-semibold text-emerald-600 hover:text-emerald-700 dark:text-emerald-400 ml-1"
                wire:navigate
            >
                {{ __('Create an account') }}
            </flux:link>
        </div>
    </div>
</x-layouts::auth>

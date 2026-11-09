<x-layouts::auth :title="__('Register - Teamate')">
    <div class="flex flex-col gap-6">
        <!-- Auth Card Heading -->
        <div class="text-center">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                {{ __('Create your account') }}
            </h1>
            <p class="mt-1.5 text-sm text-slate-500 dark:text-neutral-400">
                {{ __('Start managing projects with zero-trust RBAC & decentralized backups') }}
            </p>
        </div>

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <!-- Register Form -->
        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-5">
            @csrf

            <!-- Name -->
            <flux:input
                name="name"
                :label="__('Full name')"
                :value="old('name')"
                type="text"
                required
                autofocus
                autocomplete="name"
                placeholder="e.g. Alex Morgan"
            />

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Work email')"
                :value="old('email')"
                type="email"
                required
                autocomplete="email"
                placeholder="name@company.com"
            />

            <!-- Password -->
            <flux:input
                name="password"
                :label="__('Password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Create a secure password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <!-- Confirm Password -->
            <flux:input
                name="password_confirmation"
                :label="__('Confirm password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Repeat your password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <!-- Submit Button -->
            <div class="pt-1">
                <flux:button
                    type="submit"
                    variant="primary"
                    class="w-full !bg-emerald-600 hover:!bg-emerald-700 !text-white shadow-md font-semibold rounded-xl transition duration-150"
                    data-test="register-user-button"
                >
                    {{ __('Get started with Teamate') }}
                </flux:button>
            </div>
        </form>

        <!-- Terms Note & Switch to Login -->
        <div class="space-y-3 pt-2 text-center text-sm text-slate-500 dark:text-neutral-400">
            <div>
                <span>{{ __('Already have an account?') }}</span>
                <flux:link
                    :href="route('login')"
                    class="font-semibold text-emerald-600 hover:text-emerald-700 dark:text-emerald-400 ml-1"
                    wire:navigate
                >
                    {{ __('Sign in') }}
                </flux:link>
            </div>
            <p class="text-[11px] text-slate-400 dark:text-neutral-500 leading-normal">
                {{ __('By signing up, you agree to our security protocols, access control terms, and decentralized backup policies.') }}
            </p>
        </div>
    </div>
</x-layouts::auth>

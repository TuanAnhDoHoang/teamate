<x-layouts::auth :title="__('Forgot password')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Forgot password')" :description="__('Enter your email to receive a password reset link')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Email address')"
                type="email"
                required
                autofocus
                placeholder="email@example.com"
            />

            <flux:button variant="primary" type="submit" class="w-full !bg-emerald-600 hover:!bg-emerald-700 !text-white shadow-md font-semibold rounded-xl" data-test="email-password-reset-link-button">
                {{ __('Email password reset link') }}
            </flux:button>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-slate-500 dark:text-neutral-400">
            <span>{{ __('Or, return to') }}</span>
            <flux:link :href="route('login')" class="font-semibold text-emerald-600 hover:text-emerald-700 dark:text-emerald-400" wire:navigate>{{ __('sign in') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>

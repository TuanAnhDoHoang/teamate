@props([
    'role' => 'admin',
])

@php
    $normalized = strtolower(trim((string) $role));

    [$label, $classes] = match (true) {
        in_array($normalized, ['admin', 'owner']) => [
            'Admin',
            'bg-rose-50 text-rose-700 border-rose-200 ring-rose-600/10 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800',
        ],
        in_array($normalized, ['dev', 'developer', 'development']) => [
            'Developer',
            'bg-sky-50 text-sky-700 border-sky-200 ring-sky-600/10 dark:bg-sky-950/40 dark:text-sky-300 dark:border-sky-800',
        ],
        in_array($normalized, ['review', 'reviewer', 'tester', 'pentester']) => [
            'Reviewer',
            'bg-purple-50 text-purple-700 border-purple-200 ring-purple-600/10 dark:bg-purple-950/40 dark:text-purple-300 dark:border-purple-800',
        ],
        in_array($normalized, ['client']) => [
            'Client',
            'bg-emerald-50 text-emerald-700 border-emerald-200 ring-emerald-600/10 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800',
        ],
        default => [
            ucfirst($normalized ?: 'Member'),
            'bg-slate-100 text-slate-700 border-slate-200 ring-slate-600/10 dark:bg-zinc-800 dark:text-zinc-300 dark:border-zinc-700',
        ],
    };
@endphp

<span {{ $attributes->class([
    'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold border ring-1 ring-inset',
    $classes,
]) }}>
    <span class="size-1.5 rounded-full bg-current opacity-75"></span>
    {{ $label }}
</span>


@props([
    'variant' => 'default',
    'color' => null,
])

@php
    $colorClasses = match ($color) {
        'red', 'rose' => 'bg-rose-50 text-rose-700 border-rose-200 ring-rose-600/10',
        'blue', 'sky' => 'bg-sky-50 text-sky-700 border-sky-200 ring-sky-600/10',
        'purple' => 'bg-purple-50 text-purple-700 border-purple-200 ring-purple-600/10',
        'green', 'emerald' => 'bg-emerald-50 text-emerald-700 border-emerald-200 ring-emerald-600/10',
        'amber', 'yellow' => 'bg-amber-50 text-amber-700 border-amber-200 ring-amber-600/10',
        default => 'bg-slate-100 text-slate-700 border-slate-200 ring-slate-600/10',
    };
@endphp

<span {{ $attributes->class([
    'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium border ring-1 ring-inset',
    $colorClasses,
]) }}>
    {{ $slot }}
</span>


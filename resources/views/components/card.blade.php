@props([
    'highlight' => false,
])

<div {{ $attributes->class([
    'relative rounded-2xl bg-white p-8 transition-all duration-200',
    'border border-slate-200/80 shadow-xs hover:shadow-md' => ! $highlight,
    'border-2 border-emerald-500/80 bg-linear-to-b from-white to-emerald-50/20 shadow-md ring-4 ring-emerald-500/10' => $highlight,
]) }}>
    {{ $slot }}
</div>


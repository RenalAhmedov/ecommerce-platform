@props(['status'])

@php
    $colors = [
        'pending' => 'bg-gray-100 text-gray-700',
        'paid' => 'bg-blue-100 text-blue-800',
        'processing' => 'bg-amber-100 text-amber-800',
        'shipped' => 'bg-indigo-100 text-indigo-800',
        'delivered' => 'bg-green-100 text-green-800',
        'cancelled' => 'bg-red-100 text-red-800',
        'payment_failed' => 'bg-red-50 text-red-700',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'rounded px-2 py-0.5 text-xs '.$colors[$status->value]]) }}>{{ $status->label() }}</span>

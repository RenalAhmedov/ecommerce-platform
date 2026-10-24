@props(['status', 'order' => null])

{{-- With an order id the badge follows live status changes (resources/js/live-orders.js). --}}
<span {{ $attributes->merge(['class' => 'rounded px-2 py-0.5 text-xs '.$status->badgeClasses()]) }}
    @if ($order) data-live-order-status="{{ $order }}" @endif>{{ $status->label() }}</span>

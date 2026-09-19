@props([
    'href' => null,
    'type' => 'button',
    'variant' => 'primary',
    'size' => null,
    'disabled' => false,
])

@php
    $buttonClasses = ['btn', 'btn-'.$variant];
    if ($size && $size !== 'none') {
        $buttonClasses[] = 'btn-'.$size;
    }
@endphp

@if($href)
    <a href="{{ $href }}"
       @if($disabled) aria-disabled="true" tabindex="-1" @endif
       {{ $attributes->class($buttonClasses) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}"
            @disabled($disabled)
            {{ $attributes->class($buttonClasses) }}>{{ $slot }}</button>
@endif

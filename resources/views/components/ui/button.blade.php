
@props([
    'href' => null,
    'variant' => 'primary',
    'type' => 'button'
])

@if ($href)
    <a href="{{ $href }}"
       {{ $attributes->class(['btn', 'btn-primary' => $variant === 'primary', 'btn-outline' => $variant === 'outline']) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}"
            {{ $attributes->class(['btn', 'btn-primary' => $variant === 'primary', 'btn-outline' => $variant === 'outline']) }}>
        {{ $slot }}
    </button>
@endif

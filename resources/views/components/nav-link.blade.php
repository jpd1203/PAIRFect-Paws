@props(['href', 'active' => false])

<a href="{{ $href }}"
   {{ $attributes->merge(['class' => ($active ? 'text-white' : 'text-white/90 hover:text-white').' text-sm font-semibold tracking-wide transition']) }}>
    {{ $slot }}
</a>

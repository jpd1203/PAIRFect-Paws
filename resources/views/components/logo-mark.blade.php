@props(['class' => 'h-16 w-16'])

<div {{ $attributes->merge(['class' => $class.' rounded-full bg-gray-800 flex items-center justify-center overflow-hidden shrink-0']) }}>
    <svg viewBox="0 0 100 100" class="h-[70%] w-[70%]" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M50 20c-4 0-7 3-9 7-3-2-7-2-9 1-2 3-1 7 2 9-2 3-2 7 1 9 3 3 7 3 10 1 2 2 4 3 5 3s3-1 5-3c3 2 7 2 10-1 3-2 3-6 1-9 3-2 4-6 2-9-2-3-6-3-9-1-2-4-5-7-9-7z" fill="#fff"/>
        <circle cx="42" cy="42" r="2.2" fill="#2b2b2b"/>
        <circle cx="58" cy="42" r="2.2" fill="#2b2b2b"/>
        <path d="M46 48c1.5 1.5 6.5 1.5 8 0" stroke="#2b2b2b" stroke-width="1.5" stroke-linecap="round"/>
        <path d="M30 62c8-6 32-6 40 0 4 3 4 12-2 15-9 5-27 5-36 0-6-3-6-12-2-15z" fill="#a6242c"/>
    </svg>
</div>

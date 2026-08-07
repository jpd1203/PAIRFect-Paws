@props(['name', 'accountName', 'accountNumber', 'qr', 'accent'])

<div class="rounded-xl border-2 {{ $accent }} p-5 text-center">
    <p class="text-sm font-extrabold uppercase tracking-wide text-gray-800">{{ $name }}</p>

    <div class="mx-auto mt-3 flex aspect-square w-full max-w-[180px] items-center justify-center rounded-lg bg-white p-3 shadow-sm">
        @if (file_exists(public_path($qr)))
            <img src="{{ asset($qr) }}" alt="{{ $name }} QR code" class="h-full w-full object-contain">
        @else
            <svg viewBox="0 0 100 100" class="h-full w-full text-gray-300" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="4" y="4" width="92" height="92" rx="6" stroke="currentColor" stroke-width="3"/>
                <rect x="14" y="14" width="20" height="20" fill="currentColor"/>
                <rect x="66" y="14" width="20" height="20" fill="currentColor"/>
                <rect x="14" y="66" width="20" height="20" fill="currentColor"/>
                <rect x="44" y="44" width="12" height="12" fill="currentColor"/>
                <rect x="60" y="44" width="8" height="8" fill="currentColor"/>
                <rect x="44" y="60" width="8" height="8" fill="currentColor"/>
                <rect x="60" y="60" width="12" height="12" fill="currentColor"/>
            </svg>
        @endif
    </div>

    <p class="mt-3 text-xs font-bold text-gray-800">{{ $accountName }}</p>
    <p class="text-[11px] text-gray-500">{{ $accountNumber }}</p>
</div>

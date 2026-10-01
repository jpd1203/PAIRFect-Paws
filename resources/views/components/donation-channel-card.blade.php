@props(['name', 'accountName', 'qr', 'accent'])

<div class="flex flex-col justify-between rounded-2xl border-2 {{ $accent }} bg-white p-6 sm:p-7 text-center shadow-sm transition duration-200 hover:shadow-md">
    <div>
        <p class="text-base sm:text-lg font-extrabold uppercase tracking-wider text-gray-900">{{ $name }}</p>

        <div class="group/qr relative mx-auto mt-4 flex aspect-square w-full max-w-[230px] items-center justify-center rounded-xl bg-white p-2.5 shadow-sm border border-gray-100 cursor-pointer overflow-hidden transition-all duration-200 hover:border-gray-300 hover:shadow-md"
             onclick="openQrLightbox('{{ asset($qr) }}', '{{ addslashes($name) }}', '{{ addslashes($accountName) }}')"
             title="Click to zoom QR code">
            @if (file_exists(public_path($qr)))
                <img src="{{ asset($qr) }}" alt="{{ $name }} QR code" class="h-full w-full object-contain rounded-lg transition-transform duration-300 group-hover/qr:scale-105">
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

            <!-- Hover Zoom Overlay -->
            <div class="absolute inset-0 bg-black/45 opacity-0 group-hover/qr:opacity-100 transition-opacity duration-200 flex flex-col items-center justify-center gap-1.5 text-white backdrop-blur-[1px] rounded-xl pointer-events-none">
                <i class="fa-solid fa-magnifying-glass-plus text-2xl drop-shadow-sm"></i>
                <span class="rounded-full bg-white/20 px-3 py-1 text-xs font-bold backdrop-blur-sm shadow-sm">Click to Zoom</span>
            </div>
        </div>
    </div>

    <div class="mt-4">
        <p class="text-sm sm:text-base font-bold text-gray-900">{{ $accountName }}</p>
    </div>
</div>

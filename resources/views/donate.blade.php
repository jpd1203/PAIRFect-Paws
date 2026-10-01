<x-public-layout :title="'Donate - '.config('app.name')">
    <section class="bg-white py-16">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-3xl text-center">
                <h1 class="text-4xl font-extrabold tracking-tight text-gray-900 sm:text-5xl">Support Red Cubs Pet Patrol</h1>
                <p class="mt-4 leading-relaxed text-gray-700">
                    Your donation helps provide food, shelter, and veterinary care for rescued animals.
                    Scan the QR code for your preferred channel to donate directly.
                </p>
            </div>

            @php
                $channels = [
                    ['name' => 'GCash', 'qr' => 'images/qr/gcash.png'],
                    ['name' => 'Maya', 'qr' => 'images/qr/maya.png'],
                    ['name' => 'BPI', 'qr' => 'images/qr/bpi.png'],
                    ['name' => 'LANDBANK', 'qr' => 'images/qr/landbank.png'],
                ];
            @endphp

            <div class="mt-12 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($channels as $channel)
                    <div class="rounded-2xl border border-gray-200 bg-white p-5 text-center shadow-sm">
                        <h2 class="text-lg font-extrabold text-gray-900">{{ $channel['name'] }}</h2>
                        <img
                            src="{{ asset($channel['qr']) }}"
                            alt="{{ $channel['name'] }} donation QR code"
                            class="mx-auto mt-4 aspect-square w-full max-w-[260px] object-contain"
                        >
                    </div>
                @endforeach
            </div>

            <p class="mt-8 text-center text-sm text-gray-600">
                Please confirm the recipient shown in your payment app before sending a donation.
            </p>
        </div>
    </section>
</x-public-layout>

<x-public-layout :title="'Donate - '.config('app.name')">

    <section class="bg-white">
        <div class="mx-auto max-w-5xl px-4 py-14 sm:px-6 lg:px-8">
            <h1 class="text-5xl font-extrabold tracking-tight text-gray-900">DONATE</h1>

            <h2 class="mt-8 text-xl font-extrabold text-gray-900">Why Your Donation Matters</h2>
            <p class="mt-3 leading-relaxed text-gray-700">
                Red Cubs Pet Patrol exists because animals can't ask for help on their own.
            </p>
            <p class="mt-3 leading-relaxed text-gray-700">
                Every peso donated goes toward stepping in when pets and strays are left hungry,
                hurt, or forgotten. We use your support to provide medical care, emergency response,
                and hands-on help for animals who would otherwise be ignored.
            </p>
            <p class="mt-3 leading-relaxed text-gray-700">
                Beyond rescue, your donation helps us push for better treatment of animals through
                awareness, education, and community action. Small acts of kindness add up, and
                together, they create real change for animals who need it most.
            </p>
            <p class="mt-3 font-semibold leading-relaxed text-gray-800">
                Your support doesn't just fund our work. It gives animals a second chance.
            </p>

            <hr class="my-10 border-gray-200">

            <h2 class="text-center text-2xl font-extrabold text-gray-900">Official Donation Channels</h2>

            <div class="mt-10 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($channels as $channel)
                    <x-donation-channel-card
                        :name="$channel['name']"
                        :account-name="$channel['account_name']"
                        :account-number="$channel['account_number']"
                        :qr="$channel['qr']"
                        :accent="$channel['accent']"
                    />
                @endforeach
            </div>

            <p class="mt-8 text-center text-xs text-gray-500">
                For bank transfer receipts or donation acknowledgements, please contact us at
                +63 918 985 2149.
            </p>
        </div>
    </section>

</x-public-layout>

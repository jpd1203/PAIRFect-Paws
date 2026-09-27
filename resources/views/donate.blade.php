<x-public-layout :title="'Donate - '.config('app.name')">

    <section class="bg-white">
        <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
            <div class="max-w-4xl">
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
            </div>

            <hr class="my-12 border-gray-200">

            <div class="w-full">
                <h2 class="text-center text-3xl sm:text-4xl font-extrabold text-gray-900">Official Donation Channels</h2>

                <div class="mt-10 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 lg:gap-8">
                    <x-donation-channel-card
                        name="GCash" accountName="Red Cubs Pet Patrol" accountNumber="0918 985 2149" qr="images/qr/gcash.png" accent="border-blue-500"
                    />

                    <x-donation-channel-card
                        name="Maya" accountName="Red Cubs Pet Patrol" accountNumber="0918 985 2149" qr="images/qr/maya.png" accent="border-green-500"
                    />

                    <x-donation-channel-card
                        name="BPI" accountName="Red Cubs Pet Patrol" accountNumber="1234 5678 9012" qr="images/qr/bpi.png" accent="border-red-500"
                    />

                    <x-donation-channel-card
                        name="LANDBANK" accountName="Red Cubs Pet Patrol" accountNumber="1234 5678 9012" qr="images/qr/landbank.png" accent="border-red-500"
                    />
                </div>

                <p class="mt-10 text-center text-sm sm:text-base text-gray-600">
                    For bank transfer receipts or donation acknowledgements, please contact us at
                    <span class="font-semibold text-gray-800">+63 918 985 2149</span>.
                </p>
            </div>
        </div>
    </section>

</x-public-layout>

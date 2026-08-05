<x-public-layout :title="'Community Impact Fund - '.config('app.name')">

    <section class="bg-white">
        <div class="mx-auto max-w-5xl px-4 py-14 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-extrabold text-gray-900">Community Impact Fund</h1>
            <p class="mt-2 text-sm text-gray-600">
                A transparent record of every donation received and every peso spent caring for our
                rescued pets.
            </p>

            <div class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="rounded-xl border border-gray-200 bg-cream-100 p-5">
                    <p class="text-xs font-bold uppercase tracking-wide text-gray-500">Total Donations Added</p>
                    <p class="mt-1 text-2xl font-extrabold text-maroon-600">&#8369;{{ number_format($totalDonated, 2) }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-cream-100 p-5">
                    <p class="text-xs font-bold uppercase tracking-wide text-gray-500">Total Shelter Spending</p>
                    <p class="mt-1 text-2xl font-extrabold text-gray-800">&#8369;{{ number_format($totalSpent, 2) }}</p>
                </div>
            </div>

            <div class="mt-10 overflow-hidden rounded-xl border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-5 py-3 font-bold text-gray-700">Date</th>
                            <th scope="col" class="px-5 py-3 font-bold text-gray-700">Activity</th>
                            <th scope="col" class="px-5 py-3 font-bold text-gray-700">Donation Added</th>
                            <th scope="col" class="px-5 py-3 font-bold text-gray-700">Shelter Spent</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse ($donations as $donation)
                            <tr>
                                <td class="px-5 py-3 text-gray-600">{{ $donation->recorded_date?->format('m/d/Y') ?? '-' }}</td>
                                <td class="px-5 py-3 text-gray-800">{{ $donation->activity }}</td>
                                <td class="px-5 py-3 font-semibold text-maroon-600">
                                    {{ $donation->donation_added > 0 ? number_format($donation->donation_added, 0) : '-' }}
                                </td>
                                <td class="px-5 py-3 font-semibold text-gray-700">
                                    {{ $donation->shelter_spent > 0 ? '-'.number_format($donation->shelter_spent, 0) : '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-6 text-center text-gray-500">
                                    No donation activity has been recorded yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

</x-public-layout>

<x-public-layout :title="'Donate - '.config('app.name')">

    <section class="bg-white">
        <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
            <div class="max-w-4xl">
                <p class="text-sm font-bold uppercase tracking-wider text-maroon-600 mb-1">Support Red Cubs Pet Patrol</p>
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
                        name="GCash" accountName="Maria Lucia Fernandez" qr="images/qr/gcash.png" accent="border-blue-500"
                    />

                    <x-donation-channel-card
                        name="Maya" accountName="Maria Lucia Fernandez" qr="images/qr/maya.png" accent="border-green-500"
                    />

                    <x-donation-channel-card
                        name="BPI" accountName="Red Cubs" qr="images/qr/bpi.png" accent="border-red-500"
                    />

                    <x-donation-channel-card
                        name="LANDBANK" accountName="Maria Lucia Fernandez" qr="images/qr/landbank.png" accent="border-red-500"
                    />
                </div>

                <p class="mt-10 text-center text-sm sm:text-base text-gray-600">
                    For bank transfer receipts or donation acknowledgements, please contact us at
                    <span class="font-semibold text-gray-800">+63 918 985 2149</span>.
                </p>
            </div>
        </div>
    </section>

    <!-- QR Zoom Lightbox Modal -->
    <div id="qrLightbox"
         class="fixed inset-0 z-[10000] bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 opacity-0 pointer-events-none transition-all duration-300"
         role="dialog"
         aria-modal="true"
         aria-labelledby="qrModalTitle">
        
        <div id="qrLightboxCard"
             class="relative w-full max-w-sm sm:max-w-md bg-white rounded-3xl p-6 sm:p-8 shadow-2xl border border-gray-100 transform scale-95 transition-all duration-300 flex flex-col items-center text-center">
            
            <!-- Close Button -->
            <button type="button"
                    onclick="closeQrLightbox()"
                    aria-label="Close QR zoom"
                    class="absolute top-4 right-4 h-9 w-9 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-600 hover:text-gray-900 flex items-center justify-center transition-colors cursor-pointer">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>

            <!-- Channel Header -->
            <span id="qrModalChannel" class="text-xs font-black uppercase tracking-widest px-3.5 py-1 rounded-full bg-gray-100 text-gray-800 mb-2"></span>
            
            <h3 id="qrModalTitle" class="text-xl sm:text-2xl font-black text-gray-900 tracking-tight">Scan to Donate</h3>
            <p class="text-xs text-gray-500 mt-1">Open your banking or e-wallet app and scan this code</p>

            <!-- Large QR Image Container -->
            <div class="my-5 w-full max-w-[280px] sm:max-w-[310px] aspect-square rounded-2xl bg-white p-3 shadow-sm border-2 border-gray-200 flex items-center justify-center">
                <img id="qrModalImg" src="" alt="Enlarged donation QR code" class="w-full h-full object-contain rounded-xl">
            </div>

            <!-- Recipient details -->
            <div class="w-full bg-[#fbf9f5] border border-[#eee8df] rounded-2xl px-5 py-3.5 text-center">
                <p class="text-[10px] uppercase font-bold text-gray-500 tracking-wider">Account Name</p>
                <p id="qrModalAccountName" class="text-base font-black text-gray-900 mt-0.5 leading-tight"></p>
            </div>

            <p class="text-[11px] text-gray-400 mt-4">Click anywhere outside or press Esc to close</p>
        </div>
    </div>

    @push('scripts')
        <script>
            function openQrLightbox(qrUrl, name, accountName) {
                const lb = document.getElementById('qrLightbox');
                const card = document.getElementById('qrLightboxCard');
                const img = document.getElementById('qrModalImg');
                const channel = document.getElementById('qrModalChannel');
                const acctName = document.getElementById('qrModalAccountName');

                if (!lb || !img) return;

                img.src = qrUrl;
                if (channel) channel.textContent = name || 'Donation';
                if (acctName) acctName.textContent = accountName || 'Red Cubs Pet Patrol';

                lb.classList.remove('opacity-0', 'pointer-events-none');
                lb.classList.add('opacity-100', 'pointer-events-auto');
                card?.classList.remove('scale-95');
                card?.classList.add('scale-100');
                document.body.classList.add('overflow-hidden');
            }

            function closeQrLightbox() {
                const lb = document.getElementById('qrLightbox');
                const card = document.getElementById('qrLightboxCard');
                if (!lb) return;

                lb.classList.remove('opacity-100', 'pointer-events-auto');
                lb.classList.add('opacity-0', 'pointer-events-none');
                card?.classList.remove('scale-100');
                card?.classList.add('scale-95');
                document.body.classList.remove('overflow-hidden');
            }

            document.addEventListener('DOMContentLoaded', function () {
                const lb = document.getElementById('qrLightbox');
                if (lb) {
                    lb.addEventListener('click', function (e) {
                        if (e.target === lb) {
                            closeQrLightbox();
                        }
                    });
                }

                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') {
                        closeQrLightbox();
                    }
                });
            });

            window.openQrLightbox = openQrLightbox;
            window.closeQrLightbox = closeQrLightbox;
        </script>
    @endpush

</x-public-layout>

<header class="bg-maroon-500 sticky top-0 z-40 shadow-sm">
    <nav class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8" aria-label="Main navigation">
        <a href="{{ route('home') }}" class="flex items-center gap-3">
            <x-logo-mark class="h-11 w-11" />
            <span class="leading-tight">
                <span class="block text-[13px] font-extrabold uppercase tracking-wide text-white">Red Cubs Pet Patrol</span>
                <span class="block text-[10px] font-medium uppercase tracking-widest text-white/80">Compassion Make Us Human</span>
            </span>
            <span class="hidden text-white/60 sm:inline">&bull;</span>
            <span class="hidden text-sm font-bold text-white sm:inline">PAIRfect Paws</span>
        </a>

        <div class="hidden items-center gap-8 md:flex">
            <x-nav-link :href="route('home')" :active="request()->routeIs('home')">HOME</x-nav-link>
            <x-nav-link :href="route('donate')" :active="request()->routeIs('donate')">DONATE</x-nav-link>

            @auth
                <x-nav-link :href="route('animal.index')" :active="request()->routeIs('dashboard')">DASHBOARD</x-nav-link>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-sm font-bold text-white transition hover:text-white/80">
                        LOGOUT
                    </button>
                </form>
            @else
                <a href="{{ route('login') }}" class="text-sm font-bold text-white transition hover:text-white/80">
                    Login
                </a>
            @endauth
        </div>

        <button type="button" class="text-white md:hidden" onclick="document.getElementById('mobile-nav').classList.toggle('hidden')" aria-label="Toggle navigation">
            <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
    </nav>

    <div id="mobile-nav" class="hidden border-t border-white/10 bg-maroon-600 px-4 py-3 md:hidden">
        <div class="flex flex-col gap-3">
            <a href="{{ route('home') }}" class="text-sm font-semibold text-white">HOME</a>
            <a href="{{ route('donate') }}" class="text-sm font-semibold text-white">DONATE</a>
            @auth
                <a href="{{ route('animal.index') }}" class="text-sm font-semibold text-white">DASHBOARD</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-sm font-semibold text-white">LOGOUT</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="text-sm font-semibold text-white">LOGIN</a>
            @endauth
        </div>
    </div>
</header>

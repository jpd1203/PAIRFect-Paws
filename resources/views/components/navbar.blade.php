<header class="bg-maroon-500 sticky top-0 z-40 shadow-sm">
    <nav class="flex w-full items-center justify-between px-4 py-3 sm:px-8 lg:px-12" aria-label="Main navigation">
        <a href="{{ route('landing') }}" class="flex items-center gap-3">
            <img
                src="{{ asset('images/rcpp-logo.png') }}"
                alt="Red Cubs Pet Patrol"
                class="h-12 w-auto"
            >   
            <span class="hidden text-white/60 sm:inline">&bull;</span>
            <img
                src="{{ asset('images/pairfect-paws-logo.png') }}"
                alt="PAIRfect Paws"
                class="hidden h-5 sm:h-6 w-auto sm:inline object-contain"
            >
        </a>

        <div class="hidden items-center gap-8 md:flex">
            <x-nav-link :href="route('landing')" :active="request()->routeIs('landing') || request()->routeIs('home')">HOME</x-nav-link>
            <x-nav-link :href="route('donate')" :active="request()->routeIs('donate')">DONATE</x-nav-link>

            @auth
                @if (auth()->user()->isStaff())
                    <x-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.*')">DASHBOARD</x-nav-link>
                @else
                    <x-nav-link :href="route('animal.index')" :active="request()->routeIs('animal.*') || request()->routeIs('recommendation.*') || request()->routeIs('application.*') || request()->routeIs('adopter.*') || request()->routeIs('monitoring.*')">BROWSE PETS</x-nav-link>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-sm font-bold text-white transition hover:text-white/80 cursor-pointer">
                        LOGOUT
                    </button>
                </form>
            @else
                <a href="{{ route('login') }}" class="text-sm font-bold text-white transition hover:text-white/80">
                    Login
                </a>
            @endauth
        </div>

        <button type="button" id="mobileNavToggle" class="text-white md:hidden p-1 rounded-lg hover:bg-white/10 transition cursor-pointer" aria-label="Toggle navigation">
            <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
    </nav>

    <div id="mobile-nav" class="hidden border-t border-white/10 bg-primary-hover-600 px-4 py-3 md:hidden">
        <div class="flex flex-col items-center gap-3">
            <a href="{{ route('home') }}" class="w-full max-w-xs rounded-lg px-4 py-2 text-center text-sm font-semibold text-white hover:bg-primary-hover transition">HOME</a>
            <a href="{{ route('donate') }}" class="w-full max-w-xs rounded-lg px-4 py-2 text-center text-sm font-semibold text-white hover:bg-primary-hover transition">DONATE</a>
            @auth
                @if (auth()->user()->isStaff())
                    <a href="{{ route('admin.dashboard') }}" class="w-full max-w-xs rounded-lg px-4 py-2 text-center text-sm font-semibold text-white hover:bg-primary-hover transition">DASHBOARD</a>
                @else
                    <a href="{{ route('animal.index') }}" class="w-full max-w-xs rounded-lg px-4 py-2 text-center text-sm font-semibold text-white hover:bg-primary-hover transition">BROWSE PETS</a>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full max-w-xs rounded-lg px-4 py-2 text-center text-sm font-semibold text-white hover:bg-primary-hover transition">LOGOUT</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="w-full max-w-xs rounded-lg px-4 py-2 text-center text-sm font-semibold text-white hover:bg-primary-hover transition">LOGIN</a>
            @endauth
        </div>
    </div>
</header>

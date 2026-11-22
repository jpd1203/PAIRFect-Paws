@auth
    @if (! auth()->user()->hasVerifiedEmail())
        <div class="m-4 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 shadow-sm" role="status">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p>
                    <strong>Verify your email to continue.</strong>
                    You are signed in as <strong>{{ auth()->user()->email }}</strong>. You can browse pets, but applications and protected adopter features are locked.
                </p>
                <a href="{{ route('verification.notice') }}" class="shrink-0 rounded-lg bg-amber-900 px-4 py-2 text-center font-semibold text-white hover:bg-amber-950">
                    Verify email
                </a>
            </div>
        </div>
    @endif
@endauth

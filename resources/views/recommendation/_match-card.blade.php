<div class="match-card flex-col sm:flex-row" data-pet-card="{{ $pet->id }}">

    <img src="{{ $pet->image_url }}" alt="{{ $pet->name }}" onclick="openPetModal({{ $pet->id }})" class="cursor-pointer hover:opacity-90 transition-opacity">

    <div class="flex-1 min-w-0">

        <div class="flex items-center justify-between gap-3">
            <div>
                <strong class="font-primary text-base cursor-pointer hover:text-maroon-600 transition-colors" onclick="openPetModal({{ $pet->id }})">{{ $pet->name }}</strong>
                <span class="text-[.78rem] text-[#888] ml-1">{{ $pet->species }} &middot; {{ $pet->age_group }} &middot; {{ $pet->sex }}</span>
                <p class="mt-1 text-xs text-text-muted">Status: {{ $pet->availability_status->value }}</p>
            </div>
        </div>

        @if ($result['eligible'])
        <div class="flex flex-col gap-1.5 mt-2.5" data-rows>
            @foreach ($result['rows'] as $row)
                <div class="flex items-center gap-2.5">
                    <span class="compat-row-label">{{ $row['label'] }}</span>
                    <div class="compat-bar-track">
                        <div class="compat-bar-fill" style="width:{{ $row['percent'] }}%; background:{{ $row['percent'] >= 60 ? '#295F51' : ($row['percent'] >= 35 ? '#614E34' : '#773E47') }};"></div>
                    </div>
                    <span class="text-[.7rem] text-[#666] w-8 text-right">{{ $row['percent'] }}%</span>
                </div>
            @endforeach
        </div>
        @else
            <p class="mt-3 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                {{ \App\Services\Matching\MatchPresenter::reason($result['exclusion_reason']) }}
            </p>
        @endif

    </div>

    <div class="flex flex-col items-start sm:items-end justify-between shrink-0">
        <div class="text-right">
            @if ($result['eligible'])
            <div class="compat-score" data-overall-score>{{ $result['overall'] }}/100</div>
            <div class="compat-score-sub" data-match-label>{{ $matcher->matchLabel($result['overall']) }}</div>
            @else
                <span class="inline-flex rounded-full border border-amber-300 bg-amber-50 px-3 py-1 text-sm font-bold text-amber-900" data-match-label>Ineligible</span>
            @endif
        </div>

        <div class="flex flex-col gap-1.5 mt-3">
            <button type="button" class="btn btn-secondary cursor-pointer" onclick="openPetModal({{ $pet->id }})">View</button>
            @if (! $result['eligible'])
                <button type="button" class="btn btn-adoptMe text-center opacity-50 cursor-not-allowed" disabled>Ineligible to adopt</button>
            @elseif (auth()->check() && auth()->user()->isAdopter() && auth()->user()->hasVerifiedEmail())
                <a class="btn btn-adoptMe text-center" href="{{ route('application.apply', $pet) }}">Adopt</a>
            @elseif (auth()->guest())
                <a class="btn btn-adoptMe text-center" href="{{ route('login', ['redirect' => route('application.apply', $pet)]) }}">Sign in to adopt</a>
            @else
                <button type="button" class="btn btn-adoptMe text-center opacity-50 cursor-not-allowed" disabled>Adopt</button>
            @endif
        </div>
    </div>

</div>

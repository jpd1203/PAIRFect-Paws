<div class="match-card" data-pet-card="{{ $pet->id }}">

    <img src="{{ $pet->image_url }}" alt="{{ $pet->name }}">

    <div class="flex-1 min-w-0">

        <div class="flex items-center justify-between gap-3">
            <div>
                <strong class="font-primary text-base">{{ $pet->name }}</strong>
                <span class="text-[.78rem] text-[#888] ml-1">{{ $pet->species }} &middot; {{ $pet->age_group }} &middot; {{ $pet->sex }}</span>
            </div>
        </div>

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

    </div>

    <div class="flex flex-col items-end justify-between shrink-0">
        <div class="text-right">
            <div class="compat-score" data-overall-score>{{ $result['overall'] }}/100</div>
            <div class="compat-score-sub" data-match-label>{{ $matcher->matchLabel($result['overall']) }}</div>
        </div>

        <div class="flex flex-col gap-1.5 mt-3">
            <a class="btn btn-secondary" href="{{ route('pets.show', ['pet' => $pet, 'from' => 'matching']) }}">View</a>
            @if (auth()->check() && auth()->user()->isAdopter() && auth()->user()->hasVerifiedEmail())
                <a class="btn btn-adoptMe text-center" href="{{ route('application.apply', $pet) }}">Adopt</a>
            @elseif (auth()->guest())
                <a class="btn btn-adoptMe text-center" href="{{ route('login', ['redirect' => route('application.apply', $pet)]) }}">Sign in to adopt</a>
            @else
                <button type="button" class="btn btn-adoptMe text-center opacity-50 cursor-not-allowed" disabled>Adopt</button>
            @endif
        </div>
    </div>

</div>

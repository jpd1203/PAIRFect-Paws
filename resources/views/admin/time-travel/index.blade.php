@extends('admin.layouts.app')

@section('title', 'Post-Adoption Time Travel - PAIRfect Paws Admin')

@section('content')
    <div class="heading-text">
        <h2>Post-Adoption Time Travel</h2>
        <p>Temporarily unlock future welfare check-ins without changing the computer or database clock.</p>
    </div>

    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-status-danger-text bg-status-danger-bg p-4 text-sm text-status-danger-text" role="alert">
            <strong>The testing date could not be changed.</strong>
            <ul class="mt-2 list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mb-5 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950">
        <strong>Testing only:</strong> this affects check-in availability, labels, and overdue counts for every adopter.
        It does not change C2PA photo recency checks, submission timestamps, or automatically send future reminders.
    </div>

    <div class="stats-grid mb-6">
        <div class="stat-card">
            <h6>Effective date</h6>
            <h2>{{ $effectiveDate->format('M j, Y') }}</h2>
            <small>{{ $active ? 'Temporary testing date' : 'Real date' }}</small>
        </div>
        <div class="stat-card">
            <h6>Available incomplete check-ins</h6>
            <h1>{{ $dueCount }}</h1>
        </div>
        <div class="stat-card">
            <h6>Still upcoming</h6>
            <h1>{{ $upcomingCount }}</h1>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
        <section class="dashboard-box">
            <h3>Choose a test date</h3>
            <p class="mb-4 text-sm text-gray-600">Use any date from {{ $realDate->format('M j, Y') }} onward.</p>
            <form action="{{ route('time-travel.store') }}" method="POST" class="space-y-4">
                @csrf
                <input type="hidden" name="mode" value="date">
                <div class="form-group">
                    <label for="target_date">Effective post-adoption date</label>
                    <input
                        id="target_date"
                        name="target_date"
                        type="date"
                        min="{{ $realDate->toDateString() }}"
                        value="{{ old('target_date', $effectiveDate->toDateString()) }}"
                        required
                    >
                </div>
                <button type="submit" class="btn btn-primary w-full">
                    <i class="fa-solid fa-calendar-day"></i> Apply date
                </button>
            </form>
        </section>

        <section class="dashboard-box">
            <h3>Jump to next check-in</h3>
            <p class="mb-4 text-sm text-gray-600">
                @if ($nextDate)
                    The next incomplete milestone opens on {{ \Carbon\CarbonImmutable::parse($nextDate)->format('M j, Y') }}.
                @else
                    There is no later incomplete milestone.
                @endif
            </p>
            <form action="{{ route('time-travel.store') }}" method="POST">
                @csrf
                <input type="hidden" name="mode" value="next">
                <button type="submit" class="btn btn-primary w-full" @disabled(! $nextDate)>
                    <i class="fa-solid fa-forward-step"></i> Jump to next
                </button>
            </form>
        </section>

        <section class="dashboard-box">
            <h3>Unlock every current milestone</h3>
            <p class="mb-4 text-sm text-gray-600">
                @if ($lastDate)
                    Jump through {{ \Carbon\CarbonImmutable::parse($lastDate)->format('M j, Y') }} so all existing incomplete check-ins are available.
                @else
                    No incomplete check-ins currently exist.
                @endif
            </p>
            <form action="{{ route('time-travel.store') }}" method="POST">
                @csrf
                <input type="hidden" name="mode" value="all">
                <button type="submit" class="btn btn-primary w-full" @disabled(! $lastDate)>
                    <i class="fa-solid fa-forward-fast"></i> Unlock all
                </button>
            </form>
        </section>
    </div>

    <div class="mt-6 flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.monitoring.index') }}" class="btn btn-secondary">
            <i class="fa-solid fa-magnifying-glass"></i> View monitoring
        </a>

        <form action="{{ route('time-travel.destroy') }}" method="POST">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger" @disabled(! $active)>
                <i class="fa-solid fa-clock"></i> Return to real date
            </button>
        </form>
    </div>
@endsection

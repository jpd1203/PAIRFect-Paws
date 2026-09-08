@php
    $steps = [
        'approved' => ['title' => 'Approved', 'caption' => 'Application cleared by staff'],
        'handover_set' => ['title' => 'Released', 'caption' => 'Staff marked pet as released'],
        'awaiting' => ['title' => 'Awaiting confirmation', 'caption' => 'Adopter must confirm receipt'],
        'monitoring' => ['title' => 'Post-adoption monitoring', 'caption' => 'Pet confirmed with adopter'],
    ];
    $completed = $record->completed_steps;
    $current = $record->current_step;
    $isFailed = ($record->adopter_outcome === 'not_received');
    $isMonitoringLocked = $record->monitoring_locked;
@endphp

<section aria-label="Handover pipeline" class="bg-white border border-[#e2ddd7] rounded-card p-5 shadow-card">
    <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-[#f0ece5] pb-3">
        <h3 class="font-primary font-bold text-base text-text-dark m-0">Handover Pipeline</h3>
        <p class="text-xs text-[#777] m-0">
            Monitoring needs 
            <span class="{{ $record->released_at ? 'text-status-success-text font-bold' : 'text-text-dark font-semibold' }}">staff release</span> 
            + 
            <span class="{{ $record->adopter_outcome === 'received' ? 'text-status-success-text font-bold' : 'text-text-dark font-semibold' }}">adopter confirmation</span>
        </p>
    </div>

    <ol class="mt-4 grid gap-3 grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 m-0 p-0 list-none">
        @foreach ($steps as $key => $info)
            @php
                $isDone = $completed[$key] ?? false;
                $isCurr = ($current === $key && !$isDone);
                $stepFailed = ($isFailed && $key === 'awaiting');
                $stepLocked = ($key === 'monitoring' && $isMonitoringLocked);

                $cardBg = $stepFailed ? 'border border-status-danger-text/40 bg-status-danger-bg' : 
                          ($isDone ? 'border border-status-success-text/40 bg-status-success-bg' : 
                          ($isCurr ? 'border border-primary bg-primary-muted/20 shadow-sm' : 'border border-[#e2ddd7] bg-secondary-bg'));

                $numBg = $stepFailed ? 'bg-status-danger-text text-white' : 
                         ($isDone ? 'bg-status-success-text text-white' : 
                         ($isCurr ? 'bg-primary text-white' : 'bg-[#d1d1cb] text-[#555]'));
            @endphp
            <li class="relative rounded-xl p-3.5 transition {{ $cardBg }}">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[11px] font-bold {{ $numBg }}">
                        @if ($stepFailed)
                            <i class="fa-solid fa-triangle-exclamation text-[10px]"></i>
                        @elseif ($isDone)
                            <i class="fa-solid fa-check text-[10px]"></i>
                        @elseif ($stepLocked)
                            <i class="fa-solid fa-lock text-[10px]"></i>
                        @else
                            {{ $loop->iteration }}
                        @endif
                    </span>
                    <p class="font-primary font-bold text-sm leading-tight text-text-dark m-0">{{ $info['title'] }}</p>
                </div>

                <p class="mt-2 text-xs leading-snug text-text-muted m-0">
                    @if ($stepFailed)
                        Adopter reported the pet was not received
                    @else
                        {{ $info['caption'] }}
                    @endif
                </p>

                @if ($stepLocked)
                    <span class="badge badge-upcoming text-[10px] py-0.5 px-2 mt-2.5">
                        <i class="fa-solid fa-lock mr-1 text-[9px]"></i> Locked
                    </span>
                @elseif ($key === 'monitoring' && !$isMonitoringLocked)
                    <span class="badge badge-completed text-[10px] py-0.5 px-2 mt-2.5">
                        <i class="fa-solid fa-circle-check mr-1 text-[9px]"></i> Active
                    </span>
                @endif
            </li>
        @endforeach
    </ol>
</section>

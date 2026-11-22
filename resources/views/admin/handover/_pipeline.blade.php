@php
    $steps = ['approved' => 'Approved', 'handover_set' => ($workflowReadOnly ?? false) ? 'Handover ready' : 'Released', 'awaiting' => 'Awaiting confirmation', 'monitoring' => 'Post-adoption monitoring'];
    $completed = $record->completed_steps;
    $current = $record->current_step;
@endphp
<section aria-label="Handover pipeline" class="bg-white border border-[#e2ddd7] rounded-card p-4 shadow-card">
    <h3 class="font-bold text-base m-0">Handover Overview</h3>
    <ol class="grid grid-cols-2 lg:grid-cols-4 gap-2 mt-3 mb-0 p-0 list-none">
        @foreach($steps as $key => $title)
            @php
                $done = $completed[$key] ?? false;
                $active = $current === $key;
                $concern = $key === 'awaiting' && $record->adopter_outcome === 'not_received';
                $state = $concern ? (($workflowReadOnly ?? false) ? 'Not received' : 'Needs investigation') : ($active ? ($done ? ($key === 'monitoring' ? 'Active' : 'Complete · current') : 'Current') : ($done ? 'Complete' : 'Locked'));
                $color = $concern ? 'border-red-200 bg-red-50 text-red-800' : ($active ? 'border-primary bg-primary-muted/20 text-primary' : ($done ? 'border-completed-fg bg-completed-bg text-completed-fg' : 'border-[#e2ddd7] bg-secondary-bg text-text-muted'));
            @endphp
            <li class="border rounded-lg p-3 min-w-0 {{ $color }}" @if($active) aria-current="step" @endif>
                <span class="text-sm font-semibold block"><i aria-hidden="true" class="fa-solid {{ $concern ? 'fa-triangle-exclamation' : ($done ? 'fa-check' : ($active ? 'fa-circle-dot' : 'fa-lock')) }} mr-1"></i>{{ $title }}</span>
                <span class="text-xs block mt-1">{{ $state }}</span>
            </li>
        @endforeach
    </ol>
</section>

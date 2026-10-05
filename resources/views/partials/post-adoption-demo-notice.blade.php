@if(app(\App\Services\PostAdoptionWebDemoService::class)->enabled() && auth()->check())
    @php
        $presentationAdoptions = auth()->user()->adoptionApplications()
            ->where('status', \App\Enums\ApplicationStatus::Approved->value)
            ->whereHas('handover', fn ($query) => $query->where('adopter_outcome', 'received'))
            ->get()
            ->filter(fn ($application) => app(\App\Services\PostAdoptionWebDemoService::class)->stateFor((int) $application->id) !== null);
    @endphp
    @if($presentationAdoptions->isNotEmpty())
        <div class="mx-5 mt-4 rounded-xl border border-amber-400 bg-amber-50 p-3 text-sm text-amber-950" role="status">
            <strong>Presentation demo active.</strong> Check-in availability and notices for this adoption use a temporary demonstration date.
            Your official adoption and reminder records are unchanged.
        </div>
    @endif
@endif

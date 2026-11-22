<section class="modal-note {{ $uiTransportAlert || ($uiUnreleased && $uiExpired) ? 'caution' : '' }} mb-5" aria-labelledby="nextActionHeading">
    <h3 id="nextActionHeading" class="font-bold text-sm m-0">Current status &amp; next action</h3>
    <p class="mt-1 mb-2 text-sm font-semibold" role="status">{{ $uiNextAction }}</p>
    <a href="#{{ $uiActionTarget }}" class="font-semibold text-sm underline">{{ $uiActionLabel }}</a>
    @if($record->monitoring_locked)
        <p class="mt-2 mb-0 text-xs">{{ ($workflowReadOnly ?? false) ? 'Check-ins unlock after the adopter records receipt confirmation.' : 'Monitoring stays locked until staff release and adopter receipt confirmation are recorded.' }}</p>
    @endif
</section>

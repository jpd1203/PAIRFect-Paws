@php
    $method = old('release_method', $record->scheduled_method ?: $record->release_method ?: 'pickup');
    $localNow = \App\Support\ManilaTime::now();
    $date = old('release_date', $record->release_date ? $record->release_date->format('Y-m-d') : $localNow->toDateString());
    $time = old('release_time', $record->release_time ?: $localNow->format('H:i'));
    $staff = old('staff_name', $record->staff_name ?: auth()->user()?->full_name);
    $providers = config('handover.delivery_providers');
    $selectedProvider = old('courier_provider', collect($providers)->search(fn ($provider) => $provider['label'] === $record->courier));
    $tracking = old('tracking_number', $record->tracking_number);
@endphp

<details class="bg-white border border-[#e2ddd7] rounded-card shadow-card scroll-mt-20 p-4 sm:p-5" id="releaseFormSection" @if(!$uiReleaseBlockers || ($errors->any() && old('release_method') !== null)) open @endif>
    <summary class="cursor-pointer text-base font-bold">Release Details{{ $uiReleaseBlockers ? ' — blocked' : '' }}</summary>
    @if($uiReleaseBlockers)
        <div class="modal-note caution mt-3" id="releaseBlockers" role="note"><p class="font-semibold m-0">Before release:</p><ul class="list-disc pl-5 m-0">@foreach($uiReleaseBlockers as $blocker)<li>{{ $blocker }}</li>@endforeach</ul></div>
    @endif

    <form id="handoverForm" method="POST" action="{{ route('admin.handover.release', $record) }}" enctype="multipart/form-data" data-agreed-method="{{ $record->scheduled_method }}" data-release-allowed="{{ !$uiReleaseBlockers ? 'true' : 'false' }}">
        @csrf

        <div class="space-y-5 p-6">
            <!-- Release Method Selection -->
            <fieldset>
                <legend class="form-label mb-2">Release Method</legend>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="method-option flex cursor-pointer items-start gap-3 rounded-xl border border-[#e2ddd7] bg-white p-3.5 transition has-[:checked]:border-primary has-[:checked]:bg-primary-muted/25 has-[:checked]:ring-1 has-[:checked]:ring-primary">
                        <input type="radio" name="release_method" value="pickup" {{ $method === 'pickup' ? 'checked' : '' }} onchange="toggleCourierFields(this.value)" aria-describedby="releaseMethodError" class="mt-1 h-4 w-4 shrink-0 accent-primary">
                        <div>
                            <span class="flex items-center gap-1.5 text-sm font-bold text-text-dark"><i class="fa-solid fa-house-user text-primary"></i> Pickup at shelter</span>
                        </div>
                    </label>

                    <label class="method-option flex cursor-pointer items-start gap-3 rounded-xl border border-[#e2ddd7] bg-white p-3.5 transition has-[:checked]:border-primary has-[:checked]:bg-primary-muted/25 has-[:checked]:ring-1 has-[:checked]:ring-primary">
                        <input type="radio" name="release_method" value="delivery" {{ $method === 'delivery' ? 'checked' : '' }} onchange="toggleCourierFields(this.value)" aria-describedby="releaseMethodError" class="mt-1 h-4 w-4 shrink-0 accent-primary">
                        <div>
                            <span class="flex items-center gap-1.5 text-sm font-bold text-text-dark"><i class="fa-solid fa-truck text-primary"></i> Third-party delivery</span>
                        </div>
                    </label>
                </div>
            </fieldset>
            <p id="releaseMethodError" class="text-sm text-red-700" data-server-error="{{ $errors->has('release_method') ? 'true' : 'false' }}" role="alert">{{ $errors->first('release_method') }}</p>


            <!-- Date, Time, Staff -->
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="form-group">
                    <label for="release-date" class="form-label">Date of release (Asia/Manila)</label>
                    <input id="release-date" name="release_date" type="date" value="{{ $date }}" required
                           class="form-control" aria-invalid="{{ $errors->has('release_date') ? 'true' : 'false' }}" aria-describedby="releaseDateError">
                    @error('release_date')<p id="releaseDateError" class="text-xs text-red-700" role="alert">{{ $message }}</p>@enderror
                </div>
                <div class="form-group">
                    <label for="release-time" class="form-label">Time of release (Asia/Manila)</label>
                    <input id="release-time" name="release_time" type="time" value="{{ $time }}" required
                           class="form-control" aria-invalid="{{ $errors->has('release_time') ? 'true' : 'false' }}" aria-describedby="releaseTimeError">
                    @error('release_time')<p id="releaseTimeError" class="text-xs text-red-700" role="alert">{{ $message }}</p>@enderror
                </div>
                <div class="form-group">
                    <label for="release-staff" class="form-label">Released by</label>

                    <div class="relative">
                        <select id="release-staff" name="staff_id" class="form-select appearance-none pr-10" required aria-describedby="releaseStaffError">
                            <option value="">Select Staff</option>
                            @foreach ($volunteers as $v)
                                <option
                                    value="{{ $v->id }}"
                                    @selected($v->id == old('staff_id', $currentStaffId))>
                                    {{ $v->full_name }} ({{ $v->role->value }})
                                </option>
                            @endforeach
                        </select>
                        <i class="fa-solid fa-chevron-down pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-sm text-[#777]"></i>
                    </div>
                    @error('staff_id')<p id="releaseStaffError" class="text-xs text-red-700" role="alert">{{ $message }}</p>@enderror
                </div>
            </div>

            <div id="courierFields" class="{{ $method === 'delivery' ? 'grid' : 'hidden' }} gap-4 rounded-xl border border-[#e2ddd7] bg-secondary-bg p-4 sm:grid-cols-2">
                <div class="form-group">
                    <label for="courier-provider" class="form-label">Courier / Pet Transport Service</label>
                    <div class="relative">
                        <select id="courier-provider" name="courier_provider" class="form-select appearance-none pr-10"
                                onchange="updateCourierProviderHelp()" aria-describedby="courier-provider-help courierProviderError" @required($method === 'delivery')>
                            <option value="">Select provider</option>
                            @foreach ($providers as $key => $provider)
                                <option value="{{ $key }}" data-note="{{ $provider['note'] }}" @selected($selectedProvider === $key)>{{ $provider['label'] }}</option>
                            @endforeach
                        </select>
                    <i class="fa-solid fa-chevron-down pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-sm text-[#777]"></i>
                    </div>
                    <p id="courier-provider-help" class="mt-1 text-xs text-[#777]" aria-live="polite"></p>
                    @error('courier_provider')
                        <p id="courierProviderError" class="mt-1 text-sm text-status-danger-text" role="alert">{{ $message }}</p>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="tracking-number" class="form-label">Booking / Tracking Reference</label>
                    <input id="tracking-number" name="tracking_number" type="text" value="{{ $tracking }}" placeholder="e.g. PTG-239123"
                           class="form-control font-mono" aria-describedby="trackingNumberError" @required($method === 'delivery')>
                    @error('tracking_number')<p id="trackingNumberError" class="text-xs text-red-700" role="alert">{{ $message }}</p>@enderror
                </div>
                <div class="form-group sm:col-span-2">
                    <label for="tracking-url" class="form-label">Live Tracking Link</label>
                    <input id="tracking-url" name="tracking_url" type="url" value="{{ old('tracking_url', $record->tracking_url) }}"
                           placeholder="https://..." maxlength="2048" pattern="https://.*"
                           class="form-control" aria-describedby="tracking-url-help trackingUrlError" @required($method === 'delivery')>
                    <p id="tracking-url-help" class="mt-1 text-xs text-[#777]">Use the provider's HTTPS tracking/share link.</p>
                    @error('tracking_url')
                        <p id="trackingUrlError" class="mt-1 text-sm text-status-danger-text" role="alert">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Proof of Handover Photo -->
            <div>
                <label class="form-label mb-1.5" for="handover-proof">Proof of handover</label>

                @if ($record->proof_path || $record->legacyPublicProofPath())
                    <div id="existingProof" class="flex items-center gap-3 rounded-xl border border-[#e2ddd7] bg-secondary-bg p-3 mb-2">
                        <img src="{{ route('admin.handover.release-proof', $record) }}" alt="Proof" class="h-16 w-16 rounded-lg object-cover border border-[#e2ddd7]">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-text-dark m-0">{{ $record->proof_name ?: 'proof-image.jpg' }}</p>
                            <p class="text-xs text-[#777] m-0">Attached to this handover record</p>
                        </div>
                    </div>
                @endif

                <div>
                    <label class="flex cursor-pointer flex-col items-center rounded-xl border-2 border-dashed border-[#ccc] bg-secondary-bg px-4 py-6 text-center transition hover:border-primary hover:bg-primary-muted/10">
                        <i class="fa-solid fa-cloud-arrow-up text-[#aaa] text-2xl mb-1"></i>
                        <span class="text-sm font-bold text-text-dark font-primary">Upload handover photo</span>
                        <span class="text-xs text-[#777] mt-0.5">Pet with adopter or courier at the moment of release &middot; JPG, PNG up to 5MB</span>
                        <input id="handover-proof" type="file" name="proof" accept="image/*" class="sr-only" aria-describedby="handoverProofError" onchange="previewHandoverPhoto(event)">
                    </label>
                    <div id="photoPreviewContainer" class="hidden mt-2 flex items-center gap-3 p-3 bg-neutral-light rounded-xl border border-[#e2ddd7]">
                        <img id="photoPreviewImg" src="" alt="New Proof Preview" class="h-14 w-14 object-cover rounded-lg border border-[#ccc]">
                        <span id="photoPreviewName" class="text-xs font-medium text-text-dark truncate flex-1"></span>
                    </div>
                </div>
                @error('proof')<p id="handoverProofError" class="text-xs text-red-700" role="alert">{{ $message }}</p>@enderror
            </div>

            <!-- Important Guidance Notice -->
            <div class="modal-note mb-0 flex items-start gap-2.5">
                <i class="fa-solid fa-circle-info text-base mt-0.5 shrink-0"></i>
                <p class="text-xs leading-relaxed m-0">
                    Staff release does not confirm adopter receipt.
                </p>
            </div>
        </div>

        <!-- Action Buttons Footer -->
        <div class="flex flex-wrap items-center justify-end gap-2.5 border-t border-[#e2ddd7] bg-secondary-bg px-6 py-4">
            <button id="markReleased" type="submit" class="btn btn-primary" @disabled($uiReleaseBlockers) @if($uiReleaseBlockers) aria-describedby="releaseBlockers" @endif>
                <i class="fa-solid fa-truck-fast mr-1.5"></i> Mark as released
            </button>
        </div>
    </form>
</details>

<script>
function toggleCourierFields(method) {
    const courierDiv = document.getElementById('courierFields');
    courierDiv.querySelectorAll('input, select').forEach(field => {
        field.required = method === 'delivery';
        field.disabled = method !== 'delivery';
    });
    if (method === 'delivery') {
        courierDiv.classList.remove('hidden');
        courierDiv.classList.add('grid');
    } else {
        courierDiv.classList.add('hidden');
        courierDiv.classList.remove('grid');
    }
    const form = document.getElementById('handoverForm');
    const mismatch = method !== form.dataset.agreedMethod;
    document.getElementById('markReleased').disabled = form.dataset.releaseAllowed !== 'true' || mismatch;
    const message = document.getElementById('releaseMethodError');
    if (mismatch) message.textContent = 'Release method must match the confirmed schedule. Propose and confirm a new schedule to change it.';
    else if (message.dataset.serverError !== 'true') message.textContent = '';
}

function updateCourierProviderHelp() {
    const provider = document.getElementById('courier-provider');
    const help = document.getElementById('courier-provider-help');
    help.textContent = provider.selectedOptions[0]?.dataset.note || '';
    help.classList.toggle('hidden', !help.textContent);
}

toggleCourierFields(document.querySelector('input[name="release_method"]:checked').value);
updateCourierProviderHelp();

function previewHandoverPhoto(e) {
    const file = e.target.files[0];
    if (file) {
        const previewCont = document.getElementById('photoPreviewContainer');
        const previewImg = document.getElementById('photoPreviewImg');
        const previewName = document.getElementById('photoPreviewName');
        previewImg.src = URL.createObjectURL(file);
        previewName.textContent = file.name;
        previewCont.classList.remove('hidden');
    }
}
</script>

@php
    $method = old('release_method', $record->release_method ?: 'pickup');
    $date = old('release_date', $record->release_date ? $record->release_date->format('Y-m-d') : date('Y-m-d'));
    $time = old('release_time', $record->release_time ?: date('H:i'));
    $staff = old('staff_name', $record->staff_name ?: auth()->user()?->full_name);
    $courier = old('courier', $record->courier);
    $tracking = old('tracking_number', $record->tracking_number);
@endphp

<div class="bg-white border border-[#e2ddd7] rounded-card shadow-card overflow-hidden" id="releaseFormSection">
    <div class="border-b border-[#e2ddd7] px-6 py-4 bg-secondary-bg">
        <h3 class="text-base font-bold text-text-dark font-primary m-0">Release Details</h3>
        <p class="mt-0.5 text-xs text-[#777] m-0">Record how and when {{ $record->pet?->name ?? 'the pet' }} physically left the shelter.</p>
    </div>

    <form id="handoverForm" method="POST" action="{{ route('admin.handover.release', $record) }}" enctype="multipart/form-data">
        @csrf

        <div class="space-y-5 p-6">
            <!-- Release Method Selection -->
            <fieldset>
                <legend class="form-label mb-2">Release Method</legend>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="method-option flex cursor-pointer items-start gap-3 rounded-xl border p-3.5 transition {{ $method === 'pickup' ? 'border-primary bg-primary-muted/25 ring-1 ring-primary' : 'border-[#e2ddd7] bg-white hover:bg-neutral-light' }}">
                        <input type="radio" name="release_method" value="pickup" {{ $method === 'pickup' ? 'checked' : '' }} 
                               onchange="toggleCourierFields(this.value)" class="mt-1 h-4 w-4 accent-primary">
                        <div>
                            <span class="flex items-center gap-1.5 text-sm font-bold text-text-dark">
                                <i class="fa-solid fa-house-user text-primary"></i> Pickup at shelter
                            </span>
                            <span class="mt-0.5 block text-xs text-[#777]">Adopter collects the pet in person</span>
                        </div>
                    </label>

                    <label class="method-option flex cursor-pointer items-start gap-3 rounded-xl border p-3.5 transition {{ $method === 'delivery' ? 'border-primary bg-primary-muted/25 ring-1 ring-primary' : 'border-[#e2ddd7] bg-white hover:bg-neutral-light' }}">
                        <input type="radio" name="release_method" value="delivery" {{ $method === 'delivery' ? 'checked' : '' }} 
                               onchange="toggleCourierFields(this.value)" class="mt-1 h-4 w-4 accent-primary">
                        <div>
                            <span class="flex items-center gap-1.5 text-sm font-bold text-text-dark">
                                <i class="fa-solid fa-truck text-primary"></i> Third-party delivery
                            </span>
                            <span class="mt-0.5 block text-xs text-[#777]">Lalamove, Grab or partner courier</span>
                        </div>
                    </label>
                </div>
            </fieldset>

            <!-- Date, Time, Staff -->
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="form-group">
                    <label for="release-date" class="form-label">Date of release</label>
                    <input id="release-date" name="release_date" type="date" value="{{ $date }}" required
                           class="form-control">
                </div>
                <div class="form-group">
                    <label for="release-time" class="form-label">Time of release</label>
                    <input id="release-time" name="release_time" type="time" value="{{ $time }}" required
                           class="form-control">
                </div>
                <div class="form-group"><label for="release-staff" class="form-label">Released by</label>
                    <select id="release-staff" name="staff_id" class="form-select appearance-auto" required>
                        <option value="">Select Staff</option>
                        @foreach ($volunteers as $v)
                            <option
                                value="{{ $v->id }}"
                                {{ $v->id == $currentStaffId ? 'selected' : '' }}>
                                {{ $v->full_name }} ({{ $v->role->value }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div id="courierFields" class="{{ $method === 'delivery' ? 'grid' : 'hidden' }} gap-4 rounded-xl border border-[#e2ddd7] bg-secondary-bg p-4 sm:grid-cols-2">
                <div class="form-group">
                    <label for="courier-name" class="form-label">Courier / service</label>
                    <input id="courier-name" name="courier" type="text" value="{{ $courier }}" placeholder="e.g. Lalamove"
                           class="form-control">
                </div>
                <div class="form-group">
                    <label for="tracking-number" class="form-label">Reference / tracking no.</label>
                    <input id="tracking-number" name="tracking_number" type="text" value="{{ $tracking }}" placeholder="e.g. LLM-77341902"
                           class="form-control font-mono">
                </div>
            </div>

            <!-- Proof of Handover Photo -->
            <div>
                <label class="form-label mb-1.5">Proof of handover</label>

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
                        <input type="file" name="proof" accept="image/*" class="sr-only" onchange="previewHandoverPhoto(event)">
                    </label>
                    <div id="photoPreviewContainer" class="hidden mt-2 flex items-center gap-3 p-3 bg-neutral-light rounded-xl border border-[#e2ddd7]">
                        <img id="photoPreviewImg" src="" alt="New Proof Preview" class="h-14 w-14 object-cover rounded-lg border border-[#ccc]">
                        <span id="photoPreviewName" class="text-xs font-medium text-text-dark truncate flex-1"></span>
                    </div>
                </div>
            </div>

            <!-- Important Guidance Notice -->
            <div class="modal-note mb-0 flex items-start gap-2.5">
                <i class="fa-solid fa-circle-info text-base mt-0.5 shrink-0"></i>
                <p class="text-xs leading-relaxed m-0">
                    <strong>Marking as released is the staff-side confirmation only.</strong>
                    {{ $record->adopter_name }} still has to confirm receipt before post-adoption monitoring can begin.
                </p>
            </div>
        </div>

        <!-- Action Buttons Footer -->
        <div class="flex flex-wrap items-center justify-end gap-2.5 border-t border-[#e2ddd7] bg-secondary-bg px-6 py-4">
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-truck-fast mr-1.5"></i> Mark as released
            </button>
        </div>
    </form>
</div>

<script>
function toggleCourierFields(method) {
    const courierDiv = document.getElementById('courierFields');
    if (method === 'delivery') {
        courierDiv.classList.remove('hidden');
        courierDiv.classList.add('grid');
    } else {
        courierDiv.classList.add('hidden');
        courierDiv.classList.remove('grid');
    }
}

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

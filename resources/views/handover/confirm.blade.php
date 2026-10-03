@extends('layouts.app')

@section('title', 'Confirm Receipt - ' . ($record->pet?->name ?? 'Pet') . ' - PAIRfect Paws')

@section('content')

    <div class="sticky-header">
        <div class="heading-text">
            <h2>Confirm Pet Receipt</h2>
            <p>Verify that {{ $record->pet?->name ?? 'your pet' }} has arrived safely in your care.</p>
        </div>
    </div>

    <div class="content-area">

        <div class="max-w-[860px] mx-auto space-y-5 py-3">

            <!-- Sub Navigation Tabs -->
            <div class="flex items-center gap-2 border-b border-[#e2ddd7] pb-3 flex-wrap">
                <a href="{{ route('adopter.handover.status', $record) }}" class="btn btn-secondary btn-sm">
                    <i class="fa-solid fa-arrow-left mr-1"></i> Back to Handover Status
                </a>

                <a href="{{ route('adopter.handover.notifications', $record) }}" class="btn btn-secondary btn-sm">
                    <i class="fa-solid fa-bell mr-1"></i> Notifications
                    @if (($unreadCount ?? 0) > 0)
                        <span class="rounded-full px-1.5 py-0.2 text-xs font-bold bg-primary text-white ml-1">
                            {{ $unreadCount }}
                        </span>
                    @endif
                </a>

                <span class="btn btn-primary btn-sm pointer-events-none">
                    <i class="fa-solid fa-clipboard-check mr-1"></i> Confirm Receipt
                </span>
            </div>

            <!-- Pet Identity Card -->
            <section class="flex items-center gap-4 rounded-card border border-[#e2ddd7] bg-white p-5 shadow-card">
                <img src="{{ $record->photo_url }}" alt="{{ $record->pet?->name }}"
                     class="h-20 w-20 shrink-0 rounded-xl object-cover border border-[#e2ddd7] bg-[#fdf9f2] shadow-sm">

                <div class="min-w-0">
                    <span class="text-xs font-bold uppercase tracking-widest text-primary">
                        {{ strtoupper($record->code) }}
                    </span>
                    <h2 class="mt-0.5 text-2xl font-bold tracking-tight text-text-dark font-primary m-0">
                        Did {{ $record->pet?->name ?? 'your pet' }} arrive?
                    </h2>
                    <p class="mt-1 text-sm text-[#777] m-0">
                        Hi {{ explode(' ', $record->adopter_name)[0] ?? 'there' }} &mdash; staff marked {{ $record->pet?->name ?? 'your pet' }} as released. Please confirm receipt so we can finalize your adoption.
                    </p>
                </div>
            </section>

            <!-- Release Summary Card with Proof Photo -->
            @php
                $isDelivery = ($record->release_method === 'delivery');
                $dateTime = ($record->release_date && $record->release_time)
                    ? $record->release_date->format('M j, Y') . ' · ' . date('g:i A', strtotime($record->release_time))
                    : ($record->released_at ? $record->released_at->format('M j, Y · g:i A') : 'Recently released');
            @endphp

            <section class="rounded-card border border-[#e2ddd7] bg-white p-6 shadow-card">
                <h3 class="text-base font-bold text-text-dark font-primary m-0 mb-4">
                    Release Details
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-[1fr_auto] gap-6 items-start">
                    <div class="space-y-4">
                        <div class="flex items-start gap-3">
                            <i class="fa-solid fa-truck mt-0.5 text-[#9e9e9e] text-base w-5"></i>
                            <div>
                                <span class="text-[11px] font-bold uppercase tracking-wider text-[#777] block">RELEASE METHOD</span>
                                <span class="text-sm font-bold text-text-dark block mt-0.5">
                                    {{ $isDelivery ? ('Third-party delivery · ' . ($record->courier ?: 'Courier')) : 'Shelter pickup' }}
                                </span>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <i class="fa-regular fa-clock mt-0.5 text-[#9e9e9e] text-base w-5"></i>
                            <div>
                                <span class="text-[11px] font-bold uppercase tracking-wider text-[#777] block">DATE &amp; TIME RELEASED</span>
                                <span class="text-sm font-bold text-text-dark block mt-0.5">{{ $dateTime }}</span>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <i class="fa-regular fa-user mt-0.5 text-[#9e9e9e] text-base w-5"></i>
                            <div>
                                <span class="text-[11px] font-bold uppercase tracking-wider text-[#777] block">RELEASED BY</span>
                                <span class="text-sm font-bold text-text-dark block mt-0.5">{{ $record->staff_name ?: 'Shelter Staff' }}</span>
                            </div>
                        </div>

                        @if ($record->tracking_number)
                            <div class="flex items-start gap-3">
                                <i class="fa-solid fa-hashtag mt-0.5 text-[#9e9e9e] text-base w-5"></i>
                                <div>
                                    <span class="text-[11px] font-bold uppercase tracking-wider text-[#777] block">TRACKING REFERENCE</span>
                                    <span class="text-sm font-bold font-mono text-text-dark block mt-0.5">{{ $record->tracking_number }}</span>
                                </div>
                            </div>
                        @endif
                    </div>

                    @if ($record->proof_url || $record->photo_url)
                        <div class="flex flex-col items-center sm:items-end justify-center">
                            <figure class="w-full sm:w-56 m-0">
                                <img src="{{ $record->proof_url ?: $record->photo_url }}" alt="Proof of handover"
                                     class="w-full h-36 rounded-xl object-cover border border-[#e2ddd7] shadow-sm bg-neutral-light">
                                <figcaption class="mt-1.5 text-center text-xs text-[#777] font-medium">
                                    Proof of handover
                                </figcaption>
                            </figure>
                        </div>
                    @endif
                </div>
            </section>

            <!-- Confirmation Action Section -->
            @if ($record->adopter_outcome)
                @php $isReceived = ($record->adopter_outcome === 'received'); @endphp
                <div class="rounded-card border p-6 shadow-card {{ $isReceived ? 'border-status-success-text/30 bg-status-success-bg' : 'border-status-danger-text/30 bg-status-danger-bg' }}">
                    <div class="flex items-start gap-4">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white border {{ $isReceived ? 'border-status-success-text/20 text-status-success-text' : 'border-status-danger-text/20 text-status-danger-text' }} shadow-sm">
                            <i class="{{ $isReceived ? 'fa-solid fa-circle-check' : 'fa-solid fa-triangle-exclamation' }} text-xl"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-base font-bold font-primary {{ $isReceived ? 'text-status-success-text' : 'text-status-danger-text' }} m-0">
                                {{ $isReceived ? "Thank you — {$record->pet?->name}'s adoption is complete!" : "Reported as not received. Shelter staff have been notified." }}
                            </h3>
                            <p class="text-sm {{ $isReceived ? 'text-status-success-text' : 'text-status-danger-text' }} mt-1 mb-0 leading-relaxed">
                                @if ($isReceived)
                                    Your first post-adoption check-in has been scheduled. You can track your ongoing care milestones in My Check-ins.
                                @else
                                    Staff have received your report and will reach out to you promptly at <strong>{{ $record->adopter_phone }}</strong> to sort out {{ $record->pet?->name }}'s delivery.
                                @endif
                            </p>

                            @if ($record->adopter_note)
                                <div class="mt-3 p-3 bg-white/90 rounded-lg border border-[#e2ddd7] text-xs text-text-dark">
                                    <strong>Your Note:</strong> {{ $record->adopter_note }}
                                </div>
                            @endif

                            @if ($isReceived)
                                <p class="mt-3 text-sm text-status-success-text">
                                    Received {{ \App\Support\ManilaTime::format($record->received_at ?? $record->adopter_confirmed_at, 'M j, Y g:i A') }}.
                                    @if ($record->receipt_proof_path)
                                        <a href="{{ route('handover.receipt-proof', $record) }}" target="_blank" rel="noopener" class="underline">View your supporting photo</a>
                                    @endif
                                </p>
                                <div class="mt-4">
                                    <a href="{{ route('monitoring.index') }}" class="btn btn-primary">
                                        Go to My Check-ins &rarr;
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @elseif ($record->released_at)
                <section class="rounded-card border border-[#e2ddd7] bg-white p-6 shadow-card">
                    <form method="POST" action="{{ route('adopter.confirm.submit', $record) }}" enctype="multipart/form-data">
                        @csrf

                        <div class="mb-5">
                            <h3 class="text-lg font-bold text-text-dark font-primary m-0 mb-1">
                                Confirm Pet Delivery
                            </h3>
                            <p class="text-sm text-text-muted m-0 leading-relaxed">
                                Once you confirm receipt, your adoption is finalized and post-adoption check-ins will activate.
                            </p>
                        </div>

                        @if ($errors->any())
                            <div class="mb-4 rounded-lg border border-status-danger-text/30 bg-status-danger-bg p-3 text-sm text-status-danger-text">
                                {{ $errors->first() }}
                            </div>
                        @endif

                        <div class="form-group mb-5">
                            <label for="receiptProof" class="form-label">Proof of Receipt photo</label>
                            <p class="text-sm text-text-muted mb-2">Upload a photo showing that you have received the adopted pet. This image will be stored as supporting documentation for the handover.</p>
                            <input id="receiptProof" type="file" name="receipt_proof" accept="image/jpeg,image/png,image/webp" class="form-control" aria-describedby="receiptProofHelp">
                            <p id="receiptProofHelp" class="text-xs text-text-muted mt-1">JPEG, PNG, or WebP; maximum 5 MB. Required when confirming receipt.</p>
                            <img id="receiptProofPreview" class="hidden mt-3 h-32 w-32 rounded-xl object-cover border border-[#e2ddd7]" alt="Selected receipt photo preview">
                        </div>

                        <div class="form-group mb-6">
                            <label for="adopterNote" class="form-label">
                                Optional note or feedback on the handover
                            </label>
                            <textarea id="adopterNote" name="note" rows="3"
                                      placeholder="e.g. {{ $record->pet?->name }} is safe and settling in nicely, delivery was right on time..."
                                      class="form-control resize-y"></textarea>
                        </div>

                        <div class="flex items-center gap-4 flex-wrap justify-between pt-4 border-t border-[#e2ddd7]">
                            <button type="submit" name="outcome" value="received" class="btn btn-primary">
                                <i class="fa-solid fa-circle-check mr-2"></i> Yes, I've received {{ $record->pet?->name }}
                            </button>

                            <button type="submit" name="outcome" value="not_received"
                                    onclick="return confirm('Are you sure you want to report that {{ $record->pet?->name }} has not arrived? Staff will be alerted immediately.');"
                                    class="btn btn-danger">
                                <i class="fa-solid fa-triangle-exclamation mr-2"></i> No, I haven't received my pet
                            </button>
                        </div>
                    </form>
                </section>
            @else
                <div class="rounded-card border border-[#e2ddd7] bg-white p-6 text-sm text-text-muted">Receipt confirmation becomes available after staff mark the pet as released.</div>
            @endif

        </div>

    </div>

@endsection

@push('scripts')
<script>
    document.getElementById('receiptProof')?.addEventListener('change', function () {
        const preview = document.getElementById('receiptProofPreview');
        const file = this.files?.[0];
        if (!file) { preview?.classList.add('hidden'); return; }
        const url = URL.createObjectURL(file);
        preview.src = url;
        preview.classList.remove('hidden');
        preview.onload = () => URL.revokeObjectURL(url);
    });
</script>
@endpush

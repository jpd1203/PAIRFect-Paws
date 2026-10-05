<nav aria-label="Pet recommendation pages" class="my-5 flex flex-wrap items-center justify-between gap-3">
    <p class="m-0 text-sm text-text-muted">
        Showing {{ $matches->firstItem() ?? 0 }} to {{ $matches->lastItem() ?? 0 }} of {{ $matches->total() }} pets
    </p>
    <div class="flex flex-wrap items-center gap-3">
        @if ($matches->onFirstPage())
            <button type="button" class="btn btn-secondary btn-sm opacity-50 cursor-not-allowed" disabled>Previous</button>
        @else
            <a class="btn btn-secondary btn-sm" href="{{ $matches->previousPageUrl() }}" rel="prev">Previous</a>
        @endif
        <span class="text-sm font-semibold" aria-live="polite">Page {{ $matches->currentPage() }} of {{ $matches->lastPage() }}</span>
        @if ($matches->hasMorePages())
            <a class="btn btn-primary btn-sm" href="{{ $matches->nextPageUrl() }}" rel="next">Next</a>
        @else
            <button type="button" class="btn btn-primary btn-sm opacity-50 cursor-not-allowed" disabled>Next</button>
        @endif
    </div>
</nav>

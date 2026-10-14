@if($paginator->previousPageUrl() || $paginator->nextPageUrl())
    <nav class="pager" aria-label="Pagination">
        @if($paginator->previousPageUrl())
            <a class="outline" href="{{ $paginator->previousPageUrl() }}">← Newer</a>
        @else
            <span class="outline disabled">← Newer</span>
        @endif
        <span class="muted">Page {{ $paginator->currentPage() }}</span>
        @if($paginator->nextPageUrl())
            <a class="outline" href="{{ $paginator->nextPageUrl() }}">Older →</a>
        @else
            <span class="outline disabled">Older →</span>
        @endif
    </nav>
@endif

@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}">
        <div class="grid grid-cols-2 gap-3 sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="button-secondary cursor-not-allowed opacity-50" aria-disabled="true">{{ __('pagination.previous') }}</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="button-secondary">{{ __('pagination.previous') }}</a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="button-secondary">{{ __('pagination.next') }}</a>
            @else
                <span class="button-secondary cursor-not-allowed opacity-50" aria-disabled="true">{{ __('pagination.next') }}</span>
            @endif
        </div>

        <div class="hidden items-center justify-between gap-4 sm:flex">
            <p class="text-sm text-muted">
                {{ __('Showing') }}
                <strong class="font-semibold text-ink">{{ $paginator->firstItem() ?? 0 }}</strong>
                {{ __('to') }}
                <strong class="font-semibold text-ink">{{ $paginator->lastItem() ?? 0 }}</strong>
                {{ __('of') }}
                <strong class="font-semibold text-ink">{{ $paginator->total() }}</strong>
                {{ __('results') }}
            </p>

            <div class="flex items-center gap-1">
                @if ($paginator->onFirstPage())
                    <span class="pagination-button cursor-not-allowed opacity-50" aria-disabled="true" aria-label="{{ __('pagination.previous') }}">‹</span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="pagination-button" aria-label="{{ __('pagination.previous') }}">‹</a>
                @endif

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="pagination-button cursor-default" aria-disabled="true">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="pagination-button border-primary bg-primary text-white" aria-current="page">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="pagination-button" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="pagination-button" aria-label="{{ __('pagination.next') }}">›</a>
                @else
                    <span class="pagination-button cursor-not-allowed opacity-50" aria-disabled="true" aria-label="{{ __('pagination.next') }}">›</span>
                @endif
            </div>
        </div>
    </nav>
@endif

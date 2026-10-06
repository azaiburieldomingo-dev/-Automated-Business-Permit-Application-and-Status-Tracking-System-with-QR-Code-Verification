@props(['paginator'])

@if ($paginator->hasPages())
    <ul class="pagination">
        {{-- Previous --}}
        @if ($paginator->onFirstPage())
            <li><span class="muted">&laquo;</span></li>
        @else
            <li><a href="{{ $paginator->appends(request()->except('page'))->previousPageUrl() }}" rel="prev">&laquo;</a></li>
        @endif

        {{-- Page numbers --}}
        @foreach ($paginator->getUrlRange(1, $paginator->lastPage()) as $page => $url)
            @if ($page == $paginator->currentPage())
                <li class="active"><span>{{ $page }}</span></li>
            @else
                <li><a href="{{ $paginator->appends(request()->except('page'))->url($page) }}">{{ $page }}</a></li>
            @endif
        @endforeach

        {{-- Next --}}
        @if ($paginator->hasMorePages())
            <li><a href="{{ $paginator->appends(request()->except('page'))->nextPageUrl() }}" rel="next">&raquo;</a></li>
        @else
            <li><span class="muted">&raquo;</span></li>
        @endif
    </ul>
@endif

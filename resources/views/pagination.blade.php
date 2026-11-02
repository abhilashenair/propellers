@if($paginator->hasPages())
<nav class="pager" aria-label="Pagination">
@if($paginator->onFirstPage())<span aria-disabled="true">← Previous</span>@else<a href="{{ $paginator->previousPageUrl() }}" rel="prev">← Previous</a>@endif
@foreach($elements as $element)
@if(is_string($element))<span>{{ $element }}</span>@else
@foreach($element as $page=>$url)@if($page===$paginator->currentPage())<span class="current-page" aria-current="page">{{ $page }}</span>@else<a href="{{ $url }}" aria-label="Page {{ $page }}">{{ $page }}</a>@endif @endforeach
@endif
@endforeach
@if($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}" rel="next">Next →</a>@else<span aria-disabled="true">Next →</span>@endif
</nav>
@endif

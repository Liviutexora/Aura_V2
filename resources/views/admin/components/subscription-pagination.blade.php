@props(['items'])
<div class="pagination">
    <span>{{ $items->total() }} rezultate · Pagina {{ $items->currentPage() }} / {{ $items->lastPage() }}</span>
    <form method="GET" action="{{ url()->current() }}">
        @foreach(request()->except($items->getPageName()) as $key => $value)
            @if(is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
        @endforeach
        <button name="{{ $items->getPageName() }}" value="{{ $items->currentPage() - 1 }}" @disabled($items->onFirstPage())>‹ Înapoi</button>
        <button name="{{ $items->getPageName() }}" value="{{ $items->currentPage() + 1 }}" @disabled(!$items->hasMorePages())>Înainte ›</button>
    </form>
</div>

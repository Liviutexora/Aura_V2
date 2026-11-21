@if ($errors->any())
    <div class="notice" role="alert">
        @foreach ($errors->all() as $error)
            <p>{{ $error }}</p>
        @endforeach
    </div>
@elseif (session('status'))
    <p class="notice" role="status">{{ session('status') }}</p>
@endif

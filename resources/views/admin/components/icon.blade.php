@props(['name' => 'gem'])
@php($paths = $demoData['iconPaths'] ?? json_decode(file_get_contents(resource_path('data/admin-demo.json')), true)['iconPaths'])
<svg class="admin-svg" viewBox="0 0 24 24" aria-hidden="true">@if(isset($paths[$name]))<path d="{{ $paths[$name] }}"></path>@else<use href="{{ asset('assets/admin/icons.svg') }}#{{ $name }}"></use>@endif</svg>
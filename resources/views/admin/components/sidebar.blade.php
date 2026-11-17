<aside class="admin-side" aria-label="Navigare administrare">
@foreach($demoData['nav'] as [$group, $items])
<div class="side-group">{{ $group }}</div>
@foreach($items as [$key, $label, $icon])
<a class="side-link {{ ($demoPage === $key || str_starts_with($demoPage, $key.'/')) ? 'active' : '' }}" href="{{ $key === 'users' ? route('aura.admin.users') : url('/admin').'/#/'.$key }}" title="{{ $label }}"><x-aura-admin::icon :name="$icon" /><span class="side-label">{{ $label }}</span></a>
@endforeach
@endforeach
<button class="side-link" onclick="state.collapsed=!state.collapsed;save();document.body.classList.toggle('collapsed',state.collapsed)"><x-aura-admin::icon name="menu" /><span class="side-label">Restrânge meniul</span></button>
<div class="side-art"><h2>Aura</h2><p>Oportunități · Recomandări<br>Recompense · Ecosistem</p><a class="demo" href="{{ url('/') }}/#/dashboard">VEZI INTERFAȚA USER</a></div></aside>

@props(['status' => 'Demo'])
@php
$variant = preg_match('/așteptare|Ciornă|Pauză|Solicitat|De verificat|Programat/u', $status) ? 'pending' : (preg_match('/Eroare|Suspend|Respins|Anulat|Reversat|eșuat|Inactiv/u', $status) ? 'bad' : '');
@endphp
<span class="status {{ $variant }}">{{ $status }}</span>
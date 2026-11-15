@props(['value' => 0])
<div class="progress" role="progressbar" aria-valuenow="{{ $value }}" aria-valuemin="0" aria-valuemax="100"><i style="width: {{ max(0, min(100, $value)) }}%"></i></div>
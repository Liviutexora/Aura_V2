<form method="POST" enctype="multipart/form-data" action="{{ route('aura.missions.submit',$mission) }}">@csrf
<input type="hidden" name="request_key" value="{{ Str::uuid() }}">@if($step)<input type="hidden" name="step_id" value="{{ $step->id }}">@endif
<label>Declarație sau referință<textarea name="evidence_text" maxlength="5000"></textarea></label>
@if($method === 'document_upload')<label>Document justificativ<input type="file" name="evidence" accept=".pdf,.jpg,.jpeg,.png" required></label>@endif
@if($step?->confirms_receipt)@include('missions.payment',['required'=>false,'currency'=>$latest?->earning?->currency ?? $project->offer_snapshot['reward_currency'] ?? 'RON','receivedAmount'=>$latest?->earning ? number_format($latest->earning->amount_minor/100,2,'.','') : ''])@endif
<button class="btn secondary">Trimite spre verificare</button></form>

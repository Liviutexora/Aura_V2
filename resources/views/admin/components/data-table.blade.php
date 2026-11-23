@props(['entity', 'columns', 'rows', 'labels', 'schemas' => []])
<div class="table-wrap"><table class="admin-table"><thead><tr>
@foreach($columns as $column)<th scope="col"><button onclick="tableFilter('sort','{{ $column }}')">{{ $labels[$column] }}</button></th>@endforeach
<th>Acțiuni</th></tr></thead><tbody id="table-body">
@foreach(array_slice($rows, 0, 6) as $row)<tr>
@foreach($columns as $column)
<td>@if($column === 'status')<x-aura-admin::status-badge :status="$row[$column]" />
@elseif(in_array($column, ['amount', 'earnings', 'price'], true))<b class="{{ $column === 'amount' ? 'gold' : '' }}">{{ $column === 'amount' && ($row['rewardType'] ?? '') === 'Procentuală' ? $row[$column].'%' : number_format((float) $row[$column], 0, ',', '.').' lei' }}</b>
@elseif(in_array($column, ['name', 'title'], true))<b>{{ $row[$column] }}</b>@if(!empty($row['email']))<br><small>{{ $row['email'] }}</small>@endif
@else{{ $row[$column] ?? '' }}@endif</td>
@endforeach
<td><div class="actions"><a class="icon-button" href="{{ route('aura.admin.record', ['entity' => $entity, 'recordId' => $row['id']]) }}" aria-label="Detalii {{ $row['name'] ?? $row['title'] ?? $row['id'] }}"><x-aura-admin::icon name="eye" /></a>
@if(isset($schemas[$entity]))<a class="icon-button" href="{{ route('aura.admin.record', ['entity' => $entity, 'recordId' => $row['id'], 'mode' => 'edit']) }}" aria-label="Editează {{ $row['name'] ?? $row['title'] ?? $row['id'] }}"><x-aura-admin::icon name="edit" /></a>@endif
@if(in_array($entity, ['commissions', 'payouts', 'verification'], true))<button class="icon-button" aria-label="Revizuiește {{ $row['id'] }}" onclick="review('{{ $entity }}','{{ $row['id'] }}')"><x-aura-admin::icon name="check" /></button>@endif
</div></td></tr>
@endforeach
</tbody></table></div>

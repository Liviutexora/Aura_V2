@php
    [$label,$rules] = $definition;
    $name = isset($slotIndex) ? 'steps['.$slotIndex.']['.$key.']' : $key;
    $oldKey = isset($slotIndex) ? 'steps.'.$slotIndex.'.'.$key : $key;
    $value = old($oldKey, $values[$key] ?? null);
    $required = str_starts_with($rules, 'required|');
    $isDate = str_contains($rules,'date_format:');
    $multiline = in_array($key, ['summary','terms','conditions_text','instructions','description','eligibility_text','required_action'],true);
    $fieldId = isset($slotIndex) ? 'step-'.$slotIndex.'-'.$key : 'field-'.$key;
    $options = $choices[$key] ?? null;
    if (preg_match('/(?:^|\\|)in:([^|]+)/',$rules,$match)) {
        $options = collect(explode(',',$match[1]))->mapWithKeys(fn($code)=>[$code=>config('project_labels.'.$code,$code)]);
    }
@endphp
@if($key==='id')<input type="hidden" name="{{ $name }}" value="{{ $value }}">
@elseif($rules==='boolean')<label class="field"><input type="hidden" name="{{ $name }}" value="0"><span><input type="checkbox" name="{{ $name }}" value="1" @checked($value)> {{ $label }}</span></label>
@else
<div @class(['full'=>$multiline])><label for="{{ $fieldId }}">{{ $label }}{{ $required ? ' *' : '' }}</label>
@if($options !== null)<select id="{{ $fieldId }}" name="{{ $name }}" @required($required)>
@if(!$required || isset($choices[$key]))<option value="">—</option>@endif
@foreach($options as $code=>$caption)<option value="{{ $code }}" @selected((string)$code===(string)$value)>{{ $caption }}</option>@endforeach</select>
@elseif($multiline)
<textarea id="{{ $fieldId }}" name="{{ $name }}" @required($required)>{{ $value }}</textarea>
@else<input id="{{ $fieldId }}" name="{{ $name }}" type="{{ $isDate ? 'date' : (str_contains($rules,'integer') ? 'number' : (str_contains($rules,'url:') ? 'url' : 'text')) }}" value="{{ $isDate && $value ? substr($value,0,10) : $value }}" @required($required)>@endif
</div>@endif

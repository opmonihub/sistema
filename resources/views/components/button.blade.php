@php
$variant = $variant ?? 'primary';
$size = $size ?? null;
$block = !empty($block) ? 'btn-block' : '';
$flat = array_key_exists('flat', get_defined_vars()) ? ($flat ? 'btn-flat' : '') : 'btn-flat';
$pull = !empty($pull) ? $pull : '';
$extra = $class ?? '';
$cls = trim('btn btn-' . $variant . ' ' . ($size ? 'btn-' . $size : '') . ' ' . $block . ' ' . $flat . ' ' . $pull . ' ' . $extra);
@endphp
@if(!empty($href))
<a href="{{ $href }}" @if(!empty($id)) id="{{ $id }}" @endif class="{{ $cls }}" @if(!empty($target)) target="{{ $target }}" @endif>{{ $slot }}</a>
@else
<button type="{{ $type ?? 'button' }}" @if(!empty($id)) id="{{ $id }}" @endif class="{{ $cls }}" @if(!empty($disabled)) disabled @endif>{{ $slot }}</button>
@endif

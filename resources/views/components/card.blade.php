<div class="box {{ $class ?? 'box-primary' }} card-fiscal" @if(!empty($id)) id="{{ $id }}" @endif>
@if(!empty($title) || !empty($tool) || !empty($header))
<div class="box-header with-border">
@if(!empty($header))
{!! $header !!}
@else
{!! $icon ?? '' !!}
<h3 class="box-title">{{ $title ?? '' }}</h3>
{!! $tool ?? '' !!}
@endif
</div>
@endif
<div class="box-body">
{{ $slot }}
</div>
</div>

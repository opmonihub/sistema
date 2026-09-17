@extends('layouts.app')
@section('title', 'Difal')

@section('content')


<!-- Content Header (Page header) -->
<section class="content-header">
    
</section>
@component('components.widget', ['class' => 'box-primary'])

{!! Form::open(['url' => action('DifalController@store'), 'method' => 'post', 'id' => 'difal_form', 'files' => true ]) !!}
    <h4>Difal
        <small>@if(isset($item)) Atualizar @else Inserir @endif {{(isset($item) ? $item->uf : '')}}</small>
    </h4>
<div class="row">

    <input type="hidden" name="difal_id" value="@if(isset($item)) {{$item->id}} @else 0 @endif">
    @if(isset($estados))
    <div class="col-md-2">
        <div class="form-group">

            {!! Form::label('uf', 'UF' . ':') !!}
            {!! Form::select('uf', $estados, '', ['class' => 'form-control select2', 'required']); !!}
        </div>
    </div>
    @endif

    <div class="col-sm-2">
        <div class="form-group">
            {!! Form::label('cfop', 'CFOP' . ':*') !!}
            {!! Form::text('cfop', (isset($item) ? $item->cfop : ''), ['class' => 'form-control', 'required', 
            'placeholder' => 'CFOP', 'data-mask' => '0000']); !!}
        </div>
    </div>

    <div class="col-sm-2">
        <div class="form-group">
            {!! Form::label('pICMSUFDest', '% ICMS UF Destino' . ':*') !!}
            {!! Form::text('pICMSUFDest', (isset($item) ? $item->pICMSUFDest : ''), ['class' => 'form-control', 'required', 
            'placeholder' => '% ICMS UF Destino', 'data-mask' => '000,00', 'data-mask-reverse' => 'true']); !!}
        </div>
    </div>

    <div class="col-sm-2">
        <div class="form-group">
            {!! Form::label('pICMSInter', '% ICMS Interno' . ':*') !!}
            {!! Form::text('pICMSInter', (isset($item) ? $item->pICMSInter : ''), ['class' => 'form-control', 'required', 
            'placeholder' => '% ICMS UF Destino', 'data-mask' => '000,00', 'data-mask-reverse' => 'true']); !!}
        </div>
    </div>

    <div class="col-sm-2">
        <div class="form-group">
            {!! Form::label('pICMSInterPart', '% ICMS Interestadual UF' . ':*') !!}
            {!! Form::text('pICMSInterPart', (isset($item) ? $item->pICMSInterPart : ''), ['class' => 'form-control', 'required', 
            'placeholder' => '% ICMS Interestadual UF', 'data-mask' => '000,00', 'data-mask-reverse' => 'true']); !!}
        </div>
    </div>

    <div class="col-sm-2">
        <div class="form-group">
            {!! Form::label('pFCPUFDest', '% Fundo Combate a Pobreza' . ':*') !!}
            {!! Form::text('pFCPUFDest', (isset($item) ? $item->pFCPUFDest : ''), ['class' => 'form-control', 'required', 
            'placeholder' => '% Fundo Combate a Pobreza', 'data-mask' => '000,00', 'data-mask-reverse' => 'true']); !!}
        </div>
    </div>

    <div class="col-md-12">

        <div style="display: none" class="loader"></div>

        <button type="submit" class="btn btn-primary pull-right" id="submit_user_button">@if(isset($ibpt)) Editar @else Salvar @endif</button>
    </div>
</div>



{!! Form::close() !!}


@endcomponent
<!-- Main content -->
<section class="content">


</section>
<!-- /.content -->
@stop
@section('javascript')
<script type="text/javascript">

    $( "#ibpt_form" ).submit(function( event ) {
      $('.loader').css('display', 'block')
  });
</script>
@endsection

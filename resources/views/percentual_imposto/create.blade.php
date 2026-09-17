@extends('layouts.app')
@section('title', 'Percentual Imposto por Estado')

@section('content')


<!-- Content Header (Page header) -->
<section class="content-header">

</section>
@component('components.widget', ['class' => 'box-primary'])

{!! Form::open(['url' => action('PercentualImpostoController@store'), 'method' => 'post', 'id' => 'percentual_form', 'files' => true ]) !!}
<h4>Percentual Imposto por Estado
    <small>@if(isset($item)) Atualizar @else Inserir @endif {{(isset($item) ? $item->uf : '')}}</small>
</h4>
<div class="row">

    <input type="hidden" name="perc_id" value="@if(isset($item)) {{$item->id}} @else 0 @endif">
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
            {!! Form::label('percentual_icms', '% ICMS' . ':*') !!}
            {!! Form::text('percentual_icms', (isset($item) ? $item->percentual_icms : ''), ['class' => 'form-control', 'required', 
            'placeholder' => '% ICMS', 'data-mask' => '000,00', 'data-mask-reverse' => 'true']); !!}
        </div>
    </div>
    <div class="col-sm-2">
        <div class="form-group">
            {!! Form::label('percentual_pis', '% PIS' . ':*') !!}
            {!! Form::text('percentual_pis', (isset($item) ? $item->percentual_pis : ''), ['class' => 'form-control', 'required', 
            'placeholder' => '% PIS', 'data-mask' => '000,00', 'data-mask-reverse' => 'true']); !!}
        </div>
    </div>
    <div class="col-sm-2">
        <div class="form-group">
            {!! Form::label('percentual_cofins', '% COFINS' . ':*') !!}
            {!! Form::text('percentual_cofins', (isset($item) ? $item->percentual_cofins : ''), ['class' => 'form-control', 'required', 
            'placeholder' => '% COFINS', 'data-mask' => '000,00', 'data-mask-reverse' => 'true']); !!}
        </div>
    </div>
    <div class="col-sm-2">
        <div class="form-group">
            {!! Form::label('percentual_ipi', '% IPI' . ':*') !!}
            {!! Form::text('percentual_ipi', (isset($item) ? $item->percentual_ipi : ''), ['class' => 'form-control', 'required', 
            'placeholder' => '% IPI', 'data-mask' => '000,00', 'data-mask-reverse' => 'true']); !!}
        </div>
    </div>

    <div class="col-sm-2">
        <div class="form-group">
            {!! Form::label('percentual_fcp', '% FCP' . ':*') !!}
            {!! Form::text('percentual_fcp', (isset($item) ? $item->percentual_fcp : ''), ['class' => 'form-control', 'required', 
            'placeholder' => '% FCP', 'data-mask' => '000,00', 'data-mask-reverse' => 'true']); !!}
        </div>
    </div>
    <div class="col-sm-2">
        <div class="form-group">
            {!! Form::label('percentual_icms_interno', '% ICMS Interno' . ':*') !!}
            {!! Form::text('percentual_icms_interno', (isset($item) ? $percentual_icms_interno : ''), ['class' => 'form-control', 'required', 
            'placeholder' => '% ICMS Interno', 'data-mask' => '000,00', 'data-mask-reverse' => 'true']); !!}
        </div>
    </div>
    <div class="col-sm-2">
        <div class="form-group">
            {!! Form::label('percentual_red_bc', '% RED. BC' . ':*') !!}
            {!! Form::text('percentual_red_bc', (isset($item) ? $percentual_red_bc : ''), ['class' => 'form-control', 'required', 
            'placeholder' => '% RED. BC', 'data-mask' => '000,00', 'data-mask-reverse' => 'true']); !!}
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

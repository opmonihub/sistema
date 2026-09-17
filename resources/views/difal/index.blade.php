@extends('layouts.app')
@section('title', 'Difal')

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>Difal
        <small>Gerenciar</small>
    </h1>
</section>

<!-- Main content -->
<section class="content">
    @component('components.widget', ['class' => 'box-primary', 'title' => ''])
    @can('user.create')
    @slot('tool')
    <div class="box-tools">
        <a class="btn btn-block btn-primary" 
        href="{{ route('difal.create') }}" >
        <i class="fa fa-plus"></i> @lang( 'messages.add' )</a>
    </div>
    @endslot
    @endcan
    @can('user.view')

    <div class="row">
        @foreach($data as $i)
        <div class="col-md-4">
            @component('components.widget', ['class' => 'box-primary', 'title' => "$i->uf - " . \Carbon\Carbon::parse($i->updated_at)->format('d/m/Y H:i:s') ])

            <div class="card">
                
                <div class="card-body">
                    <h5>CFOP: <strong>{{ $i->cfop }}</strong></h5>
                    <h5>% ICMS UF Destino: <strong>{{ $i->pICMSUFDest }}</strong></h5>
                    <h5>% ICMS Interno: <strong>{{ $i->pICMSInter }}</strong></h5>
                    <h5>% ICMS Interestadual UF: <strong>{{ $i->pICMSInterPart }}</strong></h5>
                    <h5>% Fundo Combate a Pobreza: <strong>{{ $i->pFCPUFDest }}</strong></h5>

                    <form id="veiculos{{$i->id}}" method="POST" action="{{ route('difal.destroy', $i->id) }}">
                        @method('delete')
                        @csrf
                        <a class="btn btn-warning" href="{{ route('difal.edit', [$i->id])}}">
                            <i class="fa fa-edit" aria-hidden="true"></i>
                        </a>

                        <button type="button" class="btn btn-danger btn-delete">
                            <i class="fa fa-trash" aria-hidden="true"></i>

                        </button>
                    </form>

                </div>
            </div>
            @endcomponent

        </div>

        @endforeach
    </div>

    @endcan
    @endcomponent

</section>
<!-- /.content -->
@stop
@section('javascript')

@endsection

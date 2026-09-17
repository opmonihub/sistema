@extends('layouts.app')
@section('title', 'Percentual Imposto Por Estado ' . $item->uf)

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>Percentual Imposto Por Estado <strong>{{$item->uf}}</strong>
        <small>Gerenciar</small>
    </h1>
</section>

<!-- Main content -->
<section class="content">
    @component('components.widget', ['class' => 'box-primary', 'title' => 'Lista de produtos'])
    
    @can('user.view')

    <div class="row">

        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="users_table">
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>%ICMS</th>
                        <th>%PIS</th>
                        <th>%COFINS</th>
                        <th>%IPI</th>
                        <th>%FCP</th>
                        <th>%ICMS Interno</th>
                        <th>%RED. BC</th>
                        <th>Ação</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data as $i)
                    <tr>
                        <td>{{ $i->product->name }}</td>
                        <td>{{ $i->percentual_icms }}</td>
                        <td>{{ $i->percentual_pis }}</td>
                        <td>{{ $i->percentual_cofins }}</td>
                        <td>{{ $i->percentual_ipi }}</td>
                        <td>{{ $i->percentual_fcp }}</td>
                        <td>{{ $i->percentual_icms_interno }}</td>
                        <td>{{ $i->percentual_red_bc }}</td>
                        <td>
                            <form id="percentual-{{$i->id}}" method="POST" action="{{ route('percentual-imposto-estado.destroy-produto', $i->id) }}">
                                @method('delete')
                                @csrf
                                <button type="button" class="btn btn-warning" onclick="openModal('{{$i}}')">
                                    <i class="fa fa-edit" aria-hidden="true"></i>
                                </button>

                                <button type="button" class="btn btn-danger btn-delete">
                                    <i class="fa fa-trash" aria-hidden="true"></i>

                                </button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        
    </div>

    @endcan
    @endcomponent

</section>

<div class="modal fade" id="modal-edit" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content modal-lg">
            {!! Form::open(['url' => action('PercentualImpostoController@updateItem'), 'method' => 'post', 'id' => 'update_item' ]) !!}

            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="myModalLabel">
                    Atualizando Imposto do produto <strong class="produto-nome"></strong>
                </h4>
            </div>

            <div class="modal-body">
                <div class="row">
                    <input type="hidden" id="item_id" name="item_id" value="">
                    <div class="col-md-2">
                        <div class="form-group">
                            {!! Form::label('percentual_icms', '%ICMS:') !!}
                            {!! Form::text('percentual_icms', null, ['class' => 'form-control' ]); !!}
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            {!! Form::label('percentual_pis', '%PIS:') !!}
                            {!! Form::text('percentual_pis', null, ['class' => 'form-control' ]); !!}
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            {!! Form::label('percentual_cofins', '%COFINS:') !!}
                            {!! Form::text('percentual_cofins', null, ['class' => 'form-control' ]); !!}
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            {!! Form::label('percentual_ipi', '%IPI:') !!}
                            {!! Form::text('percentual_ipi', null, ['class' => 'form-control' ]); !!}
                        </div>
                    </div>

                    <div class="col-md-2">
                        <div class="form-group">
                            {!! Form::label('percentual_fcp', '%FCP:') !!}
                            {!! Form::text('percentual_fcp', null, ['class' => 'form-control' ]); !!}
                        </div>
                    </div>

                    <div class="col-md-2">
                        <div class="form-group">
                            {!! Form::label('percentual_icms_interno', '%ICMS Interno:') !!}
                            {!! Form::text('percentual_icms_interno', null, ['class' => 'form-control' ]); !!}
                        </div>
                    </div>

                    <div class="col-md-2">
                        <div class="form-group">
                            {!! Form::label('percentual_red_bc', '%RED. BC:') !!}
                            {!! Form::text('percentual_red_bc', null, ['class' => 'form-control' ]); !!}
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">
                    @lang( 'messages.close' )
                </button>
                <button type="submit" class="btn btn-primary">
                    @lang( 'messages.save' )
                </button>
            </div>

            {!! Form::close() !!}

        </div>
    </div>
</div>
<!-- /.content -->
@stop
@section('javascript')
<script type="text/javascript">
    function openModal(o){
        console.log(o)
        let p = JSON.parse(o)
        $('#modal-edit').modal('show')
        $('.produto-nome').text(p.product.name)
        $('#item_id').val(p.id)
        $('#percentual_icms').val(p.percentual_icms)
        $('#percentual_pis').val(p.percentual_pis)
        $('#percentual_cofins').val(p.percentual_cofins)
        $('#percentual_ipi').val(p.percentual_ipi)
        $('#percentual_fcp').val(p.percentual_fcp)
        $('#percentual_icms_interno').val(p.percentual_icms_interno)
        $('#percentual_red_bc').val(p.percentual_red_bc)

    }
</script>
@endsection

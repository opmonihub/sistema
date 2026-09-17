@extends('layouts.app')
@section('title', 'Percentual Imposto Por Estado')

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>Percentual Imposto Por Estado
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
        href="{{ route('percentual-imposto-estado.create') }}" >
        <i class="fa fa-plus"></i> @lang( 'messages.add' )</a>
    </div>
    @endslot
    @endcan
    @can('user.view')

    <div class="row">

        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="users_table">
                <thead>
                    <tr>
                        <th>UF</th>
                        <th>Ação</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data as $i)
                    <tr>
                        <td>{{ $i->uf }}</td>
                        <td>
                            <form id="percentual-{{$i->id}}" method="POST" action="{{ route('percentual-imposto-estado.destroy', $i->id) }}">
                                @method('delete')
                                @csrf
                                <a class="btn btn-info" href="{{ route('percentual-imposto-estado.show', [$i->id])}}">
                                    <i class="fa fa-eye" aria-hidden="true"></i>
                                </a>

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
<!-- /.content -->
@stop
@section('javascript')

@endsection

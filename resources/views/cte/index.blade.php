@extends('layouts.app')
@section('title', 'Lista de CTe')

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>
        <small>Gerencia CTe</small>
    </h1>
    <!-- <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Level</a></li>
        <li class="active">Here</li>
    </ol> -->
</section>

<!-- Main content -->
<section class="content">
    
    @component('components.widget', ['class' => 'box-primary', 'title' => 'Todos os Documentos'])
    @can('user.create')
    @slot('tool')
    <div class="box-tools">
        <a class="btn btn-block btn-primary" 
        href="{{ route('cte.create') }}" >
        <i class="fa fa-plus"></i> @lang( 'messages.add' )</a>
    </div>
    @endslot
    @endcan
    @can('user.view')
    <div class="table-responsive">
        <table class="table table-bordered table-striped" id="users_table">
            <thead>
                <tr>
                    <th>Número</th>
                    <th>Remetente</th>
                    <th>Destinatário</th>
                    <th>Valor de transporte</th>
                    <th>Valor a receber</th>
                    <th>Valor carga</th>
                    <th>Produto predominante</th>
                    <th>Data prevista de entrega</th>
                    <th>Estado</th>
                    <th>Data de Registro</th>
                    <th>Documentos</th>
                    <th>Cidade Destino</th>
                    <th>Peso KG</th>
                    <th>Tomador do Serviço</th>
                    <th class="cte-action">Ação</th>
                </tr>
            </thead>
        </table>
    </div>
    @endcan
    @endcomponent

    <div class="modal fade user_modal" tabindex="-1" role="dialog" 
    aria-labelledby="gridSystemModalLabel">
</div>

    @can('user.view')
    <div class="modal fade" id="cte_documents_modal" tabindex="-1" role="dialog"
        aria-labelledby="cte_documents_modal_title">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h4 class="modal-title" id="cte_documents_modal_title">Documentos do CT-e</h4>
                </div>
                <div class="modal-body" style="overflow-wrap: anywhere;"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>
    @endcan

</section>
<!-- /.content -->
@stop
@section('javascript')
<script src="{{ asset('js/cte-list.js?v=' . $asset_v) }}"></script>
<script type="text/javascript">
    $(document).ready(function () {
        var tableElement = document.getElementById('users_table');
        if (!tableElement) {
            return;
        }

        var users_table = $(tableElement).DataTable({
            processing: true,
            serverSide: true,
            ajax: '/cte',
            columns: CteListFrontend.buildColumns(),
            buttons: CteListFrontend.buildButtons($.fn.dataTable.defaults.buttons)
        });

        var documentsModal = document.getElementById('cte_documents_modal');
        var restoreDocumentsFocus = CteListFrontend.bindDocumentsModal(tableElement, users_table, documentsModal, function () {
            $(documentsModal).modal('show');
        });
        $(documentsModal).on('hidden.bs.modal', restoreDocumentsFocus);
    });
</script>
@endsection

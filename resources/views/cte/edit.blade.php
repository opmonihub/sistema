@extends('layouts.app')

@section('title', 'Editar CTe')

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
  <h1>Editar </h1>  


</section>

<!-- Main content -->
<section class="content">


  {!! Form::open(['url' => action('CteController@update', [$cte->id]), 'method' => 'put', 'id' => 'cte_update_form' ]) !!}
  <div class="row">
    <div class="col-md-12">
      @component('components.widget')

      
      <div class="clearfix"></div>

      <div class="col-md-2">
        <div class="form-group">
          <h4>Ultima CTe: <strong>{{$lastCte}}</strong></h4>

        </div>
      </div>

      <input type="hidden" id="clientesAux" value="{{json_encode($clientesAux)}}" name="">

      <div class="clearfix"></div>

      <input type="hidden" value="{{$cte->id}}" name="id">

      @if(is_null($default_location))

      <div class="col-md-4">
        <br>
        <div class="form-group" style="margin-top: 8px;">
          <div class="input-group">
            <span class="input-group-addon">
              <i class="fa fa-map-marker"></i>
            </span>
            {!! Form::select('select_location_id', $business_locations, $cte->location_id, ['class' => 'form-control input-sm', 
            'placeholder' => __('lang_v1.select_location'),
            'id' => 'select_location_id', 
            'required', 'autofocus'], $bl_attributes); !!}
            <span class="input-group-addon">
              @show_tooltip('Local da CTe')
            </span> 
          </div>
        </div>

      </div>
      @endif
      <div class="col-md-4">
        <div class="form-group">
          {!! Form::label('natureza_id', 'Natureza de operação' . ':*') !!}
          {!! Form::select('natureza_id', $naturezas, $cte->natureza_id, ['class' => 'form-control select2', 'id' => 'contact_type', 'required']); !!}
        </div>
      </div>

      <div class="col-md-2">
        <div class="form-group">
          {!! Form::label('globalizado', 'Tipo globalizado' . ':*') !!}
          {!! Form::select('globalizado', ['0' => 'Não', 1 => 'Sim'], $cte->globalizado, ['class' => 'form-control select2', 'id' => 'contact_type', 'required']); !!}
        </div>
      </div>

      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('cst', 'CST' . ':*') !!}
          {!! Form::select('cst', App\Models\Cte::getCsts(), $cte->cst, ['class' => 'form-control select2', 'id' => 'contact_type', 'required']); !!}
        </div>
      </div>

      <div class="col-md-2">
        <div class="form-group">
          {!! Form::label('perc_icms', '%ICMS' . ':*') !!}
          {!! Form::text('perc_icms', $cte->perc_icms, ['class' => 'form-control type-ref', 'placeholder' => '%ICMS', 'data-mask="00,00", data-mask-reverse="true"' ]); !!}
        </div>
      </div>

      <div class="row">
        <div class="col-md-12">
          <div class="box box-primary" id="bloco-finalidade">
            <div class="box-header with-border">
              <h4 class="box-title text-primary">FINALIDADE DO CT-E</h4>
              <p class="text-muted">Normal emite um CT-e novo. Substituto corrige um CT-e autorizado. Complemento complementa valores de um CT-e autorizado.</p>
            </div>
            <div class="box-body">
              <div class="row">
                <div class="col-md-4">
                  <div class="form-group">
                    {!! Form::label('tp_cte', 'Tipo do CT-e:') !!}
                    {!! Form::select('tp_cte', ['0' => 'Normal', '3' => 'Substituto', '1' => 'Complemento'], $cte->tp_cte ?? 0, ['class' => 'form-control select2', 'id' => 'tp_cte']); !!}
                  </div>
                </div>
                <div class="col-md-8" id="bloco-vinculo" style="display: {{ in_array((int)($cte->tp_cte ?? 0), [1, 3]) ? 'block' : 'none' }};">
                  <div class="form-group">
                    {!! Form::label('busca_original', 'Buscar CT-e original na lista:') !!}
                    {!! Form::text('busca_original', null, ['class' => 'form-control', 'placeholder' => 'Digite o número ou a chave do CT-e autorizado', 'id' => 'busca_original', 'autocomplete' => 'off']); !!}
                  </div>
                  <div class="form-group">
                    {!! Form::label('cte_original_id', 'CT-e original:') !!}
                    <select class="form-control select2" id="cte_original_id" name="cte_original_id">
                      <option value="">Selecione o CT-e original</option>
                      @if($cte->original)
                      <option value="{{ $cte->original->id }}" data-chave="{{ $cte->original->chave }}" selected>Nº {{ $cte->original->cte_numero }} - {{ $cte->original->destinatario ? $cte->original->destinatario->name : '' }} - {{ $cte->original->created_at ? $cte->original->created_at->format('d/m/Y') : '' }}</option>
                      @endif
                    </select>
                  </div>
                  <div class="form-group" id="grupo-chave-origem">
                    {!! Form::label('chave_origem_manual', ((int)($cte->tp_cte ?? 0) == 1 ? 'Chave do CT-e complementado (44 dígitos):' : 'Chave do CT-e original (44 dígitos):'), ['id' => 'label-chave-origem']) !!}
                    {!! Form::text((int)($cte->tp_cte ?? 0) == 1 ? 'chave_complementada' : 'chave_original', $cte->chave_original ?? $cte->chave_complementada, ['class' => 'form-control', 'placeholder' => 'Chave de acesso com 44 dígitos', 'id' => 'chave_origem_manual', 'maxlength' => '44', 'data-mask' => '00000000000000000000000000000000000000000000', 'aria-describedby' => 'ajuda-chave-origem']); !!}
                    <p class="help-block" id="ajuda-chave-origem">44 dígitos numéricos, mesmo CNPJ emitente, CT-e autorizado. <span id="contador-chave">0/44 dígitos</span></p>
                  </div>
                  <div class="checkbox" id="bloco-altera-toma" style="display: {{ (int)($cte->tp_cte ?? 0) == 3 ? 'block' : 'none' }};">
                    <label for="ind_altera_toma">
                      {!! Form::checkbox('ind_altera_toma', '1', !empty($cte->ind_altera_toma), ['id' => 'ind_altera_toma']) !!}
                      Mudou quem paga o frete? (alteração de tomador)
                    </label>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="clearfix"></div>

      <div class="col-md-6">
        <div class="form-group">
          {!! Form::label('remetente_id', 'Remetente' . ':*') !!}
          {!! Form::select('remetente_id', $clientes, $cte->remetente_id, ['class' => 'form-control select2', 'id' => 'remetente_id', 'required', 'placeholder' => 'Selecione o remetente']); !!}
        </div>

        <div class="box" id="box-remetente" style="display: none">
          <div class="box-body">
            <h5>Nome: <strong id="remetente-nome"></strong></h5>
            <h5>CNPJ: <strong id="remetente-cnpj"></strong></h5>
            <h5>IE: <strong id="remetente-ie"></strong></h5>
            <h5>Endereço: <strong id="remetente-endereco"></strong></h5>
            <h5>Cidade: <strong id="remetente-cidade"></strong></h5>
          </div>
        </div>
      </div>

      <div class="col-md-6">
        <div class="form-group">
          {!! Form::label('destinatario_id', 'Destinatário' . ':*') !!}
          {!! Form::select('destinatario_id', $clientes, $cte->destinatario_id, ['class' => 'form-control select2', 'id' => 'destinatario_id', 'required', 'placeholder' => 'Selecione o destinatário']); !!}
        </div>
        <div class="box" id="box-destinatario" style="display: none">
          <div class="box-body">
            <h5>Nome: <strong id="destinatario-nome"></strong></h5>
            <h5>CNPJ: <strong id="destinatario-cnpj"></strong></h5>
            <h5>IE: <strong id="destinatario-ie"></strong></h5>
            <h5>Endereço: <strong id="destinatario-endereco"></strong></h5>
            <h5>Cidade: <strong id="destinatario-cidade"></strong></h5>

          </div>
        </div>

      </div>

      <div class="col-md-6">
        <div class="form-group">
          {!! Form::label('recebedor_id', 'Recebedor' . ':') !!}
          {!! Form::select('recebedor_id', $clientes, $cte->recebedor_id, ['class' => 'form-control select2', 'id' => 'recebedor_id', 'placeholder' => 'Selecione o recebedor']); !!}
        </div>
        <div class="box" id="box-recebedor" style="display: none">
          <div class="box-body">
            <h5>Nome: <strong id="recebedor-nome"></strong></h5>
            <h5>CNPJ: <strong id="recebedor-cnpj"></strong></h5>
            <h5>IE: <strong id="recebedor-ie"></strong></h5>
            <h5>Endereço: <strong id="recebedor-endereco"></strong></h5>
            <h5>Cidade: <strong id="recebedor-cidade"></strong></h5>

          </div>
        </div>
      </div>

      <div class="col-md-6">
        <div class="form-group">
          {!! Form::label('expedidor_id', 'Expedidor' . ':') !!}
          {!! Form::select('expedidor_id', $clientes, $cte->expedidor_id, ['class' => 'form-control select2', 'id' => 'expedidor_id', 'placeholder' => 'Selecione o expedidor']); !!}
        </div>
        <div class="box" id="box-expedidor" style="display: none">
          <div class="box-body">
            <h5>Nome: <strong id="expedidor-nome"></strong></h5>
            <h5>CNPJ: <strong id="expedidor-cnpj"></strong></h5>
            <h5>IE: <strong id="expedidor-ie"></strong></h5>
            <h5>Endereço: <strong id="expedidor-endereco"></strong></h5>
            <h5>Cidade: <strong id="expedidor-cidade"></strong></h5>

          </div>
        </div>
      </div>


      <div class="clearfix"></div>

      <div class="row" id="bloco-documentos-fiscais">
        <div class="col-md-12">
          <div class="nav-tabs-custom">
            <ul class="nav nav-tabs nav-justified">
              <li class="active">
                <a href="#ledger_tab" data-toggle="tab" aria-expanded="true">NF-e</a>
              </li>
              <li class="''">
                <a href="#documents_and_notes_tab" data-toggle="tab" aria-expanded="false">Outros</a>
              </li>

            </ul>

            <div class="tab-content">
              <div class="tab-pane active" id="ledger_tab">
                <div class="row">
                  <div class="col-md-12">
                    <div class="col-md-9">
                      <div class="form-group">
                        <label for="ledger_date_range">Chave NFe:</label>
                        <input placeholder="Chave NFe" class="form-control type-ref" data-mask="00000000000000000000000000000000000000000000" name="chave_nfe" type="text" id="chave_nfe">
                      </div>
                    </div>
                    <div class="col-md-1"><br>
                      <a id="addChave" class="btn btn-success" style="margin-top: 4px;">
                        <i class="fa fa-plus"></i>
                        Adicionar
                      </a>
                    </div>

                  </div>

                  <div class="col-md-12">
                    <div class="col-md-9">
                      <div id="chaves_list">

                      </div>
                      
                    </div>
                  </div>
                  <div id="contact_ledger_div"></div>
                </div>                    
              </div>

              <input style="visibility: hidden;" id="chaves_nfe" name="chaves_nfe" value="{{$cte->chave_nfe}}">

              <div class="tab-pane ''" id="documents_and_notes_tab">
                <!-- model id like project_id, user_id -->
                <!-- model name like App\User -->

                <?php 
                $tipos = [
                  '00' => 'Declaração',
                  '10' => 'Dutoviário',
                  '59' => 'CF-e SAT',
                  '65' => 'NFC-e',
                  '99' => 'Outros'
                ];
                ?>
                <div class="row">
                  <div class="col-md-12">

                    <div class="col-md-3">
                      <div class="form-group">
                        {!! Form::label('tpDoc', 'Tipo documento' . ':*') !!}
                        {!! Form::select('tpDoc', $tipos, $cte->tpDoc, ['class' => 'form-control', 'id' => 'tpDoc', 'required']); !!}
                      </div>
                    </div>

                    <div class="col-md-4">
                      <div class="form-group">
                        {!! Form::label('descOutros', 'Descrição do Doc.' . ':*') !!}
                        {!! Form::text('descOutros', $cte->descOutros, ['class' => 'form-control type-ref', 'placeholder' => 'Descrição do Doc.' ]); !!}
                      </div>
                    </div>

                    <div class="col-md-2">
                      <div class="form-group">
                        {!! Form::label('nDoc', 'Numero do Doc.' . ':*') !!}
                        {!! Form::text('nDoc', $cte->nDoc, ['class' => 'form-control type-ref', 'placeholder' => 'Numero do Doc.' ]); !!}
                      </div>
                    </div>

                    <div class="col-md-2">
                      <div class="form-group">
                        {!! Form::label('vDocFisc', 'Valor do Documento' . ':*') !!}
                        {!! Form::text('vDocFisc', $cte->vDocFisc, ['class' => 'form-control type-ref', 'placeholder' => 'Valor do Documento', 'data-mask="000000,00", data-mask-reverse="true"' ]); !!}
                      </div>
                    </div>

                  </div>
                </div>

              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="clearfix"></div>
      <div id="bloco-carga">
      <div class="col-md-12">
        <h4>INFORMAÇÕES DA CARGA</h4>
      </div>

      <div class="col-md-4">

        <div class="form-group">
          {!! Form::label('veiculo_id', 'Veiculo' . ':*') !!}
          {!! Form::select('veiculo_id', $veiculos, $cte->veiculo_id, ['class' => 'form-control select2', 'id' => 'veiculo_id', 'required', 'placeholder' => 'Veiculo']); !!}
        </div>
      </div>

      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('prod_predominante', 'Produto predominante' . ':*') !!}
          {!! Form::text('prod_predominante', $cte->produto_predominante, ['class' => 'form-control type-ref', 'required', 'placeholder' => 'Produto predominante' ]); !!}
        </div>
      </div>

      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('tomador', 'Tomador' . ':*') !!}
          {!! Form::select('tomador', $tiposTomador, $cte->tomador, ['class' => 'form-control select2', 'id' => 'tomador', 'required']); !!}
        </div>
      </div>

      <div class="col-md-2">
        <div class="form-group">
          {!! Form::label('tipo_servico', 'Tipo de Serviço' . ':*') !!}
          {!! Form::select('tipo_servico', $tiposServico, $cte->tipo_servico ?? '0', ['class' => 'form-control select2', 'id' => 'tipo_servico', 'required']); !!}
        </div>
      </div>

      <div class="clearfix"></div>

      <div class="col-md-6" id="div-tomador-outros" style="display: {{ $cte->tomador == 4 ? 'block' : 'none' }};">
        <div class="form-group">
          {!! Form::label('tomador_outros_id', 'Tomador (Outros)' . ':') !!}
          {!! Form::select('tomador_outros_id', $clientes, $cte->tomador_outros_id, ['class' => 'form-control select2', 'id' => 'tomador_outros_id', 'placeholder' => 'Selecione o tomador']); !!}
        </div>
        <div class="box box-warning" id="box-tomador-outros" style="display: {{ $cte->tomador_outros_id ? 'block' : 'none' }}">
          <div class="box-body">
            <h5>Nome: <strong id="tomador-outros-nome">{{ $cte->tomadorOutros->name ?? '' }}</strong></h5>
            <h5>CNPJ: <strong id="tomador-outros-cnpj">{{ $cte->tomadorOutros->cpf_cnpj ?? '' }}</strong></h5>
            <h5>IE: <strong id="tomador-outros-ie">{{ $cte->tomadorOutros->ie_rg ?? '' }}</strong></h5>
            <h5>Endereço: <strong id="tomador-outros-endereco">{{ $cte->tomadorOutros ? $cte->tomadorOutros->rua . ', ' . $cte->tomadorOutros->numero : '' }}</strong></h5>
            <h5>Cidade: <strong id="tomador-outros-cidade">{{ $cte->tomadorOutros && $cte->tomadorOutros->cidade ? $cte->tomadorOutros->cidade->nome . ' (' . $cte->tomadorOutros->cidade->uf . ')' : '' }}</strong></h5>
          </div>
        </div>
      </div>

      <div class="clearfix"></div>

      <!-- Documentos de Transporte Anterior (para Subcontratação/Redespacho) -->
      <div class="col-md-12" id="div-doc-anterior" style="display: {{ in_array($cte->tipo_servico, ['1', '2', '3']) ? 'block' : 'none' }};">
        <div class="box box-info">
          <div class="box-header with-border">
            <h4 class="box-title text-primary">DOCUMENTOS DE TRANSPORTE ANTERIOR</h4>
            <p class="text-muted">Obrigatório para Subcontratação, Redespacho e Redespacho Intermediário</p>
          </div>
          <div class="box-body">
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  {!! Form::label('chave_cte_anterior', 'Chave do CT-e Anterior (44 dígitos)' . ':') !!}
                  {!! Form::text('chave_cte_anterior', null, ['class' => 'form-control', 'placeholder' => 'Chave do CT-e anterior', 'id' => 'chave_cte_anterior', 'data-mask="00000000000000000000000000000000000000000000"']); !!}
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  {!! Form::label('ie_emitente_anterior', 'IE do Emitente' . ':') !!}
                  {!! Form::text('ie_emitente_anterior', null, ['class' => 'form-control', 'placeholder' => 'Inscrição Estadual', 'id' => 'ie_emitente_anterior']); !!}
                </div>
              </div>
              <div class="col-md-2">
                <div class="form-group">
                  <br>
                  <a id="addDocAnterior" class="btn btn-success" style="margin-top: 3px;">
                    <i class="fa fa-plus"></i> Adicionar
                  </a>
                </div>
              </div>
            </div>
            <input type="hidden" name="documentos_anteriores" id="docs_anteriores" value="{{ json_encode($cte->documentosAnteriores ?? []) }}">
            <div class="table-responsive">
              <table class="table table-bordered table-striped" id="tbl-docs-anteriores">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Chave CT-e</th>
                    <th>CNPJ Emitente</th>
                    <th>IE Emitente</th>
                    <th>UF</th>
                    <th>Ação</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div class="clearfix"></div>

      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('valor_carga', 'Valor da Carga' . ':*') !!}
          {!! Form::text('valor_carga', $cte->valor_carga, ['class' => 'form-control', 'required type-ref', 'placeholder' => 'Valor da Carga', 'data-mask="000000,00", data-mask-reverse="true"' ]); !!}
        </div>
      </div>

      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('modal_transp', 'Modelo de Transporte' . ':*') !!}
          {!! Form::select('modal_transp', $modals, $cte->modal_transp, ['class' => 'form-control select2', 'id' => 'modal_transp', 'required']); !!}
        </div>
      </div>

      <div class="col-md-12">
        <h5 class="text-primary">INFORMAÇÕES DE QUANTIDADE</h5>
      </div>

      <div class="col-md-2">
        <div class="form-group">
          {!! Form::label('unidade_medida', 'Unidade medida' . ':*') !!}
          {!! Form::select('unidade_medida', $unidadesMedida, '', ['class' => 'form-control select2', 'id' => 'unidade_medida', 'required']); !!}
        </div>
      </div>

      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('tipo_medida', 'Tipo de medida' . ':*') !!}
          {!! Form::select('tipo_medida', $tiposMedida, '', ['class' => 'form-control select2', 'id' => 'tipo_medida', 'required']); !!}
        </div>
      </div>

      <div class="col-md-2">
        <div class="form-group">
          {!! Form::label('quantidade_carga', 'Quantidade' . ':*') !!}
          {!! Form::text('quantidade_carga', null, ['class' => 'form-control type-ref', 'placeholder' => 'Quantidade',  'data-mask="000000.000", data-mask-reverse="true"' ]); !!}
        </div>
      </div>

      <div class="col-md-2">
        <div class="form-group">
          <br>
          <a id="addMedida" class="btn btn-primary" style="margin-top: 3px;">
            <i class="fa fa-plus"></i>
            Adicionar
          </a>
        </div>
      </div>
      <div class="clearfix"></div>

      <div class="col-md-12">
        <div class="table-responsive">
          <table class="table table-bordered table-striped" id="prod">
            <thead>
              <tr>
                <th>Item</th>
                <th>Código Unidade</th>
                <th>Tipo de Medida</th>
                <th>Quantidade</th>
                <th>Ação</th>
              </tr>
            </thead>
            <tbody>
              <tr>

              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="clearfix"></div>

      <div class="col-md-12">
        <h5 class="text-primary">COMPONENTES DA CARGA</h5>
        <p class="text-red">*A soma dos valores dos componentes deve ser igual ao valor a receber</p>
      </div>

      <div class="col-md-4">
        <div class="form-group">
          {!! Form::label('nome_componente', 'Nome do componente' . ':*') !!}
          {!! Form::text('nome_componente', null, ['class' => 'form-control', 'placeholder' => 'Nome do componente' ]); !!}
        </div>
      </div>

      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('valor_componente', 'Valor do componente' . ':*') !!}
          {!! Form::text('valor_componente', null, ['class' => 'form-control', 'placeholder' => 'Valor do componente', 'data-mask="000000,00", data-mask-reverse="true"' ]); !!}
        </div>
      </div>

      <div class="col-md-2">
        <div class="form-group">
          <br>
          <a id="addComponente" class="btn btn-primary" style="margin-top: 3px;">
            <i class="fa fa-plus"></i>
            Adicionar
          </a>
        </div>
      </div>
      <div class="clearfix"></div>


      <div class="col-md-12">
        <div class="table-responsive">
          <table class="table table-bordered table-striped" id="componentes">
            <thead>
              <tr>
                <th>Item</th>
                <th>Componente</th>
                <th>Valor</th>
                <th>Ação</th>
              </tr>
            </thead>
            <tbody>
              <tr>

              </tr>
            </tbody>
          </table>
        </div>
      </div>
      </div>

      <div class="col-md-12">
        <h4>INFORMAÇÕES DA ENTREGA</h4>
      </div>

      <div class="col-md-12">

        <h6>Endereço do Tomador</h6>
        <p>
          <input @if($cte->tomador != 0) checked @endif type="checkbox" id="endereco-destinatario" />
          <label for="endereco-destinatario">Endereço do Destinatário</label>
        </p>

        <p>
          <input @if($cte->tomador == 0) checked @endif type="checkbox" id="endereco-remetente" />
          <label for="endereco-remetente">Endereço do Rementente</label>
        </p>
      </div>

      <div class="col-md-12">
        <h5>Endereço do Tomador</h5>
      </div>
      <div class="col-md-5">
        <div class="form-group">
          {!! Form::label('rua_tomador', 'Rua' . ':*') !!}
          {!! Form::text('rua_tomador', $cte->logradouro_tomador, ['class' => 'form-control type-ref', 'required', 'placeholder' => 'Rua' ]); !!}
        </div>
      </div>
      <div class="col-md-2">
        <div class="form-group">
          {!! Form::label('numero_tomador', 'Número' . ':*') !!}
          {!! Form::text('numero_tomador', $cte->numero_tomador, ['class' => 'form-control type-ref', 'required', 'placeholder' => 'Número' ]); !!}
        </div>
      </div>

      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('cep_tomador', 'CEP' . ':*') !!}
          {!! Form::text('cep_tomador', $cte->cep_tomador, ['class' => 'form-control type-ref', 'required', 'placeholder' => 'CEP' ]); !!}
        </div>
      </div>

      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('bairro_tomador', 'Bairro' . ':*') !!}
          {!! Form::text('bairro_tomador', $cte->bairro_tomador, ['class' => 'form-control type-ref', 'required', 'placeholder' => 'Bairro' ]); !!}
        </div>
      </div>

      <div class="col-md-4">
        <div class="form-group">
          {!! Form::label('cidade_tomador', 'Cidade' . ':*') !!}
          {!! Form::select('cidade_tomador', $cidades, $cte->municipio_tomador, ['class' => 'form-control select2', 'id' => 'cidade_tomador', 'required']); !!}
        </div>
      </div>

      <div class="clearfix"></div>

      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('data_prevista_entrega', 'Data previsa de entrega' . ':*') !!}
          <div class="input-group">
            <span class="input-group-addon">
              <i class="fa fa-calendar"></i>
            </span>
            {!! Form::text('data_prevista_entrega', \Carbon\Carbon::parse($cte->data_previsata_entrega)->format('d/m/Y'), ['class' => 'form-control', 'readonly', 'required', 'id' => 'vencimento']); !!}

          </div>
        </div>
      </div>

      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('valor_transporte', 'Valor da Prestação de Serviço' . ':*') !!}
          {!! Form::text('valor_transporte', number_format($cte->valor_transporte, 2, ',', ''), ['class' => 'form-control type-ref', 'required', 'placeholder' => 'Valor da Prestação de Serviço', 'data-mask="000000,00", data-mask-reverse="true"' ]); !!}
        </div>
      </div>

      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('valor_receber', 'Valor a Receber' . ':*') !!}
          {!! Form::text('valor_receber', $cte->valor_receber, ['class' => 'form-control', 'required type-ref', 'placeholder' => 'Valor a Receber', 'data-mask="000000,00", data-mask-reverse="true"' ]); !!}
        </div>
      </div>

      <div class="clearfix"></div>

      <div class="col-md-4">
        <div class="form-group">
          {!! Form::label('cidade_envio', 'Municipio envio' . ':*') !!}
          {!! Form::select('cidade_envio', $cidades, $cte->municipio_envio, ['class' => 'form-control select2', 'id' => 'cidade_envio', 'required']); !!}
        </div>
      </div>
      <div class="col-md-4">
        <div class="form-group">
          {!! Form::label('cidade_inicio', 'Municipio Inicio' . ':*') !!}
          {!! Form::select('cidade_inicio', $cidades, $cte->municipio_inicio, ['class' => 'form-control select2', 'id' => 'cidade_inicio', 'required']); !!}
        </div>
      </div>
      <div class="col-md-4">
        <div class="form-group">
          {!! Form::label('cidade_fim', 'Municipio Fim' . ':*') !!}
          {!! Form::select('cidade_fim', $cidades, $cte->municipio_fim, ['class' => 'form-control select2', 'id' => 'cidade_fim', 'required']); !!}
        </div>
      </div>

      <div class="col-md-2">
        <div class="form-group">
          {!! Form::label('retira', 'Retira' . ':*') !!}
          {!! Form::select('retira', [1 => 'sim', 0 => 'não'], $cte->retira, ['class' => 'form-control select2', 'id' => 'retira', 'required']); !!}
        </div>
      </div>

      <div class="col-md-5">
        <div class="form-group">
          {!! Form::label('detalhes_retira', 'Detalhes(opcional)' . ':*') !!}
          {!! Form::text('detalhes_retira', $cte->detalhes_retira, ['class' => 'form-control type-ref', 'placeholder' => 'Detalhes(opcional)' ]); !!}
        </div>
      </div>

      <div class="col-md-7">
        <div class="form-group">
          {!! Form::label('observacao', 'Informação Adicional' . ':*') !!}
          {!! Form::text('observacao', $cte->observacao, ['class' => 'form-control type-ref', 'placeholder' => 'Informação Adicional' ]); !!}
        </div>
      </div>

      <input style="visibility: hidden" value="{{json_encode($cte->componentes)}}" name="componentes" id="comps">
      <input style="visibility: hidden" value="{{json_encode($cte->medidas)}}" name="medidas" id="meds">

      @endcomponent
    </div>


  </div>

  @if(!empty($form_partials))
  @foreach($form_partials as $partial)
  {!! $partial !!}
  @endforeach
  @endif
  <div class="row">
    <div class="col-md-12">
      <button id="finalizar" type="submit" class="btn btn-primary pull-right" id="submit_user_button">Atualizar CTe</button>
    </div>
  </div>
  <br><br>
  {!! Form::close() !!}
  @stop
  @section('javascript')
  <script type="text/javascript">

    $('#file').change(function() {
      $('#form-import').submit();
    });

    var CLIENTES = []
    var MEDIDAS = []
    var COMPONENTES = []
    var REMETENTE = null;
    var DESTINATARIO = null;
    var CHAVES = [];
    var BUSCA_URL = "{{ route('cte.buscarOriginal') }}";
    var BUSCA_TIMER = null;
    var EXCLUIR_ID = {{ $cte->id }};

    function tipoCteAtual(){
      return $('#tp_cte').length ? $('#tp_cte').val() : '0';
    }

    function chaveOrigemValida(){
      return (($('#chave_origem_manual').val() || '').replace(/\D/g, '').length == 44);
    }

    function aplicarFinalidade(){
      var tp = tipoCteAtual();
      var vinculo = (tp == '1' || tp == '3');
      if(vinculo){
        $('#bloco-vinculo').css('display', 'block');
      }else{
        $('#bloco-vinculo').css('display', 'none');
      }
      if(tp == '3'){
        $('#bloco-altera-toma').css('display', 'block');
      }else{
        $('#bloco-altera-toma').css('display', 'none');
        $('#ind_altera_toma').prop('checked', false);
      }
      if(tp == '1'){
        $('#chave_origem_manual').attr('name', 'chave_complementada');
        $('#label-chave-origem').text('Chave do CT-e complementado (44 dígitos):');
      }else{
        $('#chave_origem_manual').attr('name', 'chave_original');
        $('#label-chave-origem').text('Chave do CT-e original (44 dígitos):');
      }
      if(tp == '1'){
        $('#bloco-documentos-fiscais').css('display', 'none');
        $('#bloco-carga').css('display', 'none');
        $('#div-doc-anterior').css('display', 'none');
        $('#bloco-documentos-fiscais [data-req="1"], #bloco-carga [data-req="1"]').prop('required', false);
      }else{
        $('#bloco-documentos-fiscais').css('display', 'block');
        $('#bloco-carga').css('display', 'block');
        $('#bloco-documentos-fiscais [data-req="1"], #bloco-carga [data-req="1"]').prop('required', true);
        $('#tipo_servico').trigger('change');
      }
      habilitaBtnSalarCTe();
    }

    function atualizarContadorChave(){
      var v = ($('#chave_origem_manual').val() || '').replace(/\D/g, '');
      $('#contador-chave').text(v.length + '/44 dígitos');
      $('#grupo-chave-origem').toggleClass('has-error', v.length > 0 && v.length != 44);
      $('#grupo-chave-origem').toggleClass('has-success', v.length == 44);
    }

    function buscarOriginal(termo){
      $.ajax({
        type: 'GET',
        url: BUSCA_URL,
        data: { q: termo, exclude_id: EXCLUIR_ID },
        dataType: 'json',
        success: function(lista){
          var atual = $('#cte_original_id').val();
          var html = '';
          if(!lista || lista.length == 0){
            html = '<option value="">Nenhum CT-e autorizado encontrado</option>';
          }else{
            html = '<option value="">Selecione o CT-e original</option>';
            lista.forEach(function(c){
              var sel = (String(c.id) == String(atual)) ? ' selected' : '';
              html += '<option value="' + c.id + '" data-chave="' + c.chave + '"' + sel + '>Nº ' + c.numero + ' - ' + c.destinatario + ' - ' + c.data + '</option>';
            });
          }
          $('#cte_original_id').html(html).trigger('change');
        },
        error: function(){
          $('#cte_original_id').html('<option value="">Erro ao buscar. Tente novamente.</option>').trigger('change');
        }
      });
    }

    $('#tp_cte').change(() => {
      aplicarFinalidade();
    });

    $('#busca_original').on('keyup', function(){
      clearTimeout(BUSCA_TIMER);
      var termo = $(this).val();
      BUSCA_TIMER = setTimeout(function(){ buscarOriginal(termo); }, 400);
    });

    $('#cte_original_id').on('change', function(){
      var chave = $(this).find('option:selected').attr('data-chave');
      if(chave){
        $('#chave_origem_manual').val(chave).trigger('input');
        atualizarContadorChave();
        habilitaBtnSalarCTe();
      }
    });

    $('#chave_origem_manual').on('keyup change', function(){
      var sel = $('#cte_original_id').find('option:selected');
      if(sel.length && sel.val() && sel.attr('data-chave') && ($(this).val() || '') != sel.attr('data-chave')){
        $('#cte_original_id').val('').trigger('change');
      }
      atualizarContadorChave();
      habilitaBtnSalarCTe();
    });

    $('#remetente_id').change(() => {
      changeRemetente();
    })

    function changeRemetente(){
      let id =  $('#remetente_id').val()
      CLIENTES.map((c) => {
        if(c.id == id){
          REMETENTE = c
          $('#remetente-nome').html(c.name)
          $('#remetente-cnpj').html(c.cpf_cnpj)
          $('#remetente-ie').html(c.ie_rg)
          $('#remetente-endereco').html(c.rua + ', ' + c.numero)
          $('#remetente-cidade').html(c.cidade.nome + ' (' + c.cidade.uf + ')')

          $('#box-remetente').css('display', 'block')
        }
      })
    }

    $('#destinatario_id').change(() => {
      changeDestinatario();
    })

    function changeDestinatario(){
      let id =  $('#destinatario_id').val()
      CLIENTES.map((c) => {
        if(c.id == id){
          DESTINATARIO = c
          $('#destinatario-nome').html(c.name)
          $('#destinatario-cnpj').html(c.cpf_cnpj)
          $('#destinatario-ie').html(c.ie_rg)
          $('#destinatario-endereco').html(c.rua + ', ' + c.numero)
          $('#destinatario-cidade').html(c.cidade.nome + ' (' + c.cidade.uf + ')')

          $('#box-destinatario').css('display', 'block')
        }
      })
    }

    $('#recebedor_id').change(() => {
      selecetRecebedor();
    })

    function selecetRecebedor(){
      let id = $('#recebedor_id').val()
      if(id){
        CLIENTES.map((c) => {
          if(c.id == id){
            DESTINATARIO = c
            $('#recebedor-nome').html(c.name)
            $('#recebedor-cnpj').html(c.cpf_cnpj)
            $('#recebedor-ie').html(c.ie_rg)
            $('#recebedor-endereco').html(c.rua + ', ' + c.numero)
            $('#recebedor-cidade').html(c.cidade.nome + ' (' + c.cidade.uf + ')')

            $('#box-recebedor').css('display', 'block')
          }
        })
      }
    }

    $('#expedidor_id').change(() => {
      selectExpedidor()
    })

    function selectExpedidor(){
      let id = $('#expedidor_id').val()
      if(id){
        CLIENTES.map((c) => {
          if(c.id == id){
            DESTINATARIO = c
            $('#expedidor-nome').html(c.name)
            $('#expedidor-cnpj').html(c.cpf_cnpj)
            $('#expedidor-ie').html(c.ie_rg)
            $('#expedidor-endereco').html(c.rua + ', ' + c.numero)
            $('#expedidor-cidade').html(c.cidade.nome + ' (' + c.cidade.uf + ')')

            $('#box-expedidor').css('display', 'block')
          }
        })
      }
    }

    // Adicionando chave nfe

    $('#addChave').click(() => {

      let chave = $('#chave_nfe').val();
      if(chave.length == 44){
        adicionarChaveArray(chave)
      }else{
        swal('Erro', 'Informe 44 caracteres correspondentes a NF-e', 'error')
      }
    })

    function adicionarChaveArray(chave){
      if(!CHAVES.includes(chave)){

        CHAVES.push(chave)
        montaHtmlChaveNfe((html) => {
          $('#chaves_list').html(html)
        })
        $('#chaves_nfe').val(CHAVES)
        $('#chave_nfe').val('')
      }else{
        swal('Erro', 'Esta chave ja esta na lista', 'error')
      }
    }

    function montaHtmlChaveNfe(call){
      let html = '';
      CHAVES.map((ch) => {

        html += '<p><strong> '+ch+
        '<i onclick="deleteChave(\''+ch+'\')" class="fa fa-times text-danger"></i></strong></p>'

      })

      call(html)
    }

    function deleteChave(chave){
      let temp = [];
      CHAVES.map((ch) => {
        if(ch != chave) temp.push(ch)
      })

      CHAVES = temp;
      $('#chaves_nfe').val(CHAVES)

      montaHtmlChaveNfe((html) => {
        $('#chaves_list').html(html)
      })
    }

    // Tomador Outros
    $('#tomador').change(() => {
      let v = $('#tomador').val()
      if(v == '4'){
        $('#div-tomador-outros').css('display', 'block')
      }else{
        $('#div-tomador-outros').css('display', 'none')
        $('#box-tomador-outros').css('display', 'none')
      }
    })

    $('#tomador_outros_id').change(() => {
      let id = $('#tomador_outros_id').val()
      CLIENTES.map((c) => {
        if(c.id == id){
          $('#tomador-outros-nome').html(c.name)
          $('#tomador-outros-cnpj').html(c.cpf_cnpj)
          $('#tomador-outros-ie').html(c.ie_rg)
          $('#tomador-outros-endereco').html(c.rua + ', ' + c.numero)
          $('#tomador-outros-cidade').html(c.cidade.nome + ' (' + c.cidade.uf + ')')
          $('#box-tomador-outros').css('display', 'block')

          // Preencher endereço do tomador
          $('#rua_tomador').val(c.rua)
          $('#numero_tomador').val(c.numero)
          $('#bairro_tomador').val(c.bairro)
          $('#cep_tomador').val(c.cep)
          $('#cidade_tomador').val(c.cidade.id).change()
        }
      })
    })

    // Tipo de Serviço - mostrar/ocultar documentos anteriores
    var DOCS_ANTERIORES = [];

    // Função para converter código UF para sigla
    function codigoUfParaSigla(codigo) {
      const ufs = {
        '11': 'RO', '12': 'AC', '13': 'AM', '14': 'RR', '15': 'PA', '16': 'AP', '17': 'TO',
        '21': 'MA', '22': 'PI', '23': 'CE', '24': 'RN', '25': 'PB', '26': 'PE', '27': 'AL', '28': 'SE', '29': 'BA',
        '31': 'MG', '32': 'ES', '33': 'RJ', '35': 'SP',
        '41': 'PR', '42': 'SC', '43': 'RS',
        '50': 'MS', '51': 'MT', '52': 'GO', '53': 'DF'
      };
      return ufs[codigo] || codigo;
    }
    
    // Carregar documentos anteriores existentes
    try {
      let docsExistentes = JSON.parse($('#docs_anteriores').val() || '[]')
      if(docsExistentes.length > 0){
        docsExistentes.map((d, idx) => {
          DOCS_ANTERIORES.push({
            id: idx + 1,
            chave: d.chave_cte || d.chave,
            cnpj: d.cnpj_emitente || d.cnpj,
            uf: d.uf_emitente || d.uf,
            ie: d.ie_emitente || d.ie || ''
          })
        })
        montaTabelaDocsAnteriores()
      }
    } catch(e) {}

    $('#tipo_servico').change(() => {
      let v = $('#tipo_servico').val()
      if(v == '1' || v == '2' || v == '3'){
        $('#div-doc-anterior').css('display', 'block')
      }else{
        $('#div-doc-anterior').css('display', 'none')
      }
    })

    // Adicionar documento anterior
    $('#addDocAnterior').click(() => {
      let chave = $('#chave_cte_anterior').val()
      let ie = $('#ie_emitente_anterior').val()
      if(chave.length == 44){
        if(!ie || ie.trim() == ''){
          swal('Erro', 'Informe a IE do Emitente', 'error')
          return
        }
        let ufCodigo = chave.substring(0, 2)
        let uf = codigoUfParaSigla(ufCodigo)
        let cnpj = chave.substring(6, 20)
        
        DOCS_ANTERIORES.push({
          id: DOCS_ANTERIORES.length + 1,
          chave: chave,
          cnpj: cnpj,
          uf: uf,
          ie: ie
        })
        
        montaTabelaDocsAnteriores()
        $('#chave_cte_anterior').val('')
        $('#ie_emitente_anterior').val('')
      }else{
        swal('Erro', 'A chave do CT-e deve ter 44 caracteres', 'error')
      }
    })

    function montaTabelaDocsAnteriores(){
      let html = ''
      DOCS_ANTERIORES.map((d) => {
        html += '<tr>'
        html += '<td>' + d.id + '</td>'
        html += '<td>' + d.chave + '</td>'
        html += '<td>' + d.cnpj + '</td>'
        html += '<td>' + (d.ie || '') + '</td>'
        html += '<td>' + d.uf + '</td>'
        html += '<td><a href="#!" class="btn btn-danger btn-sm" onclick="deleteDocAnterior(' + d.id + ')"><i class="fa fa-trash"></i></a></td>'
        html += '</tr>'
      })
      $('#tbl-docs-anteriores tbody').html(html)
      $('#docs_anteriores').val(JSON.stringify(DOCS_ANTERIORES))
    }

    function deleteDocAnterior(id){
      DOCS_ANTERIORES = DOCS_ANTERIORES.filter(d => d.id != id)
      let cont = 1
      DOCS_ANTERIORES = DOCS_ANTERIORES.map(d => {
        d.id = cont++
        return d
      })
      montaTabelaDocsAnteriores()
    }

    // MEDIDAS CTE >>>>>>>

    $('#addMedida').click(() => {
      let unidade_medida = $('#unidade_medida').val();
      let tipo_medida = $('#tipo_medida').val();
      let quantidade = $('#quantidade_carga').val();
      MEDIDAS.push({id: (MEDIDAS.length+1), unidade_medida: unidade_medida,
        tipo_medida: tipo_medida, quantidade: quantidade});
      // console.log(MEDIDAS)

      let t = montaTabela();
      // $('#prod tbody').html(t)
    })

    function montaTabela(){
      let t = ""; 
      MEDIDAS.map((v) => {
        t += "<tr>";
        t += "<td>"+v.id+"</td>";
        t += "<td>"+unidadeMedidaExibe(v.unidade_medida)+"</td>";
        t += "<td>"+v.tipo_medida+"</td>";
        t += "<td>"+v.quantidade+"</td>";
        t += "<td><a href='#!' class='btn btn-danger btn-sm' onclick='deleteItem("+v.id+")'>"
        t += "<i class='fa fa-trash'></i></a></td>";
        t+= "</tr>";
      });
      $('#meds').val(JSON.stringify(MEDIDAS))
      $('#prod tbody').html(t)

      habilitaBtnSalarCTe()
      return t;
    }

    function montaTabela2(){
      let t = ""; 
      MEDIDAS.map((v) => {
        t += "<tr>";
        t += "<td>"+v.id+"</td>";
        t += "<td>"+unidadeMedidaExibe(v.cod_unidade)+"</td>";
        t += "<td>"+v.tipo_medida+"</td>";
        t += "<td>"+v.quantidade_carga+"</td>";
        t += "<td><a href='#!' class='btn btn-danger btn-sm' onclick='deleteItem("+v.id+")'>"
        t += "<i class='fa fa-trash'></i></a></td>";
        t+= "</tr>";
      });
      $('#meds').val(JSON.stringify(MEDIDAS))
      $('#prod tbody').html(t)

      habilitaBtnSalarCTe()
      return t;
    }

    function deleteItem(id){
      let temp = [];
      MEDIDAS.map((v) => {
        if(v.id != id){
          temp.push(v)
        }
      });
      MEDIDAS = temp;
      refatoreItens()
      let t = montaTabela(); 
      $('#prod tbody').html(t)
    }

    function refatoreItens(){
      let cont = 1;
      let temp = [];
      MEDIDAS.map((v) => {
        v.id = cont;
        temp.push(v)
        cont++;
      })
      MEDIDAS = temp;
    }

    function unidadeMedidaExibe(cod){
      if(cod == '00'){ 
        return 'M3'
      }else if(cod == '01'){ 
        return 'KG' 
      }else if(cod == '02'){
        return 'TON'
      }else if(cod == '03') {
        return 'UNIDADE'
      }else if(cod == '04') {
        return 'M2'
      }
    }

    // MEDIDAS CTE FIM >>>>>>>

    // COMPONENTES CTE >>>>>>>

    $('#addComponente').click(() => {
      let nome_componente = $('#nome_componente').val();
      let valor_componente = $('#valor_componente').val();
      COMPONENTES.push({id: (COMPONENTES.length+1), valor: valor_componente,
        nome: nome_componente});
      let t = montaTabelaComponentes();
      // console.log(JSON.stringify(COMPONENTES))
      
      habilitaBtnSalarCTe();
    });

    function montaTabelaComponentes(){
      let t = ""; 
      SOMACOMPONENTES = 0;
      console.log("comps",COMPONENTES)
      COMPONENTES.map((v) => {
        t += "<tr>";
        t += "<td>"+v.id+"</td>";
        t += "<td>"+v.nome+"</td>";
        t += "<td>"+v.valor+"</td>";
        t += "<td><a href='#!' class='btn btn-danger btn-sm'  onclick='deleteComponente("+v.id+")'>"
        t += "<i class='fa fa-trash'></i></a></td>";
        t+= "</tr>";

        SOMACOMPONENTES += parseFloat(v.valor.replace(',', '.'));
      });
      $('#comps').val(JSON.stringify(COMPONENTES))
      $('#valor_receber').val(SOMACOMPONENTES.toFixed(2).replace('.', ','));
      $('#valor_transporte').val(SOMACOMPONENTES.toFixed(2).replace('.', ','));
      habilitaBtnSalarCTe()
      $('#componentes tbody').html(t)

      return t;
    }

    function deleteComponente(id){
      let temp = [];
      COMPONENTES.map((v) => {
        if(v.id != id){
          temp.push(v)
        }
      });
      COMPONENTES = temp;
      refatoreComponentes()
      let t = montaTabelaComponentes(); 
      $('#componentes tbody').html(t)
    }

    function refatoreComponentes(){
      let cont = 1;
      let temp = [];
      COMPONENTES.map((v) => {
        v.id = cont;
        temp.push(v)
        cont++;
      })
      COMPONENTES = temp;
    }

    // COMPONENTES CTE  FIM >>>>>>>

    function habilitaBtnSalarCTe(){
      $('#finalizar').addClass('disabled');
      let tipoDocumento = false;
      let inputs = false;

      console.log(CHAVES.length)

      var tpAtual = tipoCteAtual();

      if(tpAtual == '1'){
        if(chaveOrigemValida() && DESTINATARIO != null && REMETENTE != null){
          $('#finalizar').removeClass('disabled')
        }
        return;
      }

      if(tpAtual == '3' && !chaveOrigemValida()){
        return;
      }

      if(CHAVES.length == 0 && $('#descOutros').val() != "" && $('#nDoc').val() != "" && $('#vDocFisc').val() != ""){
        tipoDocumento = true;
      }else if(CHAVES.length >= 1 && $('#descOutros').val() == "" && $('#nDoc').val() == "" && 
        $('#vDocFisc').val() == ""){
        tipoDocumento = true
      }

      if($('#prod_predominante').val() != "" && $('#valor_carga').val() != "" && $('#valor_transporte').val() != "" && $('#valor_receber').val() != ""){
        inputs = true;
      }

      // console.log(tipoDocumento)

      if(MEDIDAS.length > 0 && COMPONENTES.length > 0 && DESTINATARIO != null && REMETENTE != null &&tipoDocumento && inputs){
        $('#finalizar').removeClass('disabled')

      }
    }

    $('.type-ref').keyup(() => {
      habilitaBtnSalarCTe()
    })


    $('#endereco-destinatario').click(() => {
      let v = $('#endereco-destinatario').is(':checked');
      $('#endereco-remetente').prop('checked', false);
      if(v){
        if(DESTINATARIO){
          $('#tomador').val('3').change()
          $('#rua_tomador').val(DESTINATARIO.rua)
          $('#numero_tomador').val(DESTINATARIO.numero)
          $('#bairro_tomador').val(DESTINATARIO.bairro)
          $('#cep_tomador').val(DESTINATARIO.cep)
          $('#cidade_tomador').val(DESTINATARIO.cidade.id).change()

          habilitaCampos();

        }else{
          swal("Erro!", "Destinatário não selecionado!", "warning")
          $('#endereco-destinatario').prop('checked', false); 
        }
      }else{
        desabilitaCampos();
      }
    })

    $('#endereco-remetente').click(() => {
      let v = $('#endereco-remetente').is(':checked');
      $('#endereco-destinatario').prop('checked', false);
      if(v){
        if(REMETENTE){
          $('#tomador').val('0').change()
          $('#rua_tomador').val(REMETENTE.rua)
          $('#numero_tomador').val(REMETENTE.numero)
          $('#bairro_tomador').val(REMETENTE.bairro)
          $('#cep_tomador').val(REMETENTE.cep)
          $('#cidade_tomador').val(REMETENTE.cidade.id).change()

          habilitaCampos();

        }else{

          swal("Erro!", "Remetente não selecionado!", "warning")

          $('#endereco-remetente').prop('checked', false); 
        }
      }else{
        desabilitaCampos();
      }
    })

    function habilitaCampos(){
      // $('#rua_tomador').prop('disabled', true)
      // $('#numero_tomador').prop('disabled', true)
      // $('#bairro_tomador').prop('disabled', true)
      // $('#cep_tomador').prop('disabled', true)
      // $('#autocomplete-cidade-tomador').prop('disabled', true)
    }

    function desabilitaCampos(){
      // $('#rua_tomador').removeAttr('disabled')
      // $('#numero_tomador').removeAttr('disabled')
      // $('#bairro_tomador').removeAttr('disabled')
      // $('#cep_tomador').removeAttr('disabled')
      // $('#autocomplete-cidade-tomador').removeAttr('disabled')
    }



    $(document).ready(function(){

      CLIENTES = JSON.parse($('#clientesAux').val())
      $('#bloco-documentos-fiscais [required], #bloco-carga [required]').attr('data-req', '1');
      atualizarContadorChave();
      aplicarFinalidade();
      if(tipoCteAtual() == '1' || tipoCteAtual() == '3'){
        if(!$('#cte_original_id').val()){
          buscarOriginal('');
        }
      }
      setTimeout(() => {
        console.clear()
        COMPONENTES = JSON.parse($('#comps').val())
        MEDIDAS = JSON.parse($('#meds').val())
        // console.log(MEDIDAS)
        
        // console.log("medidas", MEDIDAS)
      }, 500)

      setTimeout(() => {
        let temp = []
        MEDIDAS.map((rs) => {
          // console.log(rs)
          temp.push({id: rs.id, unidade_medida: rs.cod_unidade,
            tipo_medida: rs.tipo_medida, quantidade: rs.quantidade_carga});
        })
        MEDIDAS = temp
        changeRemetente()
        changeDestinatario()
        selecetRecebedor()
        selectExpedidor()
        montaTabelaComponentes()
        montaTabela()

        let chaves = $('#chaves_nfe').val()
        let chTemp = chaves.split(';')
        console.log("ch",chTemp[0])
        chTemp.map((c) => {
          CHAVES.push(c)
        })
        if(chTemp[0]){
          montaHtmlChaveNfe((html) => {
            $('#chaves_list').html(html)
          })
        }
      }, 500)

      $('#selected_contacts').on('ifChecked', function(event){
        $('div.selected_contacts_div').removeClass('hide');
      });
      $('#selected_contacts').on('ifUnchecked', function(event){
        $('div.selected_contacts_div').addClass('hide');
      });

      $('#allow_login').on('ifChecked', function(event){
        $('div.user_auth_fields').removeClass('hide');
      });
      $('#allow_login').on('ifUnchecked', function(event){
        $('div.user_auth_fields').addClass('hide');
      });
    });

    $(document).on('click', '#finalizar', function(e) {
      e.preventDefault();

      $('form#cte_update_form').validate()
      if ($('form#cte_update_form').valid()) {
        $('form#cte_update_form').submit();
      }
    })
  </script>
  @endsection

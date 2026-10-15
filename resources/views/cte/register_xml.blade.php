@extends('layouts.app')

@section('title', 'Adicionar CTe')

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
  <h1>Adicionar </h1>  


</section>

<!-- Main content -->
<section class="content">


  {!! Form::open(['url' => action('CteController@store'), 'method' => 'post', 'id' => 'cte_add_form' ]) !!}
  <input type="hidden" name="_cte_xml_import" value="1">
  
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

      
      <div class="col-md-4">
        <div class="form-group">
          {!! Form::label('natureza_id', 'Natureza de operação' . ':*') !!}
          {!! Form::select('natureza_id', $naturezas, '', ['class' => 'form-control select2', 'id' => 'contact_type', 'required']); !!}
        </div>
      </div>

      <div class="col-md-2">
        <div class="form-group">
          {!! Form::label('globalizado', 'Tipo globalizado' . ':*') !!}
          {!! Form::select('globalizado', ['0' => 'Não', 1 => 'Sim'], '', ['class' => 'form-control select2', 'id' => 'contact_type', 'required']); !!}
        </div>
      </div>

      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('cst', 'CST' . ':*') !!}
          {!! Form::select('cst', App\Models\Cte::getCsts(), '', ['class' => 'form-control select2', 'id' => 'contact_type', 'required']); !!}
        </div>
      </div>

      <div class="col-md-2">
        <div class="form-group">
          {!! Form::label('perc_icms', '%ICMS' . ':*') !!}
          {!! Form::text('perc_icms', null, ['class' => 'form-control type-ref', 'placeholder' => '%ICMS', 'data-mask="00,00", data-mask-reverse="true"' ]); !!}
        </div>
      </div>

      @if(is_null($default_location))

      <div class="col-md-4">
        <br>
        <div class="form-group" style="margin-top: 8px;">
          <div class="input-group">
            <span class="input-group-addon">
              <i class="fa fa-map-marker"></i>
            </span>
            {!! Form::select('select_location_id', $business_locations, null, ['class' => 'form-control input-sm', 
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

      <div class="clearfix"></div>

      <div class="col-md-6">
        <div class="form-group">
          {!! Form::label('remetente_id', 'Remetente' . ':*') !!}
          {!! Form::select('remetente_id', $clientes, $dadosDaNFe['remetente'], ['class' => 'form-control select2', 'id' => 'remetente_id', 'required', 'placeholder' => 'Selecione o remetente']); !!}
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
          {!! Form::select('destinatario_id', $clientes, $dadosDaNFe['destinatario'], ['class' => 'form-control select2', 'id' => 'destinatario_id', 'required', 'placeholder' => 'Selecione o destinatário']); !!}
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
          {!! Form::select('recebedor_id', $clientes, '', ['class' => 'form-control select2', 'id' => 'recebedor_id', 'placeholder' => 'Selecione o recebedor']); !!}
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
          {!! Form::select('expedidor_id', $clientes, '', ['class' => 'form-control select2', 'id' => 'expedidor_id', 'placeholder' => 'Selecione o expedidor']); !!}
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

      <div class="row">
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
                        <input placeholder="Chave NFe" class="form-control type-ref" data-mask="00000000000000000000000000000000000000000000" value="{{$dadosDaNFe['chave']}}" name="chave_nfe" type="text" id="chave_nfe">
                      </div>
                    </div>
                    <input type="hidden" id="chaves_nfe" name="chaves_nfe" value="{{ old('chaves_nfe', '') }}">
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
              <div class="tab-pane ''" id="documents_and_notes_tab">
                <!-- model id like project_id, user_id -->
                <!-- model name like App\User -->

                <?php 
                $tipos = [
                  '00' => 'Declaração',
                  '10' => 'Dutoviário',
                  '59' => 'CFe SAT',
                  '65' => 'NFCe',
                  '99' => 'Outros'
                ];
                ?>
                <div class="row">
                  <div class="col-md-12">

                    <div class="col-md-3">
                      <div class="form-group">
                        {!! Form::label('tpDoc', 'Tipo documento' . ':*') !!}
                        {!! Form::select('tpDoc', $tipos, '', ['class' => 'form-control', 'id' => 'tpDoc', 'required']); !!}
                      </div>
                    </div>

                    <div class="col-md-4">
                      <div class="form-group">
                        {!! Form::label('descOutros', 'Descrição do Doc.' . ':*') !!}
                        {!! Form::text('descOutros', null, ['class' => 'form-control type-ref', 'placeholder' => 'Descrição do Doc.' ]); !!}
                      </div>
                    </div>

                    <div class="col-md-2">
                      <div class="form-group">
                        {!! Form::label('nDoc', 'Numero do Doc.' . ':*') !!}
                        {!! Form::text('nDoc', null, ['class' => 'form-control type-ref', 'placeholder' => 'Numero do Doc.' ]); !!}
                      </div>
                    </div>

                    <div class="col-md-3">
                      <div class="form-group">
                        {!! Form::label('vDocFisc', 'Valor do Documento' . ':*') !!}
                        {!! Form::text('vDocFisc', null, ['class' => 'form-control type-ref', 'placeholder' => 'Valor do Documento', 'data-mask="000000.00", data-mask-reverse="true"' ]); !!}
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
      <div class="col-md-12">
        <h4>INFORMAÇÕES DA CARGA</h4>
      </div>

      <div class="col-md-4">

        <div class="form-group">
          {!! Form::label('veiculo_id', 'Veiculo' . ':*') !!}
          {!! Form::select('veiculo_id', $veiculos, '', ['class' => 'form-control select2', 'id' => 'veiculo_id', 'required', 'placeholder' => 'Veiculo']); !!}
        </div>
      </div>

      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('prod_predominante', 'Produto predominante' . ':*') !!}
          {!! Form::text('prod_predominante', $dadosDaNFe['produto_predominante'], ['class' => 'form-control type-ref', 'required', 'placeholder' => 'Produto predominante' ]); !!}
        </div>
      </div>

      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('tomador', 'Tomador' . ':*') !!}
          {!! Form::select('tomador', $tiposTomador, '', ['class' => 'form-control select2', 'id' => 'tomador', 'required']); !!}
        </div>
      </div>

      <div class="col-md-2">
        <div class="form-group">
          {!! Form::label('tipo_servico', 'Tipo de Serviço' . ':*') !!}
          {!! Form::select('tipo_servico', $tiposServico, '0', ['class' => 'form-control select2', 'id' => 'tipo_servico', 'required']); !!}
        </div>
      </div>

      <div class="clearfix"></div>

      <div class="col-md-6" id="div-tomador-outros" style="display: none;">
        <div class="form-group">
          {!! Form::label('tomador_outros_id', 'Tomador (Outros)' . ':') !!}
          {!! Form::select('tomador_outros_id', $clientes, '', ['class' => 'form-control select2', 'id' => 'tomador_outros_id', 'placeholder' => 'Selecione o tomador']); !!}
        </div>
        <div class="box box-warning" id="box-tomador-outros" style="display: none">
          <div class="box-body">
            <h5>Nome: <strong id="tomador-outros-nome"></strong></h5>
            <h5>CNPJ: <strong id="tomador-outros-cnpj"></strong></h5>
            <h5>IE: <strong id="tomador-outros-ie"></strong></h5>
            <h5>Endereço: <strong id="tomador-outros-endereco"></strong></h5>
            <h5>Cidade: <strong id="tomador-outros-cidade"></strong></h5>
          </div>
        </div>
      </div>

      <div class="clearfix"></div>

      <!-- Documentos de Transporte Anterior (para Subcontratação/Redespacho) -->
      <div class="col-md-12" id="div-doc-anterior" style="display: none;">
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
            <input type="hidden" name="documentos_anteriores" id="docs_anteriores" value="{{ is_string(old('documentos_anteriores', '[]')) ? old('documentos_anteriores', '[]') : '[]' }}">
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
          {!! Form::text('valor_carga', $dadosDaNFe['valor_carga'], ['class' => 'form-control', 'required type-ref', 'placeholder' => 'Valor da Carga', 'data-mask="000000.00", data-mask-reverse="true"' ]); !!}
        </div>
      </div>

      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('modal_transp', 'Modelo de Transporte' . ':*') !!}
          {!! Form::select('modal_transp', $modals, '', ['class' => 'form-control select2', 'id' => 'modal_transp', 'required']); !!}
        </div>
      </div>

      <div class="col-md-12">
        <h5 class="text-primary">INFORMAÇÕES DE QUANTIDADE</h5>
      </div>

      <div class="col-md-2">
        <div class="form-group">
          {!! Form::label('unidade_medida', 'Unidade medida' . ':*') !!}
          {!! Form::select('unidade_medida', $unidadesMedida, $dadosDaNFe['unidade'], ['class' => 'form-control select2', 'id' => 'unidade_medida', 'required']); !!}
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
          {!! Form::text('quantidade_carga', $dadosDaNFe['quantidade'], ['class' => 'form-control type-ref', 'placeholder' => 'Quantidade',  'data-mask="000000.000", data-mask-reverse="true"' ]); !!}
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
          {!! Form::text('nome_componente', $dadosDaNFe['componente'], ['class' => 'form-control', 'required', 'placeholder' => 'Nome do componente' ]); !!}
        </div>
      </div>

      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('valor_componente', 'Valor do componente' . ':*') !!}
          {!! Form::text('valor_componente', $dadosDaNFe['valor_frete'], ['class' => 'form-control', 'required', 'placeholder' => 'Valor do componente', 'data-mask="000000.00", data-mask-reverse="true"' ]); !!}
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

      <div class="col-md-12">
        <h4>INFORMAÇÕES DA ENTREGA</h4>
      </div>

      <div class="col-md-12">

        <h6>Endereço do Tomador</h6>
        <p>
          <input type="checkbox" id="endereco-destinatario" />
          <label for="endereco-destinatario">Endereço do Destinatário</label>
        </p>

        <p>
          <input type="checkbox" id="endereco-remetente" />
          <label for="endereco-remetente">Endereço do Rementente</label>
        </p>
      </div>

      <div class="col-md-12">
        <h5>Endereço do Tomador</h5>
      </div>
      <div class="col-md-5">
        <div class="form-group">
          {!! Form::label('rua_tomador', 'Rua' . ':*') !!}
          {!! Form::text('rua_tomador', null, ['class' => 'form-control', 'required', 'placeholder' => 'Rua' ]); !!}
        </div>
      </div>
      <div class="col-md-2">
        <div class="form-group">
          {!! Form::label('numero_tomador', 'Número' . ':*') !!}
          {!! Form::text('numero_tomador', null, ['class' => 'form-control', 'required', 'placeholder' => 'Número' ]); !!}
        </div>
      </div>

      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('cep_tomador', 'CEP' . ':*') !!}
          {!! Form::text('cep_tomador', null, ['class' => 'form-control', 'required', 'placeholder' => 'CEP' ]); !!}
        </div>
      </div>

      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('bairro_tomador', 'Bairro' . ':*') !!}
          {!! Form::text('bairro_tomador', null, ['class' => 'form-control', 'required', 'placeholder' => 'Bairro' ]); !!}
        </div>
      </div>

      <div class="col-md-4">
        <div class="form-group">
          {!! Form::label('cidade_tomador', 'Cidade' . ':*') !!}
          {!! Form::select('cidade_tomador', $cidades, '', ['class' => 'form-control select2', 'id' => 'cidade_tomador', 'required']); !!}
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
            {!! Form::text('data_prevista_entrega', $dadosDaNFe['data_entrega'], ['class' => 'form-control type-ref', 'required', 'data-mask="00/00/0000"']); !!}
          </div>
        </div>
      </div>

      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('valor_transporte', 'Valor da Prestação de Serviço' . ':*') !!}
          {!! Form::text('valor_transporte', null, ['class' => 'form-control type-ref', 'required', 'placeholder' => 'Valor da Prestação de Serviço' ]); !!}
        </div>
      </div>

      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('valor_receber', 'Valor a Receber' . ':*') !!}
          {!! Form::text('valor_receber', null, ['class' => 'form-control', 'required', 'placeholder' => 'Valor a Receber' ]); !!}
        </div>
      </div>

      <div class="clearfix"></div>

      <div class="col-md-4">
        <div class="form-group">
          {!! Form::label('cidade_envio', 'Municipio envio' . ':*') !!}
          {!! Form::select('cidade_envio', $cidades, $dadosDaNFe['munipio_envio'], ['class' => 'form-control select2', 'id' => 'cidade_envio', 'required']); !!}
        </div>
      </div>
      <div class="col-md-4">
        <div class="form-group">
          {!! Form::label('cidade_inicio', 'Municipio Inicio' . ':*') !!}
          {!! Form::select('cidade_inicio', $cidades, $dadosDaNFe['munipio_envio'], ['class' => 'form-control select2', 'id' => 'cidade_inicio', 'required']); !!}
        </div>
      </div>
      <div class="col-md-4">
        <div class="form-group">
          {!! Form::label('cidade_fim', 'Municipio Fim' . ':*') !!}
          {!! Form::select('cidade_fim', $cidades, $dadosDaNFe['munipio_final'], ['class' => 'form-control select2', 'id' => 'cidade_fim', 'required']); !!}
        </div>
      </div>

      <div class="col-md-2">
        <div class="form-group">
          {!! Form::label('retira', 'Retira' . ':*') !!}
          {!! Form::select('retira', [1 => 'sim', 0 => 'não'], '', ['class' => 'form-control select2', 'id' => 'retira', 'required']); !!}
        </div>
      </div>

      <div class="col-md-5">
        <div class="form-group">
          {!! Form::label('detalhes_retira', 'Detalhes(opcional)' . ':*') !!}
          {!! Form::text('detalhes_retira', null, ['class' => 'form-control type-ref', 'placeholder' => 'Detalhes(opcional)' ]); !!}
        </div>
      </div>

      <div class="col-md-7">
        <div class="form-group">
          {!! Form::label('obs', 'Informação Adicional' . ':*') !!}
          {!! Form::text('obs', null, ['class' => 'form-control type-ref', 'placeholder' => 'Informação Adicional' ]); !!}
        </div>
      </div>
      <input type="hidden" name="componentes" id="comps" value="{{ is_string(old('componentes', '[]')) ? old('componentes', '[]') : '[]' }}">
      <input type="hidden" name="medidas" id="meds" value="{{ is_string(old('medidas', '[]')) ? old('medidas', '[]') : '[]' }}">

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
      <button id="finalizar" type="submit" class="btn btn-primary pull-right disabled" id="submit_user_button">@lang( 'messages.save' ) CTe</button>
    </div>
  </div>
  {!! Form::close() !!}
  @stop
  @section('javascript')
  <script type="text/javascript">

    $('#file').change(function() {
      $('#form-import').submit();
    });

    // Recuperar listas completas: nunca aproveitar só os primeiros itens de um payload inválido.
    function lerListaCte(selector, normalizar) {
      try {
        const lista = JSON.parse($(selector).val() || '[]');
        if(!Array.isArray(lista)) return [];
        return lista.map((item, index) => {
          if(!item || typeof item !== 'object' || Array.isArray(item)) throw new Error('Item inválido');
          return Object.assign({id: index + 1}, normalizar(item));
        });
      } catch(error) { return []; }
    }
    function campoListaCte(item, campo, alternativo) {
      const valor = item[campo] !== undefined ? item[campo] : item[alternativo];
      if(typeof valor !== 'string' && typeof valor !== 'number') throw new Error('Campo inválido');
      return String(valor);
    }
    function recuperarListasCte() {
      MEDIDAS = lerListaCte('#meds', item => ({
        unidade_medida: campoListaCte(item, 'unidade_medida', 'cod_unidade'),
        tipo_medida: campoListaCte(item, 'tipo_medida'),
        quantidade: campoListaCte(item, 'quantidade', 'quantidade_carga')
      }));
      COMPONENTES = lerListaCte('#comps', item => ({nome: campoListaCte(item, 'nome'), valor: campoListaCte(item, 'valor')}));
      DOCS_ANTERIORES = lerListaCte('#docs_anteriores', item => ({
        chave: campoListaCte(item, 'chave', 'chave_cte'),
        ie: campoListaCte(item, 'ie', 'ie_emitente'),
        cnpj: campoListaCte(item, 'cnpj', 'cnpj_emitente'),
        uf: campoListaCte(item, 'uf', 'uf_emitente')
      }));
      $('#prod tbody').empty().append(montaTabela());
      $('#componentes tbody').empty().append(montaTabelaComponentes(true));
      montaTabelaDocsAnteriores();
    }
    // Fim da recuperação de listas

    var CLIENTES = []
    var MEDIDAS = []
    var COMPONENTES = []
    var REMETENTE = null;
    var DESTINATARIO = null;
    var CHAVES = [];
    $('#remetente_id').change(() => {
      selectRemetente();
    })

    function selectRemetente(){
      let id = $('#remetente_id').val()
      CLIENTES.map((c) => {
        if(c.id == id){
          console.log(c)
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
      selectDestinatario();
    })

    function selectDestinatario(){
      let id = $('#destinatario_id').val()
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

    // Tomador Outros
    function atualizarTomadorOutros(preencherEndereco) {
      const outros = $('#tomador').val() === '4';
      const contato = CLIENTES.find(c => String(c.id) === $('#tomador_outros_id').val());
      $('#div-tomador-outros').toggle(outros);
      $('#box-tomador-outros').toggle(outros && !!contato);
      if(!outros || !contato) return;

      $('#tomador-outros-nome').text(contato.name || '');
      $('#tomador-outros-cnpj').text(contato.cpf_cnpj || '');
      $('#tomador-outros-ie').text(contato.ie_rg || '');
      $('#tomador-outros-endereco').text((contato.rua || '') + ', ' + (contato.numero || ''));
      $('#tomador-outros-cidade').text(contato.cidade ? contato.cidade.nome + ' (' + contato.cidade.uf + ')' : '');

      if(preencherEndereco){
        $('#rua_tomador').val(contato.rua || '');
        $('#numero_tomador').val(contato.numero || '');
        $('#bairro_tomador').val(contato.bairro || '');
        $('#cep_tomador').val(contato.cep || '');
        $('#cidade_tomador').val(contato.cidade ? contato.cidade.id : '').trigger('change');
      }
    }

    $('#tomador').on('change', () => atualizarTomadorOutros(false));
    $('#tomador_outros_id').on('change', () => atualizarTomadorOutros(true));

    // Tipo de Serviço - documentos anteriores para Subcontratação/Redespacho.
    var DOCS_ANTERIORES = [];

    function codigoUfParaSigla(codigo) {
      const ufs = {
        '11': 'RO', '12': 'AC', '13': 'AM', '14': 'RR', '15': 'PA', '16': 'AP', '17': 'TO',
        '21': 'MA', '22': 'PI', '23': 'CE', '24': 'RN', '25': 'PB', '26': 'PE', '27': 'AL', '28': 'SE', '29': 'BA',
        '31': 'MG', '32': 'ES', '33': 'RJ', '35': 'SP',
        '41': 'PR', '42': 'SC', '43': 'RS',
        '50': 'MS', '51': 'MT', '52': 'GO', '53': 'DF'
      };
      return ufs[codigo] || null;
    }

    function documentoAnteriorValido(chave, ie) {
      if(typeof chave !== 'string' || !/^[0-9]{44}$/.test(chave) || chave.substring(20, 22) !== '57' || !codigoUfParaSigla(chave.substring(0, 2))){
        return false;
      }
      let soma = 0, peso = 2;
      for(let i = 42; i >= 0; i--){
        soma += Number(chave[i]) * peso;
        peso = peso === 9 ? 2 : peso + 1;
      }
      let dv = 11 - (soma % 11);
      dv = dv >= 10 ? 0 : dv;
      return Number(chave[43]) === dv && typeof ie === 'string' && /^(?:[0-9]{2,14}|ISENTO)$/.test(ie.trim());
    }

    $('#cte_add_form').on('submit', function(event) {
      if((['1', '2', '3'].includes($('#tipo_servico').val()) && DOCS_ANTERIORES.length === 0) ||
          DOCS_ANTERIORES.some(doc => !documentoAnteriorValido(doc.chave, doc.ie))){
        event.preventDefault();
        swal('Erro', 'Informe CT-e anterior modelo 57 com chave válida e IE de 2 a 14 dígitos ou ISENTO.', 'error');
      }
    });

    $('#tipo_servico').on('change', function() {
      $('#div-doc-anterior').toggle(['1', '2', '3'].includes($(this).val()));
    }).trigger('change');

    $('#addDocAnterior').click(() => {
      let chave = $('#chave_cte_anterior').val();
      let ie = $('#ie_emitente_anterior').val();
      if(!documentoAnteriorValido(chave, ie)){
        swal('Erro', 'Informe CT-e anterior modelo 57 com 44 dígitos, DV e UF válidos; IE de 2 a 14 dígitos ou ISENTO.', 'error');
        return;
      }

      DOCS_ANTERIORES.push({
        id: DOCS_ANTERIORES.length + 1,
        chave: chave,
        cnpj: chave.substring(6, 20),
        uf: codigoUfParaSigla(chave.substring(0, 2)),
        ie: ie.trim()
      });
      montaTabelaDocsAnteriores();
      $('#chave_cte_anterior').val('');
      $('#ie_emitente_anterior').val('');
    });

    function montaTabelaDocsAnteriores(){
      let tbody = $('#tbl-docs-anteriores tbody').empty();
      DOCS_ANTERIORES.forEach((doc) => {
        let row = $('<tr>');
        [doc.id, doc.chave, doc.cnpj, doc.ie, doc.uf].forEach((value) => {
          row.append($('<td>').text(value));
        });
        let remover = $('<button type="button" class="btn btn-danger btn-sm" aria-label="Remover documento anterior">')
          .append($('<i class="fa fa-trash">'))
          .click(() => deleteDocAnterior(doc.id));
        row.append($('<td>').append(remover));
        tbody.append(row);
      });
      $('#docs_anteriores').val(JSON.stringify(DOCS_ANTERIORES));
    }

    function deleteDocAnterior(id){
      DOCS_ANTERIORES = DOCS_ANTERIORES.filter(doc => doc.id != id);
      DOCS_ANTERIORES.forEach((doc, index) => { doc.id = index + 1; });
      montaTabelaDocsAnteriores();
    }

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


    // MEDIDAS CTE >>>>>>>

    $('#addMedida').click(() => {
      let unidade_medida = $('#unidade_medida').val();
      let tipo_medida = $('#tipo_medida').val();
      let quantidade = $('#quantidade_carga').val();
      MEDIDAS.push({id: (MEDIDAS.length+1), unidade_medida: unidade_medida,
        tipo_medida: tipo_medida, quantidade: quantidade});
      console.log(MEDIDAS)

      let t = montaTabela();
      $('#prod tbody').empty().append(t)
    })

    function montaTabela(){
      const rows = MEDIDAS.map(v => {
        const row = $('<tr>');
        [v.id, unidadeMedidaExibe(v.unidade_medida), v.tipo_medida, v.quantidade].forEach(value => row.append($('<td>').text(value)));
        const remover = $('<button type="button" class="btn btn-danger btn-sm" aria-label="Remover medida">')
          .append($('<i class="fa fa-trash">')).click(() => deleteItem(v.id));
        return row.append($('<td>').append(remover));
      });
      $('#meds').val(JSON.stringify(MEDIDAS));
      habilitaBtnSalarCTe();
      return rows;
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
      $('#prod tbody').empty().append(t)

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
      $('#componentes tbody').empty().append(t)
      console.log(JSON.stringify(COMPONENTES))
      
      habilitaBtnSalarCTe();
    });

    function montaTabelaComponentes(preservarValores){
      let soma = 0;
      const rows = COMPONENTES.map(v => {
        const row = $('<tr>');
        [v.id, v.nome, v.valor].forEach(value => row.append($('<td>').text(value)));
        const remover = $('<button type="button" class="btn btn-danger btn-sm" aria-label="Remover componente">')
          .append($('<i class="fa fa-trash">')).click(() => deleteComponente(v.id));
        soma += parseFloat(String(v.valor).replace(',', '.')) || 0;
        return row.append($('<td>').append(remover));
      });
      $('#comps').val(JSON.stringify(COMPONENTES));
      if(!preservarValores){
        $('#valor_receber').val(soma.toFixed(2));
        $('#valor_transporte').val(soma.toFixed(2));
      }
      habilitaBtnSalarCTe();
      return rows;
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
      $('#componentes tbody').empty().append(t)

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
      let tipoDocumento = false;
      let inputs = false;

      console.log(CHAVES.length)

      if(CHAVES.length == 0 && $('#descOutros').val() != "" && $('#nDoc').val() != "" && $('#vDocFisc').val() != ""){
        tipoDocumento = true;
      }else if(CHAVES.length >= 1 && $('#descOutros').val() == "" && $('#nDoc').val() == "" && 
        $('#vDocFisc').val() == ""){
        tipoDocumento = true
      }

      if($('#prod_predominante').val() != "" && $('#valor_carga').val() != "" && $('#valor_transporte').val() != "" && $('#valor_receber').val() != ""){
        inputs = true;
      }

      console.log(tipoDocumento)

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
      atualizarTomadorOutros(false);

      recuperarListasCte();
      selectRemetente();
      selectDestinatario();

      CHAVES = ($('#chaves_nfe').val() || '').split(/[;,]/).filter(chave => /^[0-9]{44}$/.test(chave));
      if(!CHAVES.length){
        let chave = $('#chave_nfe').val();
        if(/^[0-9]{44}$/.test(chave)) adicionarChaveArray(chave);
      }
      montaHtmlChaveNfe(html => $('#chaves_list').html(html));
      habilitaBtnSalarCTe();

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

    $('form#veiculo_add_form').validate({
      rules: {
        placa: {
          required: true,
          minlength: 8
        },
        rntrc: {
          required: true,
          minlength: 8
        },
      },
      messages: {
        placa: {
          required: 'Campo obrigatório',
          minlength: 'Valor inválido'

        },
        modelo: {
          required: 'Campo obrigatório' ,
        },
        modelo: {
          required: 'Campo obrigatório' ,
        },
        marca: {
          required: 'Campo obrigatório' ,
        },
        cor: {
          required: 'Campo obrigatório' ,
        },
        tara: {
          required: 'Campo obrigatório' ,
        },
        uf: {
          required: 'Campo obrigatório' ,
        },
        capacidade: {
          required: 'Campo obrigatório' ,
        },
        proprietario_nome: {
          required: 'Campo obrigatório' ,
        },
        proprietario_documento: {
          required: 'Campo obrigatório' ,
        },
        proprietario_ie: {
          required: 'Campo obrigatório' ,
        },
        rntrc: {
          required: 'Campo obrigatório',
          minlength: 'Informe no minimo 8 caracteres'
        },
      }
    });
    $('#username').change( function(){
      if($('#show_username').length > 0){
        if($(this).val().trim() != ''){
          $('#show_username').html("{{__('lang_v1.your_username_will_be')}}: <b>" + $(this).val() + "{{$username_ext}}</b>");
        } else {
          $('#show_username').html('');
        }
      }
    });
  </script>
  @endsection

@extends('layouts.app')

@section('title', 'Manter CTe')

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
  <h1>Manter CTe</h1>
</section>

<!-- Main content -->
<section class="content">

  <div class="row">
    <div class="col-md-12">
      @component('components.widget')
      
      <input type="hidden" id="id" value="{{$cte->id}}" name="">
      <div class="col-md-12">
        <h4>Número CTe: <strong>{{$cte->cte_numero}}</strong></h4>
        <h4>Natureza de Operação: <strong>{{$cte->natureza->natureza}}</strong></h4>
        <h4>Remetente: <strong>{{$cte->remetente->cpf_cnpj}}</strong></h4>
        <h4>Destinatário: <strong>{{$cte->destinatario->name}}</strong></h4>
        <h4>Tomador: <strong>{{$cte->getTomador()}}</strong></h4>
        <h4>Produto predominante: <strong>{{$cte->produto_predominante}}</strong></h4>
        <h4>Data precisa de entrega: <strong>{{ \Carbon\Carbon::parse($cte->data_previsata_entrega)->format('d/m/Y')}}</strong></h4>
        <h4>Estado: <strong>{{$cte->estado}}</strong></h4>
        <h4>Chave: <strong>{{$cte->chave}}</strong></h4>
        <h4>Finalidade: <strong>{{ (int)($cte->tp_cte ?? 0) == 3 ? 'Substituto' : ((int)($cte->tp_cte ?? 0) == 1 ? 'Complemento' : 'Normal') }}</strong></h4>
      </div>

      @php
      $tpCteVinculo = (int)($cte->tp_cte ?? 0);
      $chaveVinculo = $tpCteVinculo == 3 ? $cte->chave_original : ($tpCteVinculo == 1 ? $cte->chave_complementada : null);
      @endphp

      @if($tpCteVinculo == 3 && $chaveVinculo)
      <div class="col-md-12">
        <div class="alert alert-warning">
          <strong>CT-e Substituto.</strong> Substitui a chave <code style="word-break: break-all;">{{ $chaveVinculo }}</code>.
          @if($cte->original)
          <a href="{{ route('cte.ver', [$cte->original->id]) }}">Ver CT-e original nº {{ $cte->original->cte_numero }}</a>
          @else
          <span class="text-muted">Original fora da base.</span>
          @endif
          @if($cte->ind_altera_toma)
          <span class="label label-warning">troca de tomador</span>
          @endif
        </div>
      </div>
      @endif

      @if($tpCteVinculo == 1 && $chaveVinculo)
      <div class="col-md-12">
        <div class="alert alert-info">
          <strong>CT-e Complemento.</strong> Complementa a chave <code style="word-break: break-all;">{{ $chaveVinculo }}</code>.
          @if($cte->original)
          <a href="{{ route('cte.ver', [$cte->original->id]) }}">Ver CT-e complementado nº {{ $cte->original->cte_numero }}</a>
          @else
          <span class="text-muted">Complementado fora da base.</span>
          @endif
        </div>
      </div>
      @endif

      @if($cte->substitutos && count($cte->substitutos) > 0)
      <div class="col-md-12">
        <div class="alert alert-success">
          @foreach($cte->substitutos as $sub)
          <p><strong>Substituído pelo <a href="{{ route('cte.ver', [$sub->id]) }}">CT-e nº {{ $sub->cte_numero }}</a>.</strong></p>
          @endforeach
        </div>
      </div>
      @endif

      <input type="hidden" id="cte_numero" value="{{$cte->cte_numero}}" name="">
      @php
      $estadoCte = strtolower((string) $cte->estado);
      @endphp
      
      <div class="clearfix"></div>


      <div class="col-md-12">
        <a class="btn btn-lg btn-primary" target="_blank" href="{{ route('cte.imprimir', [$cte->id]) }}" id="submit_user_button">Imprimir</a>
        <a class="btn btn-lg btn-info" target="_blank" href="{{ route('cte.baixarXml', [$cte->id]) }}" id="submit_user_button">Baixar XML</a>

        <a class="btn btn-lg btn-question" style="background: #673ab7; color: #fff" id="consultar">
        Consultar</a>
        
        @if($estadoCte != 'cancelado')
        <a class="btn btn-lg btn-danger" id="cancelar">Cancelar CTe</a>
        @endif

        @if($estadoCte == 'aprovado')
        <a class="btn btn-lg btn-warning" id="corrigir">Corrigir CT-e</a>
        @endif

        @if($cte->sequencia_cce > 0)
        <a class="btn btn-lg btn-question" style="background: #90caf9; color: #fff" target="_blank" href="{{ route('cte.imprimirCorrecao', [$cte->id]) }}" id="submit_user_button">Imprimir Correção</a>
        <a class="btn btn-lg btn-question" style="background: #546e7a; color: #fff" target="_blank" href="{{ route('cte.baixarXmlCorrecao', [$cte->id]) }}" id="submit_user_button">Baixar XML Correção</a>
        @endif

        @if($estadoCte == 'cancelado')
        <a class="btn btn-lg btn-question" style="background: #d84315; color: #fff" target="_blank" href="{{ route('cte.imprimirCancelamento', [$cte->id]) }}" id="submit_user_button">Imprimir Cancelamento</a>
        @endif
      </div>
      
      @endcomponent
    </div>

  </div>

  

  <input type="hidden" id="token" value="{{csrf_token()}}" name="">
  <input type="hidden" id="id" value="{{$cte->id}}" name="">

  <br>
  <div class="row" id="action" style="display: none">
    <div class="col-md-12">
      @component('components.widget')
      <div class="info-box-content">
        <div class="col-md-4 col-md-offset-4">

          <span class="info-box-number total_purchase">
            <strong id="acao"></strong>
            <i class="fas fa-spinner fa-pulse fa-spin fa-fw margin-bottom"></i></span>
          </div>
        </div>
        @endcomponent

      </div>
    </div>

    @stop



    @section('javascript')
    <script type="text/javascript">
      // swal("Good job!", "You clicked the button!", "success");
      var path = window.location.protocol + '//' + window.location.host

      function mensagemErroCte(response){
        let data = response.responseJSON || response.responseText || response

        if(typeof data === 'string'){
          return data
        }

        if(data && data.errors){
          let mensagens = []
          Object.keys(data.errors).forEach(campo => {
            mensagens = mensagens.concat(data.errors[campo])
          })
          return mensagens.join('\n')
        }

        if(data && data.data){
          if(typeof data.data === 'string'){
            return data.data
          }

          if(data.data.infEvento && data.data.infEvento.xMotivo){
            return data.data.infEvento.xMotivo
          }

          if(data.data.retEvento && data.data.retEvento.infEvento && data.data.retEvento.infEvento.xMotivo){
            return data.data.retEvento.infEvento.xMotivo
          }
        }

        if(data && data.erro && data.data){
          return typeof data.data === 'string' ? data.data : JSON.stringify(data.data)
        }

        if(data && data.infEvento && data.infEvento.xMotivo){
          return data.infEvento.xMotivo
        }

        if(data && data.protCTe && data.protCTe.infProt && data.protCTe.infProt.xMotivo){
          return data.protCTe.infProt.xMotivo
        }

        if(data && data.retConsSitCTe && data.retConsSitCTe.xMotivo){
          return data.retConsSitCTe.xMotivo
        }

        if(data && data.retConsSitCTe && data.retConsSitCTe.protCTe && data.retConsSitCTe.protCTe.infProt && data.retConsSitCTe.protCTe.infProt.xMotivo){
          return data.retConsSitCTe.protCTe.infProt.xMotivo
        }

        if(data && data.xMotivo){
          return (data.cStat ? '[' + data.cStat + '] ' : '') + data.xMotivo
        }

        if(data && data.message){
          return data.message
        }

        return 'Não foi possível concluir a operação.'
      }

      function dadosConsultaCte(response){
        let data = response.responseJSON || response.responseText || response

        if(typeof data === 'string'){
          return {motivo: data}
        }

        if(data && data.retConsSitCTe){
          data = data.retConsSitCTe
        }

        let prot = data && data.protCTe && data.protCTe.infProt ? data.protCTe.infProt : null

        return {
          cStat: data && data.cStat ? String(data.cStat) : (prot && prot.cStat ? String(prot.cStat) : ''),
          motivo: data && data.xMotivo ? data.xMotivo : (prot && prot.xMotivo ? prot.xMotivo : mensagemErroCte(response)),
          chave: prot && prot.chCTe ? prot.chCTe : ''
        }
      }

      function consultaEstaOk(cStat){
        return ['100', '101', '135'].indexOf(String(cStat)) >= 0
      }

      $('#cancelar').click(() => {
        let cte_numero = $('#cte_numero').val();
        swal({
          text: 'Cancelamento de CTe '+cte_numero+'.',
          content: "input",
          button: {
            text: "Cancelar!",
            closeModal: false,
            type: 'error'
          },
        })
        .then(v => {
          if (!v) swal("Erro!", "Informe um motivo para Cancelamento!", "error");
          else{
            let token = $('#token').val();
            let id = $('#id').val();
            $.ajax
            ({
              type: 'POST',
              data: {
                id: id,
                _token: token,
                justificativa: v
              },
              url: '{{ route("cte.cancelar") }}',
              dataType: 'json',
              success: function(e){
                console.log("retorno", e)

                swal("sucesso", mensagemErroCte(e), "success")
                .then(() => {
                  window.open('{{ route("cte.imprimirCancelamento", [$cte->id]) }}')
                  location.reload()
                });

              }, error: function(e){
                console.log("erro",e)
                swal("Erro ao cancelar", mensagemErroCte(e), "error");

              }

            })
          }         


        })
        
        .catch(err => {
          if (err) {
            swal("Erro", "Algo não ocorreu bem!", "error");
          } else {
            swal.stopLoading();
            swal.close();
          }
        });
      })

      $('#corrigir').click(() => {
        let cte_numero = $('#cte_numero').val();
        swal({
          title: 'Carta de correção para CT-e '+cte_numero+'.',
          text: 'Informe a correção. Ex.: Onde se lê ..., leia-se ...\nNão corrija valores/impostos, partes do CT-e ou data de emissão/saída.',
          content: "input",
          button: {
            text: "Corrigir!",
            closeModal: false,
            type: 'error'
          },
        })
        .then(v => {
          if (!v) swal("Erro!", "Informe a correção!", "error");
          else if(v.length > 500){
            swal("Erro!", "Informe no máximo 500 caracteres.", "error");
          }
          else{
            let token = $('#token').val();
            let id = $('#id').val();
            $.ajax
            ({
              type: 'POST',
              data: {
                id: id,
                _token: token,
                justificativa: v
              },
              url: '{{ route("cte.corrigir") }}',
              dataType: 'json',
              success: function(e){
                console.log(e)

                swal("sucesso", mensagemErroCte(e), "success")
                .then(() => {
                  window.open('{{ route("cte.imprimirCorrecao", [$cte->id]) }}')
                  location.reload()
                });

              }, error: function(e){
                console.log(e)
                swal("Erro ao corrigir", mensagemErroCte(e), "error");

              }

            })
          }
        })
        .catch(err => {
          if (err) {
            swal("Erro", "Algo não ocorreu bem!", "error");
          } else {
            swal.stopLoading();
            swal.close();
          }
        });
      })

      $('#consultar').click(() => {

        let token = $('#token').val();
        let id = $('#id').val();

        $.ajax
        ({
          type: 'POST',
          data: {
            id: id,
            _token: token
          },
          url: '{{ route("cte.consultar") }}',
          dataType: 'json',
	          success: function(e){
	            console.log(e)

              if(e && e.erro){
                swal("Erro ao consultar", mensagemErroCte(e), "error")
                return
              }

              let consulta = dadosConsultaCte(e)
              let texto = (consulta.cStat ? '[' + consulta.cStat + '] ' : '') + consulta.motivo + (consulta.chave ? ' - Chave: ' + consulta.chave : '')

              if(!consultaEstaOk(consulta.cStat)){
                swal("Atenção", texto, "warning")
                return
              }

	            swal("sucesso", "Resultado: " + texto, "success")
	            .then(() => {
	            });


	          }, error: function(e){
	            console.log(e)
	            swal("Erro ao consultar", mensagemErroCte(e), "error");

	          }

        })
      })


    </script>
    @endsection

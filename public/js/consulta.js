$('#cpf_cnpj').blur((event) => {

  let doc = $('#cpf_cnpj').val()
  $.get($('#path-consulta').val(), {doc: doc} )
  .done((success) => {
    console.log(success)
    BuscaCNPJ(doc)
  })
  .fail((err) => {
    console.log(err)
    if(err.status == 401){
      swal("Alerta", "Já existe um cadastro com este documento " + err.responseJSON.name, "warning")
      $('#cpf_cnpj').val('')
    }else{
      swal("Erro", "Algo errado ao consultar", "error")
    }
  })
});

function BuscaCNPJ(cpf_cnpj){
  cpf_cnpj = cpf_cnpj.replace(/[^0-9]/g,'')

  if(cpf_cnpj.length < 14){
    $("#consumidor_final").val(1).change();
    $("#contribuinte").val(0).change();
  }else{
    $("#consumidor_final").val(0).change();
    $("#contribuinte").val(1).change();
    fetch('https://publica.cnpj.ws/cnpj/' + cpf_cnpj)
      .then(response => {
        if (!response.ok) {
          return response.json().then(err => { throw err; });
        }
        return response.json();
      })
      .then(data => {
        console.log(data)
        if (data != null) {

          let ie = ''
          if (data.estabelecimento.inscricoes_estaduais.length > 0) {
            ie = data.estabelecimento.inscricoes_estaduais[0].inscricao_estadual

            if(data.estabelecimento.inscricoes_estaduais[0].ativo == false){
              swal({
                icon: 'warning',
                title: 'ALERTA!',
                text: 'Inscrição Estadual não habilitada ou irregular para NFe.',
              })
            }
          }

          $('#ie_rg').val(ie)
          $('#name').val(data.razao_social)
          $('#nome_fantasia').val(data.estabelecimento.nome_fantasia)
          $("#rua").val(data.estabelecimento.tipo_logradouro + " " + data.estabelecimento.logradouro)
          $("#rua_entrega").val(data.estabelecimento.tipo_logradouro + " " + data.estabelecimento.logradouro)
          $('#numero').val(data.estabelecimento.numero)
          $('#numero_entrega').val(data.estabelecimento.numero)
          $("#bairro").val(data.estabelecimento.bairro);
          $("#bairro_entrega").val(data.estabelecimento.bairro);
          $('#complement').val(data.estabelecimento.complemento ? data.estabelecimento.complemento.replace(/( )+/g, " ") : "");

          $('#email').val(data.estabelecimento.email)
          var cep = data.estabelecimento.cep.replace(/[^\d]+/g, '');

          $('#cep').val(cep.substring(0, 5) + '-' + cep.substring(5, 9))
          $('#cep_entrega').val(cep.substring(0, 5) + '-' + cep.substring(5, 9))

          var $option = $("<option selected></option>").val(data.estabelecimento.cidade.id).text(data.estabelecimento.cidade.nome + " (" + data.estabelecimento.estado.sigla + ")");

          $('#cidade').append($option).trigger('change');
          $('#cidade').val(data.estabelecimento.cidade.id).change();

          $('#cidade_entrega').val(data.estabelecimento.cidade.id).change();    
          $('#cidade_entrega').append($option).trigger('change');
        } else {
          console.log(data)
          swal('Algo deu errado', '', 'warning')
        }
      })
      .catch(err => {
        let msg = err?.detalhes || 'Erro ao consultar CNPJ';
        swal('Algo deu errado', msg, 'warning')
      })
  }
}
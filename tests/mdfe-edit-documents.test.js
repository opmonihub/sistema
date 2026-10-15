'use strict';

// DOM e dados sintéticos: executa os trechos verdadeiros da view, sem rede/banco.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const {JSDOM} = require(process.env.MDFE_TEST_JSDOM || 'jsdom');
const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root,'resources/views/mdfe/create.blade.php'),'utf8');
function fiscalKey(model, number = 1) {
    const body = '35261000000000000100' + model + '001' + String(number).padStart(9,'0') + '112345678';
    let sum=0, weight=2;
    for(let i=42;i>=0;i--) {sum+=Number(body[i])*weight;weight=weight===9?2:weight+1;}
    const digit=11-sum%11;
    return body + (digit>=10?0:digit);
}
test('JavaScript completo da view é sintaticamente válido após diretivas Blade',()=>{
    const script=source.match(/<script type="text\/javascript">([\s\S]*?)<\/script>/)[1];
    const compiled=script.replace(/^\s*@(if|elseif|else|endif).*$/gm,'');
    assert.doesNotThrow(()=>new (require('node:vm').Script)(compiled));
});

const nfe = fiscalKey('55');
const cte = fiscalKey('57');
function saved(overrides = {}) {
    return Object.assign({id:1,tp_unid_transp:1,id_unid_transp:'ABC1234',quantidade_rateio:'1.00',cidade_id:10,
        unidade_carga:{id_unidade_carga:'CARGA1',quantidade_rateio:'1.00'},nfe:null,cte:{chave:cte,seg_cod_barras:''},
        lacres_transp:[{numero:'LT1'}],lacres_unid_carga:[{numero:'LU1'}]},overrides);
}
function fixture(input, oldInput = '') {
    const dom = new JSDOM('<!doctype html><form id="mdfe_add_form"><input id="init_descargas"><input id="old_descargas"><input id="descargas" name="descargas"><button id="finalizar"></button></form><div id="mdfe_descargas_error" hidden></div><table id="descarregamento_table"><tbody></tbody></table>',{runScripts:'outside-only'});
    const window = dom.window;
    window.eval(fs.readFileSync(path.join(root,'resources/plugins/jquery-validation-1.16.0/lib/jquery-3.1.1.js'),'utf8'));
    const $ = window.jQuery;
    $('#init_descargas').val(typeof input === 'string' ? input : JSON.stringify(input));
    $('#old_descargas').val(typeof oldInput === 'string' ? oldInput : JSON.stringify(oldInput));
    window.alerts = [];
    window.swal = (...args) => window.alerts.push(args);
    window.habilitaBtnSalarMdfe = () => {};
    window.CIDADES = {10:'Cidade sintética / SP'};
    window.LACRESTRANSPORTE = [];
    window.LACRESUNIDADECARGA = [];
    window.DESCARGAS = [];
    window.PROXIMA_DESCARGA = 1;
    window.DESCARGAS_CARREGADAS = false;
    window.DESCARGAS_ERRO = false;
    function loadFunction(name) {
        const start = source.indexOf('    function ' + name + '(');
        assert.ok(start >= 0,name + ' existe na view');
        const end = source.indexOf('\n    }',start) + '\n    }'.length;
        window.eval(source.slice(start,end));
    }
    ['montaHtmlDescarregamento','preparaLacres','__set'].forEach(loadFunction);
    if (source.includes('    function validaChavesDocumentos(')) loadFunction('validaChavesDocumentos');
    const initStart = source.indexOf('      try {',source.indexOf('// iniciando descargas'));
    const initEnd = source.indexOf('      setTimeout(',initStart);
    window.eval(source.slice(initStart,initEnd));
    const submitStart = source.indexOf("    $(document).on('click', '#finalizar'");
    const submitEnd = source.indexOf('\n    })',submitStart) + '\n    })'.length;
    let submissions = 0;
    $.fn.validate = () => {};
    $.fn.valid = () => true;
    $.fn.submit = () => { submissions++; };
    window.eval(source.slice(submitStart,submitEnd));
    return {dom,window,$,loadFunction,submissions:()=>submissions,close:()=>window.close(),payload:()=>JSON.parse($('#descargas').val())};
}

for (const [name,row] of [
    ['somente CT-e',saved()],
    ['somente NF-e',saved({nfe:{chave:nfe,seg_cod_barras:''},cte:null})],
    ['NF-e e CT-e',saved({nfe:{chave:nfe,seg_cod_barras:''}})],
    ['contingência',saved({cte:{chave:'',seg_cod_barras:'3'.repeat(44)}})],
    ['referência malformada preservada',saved({cte:{chave:'D'+'2'.repeat(43),seg_cod_barras:''}})],
]) {
    test('edição reidrata '+name+' sem perder documentos',()=>{
        const f = fixture([row]);
        try {
            assert.equal(f.$('#descarregamento_table tbody tr').length,1);
            const restored = f.payload()[0];
            assert.equal(restored.chave_cte,row.cte ? row.cte.chave : '');
            assert.equal(restored.chave_nfe,row.nfe ? row.nfe.chave : '');
            assert.deepEqual(restored.lacres_transporte,['LT1']);
            assert.deepEqual(restored.lacres_unidade_carga,['LU1']);
            f.$('#finalizar').trigger('click');
            assert.equal(f.submissions(),name === 'referência malformada preservada' ? 0 : 1);
        } finally { f.close(); }
    });
}

test('selecionar data ou veículo por último habilita Salvar sem keyup extra',()=>{
    const f=fixture([saved()]);
    try {
        const fields=['uf_inicio','uf_fim','data_inicio_viagem','lac_rodo','cnpj_contratante','quantidade_carga','valor_carga','veiculo_tracao_id','condutor_nome','condutor_cpf'];
        fields.forEach(id=>f.$('#mdfe_add_form').append('<input class="type-ref" id="'+id+'" value="ok">'));
        f.window.CIDADESADICIONADAS=[10];
        ['validaInputs','validaFormulario','habilitaBtnSalarMdfe'].forEach(f.loadFunction);
        const start=source.indexOf("    $('.type-ref')");
        const end=source.indexOf("    $('#add-cidade')",start);
        f.window.eval(source.slice(start,end));
        for (const [id,event] of [['data_inicio_viagem','dp.change'],['veiculo_tracao_id','change'],['uf_fim','change']]) {
            f.$('#'+id).val(''); f.window.habilitaBtnSalarMdfe();
            assert.equal(f.$('#finalizar').prop('disabled'),true);
            f.$('#'+id).val('ok').trigger(event);
            assert.equal(f.$('#finalizar').prop('disabled'),false,id+' revalida sem digitação');
        }
    } finally {f.close();}
});

test('Adicionar rejeita letras, tamanho, modelo e DV incorretos sem descartar a lista',()=>{
    const f=fixture([saved()]);
    try {
        ['validaChavesDocumentos','validaDescarregamento'].forEach(f.loadFunction);
        const values={tipo_unidade_transporte:'1',id_unidade_transporte:'ABC1234',qtd_rateio_transporte:'1.00',id_unidade_carga:'CARGA1',qtd_rateio_unidade:'1.00',chave_nfe:'',segunda_nfe:'',chave_cte:'',segunda_cte:'',municipio_descarregamento:'10'};
        Object.entries(values).forEach(([id,value])=>f.$('#mdfe_add_form').append(f.$('<input>').attr('id',id).val(value)));
        f.$('#mdfe_add_form').append('<button id="add-descarregamento" type="button"></button>');
        const start=source.indexOf("    $('#add-descarregamento').click");
        const end=source.indexOf('\n    })',start)+'\n    })'.length;
        f.window.eval(source.slice(start,end));
        assert.ok(f.window.validaChavesDocumentos({chave_cte:cte+'\n'}),'Payload com quebra final é rejeitado');
        const invalid=['D'+cte.slice(1),cte.slice(0,43),cte+'0',nfe,cte.slice(0,43)+(cte[43]==='9'?'0':'9')];
        for(const key of invalid) {
            f.$('#chave_cte').val(key); f.$('#add-descarregamento').trigger('click');
            assert.equal(f.payload().length,1);
            assert.ok(f.window.alerts.length>0);
        }
        f.$('#chave_cte').val(cte); f.$('#add-descarregamento').trigger('click');
        assert.equal(f.payload().length,2);
        assert.equal(new Set(f.payload().map(row=>row.rand)).size,2);
    } finally {f.close();}
});

test('edição restaura 98 descargas CT-e e IDs não colidem',()=>{
    const f=fixture(Array.from({length:98},(_,i)=>saved({id:i+1,cte:{chave:fiscalKey('57',i+1),seg_cod_barras:''}})));
    try {
        assert.equal(f.$('#descarregamento_table tbody tr').length,98);
        assert.equal(f.payload().length,98);
        assert.equal(new Set(f.payload().map(row=>row.rand)).size,98);
        assert.equal(f.payload()[97].chave_cte,fiscalKey('57',98));
        f.$('#finalizar').trigger('click');
        assert.equal(f.submissions(),1);
    } finally {f.close();}
});

test('relações opcionais ausentes não fazem a tabela desaparecer',()=>{
    const f=fixture([saved({unidade_carga:null,lacres_transp:null,lacres_unid_carga:null,cidade_id:999})]);
    try {
        assert.equal(f.$('#descarregamento_table tbody tr').length,1);
        assert.equal(f.payload()[0].chave_cte,cte);
        assert.equal(f.payload()[0].id_unidade_carga,'');
        assert.ok(!f.$('#descarregamento_table').text().includes('undefined'));
    } finally {f.close();}
});

for(const input of ['{json inválido',{},[saved(),null]]) {
    test('falha de carga bloqueia submit e não envia lista parcial: '+JSON.stringify(input).slice(0,35),()=>{
        const f=fixture(input);
        try {
            f.$('#finalizar').trigger('click');
            assert.equal(f.submissions(),0);
            assert.equal(f.$('#descargas').val(),'');
            assert.ok(f.window.alerts.length || !f.$('#mdfe_descargas_error').prop('hidden'));
        } finally {f.close();}
    });
}

test('lista vazia não permite salvar manifesto sem documentos',()=>{
    const f=fixture([]);
    try {f.$('#finalizar').trigger('click');assert.equal(f.submissions(),0);} finally{f.close();}
});

for (const empty of ['[]', '', null]) {
test('retorno de validação preserva listas auxiliares vazias: '+JSON.stringify(empty),()=>{
    const f=fixture([saved()]);
    try {
        f.$('#mdfe_add_form').append('<input id="old_listas"><input id="municipios_descarregamentos"><input id="percurso"><input id="ciots"><input id="vales">');
        const pending={municipios_descarregamentos:'[20]',percurso:empty,ciots:'[{"codigo":"NOVO","doc_ciot":"123"}]',vales:empty};
        f.$('#old_listas').val(JSON.stringify(pending));
        f.window.CIDADESADICIONADAS=[10];f.window.PERCURSO=['SP'];f.window.CIOT=[];f.window.VALES=[{numero_compra:'ANTIGO'}];
        for(const name of ['montaHtmlCidades','montaHtmlUF','montaHtmlCiot','montaHtmlVale']) f.window[name]=cb=>cb('');
        const start=source.indexOf('      //iniciando munucipios');
        const end=source.indexOf('      // iniciando descargas',start);
        f.window.eval(source.slice(start,end));
        assert.equal(f.$('#municipios_descarregamentos').val(),'[20]');
        assert.equal(f.$('#percurso').val(),'[]');
        assert.equal(f.$('#ciots').val(),pending.ciots);
        assert.equal(f.$('#vales').val(),'[]');
        assert.equal(f.window.DESCARGAS_ERRO,false);
    } finally {f.close();}
});
}

test('listas auxiliares restauradas renderizam conteúdo não confiável somente como texto',()=>{
    const f=fixture([saved()]);
    try {
        f.$('#mdfe_add_form').append('<table id="ciot_table"><tbody></tbody></table><table id="vale_table"><tbody></tbody></table>');
        f.window.CIOT=[{codigo:'<img src=x>',doc_ciot:'<svg/onload=alert(1)>'}];
        f.window.VALES=[{cnpj_fornecedor:'<img src=x>',doc_pagador:'123',numero_compra:'\' onclick=alert(1) ',valor:'1'}];
        ['montaHtmlCiot','montaHtmlVale'].forEach(f.loadFunction);
        f.window.montaHtmlCiot(html=>f.$('#ciot_table tbody').html(html));
        f.window.montaHtmlVale(html=>f.$('#vale_table tbody').html(html));
        assert.equal(f.$('#ciot_table img, #ciot_table svg, #vale_table img').length,0);
        assert.ok(f.$('#ciot_table').text().includes('<img src=x>'));
        let removedCiot, removedVale;
        f.window.removeCiot=value=>{removedCiot=value;};f.window.removeVale=value=>{removedVale=value;};
        f.window.eval(f.$('#ciot_table i').attr('onclick'));
        f.window.eval(f.$('#vale_table i').attr('onclick'));
        assert.equal(removedCiot,f.window.CIOT[0].codigo);
        assert.equal(removedVale,f.window.VALES[0].numero_compra);
    } finally {f.close();}
});

test('vínculo legado sem unidade informa como corrigir antes de salvar',()=>{
    const f=fixture([saved({unidade_carga:null})]);
    try {
        f.$('#finalizar').trigger('click');
        assert.equal(f.submissions(),0);
        assert.ok(f.window.alerts.some(args=>String(args[1]).includes('remova e adicione')));
        assert.equal(f.payload().length,1);
    } finally {f.close();}
});

test('retorno de validação restaura a lista enviada em vez dos vínculos antigos',()=>{
    const pending = {rand:5,tipo_unidade_transporte:1,id_unidade_transporte:'ABC1234',qtd_rateio_transporte:'1,50',id_unidade_carga:'CARGA1',qtd_rateio_unidade:'1,50',chave_nfe:nfe,segunda_nfe:'',chave_cte:'',segunda_cte:'',municipio_descarregamento:10,lacres_transporte:['NOVO'],lacres_unidade_carga:[]};
    const f = fixture([saved()], [pending]);
    try {
        assert.equal(f.payload().length,1);
        assert.equal(f.payload()[0].chave_nfe,nfe);
        assert.equal(f.payload()[0].chave_cte,'');
        assert.deepEqual(f.payload()[0].lacres_transporte,['NOVO']);
    } finally {f.close();}
});

test('retorno com JSON rejeitado não retorna silenciosamente à lista persistida',()=>{
    const f = fixture([saved()], '{inválido');
    try {assert.equal(f.$('#descargas').val(),'');assert.equal(f.window.DESCARGAS_ERRO,true);} finally {f.close();}
});

test('remoção do último documento atualiza hidden e estado do botão imediatamente',()=>{
    const f=fixture([saved()]);
    try {
        const fields=['uf_inicio','uf_fim','data_inicio_viagem','lac_rodo','cnpj_contratante','quantidade_carga','valor_carga','veiculo_tracao_id','condutor_nome','condutor_cpf'];
        fields.forEach(id=>f.$('#mdfe_add_form').append('<input id="'+id+'" value="ok">'));
        f.window.CIDADESADICIONADAS=[10];
        ['validaInputs','validaFormulario','habilitaBtnSalarMdfe','removeDescarregamento'].forEach(f.loadFunction);
        f.window.habilitaBtnSalarMdfe();
        assert.equal(f.$('#finalizar').prop('disabled'),false);
        f.window.removeDescarregamento(1);
        assert.deepEqual(f.payload(),[]);
        assert.equal(f.$('#finalizar').prop('disabled'),true);
    } finally {f.close();}
});

test('tabela apresenta contingência e lacres da própria linha como texto seguro',()=>{
    const f=fixture([saved({cte:{chave:'',seg_cod_barras:'3'.repeat(44)},lacres_transp:[{numero:'<img src=x>'}]}),saved({id:2,lacres_transp:[{numero:'OUTRO'}]})]);
    try {
        const rows=f.$('#descarregamento_table tbody tr');
        assert.ok(rows.eq(0).text().includes('3'.repeat(44)));
        assert.ok(rows.eq(0).text().includes('<img src=x>'));
        assert.ok(!rows.eq(0).text().includes('OUTRO'));
        assert.equal(f.$('#descarregamento_table img').length,0);
    } finally {f.close();}
});

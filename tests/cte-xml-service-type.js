const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

// Executa o JavaScript real sem navegador, rede ou banco de dados.
const source = fs.readFileSync(path.join(__dirname, '../resources/views/cte/register_xml.blade.php'), 'utf8');
const start = source.indexOf('    // Tipo de Serviço - documentos anteriores');
const end = source.indexOf("    $('#addChave').click", start);
assert(start >= 0 && end > start, 'O formulário deve implementar os documentos anteriores.');

const elements = new Map();
function element() {
    return {
        value: '', children: [], handlers: {}, visible: false,
        val(value) { if (arguments.length === 0) return this.value; this.value = value; return this; },
        on(event, handler) { this.handlers[event] = handler; return this; },
        trigger(event) { this.handlers[event].call(this); return this; },
        toggle(visible) { this.visible = visible; return this; },
        click(handler) { this.handlers.click = handler; return this; },
        empty() { this.children = []; return this; },
        append(child) { this.children.push(child); return this; },
        text(value) { this.content = String(value); return this; }
    };
}
function $(selector) {
    if (typeof selector !== 'string') return selector;
    if (selector.startsWith('<')) return element();
    if (!elements.has(selector)) elements.set(selector, element());
    return elements.get(selector);
}
function keyFor(uf = '35', model = '57', number = '000000123') {
    const base = uf + '2610' + '12345678000195' + model + '001' + number + '1' + '12345678';
    let sum = 0, weight = 2;
    for (let i = 42; i >= 0; i--) { sum += Number(base[i]) * weight; weight = weight === 9 ? 2 : weight + 1; }
    const digit = 11 - sum % 11;
    return base + (digit >= 10 ? 0 : digit);
}
const alerts = [];
$('#tipo_servico').val('0');
const context = vm.createContext({ $, swal: (...args) => alerts.push(args) });
vm.runInContext(source.slice(start, end), context);

for (const value of ['0', '1', '2', '3', '4']) {
    $('#tipo_servico').val(value).trigger('change');
    assert.strictEqual($('#div-doc-anterior').visible, ['1', '2', '3'].includes(value));
}
console.log('PASS visibilidade dos documentos anteriores para todos os tipos de serviço');

function add(chave, ie) {
    $('#chave_cte_anterior').val(chave);
    $('#ie_emitente_anterior').val(ie);
    $('#addDocAnterior').trigger('click');
}
const chave = keyFor();
const badDigit = chave.slice(0, 43) + ((Number(chave[43]) + 1) % 10);
for (const [key, ie] of [
    ['123', '12345'], ['a'.repeat(44), '12345'], [chave + '\n', '12345'], [badDigit, '12345'],
    [keyFor('35', '55'), '12345'], [keyFor('99'), '12345'],
    [chave, '   '], [chave, '<img src=x onerror=alert(1)>'], [chave, '1'],
    [chave, '1'.repeat(15)], [chave, '12.345'], [chave, 'ISENTA']
]) {
    const before = alerts.length;
    add(key, ie);
    assert.strictEqual(alerts.length, before + 1, `Deve rejeitar ${key}/${ie}`);
    assert.strictEqual($('#tbl-docs-anteriores tbody').children.length, 0);
}
console.log('PASS rejeição de chave inválida, DV, modelo, UF e IE fora do domínio');

function submit() {
    let blocked = false;
    const handler = $('#cte_add_form').handlers.submit;
    assert.strictEqual(typeof handler, 'function', 'O formulário deve validar documentos antes de submeter.');
    handler({ preventDefault() { blocked = true; } });
    return blocked;
}
for (const type of ['0', '1', '2', '3', '4']) {
    $('#tipo_servico').val(type);
    assert.strictEqual(submit(), ['1', '2', '3'].includes(type), `Obrigatoriedade tipo ${type}`);
}
console.log('PASS bloqueio de submissão sem documento para tipos 1/2/3, preservando 0/4');

add(chave, ' 12345 ');
const docs = JSON.parse($('#docs_anteriores').val());
assert.deepStrictEqual(docs, [{ id: 1, chave, cnpj: '12345678000195', uf: 'SP', ie: '12345' }]);
assert.strictEqual($('#chave_cte_anterior').val(), '');
assert.strictEqual($('#ie_emitente_anterior').val(), '');
for (const type of ['0', '1', '2', '3', '4']) { $('#tipo_servico').val(type); assert.strictEqual(submit(), false); }
context.DOCS_ANTERIORES[0].ie = '<img src=x>';
assert.strictEqual(submit(), true, 'Revalidar lista também na submissão');
context.DOCS_ANTERIORES[0].ie = '12345';
add(keyFor('43'), ' ISENTO ');
add(keyFor('53'), '12');
add(keyFor('41'), '1'.repeat(14));
assert.strictEqual(JSON.parse($('#docs_anteriores').val())[1].ie, 'ISENTO');
$('#tbl-docs-anteriores tbody').children[0].children[5].children[0].trigger('click');
const remaining = JSON.parse($('#docs_anteriores').val());
assert.strictEqual(remaining.length, 3);
assert.strictEqual(remaining[0].id, 1);
while (context.DOCS_ANTERIORES.length) context.deleteDocAnterior(1);
assert.strictEqual($('#docs_anteriores').val(), '[]');
assert.strictEqual($('#tbl-docs-anteriores tbody').children.length, 0);
console.log('PASS serialização, identidade derivada, ISENTO/limites IE, exclusão e reindexação');

for (const view of ['register_xml', 'create', 'edit']) {
    const code = fs.readFileSync(path.join(__dirname, `../resources/views/cte/${view}.blade.php`), 'utf8');
    const from = code.indexOf('    function montaTabelaDocsAnteriores(){');
    const until = code.indexOf('    function deleteDocAnterior(', from);
    const sandbox = vm.createContext({ $, DOCS_ANTERIORES: [{ id: 1, chave: '<img src=x>', cnpj: '<svg>', ie: '<script>', uf: '<iframe>' }] });
    vm.runInContext(code.slice(from, until), sandbox);
    sandbox.montaTabelaDocsAnteriores();
    const cells = $('#tbl-docs-anteriores tbody').children[0].children;
    assert.deepStrictEqual(cells.slice(0, 5).map(c => c.content), ['1', '<img src=x>', '<svg>', '<script>', '<iframe>'], `Renderizar texto em ${view}`);
}
console.log('PASS renderização segura como texto nas três tabelas, inclusive dados legados maliciosos');

for (const view of ['register_xml', 'create', 'edit']) {
    const code = fs.readFileSync(path.join(__dirname, `../resources/views/cte/${view}.blade.php`), 'utf8');
    const from = code.indexOf('    // Recuperar listas completas');
    const until = code.indexOf('    // Fim da recuperação de listas', from);
    assert(from >= 0 && until > from, `${view} deve recuperar listas no JavaScript real`);
    let measuresRendered, componentsRendered, docsRendered;
    const hydration = vm.createContext({ $, MEDIDAS: [], COMPONENTES: [], DOCS_ANTERIORES: [],
        montaTabela() { measuresRendered = this.MEDIDAS; return ''; },
        montaTabelaComponentes(preserve) { assert.strictEqual(preserve, true); componentsRendered = this.COMPONENTES; return ''; },
        montaTabelaDocsAnteriores() { docsRendered = this.DOCS_ANTERIORES; },
    });
    vm.runInContext(code.slice(from, until), hydration);
    $('#meds').val(JSON.stringify([{id: 99, unidade_medida: '01', tipo_medida: '<img>', quantidade: '4,5'}]));
    $('#comps').val(JSON.stringify([{nome: '<script>', valor: '40,00'}]));
    $('#docs_anteriores').val(JSON.stringify([{chave, ie: '12345', uf: 'SP', cnpj: '12345678000195'}]));
    hydration.recuperarListasCte();
    assert.strictEqual(hydration.MEDIDAS[0].unidade_medida, '01', `${view}: não reconverter old como modelo`);
    assert.strictEqual(hydration.MEDIDAS[0].quantidade, '4,5');
    assert.strictEqual(hydration.MEDIDAS[0].id, 1);
    assert.strictEqual(hydration.COMPONENTES[0].nome, '<script>');
    assert.strictEqual(hydration.DOCS_ANTERIORES[0].chave, chave);
    $('#meds').val(JSON.stringify([{cod_unidade: '02', tipo_medida: 'PESO', quantidade_carga: '9'}]));
    hydration.recuperarListasCte();
    assert.strictEqual(hydration.MEDIDAS[0].unidade_medida, '02');
    for (const selector of ['#meds', '#comps', '#docs_anteriores']) $(selector).val('[]');
    hydration.recuperarListasCte();
    assert.strictEqual(hydration.MEDIDAS.length + hydration.COMPONENTES.length + hydration.DOCS_ANTERIORES.length, 0, `${view}: [] sobrescreve lista anterior`);
    for (const [selector, variable, valid] of [
        ['#comps', 'COMPONENTES', {nome: 'Frete', valor: '1'}],
        ['#meds', 'MEDIDAS', {unidade_medida: '01', tipo_medida: 'PESO', quantidade: '2'}],
        ['#docs_anteriores', 'DOCS_ANTERIORES', {chave, ie: '12', cnpj: '12345678000195', uf: 'SP'}]
    ]) {
        for (const invalid of ['{', '{}', 'null', '[{},null]', JSON.stringify([valid, {}])]) {
            $(selector).val(invalid);
            hydration.recuperarListasCte();
            assert.strictEqual(hydration[variable].length, 0, `${view}/${variable}: lista inválida inteira, nunca parcial`);
        }
    }
    if (view === 'edit') assert(!code.includes('setTimeout('), 'Init da edição não pode sobrescrever old por timer');
}
console.log('PASS recuperação integral de listas, [] explícito, unidade já normalizada e init sem timers');
for (const view of ['register_xml', 'create', 'edit']) {
    const code = fs.readFileSync(path.join(__dirname, `../resources/views/cte/${view}.blade.php`), 'utf8');
    const from = code.indexOf('    function montaTabela(){');
    const until = code.indexOf('    // COMPONENTES CTE  FIM', from);
    const renderContext = vm.createContext({ $, console: {log() {}},
        MEDIDAS: [{id: 1, unidade_medida: '01', tipo_medida: '<img src=x>', quantidade: '<script>'}],
        COMPONENTES: [{id: 1, nome: '<svg onload=alert(1)>', valor: '12,50'}],
        habilitaBtnSalarCTe() {},
    });
    vm.runInContext(code.slice(from, until), renderContext);
    const rows = renderContext.montaTabela();
    const comps = renderContext.montaTabelaComponentes(true);
    assert(Array.isArray(rows), `${view}: medidas devem criar nós, não HTML interpolado`);
    assert(Array.isArray(comps), `${view}: componentes devem criar nós, não HTML interpolado`);
    assert.strictEqual(rows[0].children[2].content, '<img src=x>');
    assert.strictEqual(rows[0].children[3].content, '<script>');
    assert.strictEqual(comps[0].children[1].content, '<svg onload=alert(1)>');
}
console.log('PASS medidas e componentes recuperados são renderizados como texto nas três views');

for (const view of ['register_xml', 'create', 'edit']) {
    const code = fs.readFileSync(path.join(__dirname, `../resources/views/cte/${view}.blade.php`), 'utf8');
    const from = code.indexOf('    $(document).ready(function(){') + '    $(document).ready(function(){'.length;
    const until = code.indexOf("      $('#selected_contacts')", from);
    const values = new Map(Object.entries({'#clientesAux': '[]', '#remetente_id': '8', '#destinatario_id': '9', '#chaves_nfe': chave, '#chave_nfe': '', '#valor_receber': '999,00'}));
    function ready$(selector) {
        return {
            val(value) { if(!arguments.length) return values.get(selector) || ''; values.set(selector, value); return this; },
            trigger() { return this; }, change() { return this; }, attr() { return this; }, html() { return this; }
        };
    }
    let hydrationCalls = 0;
    const init = vm.createContext({ $: ready$, console: {log() {}, clear() {}}, CHAVES: [],
        recuperarListasCte() { hydrationCalls++; },
        initBuscaOriginal() {}, atualizarContadorChave() {}, aplicarFinalidade() {},
        changeRemetente() {}, changeDestinatario() {}, selecetRecebedor() {}, selectExpedidor() {},
        selectRemetente() {}, selectDestinatario() {}, habilitaBtnSalarCTe() {},
        atualizarTomadorOutros(fillAddress) { assert.strictEqual(fillAddress, false, 'Inicialização não deve sobrescrever o endereço informado'); },
        montaHtmlChaveNfe(callback) { callback(''); }, adicionarChaveArray(key) { this.CHAVES.push(key); },
    });
    vm.runInContext(code.slice(from, until), init);
    assert.strictEqual(hydrationCalls, 1, `${view}: ready real deve recuperar listas uma única vez`);
    assert.strictEqual(values.get('#remetente_id'), '8');
    assert.strictEqual(values.get('#destinatario_id'), '9');
    assert.strictEqual(values.get('#valor_receber'), '999,00', 'Init preserva valor informado, não recalcula componentes');
    assert.strictEqual(init.CHAVES.length, 1, `${view}: recuperar chaves NF-e antigas para habilitar salvamento`);
}
console.log('PASS ready real preserva contatos, valores e chaves, com recuperação única e síncrona');

// DOM/jQuery reais: .empty() em nós já inseridos pode apagar handlers de remoção.
const {JSDOM} = require('jsdom');
for (const view of ['register_xml', 'create', 'edit']) {
    const code = fs.readFileSync(path.join(__dirname, `../resources/views/cte/${view}.blade.php`), 'utf8');
    const dom = new JSDOM('<table id="prod"><tbody></tbody></table><table id="componentes"><tbody></tbody></table><input id="meds"><input id="comps"><input id="valor_receber"><input id="valor_transporte">', {runScripts: 'outside-only'});
    try {
        const w = dom.window;
        w.eval(fs.readFileSync(path.join(__dirname, '../resources/plugins/jquery-validation-1.16.0/lib/jquery-3.1.1.js'), 'utf8'));
        w.MEDIDAS = [1, 2, 3].map(id => ({id, unidade_medida:'01', tipo_medida:'PESO', quantidade:'1'}));
        w.COMPONENTES = [1, 2, 3].map(id => ({id, nome:'Frete', valor:'1'}));
        w.habilitaBtnSalarCTe = () => {};
        const from = code.indexOf('    function montaTabela(){');
        const until = code.indexOf('    // COMPONENTES CTE  FIM', from);
        w.eval(code.slice(from, until));
        w.jQuery('#prod tbody').empty().append(w.montaTabela());
        w.jQuery('#componentes tbody').empty().append(w.montaTabelaComponentes(true));
        for (const [table, input] of [['prod', 'meds'], ['componentes', 'comps']]) {
            for (const remaining of [2, 1, 0]) {
                w.jQuery('#'+table+' tbody button').first().trigger('click');
                assert.strictEqual(w.jQuery('#'+table+' tbody tr').length, remaining, `${view}/${table}: exclusões consecutivas`);
                assert.strictEqual(JSON.parse(w.jQuery('#'+input).val()).length, remaining);
            }
        }
    } finally {dom.window.close();}
}
console.log('PASS exclusões consecutivas mantêm handlers e hidden sincronizados nas três views');

const payerStart = source.indexOf('    // Tomador Outros');
const payerEnd = source.indexOf('    // Tipo de Serviço', payerStart);
assert(payerStart >= 0 && payerEnd > payerStart, 'A importação XML deve implementar a seleção de Tomador (Outros).');
const payerDom = new JSDOM(`
    <select id="tomador">${[0, 1, 2, 3, 4].map(id => `<option value="${id}">${id}</option>`).join('')}</select>
    <select id="tomador_outros_id"><option value=""></option><option value="8">Contato</option><option value="9">Sem cidade</option></select>
    <div id="div-tomador-outros" style="display:none"><div id="box-tomador-outros" style="display:none">
        ${['nome', 'cnpj', 'ie', 'endereco', 'cidade'].map(field => `<strong id="tomador-outros-${field}"></strong>`).join('')}
    </div></div>
    ${['rua_tomador', 'numero_tomador', 'bairro_tomador', 'cep_tomador', 'cidade_tomador'].map(id => `<input id="${id}">`).join('')}
`, {runScripts: 'outside-only'});
try {
    const w = payerDom.window;
    w.eval(fs.readFileSync(path.join(__dirname, '../resources/plugins/jquery-validation-1.16.0/lib/jquery-3.1.1.js'), 'utf8'));
    const $payer = w.jQuery;
    w.CLIENTES = [
        {id: 8, name: '<img src=x onerror=alert(1)>', cpf_cnpj: '12.345.678/0001-95', ie_rg: '12345', rua: 'Rua de teste', numero: '40', bairro: 'Centro', cep: '65000-000', cidade: {id: 10, nome: 'São Luís', uf: 'MA'}},
        {id: 9, name: 'Sem cidade', cpf_cnpj: '98.765.432/0001-10', ie_rg: 'ISENTO', rua: 'Outra rua', numero: '50', bairro: 'Bairro', cep: '00000-000', cidade: null},
    ];
    $payer('#tomador').val('4');
    $payer('#tomador_outros_id').val('8');
    $payer('#rua_tomador').val('Endereço já informado');
    w.eval(source.slice(payerStart, payerEnd));
    // Executa o ready real com o mesmo DOM, sem os demais componentes do formulário.
    const readyStart = source.indexOf('    $(document).ready(function(){') + '    $(document).ready(function(){'.length;
    const readyEnd = source.indexOf("      $('#selected_contacts')", readyStart);
    $payer('<input id="clientesAux">').val(JSON.stringify(w.CLIENTES)).appendTo(w.document.body);
    for (const fn of ['recuperarListasCte', 'selectRemetente', 'selectDestinatario', 'habilitaBtnSalarCTe', 'montaHtmlChaveNfe']) w[fn] = () => {};
    w.eval(source.slice(readyStart, readyEnd));
    assert.notStrictEqual($payer('#div-tomador-outros').css('display'), 'none', 'Old Tomador=Outros deve mostrar o seletor na inicialização');
    assert.notStrictEqual($payer('#box-tomador-outros').css('display'), 'none');
    assert.strictEqual($payer('#rua_tomador').val(), 'Endereço já informado');
    assert.strictEqual($payer('#tomador-outros-nome').text(), w.CLIENTES[0].name);
    assert.strictEqual($payer('#box-tomador-outros img').length, 0, 'Dados do contato devem ser texto, nunca HTML');
    for (const value of ['0', '1', '2', '3']) {
        $payer('#tomador').val(value).trigger('change');
        assert.strictEqual($payer('#div-tomador-outros').css('display'), 'none');
        assert.strictEqual($payer('#box-tomador-outros').css('display'), 'none');
    }
    $payer('#tomador').val('4').trigger('change');
    assert.notStrictEqual($payer('#div-tomador-outros').css('display'), 'none');
    assert.notStrictEqual($payer('#box-tomador-outros').css('display'), 'none');
    assert.strictEqual($payer('#rua_tomador').val(), 'Endereço já informado');
    $payer('#tomador_outros_id').trigger('change');
    for (const [field, expected] of Object.entries({rua_tomador: 'Rua de teste', numero_tomador: '40', bairro_tomador: 'Centro', cep_tomador: '65000-000', cidade_tomador: '10'})) {
        assert.strictEqual($payer('#'+field).val(), expected);
    }
    assert.strictEqual($payer('#tomador-outros-cnpj').text(), w.CLIENTES[0].cpf_cnpj);
    assert.strictEqual($payer('#tomador-outros-ie').text(), '12345');
    assert.strictEqual($payer('#tomador-outros-endereco').text(), 'Rua de teste, 40');
    assert.strictEqual($payer('#tomador-outros-cidade').text(), 'São Luís (MA)');
    $payer('#tomador_outros_id').val('9').trigger('change');
    assert.strictEqual($payer('#tomador-outros-cidade').text(), '');
    assert.strictEqual($payer('#cidade_tomador').val(), '');
    $payer('#tomador_outros_id').val('').trigger('change');
    assert.strictEqual($payer('#box-tomador-outros').css('display'), 'none', 'Limpar contato deve ocultar dados do contato anterior');
} finally {payerDom.window.close();}
console.log('PASS Tomador Outros na importação: seletor, dados seguros, endereço, cidade ausente e recuperação sem sobrescrita');

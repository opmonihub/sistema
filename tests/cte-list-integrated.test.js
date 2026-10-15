'use strict';

// Dependência temporária: npm install --prefix /tmp/cte-integration-node --ignore-scripts jsdom@26.1.0
// Requer um MySQL descartável com --network none e o executor PHP externo já instalado.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {spawn, execFileSync} = require('node:child_process');
const {createInterface} = require('node:readline');
const test = require('node:test');
const {JSDOM} = require(process.env.CTE_INTEGRATION_JSDOM || '/tmp/cte-integration-node/node_modules/jsdom');
const root = path.resolve(__dirname, '..');
const container = process.env.CTE_INTEGRATION_MYSQL;
const executor = process.env.CTE_INTEGRATION_EXECUTOR || '/tmp/cte-backend-executor';
const plugins = 'resources/plugins/AdminLTE/plugins/DataTables/';
const read = name => fs.readFileSync(path.join(root, name), 'utf8');
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));
async function until(check, message) {
    for (let i = 0; i < 200; i++) { if (check()) return; await pause(10); }
    throw new Error(message);
}
function connectBackend() {
    assert.match(container || '', /^cte-integrated-gate-mysql-[a-z0-9-]+$/, 'Informe somente o container descartável deste gate');
    const isolated = JSON.parse(execFileSync('docker',['inspect',container],{encoding:'utf8'}))[0];
    assert.equal(isolated.HostConfig.NetworkMode,'none','Proibido usar rede de produção');
    assert.deepEqual(isolated.HostConfig.PortBindings,{});
    assert.equal(isolated.Mounts.length,0,'Proibido usar dados persistentes/volumes');
    assert.ok(isolated.HostConfig.Tmpfs['/var/lib/mysql'],'Banco deve ser efêmero em tmpfs');
    assert.ok(isolated.Config.Env.includes('MYSQL_DATABASE=cte_synthetic_validation'));
    const args = ['run','--rm','--interactive','--network','container:' + container,'--read-only',
        '--tmpfs','/tmp:rw,noexec,nosuid,size=128m'];
    for (const [src, dst] of [[executor,'/executor'],['app/Utils','/source/app/Utils'],
        ['app/Models','/source/app/Models'],['app/Http/Controllers','/source/app/Http/Controllers'],
        ['tests','/source/tests'],['resources/views/cte/index.blade.php','/source/resources/views/cte/index.blade.php'],
        ['resources/views/components/widget.blade.php','/source/resources/views/components/widget.blade.php']]) {
        args.push('--mount', 'type=bind,src=' + (path.isAbsolute(src) ? src : path.join(root,src)) + ',dst=' + dst + ',readonly');
    }
    args.push('--env','CTE_BACKEND_AUTOLOAD=/executor/vendor/autoload.php','--env','CTE_QUERY_ISOLATED=1',
        '--entrypoint','php','gacont-php:8.0-r2','-d','display_errors=stderr','/source/tests/Support/CteListIntegratedExecutor.php');
    const child = spawn('docker', args, {stdio:['pipe','pipe','pipe']});
    let stderr = '';
    child.stderr.on('data', data => { stderr += data; });
    const pending = [];
    const closed = new Promise(resolve => child.once('close', code => resolve({code, stderr})));
    const ready = new Promise((resolve, reject) => {
        createInterface({input:child.stdout}).on('line', line => {
            try {
                const data = JSON.parse(line);
                if (data.ready) resolve(data);
                else {
                    const waiter = pending.shift();
                    if (!waiter) throw new Error('Resposta sem requisição pendente');
                    data.error ? waiter.reject(new Error(data.error)) : waiter.resolve(data.response);
                }
            } catch (error) { reject(error); }
        });
        child.once('error', reject);
        child.once('close', code => {
            const error = new Error('Executor terminou: ' + code + ' ' + stderr);
            reject(error);
            pending.splice(0).forEach(waiter => waiter.reject(error));
        });
    });
    return {ready, request: input => new Promise((resolve,reject) => {
        pending.push({resolve,reject}); child.stdin.write(JSON.stringify(input) + '\n');
    }), close: async () => { child.stdin.end(); const result = await closed; assert.equal(result.code,0,result.stderr); }};
}

test('integração real: Laravel/Blade/MySQL → DataTables/Bootstrap → exports ZIP/CSV/PDF/print', {timeout:60000}, async t => {
    const backend = connectBackend();
    let dom;
    let printDom;
    try {
        const ready = await backend.ready;
        assert.match(ready.backendOutput,/OK backend_checks=14/);
        assert.equal(ready.bladeChecks,4);
        console.log(ready.backendOutput.trim());
        console.log('BLADE Laravel 8.61.0: 4 combinações de permissão compiladas e renderizadas; widget real/layout sintético');
        dom = new JSDOM(ready.html,{url:'http://synthetic.test/cte',runScripts:'outside-only',pretendToBeVisual:true});
        const {window} = dom;
        const document = window.document;
        const saves = [];
        const network = [];
        const requests = [];
        const responses = [];
        const errors = [];
        window.alert = message => { throw new Error(message); };
        window.fetch = (...args) => { network.push(args); throw new Error('Rede externa proibida'); };
        window.XMLHttpRequest.prototype.open = function (...args) { network.push(args); throw new Error('XHR real proibido'); };
        window.navigator.msSaveOrOpenBlob = (blob,name) => { saves.push({blob,name}); };
        // JSDOM não faz layout. Retângulos sintéticos somente para a guarda de visibilidade/foco.
        window.HTMLElement.prototype.getClientRects = function () {
            if (!this.isConnected) return [];
            for (let node = this; node; node = node.parentElement) {
                if (node.hidden || node.style.display === 'none') return [];
            }
            return [{width:100,height:20}];
        };
        const load = file => window.eval(read(file));
        load('resources/plugins/jquery-validation-1.16.0/lib/jquery-3.1.1.js');
        load(plugins + 'DataTables-1.10.16/js/jquery.dataTables.js');
        load(plugins + 'Buttons-1.4.2/js/dataTables.buttons.js');
        load(plugins + 'JSZip-2.5.0/jszip.js');
        load(plugins + 'Buttons-1.4.2/js/buttons.html5.js');
        load(plugins + 'Buttons-1.4.2/js/buttons.colVis.js');
        load(plugins + 'Buttons-1.4.2/js/buttons.print.js');
        load('resources/plugins/bootstrap/js/bootstrap.js');
        load('public/js/cte-list.js');
        const $ = window.jQuery;
        $.support.transition = false;
        $.fn.dataTable.ext.errMode = 'throw';
        $.ajax = options => {
            assert.equal(options.url,'/cte');
            requests.push(JSON.parse(JSON.stringify(options.data)));
            backend.request(requests[requests.length-1]).then(response => {
                responses.push(response); options.success(response);
            }).catch(error => errors.push(error));
            return {abort() { throw new Error('Cancelamento inesperado'); }};
        };
        // Executa o bloco verdadeiro de defaults/botões, sem os plugins globais não relacionados.
        const common = read('public/js/common.js');
        const defaultsStart = common.indexOf('    var buttons = [');
        const defaultsEnd = common.indexOf("    if ($('input#iraqi_selling_price_adjustment')",defaultsStart);
        assert.ok(defaultsStart >= 0 && defaultsEnd > defaultsStart);
        window.LANG = new Proxy({}, {get: (_object,key) => String(key)});
        window.non_utf8_languages = [];
        window.app_locale = 'pt';
        window.__default_datatable_page_entries = 3;
        window.eval(common.slice(defaultsStart,defaultsEnd));
        const originalDefaults = $.fn.dataTable.defaults.buttons;
        const originalSnapshot = JSON.stringify(originalDefaults);
        const inline = Array.from(document.scripts).find(script => !script.src).textContent;
        window.eval(inline);
        await until(() => errors.length || $.fn.dataTable.isDataTable('#users_table') && $('#users_table tbody tr').length === 3,'Inicialização DataTables não terminou');
        if (errors.length) throw errors[0];
        const table = $('#users_table').DataTable();
        const modal = document.getElementById('cte_documents_modal');
        const names = Array.from(window.CteListFrontend.buildColumns(),column => column.data);
        const redraw = async action => {
            const before = responses.length;
            action();
            await until(() => errors.length || responses.length > before,'Redraw não recebeu resposta real');
            if (errors.length) throw errors[0];
        };
        const rowId = row => Number(row.action.match(/id="cte(\d+)"/)[1]);
        const currentRows = () => Array.from(table.rows({page:'current'}).data().toArray());
        const cell = (index,name) => table.cell(index,names.indexOf(name)).node();
        const activate = index => {
            // Bootstrap recalcula suporte a transições no ready; DOM sem CSS não anima.
            $.support.transition = false;
            const trigger = cell(index,'documentos').querySelector('button');
            assert.ok(trigger,'Linha deve fornecer botão de documentos');
            trigger.focus();
            const before = requests.length;
            trigger.dispatchEvent(new window.MouseEvent('click',{bubbles:true,cancelable:true,detail:0}));
            assert.equal(requests.length,before,'Abrir modal não consulta backend');
            assert.ok($(modal).hasClass('in'));
            assert.notEqual(modal.getAttribute('aria-hidden'),'true');
            assert.equal(document.activeElement,modal);
            return trigger;
        };
        const escape = () => $(modal).trigger($.Event('keydown.dismiss.bs.modal',{which:27}));
        const exportOptions = extension => window.CteListFrontend.buildButtons(originalDefaults).find(button => button.extend === extension).exportOptions;
        const exportData = extension => table.buttons.exportData(exportOptions(extension));
        const blobBuffer = blob => new Promise((resolve,reject) => {
            const reader = new window.FileReader();
            reader.onload = () => resolve(reader.result); reader.onerror = reject; reader.readAsArrayBuffer(blob);
        });
        const sheet = async () => {
            const before = saves.length;
            table.button('.buttons-excel').trigger();
            assert.equal(saves.length,before+1,'A action real deve chegar à fronteira download');
            const saved = saves[saves.length-1];
            assert.match(saved.name,/\.xlsx$/);
            const bytes = await blobBuffer(saved.blob);
            const zip = new window.JSZip(bytes);
            assert.ok(zip.file('[Content_Types].xml'));
            const stylesDocument = new window.DOMParser().parseFromString(zip.file('xl/styles.xml').asText(),'text/xml');
            const styleFormats = Array.from(stylesDocument.getElementsByTagName('cellXfs')[0].getElementsByTagName('xf'));
            const formatCodes = new Map(Array.from(stylesDocument.getElementsByTagName('numFmt'),node => [node.getAttribute('numFmtId'),node.getAttribute('formatCode')]));
            const xml = zip.file('xl/worksheets/sheet1.xml').asText();
            const document = new window.DOMParser().parseFromString(xml,'text/xml');
            assert.equal(document.querySelector('parsererror'),null);
            assert.equal(document.querySelectorAll('f').length,0);
            const headers = exportData('excel').header;
            Array.from(document.querySelectorAll('c')).filter(node => node.querySelector('v') && (!node.hasAttribute('t') || node.getAttribute('t') === 'n')).forEach(node => {
                const column = node.getAttribute('r').replace(/\d/g,'');
                let index = 0;
                for (const letter of column) index = index * 26 + letter.charCodeAt(0) - 64;
                const label = headers[index - 1];
                const precision = label === 'Peso KG' ? 4 : ['Valor de transporte','Valor a receber','Valor carga'].includes(label) ? 2 : null;
                if (precision !== null) {
                    const style = styleFormats[Number(node.getAttribute('s'))];
                    assert.equal(formatCodes.get(style.getAttribute('numFmtId')), '[$-416]#,##0.' + '0'.repeat(precision),'Formato brasileiro numérico: ' + column);
                }
            });
            console.log('XLSX real JSZip 2.5.0: bytes=' + bytes.byteLength + ' worksheetCells=' + document.querySelectorAll('c').length);
            return document;
        };

        await t.test('contrato AJAX nativo, 15 colunas e renderizadores escapados', () => {
            assert.equal($.fn.dataTable.version,'1.10.16');
            assert.equal($.fn.dataTable.Buttons.version,'1.4.2');
            assert.equal($.fn.modal.Constructor.VERSION,'3.3.7');
            assert.equal(table.columns().count(),15);
            assert.equal(document.querySelectorAll('#users_table thead th').length,15);
            assert.deepEqual(requests[0].columns.map(column => column.data),names);
            ['documentos','cidade_destino','peso_kg','tomador_servico'].forEach(name => {
                const column = requests[0].columns.find(column => column.data === name);
                assert.equal(column.name,name); assert.equal(column.searchable,true); assert.equal(column.orderable,true);
            });
            const action = requests[0].columns.find(column => column.data === 'action');
            assert.equal(action.searchable,false); assert.equal(action.orderable,false);
            assert.ok(document.querySelector('#users_table tbody .cte-action form input[name="_token"]'));
            assert.equal(document.querySelectorAll('#users_table tbody script, #users_table tbody svg, #users_table tbody img, #users_table tbody b').length,0);
            const index = currentRows().findIndex(row => rowId(row) === 1);
            assert.ok(index >= 0);
            assert.equal(cell(index,'cidade_destino').textContent,'Cidade <b>literal</b>\nLinha / SP');
            assert.equal(cell(index,'tomador_servico').textContent,'<script>Remetente</script> — Remetente');
            assert.equal(cell(index,'peso_kg').textContent,'—');
            assert.equal(JSON.stringify(originalDefaults),originalSnapshot,'Configuração global não é mutada');
        });

        await t.test('paginação/redraw reais → modal categorizado, ARIA e restauração de foco', async () => {
            await redraw(() => table.page.len(2).order([[10,'desc']]).draw());
            assert.equal(rowId(currentRows()[0]),2);
            assert.equal(cell(0,'peso_kg').textContent,'Múltiplas medidas');
            let trigger = activate(0);
            assert.equal(modal.querySelectorAll('.cte-documento').length,6);
            assert.deepEqual(Array.from(modal.querySelectorAll('.modal-body h4'),heading => heading.textContent),
                ['NF-e da carga','Outros documentos da carga','CT-e anteriores']);
            assert.ok(modal.textContent.includes('<img src=x onerror=alert(1)> & carga\nsegunda linha'));
            assert.ok(modal.textContent.includes('Valor informado10,00'),'Valor de outro documento no modal usa pt-BR');
            assert.equal(modal.querySelectorAll('img,svg,script').length,0);
            escape();
            assert.equal(document.activeElement,trigger,'M1: Escape restaura trigger visível');
            assert.equal($(modal).css('display'),'none');
            await redraw(() => table.page('next').draw('page'));
            assert.ok(!trigger.isConnected,'Redraw remove o trigger antigo');
            const next = currentRows();
            const singleIndex = next.findIndex(row => row.documentos.count === 1);
            assert.ok(singleIndex >= 0);
            assert.equal(cell(singleIndex,'documentos').textContent,next[singleIndex].documentos.display);
            assert.equal(cell(singleIndex,'documentos').querySelector('button'),null);
            const secondIndex = next.findIndex(row => row.documentos.count > 1);
            assert.ok(secondIndex >= 0);
            trigger = activate(secondIndex);
            assert.equal(modal.querySelector('#cte_documents_modal_title').textContent,'Documentos do CT-e ' + next[secondIndex].numero_cte);
            assert.equal(modal.querySelectorAll('.cte-documento').length,next[secondIndex].documentos.count);
            assert.ok(!modal.textContent.includes('segunda linha'),'Não reusar documentos da página anterior');
            await redraw(() => table.page('previous').draw('page'));
            // Bootstrap mantém focus trap enquanto aberto; não esperar foco externo nesse estado.
            const beforeClose = document.activeElement;
            escape();
            assert.ok(!trigger.isConnected);
            assert.notEqual(document.activeElement,trigger,'M1: não focar trigger removido pelo redraw');
            assert.equal(document.activeElement,beforeClose,'M1: restoreFocus não força novo destino quando trigger se desconecta');
        });

        await t.test('colvis real e exportData real respeitam página/visibilidade/documentos completos', async () => {
            await redraw(() => table.page.len(3).order([[10,'desc']]).draw());
            const data = exportData('csv');
            assert.equal(data.body.length,3);
            assert.equal(data.body[0][10],currentRows()[0].documentos.export.replace(/\n/g,' '));
            assert.ok(data.body[0][10].includes('35261000000000000100550070000000431234567890'),'NF-e não inicial exportada');
            assert.ok(data.body[0][10].includes('CT-e anterior 11'));
            assert.ok(data.body[0][10].includes('ref;anterior,ruim'));
            assert.ok(!/^6 documentos$/.test(data.body[0][10]));
            table.button('.buttons-colvis').trigger();
            const toggles = table.buttons('.buttons-columnVisibility');
            assert.equal(toggles.count(),15);
            const documentsToggle = toggles.nodes().toArray().find(node => node.textContent === 'Documentos');
            assert.ok(documentsToggle); table.button(documentsToggle).trigger();
            assert.equal(table.column(10).visible(),false);
            assert.equal(exportData('csv').header.includes('Documentos'),false);
            table.column(10).visible(true);
            // Ação permanece visível/exportável como no contrato global legado, mas nunca como HTML ativo.
            assert.ok(exportData('print').header.includes('Ação'));
            assert.ok(exportData('print').body[0][14].includes('Ver CTe'));
            assert.ok(!exportData('print').body[0][14].includes('<form'));
        });

        await t.test('filtros nativos das quatro colunas alcançam controller antes da paginação', async () => {
            for (const [index,term] of [[10,'43'],[11,'MG'],[12,'1.234,5678'],[13,'Contato externo']]) {
                await redraw(() => { table.columns().search(''); table.column(index).search(term); table.draw(); });
                const expected = index === 13 ? [] : index === 12 ? [7] : [2];
                assert.deepEqual(currentRows().map(rowId),expected);
                assert.equal(responses[responses.length-1].recordsFiltered,expected.length);
            }
            await redraw(() => { table.columns().search(''); table.draw(); });
        });

        await t.test('XLSX ZIP real preserva peso decimal e neutraliza fórmulas com texto literal', async () => {
            await redraw(() => table.order([[12,'asc']]).draw());
            assert.deepEqual(currentRows().map(rowId),[10,3,7]);
            currentRows().forEach((row,index) => assert.equal(cell(index,'peso_kg').textContent,row.peso_kg.display));
            assert.deepEqual(Array.from(exportData('csv').body,row => row[12]),['0,0000','1.234,5000','1.234,5678']);
            assert.equal(cell(2,'valor_transporte').textContent,'1.234.567,89');
            assert.equal(exportData('csv').body[2][3],'1.234.567,89');
            assert.equal(exportData('excel').body[2][3],'1234567.89');
            let xml = await sheet();
            const weights = Array.from(xml.querySelectorAll('row')).map(row => row.querySelector('c[r^="M"]'))
                .filter(node => node && node.getAttribute('t') === 'n');
            assert.deepEqual(weights.map(node => node.querySelector('v').textContent),['0.0000','1234.5000','1234.5678']);
            table.column(1).visible(false);
            const shifted = await sheet();
            assert.deepEqual(Array.from(shifted.querySelectorAll('c[r^="L"][t="n"]'),node => node.querySelector('v').textContent),['0.0000','1234.5000','1234.5678'],'Formato acompanha peso após ocultar remetente');
            table.column(1).visible(true);
            await redraw(() => table.column(13).search('Recebedor').draw());
            assert.equal(currentRows().length,1);
            assert.equal(rowId(currentRows()[0]),6);
            assert.equal(cell(0,'peso_kg').textContent,'Medida inválida');
            const csv = exportData('csv');
            assert.ok(csv.body[0][13].startsWith("'=HYPERLINK(\"x\")"));
            assert.ok(csv.body[0][1].startsWith("'=HYPERLINK(\"x\")"),'Remetente CSV inerte');
            assert.ok(csv.body[0][2].startsWith("'=HYPERLINK(\"x\")"),'Destinatário CSV inerte');
            assert.equal(csv.body[0][6],"'=1+1",'Produto CSV inerte');
            xml = await sheet();
            ['B','C','G'].forEach(letter => {
                assert.ok(Array.from(xml.querySelectorAll('c')).some(node =>
                    node.getAttribute('r').startsWith(letter) && node.getAttribute('t') === 'inlineStr' &&
                    node.textContent.startsWith("'=")), 'Campo legado XLSX inerte: ' + letter);
            });
            const taker = Array.from(xml.querySelectorAll('c')).find(node => node.getAttribute('r').startsWith('N') && node.textContent.startsWith("'=HYPERLINK"));
            assert.ok(taker); assert.equal(taker.getAttribute('t'),'inlineStr');
            assert.ok(taker.textContent.includes('<svg onload=alert(1)>'));
            assert.equal(xml.querySelectorAll('svg').length,0);
            await redraw(() => { table.column(13).search(''); table.order([[12,'desc']]).draw(); });
            assert.equal(currentRows()[0].peso_kg.raw,'999999999.9000');
            xml = await sheet();
            assert.ok(Array.from(xml.querySelectorAll('c')).some(node => node.getAttribute('t') === 'n' && node.textContent === '999999999.9000'));
        });

        await t.test('actions CSV/print e documento PDF reais preservam detalhes e escapam HTML', async () => {
            await redraw(() => table.order([[10,'desc']]).draw());
            const xml = await sheet();
            const documentCell = Array.from(xml.querySelectorAll('c')).find(node => node.getAttribute('r').startsWith('K') && node.textContent.includes('CT-e anterior 11'));
            assert.ok(documentCell,'XLSX contém documentos completos, não somente o resumo');
            assert.ok(documentCell.textContent.includes('35261000000000000100550070000000431234567890'));
            assert.ok(documentCell.textContent.includes('<img src=x onerror=alert(1)> & carga segunda linha'));
            assert.ok(documentCell.textContent.includes('valor 10,00'),'Texto do documento XLSX em pt-BR');
            assert.equal(xml.querySelectorAll('img,svg,script').length,0);
            const before = saves.length;
            table.button('.buttons-csv').trigger();
            assert.equal(saves.length,before+1);
            const bytes = await blobBuffer(saves[saves.length-1].blob);
            assert.deepEqual(Array.from(new Uint8Array(bytes).slice(0,3)),[0xEF,0xBB,0xBF],'BOM UTF-8 nos bytes reais do CSV');
            const csv = new TextDecoder().decode(bytes);
            assert.ok(csv.includes('CT-e anterior 11'));
            assert.ok(csv.includes('valor 10,00'),'Texto do documento CSV em pt-BR');
            assert.ok(csv.split('\n')[0].includes(';'),'CSV separado por ponto e vírgula');
            assert.ok(csv.includes('1.234,5678') || csv.includes('Múltiplas medidas'),'Conteúdo pt-BR preservado');
            assert.ok(csv.includes('<img src=x onerror=alert(1)> & carga segunda linha'));
            assert.ok(!csv.includes('\nsegunda linha'),'Newlines seguem stripNewlines legado do Buttons');
            let printCustomized = false;
            window.__currency_convert_recursively = () => { printCustomized = true; };
            window.open = () => {
                printDom = new JSDOM('<!doctype html><html><head></head><body></body></html>',{url:'http://synthetic.test/print',runScripts:'outside-only'});
                printDom.window.print = () => {};
                return printDom.window;
            };
            table.button('.buttons-print').trigger();
            assert.equal(printCustomized,true,'Mantém customize verdadeiro de common.js');
            const body = printDom.window.document.body;
            assert.equal(body.querySelectorAll('tbody tr').length,3);
            assert.equal(body.querySelectorAll('script,img,svg,form,input').length,0);
            assert.ok(body.textContent.includes('<img src=x onerror=alert(1)> & carga segunda linha'));
            assert.ok(body.textContent.includes('CT-e anterior 11'));
            assert.ok(body.textContent.includes('valor 10,00'),'Texto do documento na impressão em pt-BR');
            let pdf;
            window.pdfMake = {createPdf: value => { pdf = value; return {open() {}}; }};
            const pdfConfig = $.extend(true,{},$.fn.dataTable.ext.buttons.pdfHtml5,
                window.CteListFrontend.buildButtons(originalDefaults).find(button => button.extend === 'pdf'),{download:'open'});
            $.fn.dataTable.ext.buttons.pdfHtml5.action.call({processing() {}},null,table,null,pdfConfig);
            const pdfTable = pdf.content.find(item => item.table).table;
            assert.equal(pdfTable.body.length,4);
            assert.ok(JSON.stringify(pdfTable.body).includes('CT-e anterior 11'));
            assert.ok(JSON.stringify(pdfTable.body).includes('valor 10,00'),'Texto do documento PDF em pt-BR');
            assert.ok(JSON.stringify(pdfTable.body).includes('<img src=x onerror=alert(1)>'));
            assert.equal(network.length,0,'Nenhum XHR/fetch real: apenas IPC para executor isolado');
            assert.equal(errors.length,0);
            console.log('INTEGRATED ajax=' + requests.length + ' externalCalls=' + network.length + ' csvBytes=' + bytes.byteLength + ' printRows=3 pdfRows=3');
        });
    } finally {
        if (printDom) printDom.window.close();
        if (dom) dom.window.close();
        await backend.close();
    }
});

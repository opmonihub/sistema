'use strict';

// Somente fixtures sintéticas: não carrega Laravel, .env, banco ou navegador.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const assetPath = path.join(__dirname, '../public/js/cte-list.js');
const frontend = fs.existsSync(assetPath) ? require(assetPath) : {};

const keyNfe = '35261000000000000100550070000000421234567890';
const keyCte = '35261000000000000100570080000000421234567890';
function item(overrides = {}) {
    return Object.assign({
        categoria: 'nfe_carga', tipo: 'NF-e', numero: '42', serie: '7',
        chave: keyNfe, descricao: null, emitente: null, status: 'valid', raw: keyNfe,
        export: 'NF-e 42 / série 7 / chave ' + keyNfe,
    }, overrides);
}
function documents(items) {
    const invalid = items.filter(document => document.status === 'malformed').length;
    return {
        display: !items.length ? '—' : items.length === 1 ? items[0].export.split(' / ')[0] : items.length + ' documentos',
        status: !items.length ? 'empty' : !invalid ? 'valid' : invalid === items.length ? 'malformed' : 'mixed',
        count: items.length, raw: items.length, items,
        export: items.map(document => document.export).join(' | '),
    };
}
function cell(display, raw = null, status = 'resolved', exported = display) {
    return {display, status, raw, export: exported};
}

// DOM mínimo para exercitar os nós reais criados pelo asset. innerHTML é proibido.
class Element extends EventTarget {
    constructor(tagName, ownerDocument) {
        super();
        this.tagName = tagName.toUpperCase();
        this.ownerDocument = ownerDocument;
        this.children = [];
        this.attributes = {};
        this._text = '';
        this.parentNode = null;
        this.style = {};
        this.hidden = false;
        this.offsetWidth = 100;
        this.offsetHeight = 20;
    }
    get isConnected() { return this === this.ownerDocument.documentElement || !!(this.parentNode && this.parentNode.isConnected); }
    getClientRects() {
        let node = this;
        while (node) {
            if (node.hidden || node.style.display === 'none') return [];
            node = node.parentNode;
        }
        return [{width: this.offsetWidth, height: this.offsetHeight}];
    }
    focus() { this.ownerDocument.activeElement = this; }
    set textContent(value) { this._text = String(value); this.children = []; }
    get textContent() { return this._text + this.children.map(child => child.textContent).join(''); }
    set innerHTML(value) { throw new Error('HTML interpolado não é permitido: ' + value); }
    get firstChild() { return this.children[0] || null; }
    appendChild(child) { child.parentNode = this; this.children.push(child); return child; }
    removeChild(child) { this.children.splice(this.children.indexOf(child), 1); child.parentNode = null; return child; }
    setAttribute(name, value) { this.attributes[name] = String(value); }
    getAttribute(name) { return this.attributes[name] || null; }
    get className() { return this.attributes.class || ''; }
    set className(value) { this.setAttribute('class', value); }
    matches(selector) {
        return selector[0] === '.' ? this.className.split(' ').includes(selector.slice(1)) :
            selector[0] === '#' ? this.getAttribute('id') === selector.slice(1) : this.tagName.toLowerCase() === selector;
    }
    closest(selector) { return this.matches(selector) ? this : this.parentNode ? this.parentNode.closest(selector) : null; }
    querySelector(selector) { return this.querySelectorAll(selector)[0] || null; }
    querySelectorAll(selector) {
        return this.children.flatMap(child => (child.matches(selector) ? [child] : []).concat(child.querySelectorAll(selector)));
    }
}
function modalFixture() {
    const document = {createElement: tag => new Element(tag, document)};
    const modal = document.createElement('div');
    const title = document.createElement('h4');
    title.setAttribute('id', 'cte_documents_modal_title');
    const body = document.createElement('div');
    body.className = 'modal-body';
    modal.appendChild(title);
    modal.appendChild(body);
    return {document, modal, title, body};
}

// Equivalente ao formatador padrão do Buttons para estes textos sintéticos.
function buttonsText(encoded, decodeEntities = true) {
    const stripped = encoded.replace(/<[^>]*>/g, '').trim().replace(/\n/g, ' ');
    if (!decodeEntities) return stripped;
    return stripped.replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"')
        .replace(/&#39;/g, "'").replace(/&amp;/g, '&');
}

// DOM XML e fronteira ZIP/download mínimos: a criação/serialização das células
// é executada pela action excelHtml5 real, não por uma cópia do exporter.
class XmlNode {
    constructor(name, document, value = null) {
        this.nodeName = name;
        this.ownerDocument = document;
        this.nodeValue = value;
        this.attributes = {};
        this.childNodes = [];
    }
    appendChild(child) { this.childNodes.push(child); return child; }
    setAttribute(name, value) { this.attributes[name] = String(value); }
    getAttribute(name) { return this.attributes[name] ?? null; }
    getElementsByTagName(name) {
        return this.childNodes.flatMap(child => (child.nodeName === name ? [child] : [])
            .concat(child.getElementsByTagName(name)));
    }
    get textContent() { return this.nodeValue === null ? this.childNodes.map(child => child.textContent).join('') : String(this.nodeValue); }
}
class XmlDocument extends XmlNode {
    constructor() { super('#document', null); this.ownerDocument = this; }
    createElement(name) { return new XmlNode(name, this); }
    createTextNode(value) { return new XmlNode('#text', this, String(value)); }
}
function parseXml(source) {
    const decode = value => value.replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"')
        .replace(/&apos;/g, "'").replace(/&amp;/g, '&');
    const document = new XmlDocument();
    const stack = [document];
    for (const token of source.match(/<[^>]+>|[^<]+/g) || []) {
        if (token.startsWith('<?') || token.startsWith('<!')) continue;
        if (token.startsWith('</')) { stack.pop(); continue; }
        if (token.startsWith('<')) {
            const node = document.createElement(token.match(/^<([^\s/>]+)/)[1]);
            for (const attribute of token.matchAll(/([^\s=]+)="([^"]*)"/g)) node.setAttribute(attribute[1], decode(attribute[2]));
            stack[stack.length - 1].appendChild(node);
            if (!token.endsWith('/>')) stack.push(node);
        } else {
            stack[stack.length - 1].appendChild(document.createTextNode(decode(token)));
        }
    }
    return document;
}
function serializeXml(node) {
    if (node.nodeName === '#document') return node.childNodes.map(serializeXml).join('');
    const encode = value => String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    if (node.nodeName === '#text') return encode(node.nodeValue);
    const attributes = Object.entries(node.attributes).map(([name, value]) => ' ' + name + '="' + encode(value) + '"').join('');
    return '<' + node.nodeName + attributes + '>' + node.childNodes.map(serializeXml).join('') + '</' + node.nodeName + '>';
}
function generateXlsx(rows) {
    const files = {};
    const jquery = (selector, context) => {
        const nodes = typeof selector === 'string' ? selector.split(' ').reduce((parents, name) =>
            parents.flatMap(parent => parent.getElementsByTagName(name)), [context]) : [selector];
        return Object.assign(nodes, {
            attr(name, value) {
                if (typeof name === 'object') Object.entries(name).forEach(([key, val]) => nodes.forEach(node => node.setAttribute(key, val)));
                else if (value !== undefined) nodes.forEach(node => node.setAttribute(name, value));
                else return nodes[0] && nodes[0].getAttribute(name);
                return this;
            },
            prepend(node) { nodes.forEach(parent => parent.childNodes.unshift(node)); return this; },
        });
    };
    jquery.each = (object, callback) => Object.entries(object).forEach(([key, value]) => callback(key, value));
    jquery.trim = value => String(value).trim();
    jquery.parseXML = parseXml;
    jquery.isPlainObject = value => value !== null && typeof value === 'object' &&
        Object.prototype.toString.call(value) === '[object Object]' && Object.getPrototypeOf(value).constructor.name === 'Object';
    jquery.fn = {dataTable: {ext: {buttons: {}}}};
    class ZipBoundary {
        constructor(prefix = '') { this.prefix = prefix; }
        folder(name) { return new ZipBoundary(this.prefix + name + '/'); }
        file(name, value) { files[this.prefix + name] = value; }
        generate() { return {type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'}; }
    }
    const document = {createElementNS: () => ({})};
    const window = {document, JSZip: ZipBoundary};
    const navigator = {userAgent: 'Node synthetic', msSaveOrOpenBlob: () => {}};
    vm.runInNewContext(fs.readFileSync(path.join(__dirname,
        '../resources/plugins/AdminLTE/plugins/DataTables/Buttons-1.4.2/js/buttons.html5.js'), 'utf8'), {
        jQuery: jquery, window, document, navigator, setTimeout,
        XMLSerializer: class {serializeToString(document) { return serializeXml(document); }},
    });
    const columns = frontend.buildColumns().filter(column => ['peso_kg', 'tomador_servico', 'documentos'].includes(column.data));
    const localButton = frontend.buildButtons([{extend: 'excel', exportOptions: {columns: ':visible'}}])[0];
    const config = Object.assign({}, jquery.fn.dataTable.ext.buttons.excelHtml5, localButton, {header: false, footer: false});
    const dt = {buttons: {
        exportData(options) {
            assert.equal(options.modifier.page, 'current');
            return {header: columns.map(column => ({peso_kg:'Peso KG',tomador_servico:'Tomador do Serviço',documentos:'Documentos'}[column.name] || column.name)), footer: null,
                body: rows.map(row => columns.map(column => buttonsText(column.render(row[column.data], options.orthogonal))))};
        },
        exportInfo: () => ({filename: 'synthetic.xlsx'}),
    }};
    const processing = [];
    jquery.fn.dataTable.ext.buttons.excelHtml5.action.call({processing: value => processing.push(value)}, null, dt, null, config);
    assert.deepEqual(processing, [true, false]);
    assert.ok(files['xl/worksheets/sheet1.xml'], 'A action deve serializar a worksheet para o ZIP');
    return {sheet: parseXml(files['xl/worksheets/sheet1.xml']), styles: parseXml(files['xl/styles.xml']), columns};
}

for (const raw of ['0.0000', '1.0000', '12.3456', '999.9999', '1000.0000', '1234.5678', '999999999.9999']) {
    test('I1: action XLSX real preserva peso numérico e precisão raw=' + raw, () => {
        const [integer, fraction] = raw.split('.');
        const display = integer.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + fraction;
        const row = {peso_kg: cell(display, raw, 'single'), tomador_servico: cell('=HYPERLINK("x")'),
            documentos: documents([item()])};
        const {sheet, styles, columns} = generateXlsx([row]);
        const weightId = String.fromCharCode(65 + columns.findIndex(column => column.data === 'peso_kg')) + '1';
        const weight = sheet.getElementsByTagName('c').find(node => node.getAttribute('r') === weightId);
        const value = weight.getElementsByTagName('v')[0];
        assert.equal(value && value.textContent, raw, 'Não remover a vírgula pt-BR como milhares nem arredondar');
        assert.equal(weight.getAttribute('t'), 'n', 'Peso decimal canônico deve ser célula numérica, não texto');
        const formats = styles.getElementsByTagName('cellXfs')[0].getElementsByTagName('xf');
        const applied = formats[Number(weight.getAttribute('s'))];
        assert.ok(applied, 'Peso tem estilo numérico explícito');
        const format = styles.getElementsByTagName('numFmt').find(node => node.getAttribute('numFmtId') === applied.getAttribute('numFmtId'));
        assert.equal(format && format.getAttribute('formatCode'), '[$-416]#,##0.0000', 'Peso XLSX usa pt-BR e quatro casas');
        assert.equal(sheet.getElementsByTagName('f').length, 0, 'Não criar fórmulas');
        const takerId = String.fromCharCode(65 + columns.findIndex(column => column.data === 'tomador_servico')) + '1';
        const taker = sheet.getElementsByTagName('c').find(node => node.getAttribute('r') === takerId);
        assert.equal(taker.getAttribute('t'), 'inlineStr');
        assert.equal(taker.textContent, "'=HYPERLINK(\"x\")");
        const weightColumn = columns.find(column => column.data === 'peso_kg');
        ['cte-spreadsheet', 'export', 'display'].forEach(type =>
            assert.equal(buttonsText(weightColumn.render(row.peso_kg, type)), display, 'CSV/PDF/impressão continuam pt-BR'));
    });
}

test('I1: raw não canônico não vira número Excel e fórmulas continuam neutralizadas', () => {
    const render = frontend.buildColumns().find(column => column.data === 'peso_kg').render;
    ['12,3456', 'NaN', '=1+1', '-1.0000', '01.0000', '12.12345', null].forEach(raw => {
        const value = cell('Medida inválida', raw, 'single');
        assert.equal(render(value, 'cte-excel'), 'Medida inválida');
    });
    const {sheet} = generateXlsx([{peso_kg: cell('=1+1', '=1+1', 'single'),
        tomador_servico: cell('@SUM(1)', null), documentos: documents([item({
            categoria: 'outros_carga', tipo: 'Outros', chave: null, export: '+1+1',
        })])}]);
    assert.equal(sheet.getElementsByTagName('f').length, 0);
    assert.equal(sheet.getElementsByTagName('c').every(node => node.getAttribute('t') === 'inlineStr'), true);
    assert.equal(sheet.getElementsByTagName('c').every(node => node.textContent[0] === "'"), true);
});

test('I1: XLSX mantém ausência e estados ambíguos/inválidos sem inventar peso', () => {
    const rows = [cell('—', null, 'missing', ''), cell('Múltiplas medidas', null, 'multiple'),
        cell('Medida inválida', null, 'invalid')].map(peso_kg => ({peso_kg, documentos: documents([]), tomador_servico: cell('—', null, 'invalid', '')}));
    const {sheet, columns} = generateXlsx(rows);
    const letter = String.fromCharCode(65 + columns.findIndex(column => column.data === 'peso_kg'));
    assert.equal(sheet.getElementsByTagName('c').some(node => node.getAttribute('r') === letter + '1'), false);
    ['Múltiplas medidas', 'Medida inválida'].forEach((value, index) => {
        const node = sheet.getElementsByTagName('c').find(node => node.getAttribute('r') === letter + (index + 2));
        assert.equal(node.getAttribute('t'), 'inlineStr');
        assert.equal(node.textContent, value);
    });
});

function bootstrapModalFixture() {
    const fixture = modalFixture();
    const {document, modal} = fixture;
    document.documentElement = document.createElement('html');
    document.documentElement.clientHeight = 800;
    document.body = document.createElement('body');
    document.body.clientWidth = 1200;
    document.documentElement.appendChild(document.body);
    document.body.appendChild(modal);
    const window = {innerWidth: 1200};
    const states = new WeakMap();
    const state = node => {
        if (!states.has(node)) states.set(node, {data: {}, events: []});
        return states.get(node);
    };
    const classes = node => node.className.split(' ').filter(Boolean);
    const eventMatches = (handler, type) => {
        const [base, ...namespaces] = type.split('.');
        const registered = handler.type.split('.');
        return base === registered[0] && namespaces.every(namespace => registered.includes(namespace));
    };
    class Collection extends Array {
        each(callback) { this.forEach((node, index) => callback.call(node, index, node)); return this; }
        data(key, value) {
            if (key === undefined) return state(this[0]).data;
            if (value === undefined) return state(this[0]).data[key];
            return this.each(function () { state(this).data[key] = value; });
        }
        find(selector) { return jquery(this.flatMap(node => node.querySelectorAll(selector))); }
        on(type, selector, callback) {
            if (typeof selector === 'function') callback = selector;
            return this.each(function () { state(this).events.push({type, callback, once: false}); });
        }
        one(type, callback) {
            return this.each(function () { state(this).events.push({type, callback, once: true}); });
        }
        off(type) {
            return this.each(function () { state(this).events = state(this).events.filter(handler => !eventMatches(handler, type)); });
        }
        trigger(event) {
            const object = typeof event === 'string' ? jquery.Event(event) : event;
            return this.each(function () {
                object.target = this;
                if (object.type === 'focus' && this.focus) this.focus();
                const handlers = state(this).events.slice();
                handlers.filter(handler => eventMatches(handler, object.type)).forEach(handler => {
                    if (handler.once) state(this).events.splice(state(this).events.indexOf(handler), 1);
                    handler.callback.call(this, object);
                });
            });
        }
        addClass(value) { return this.each(function () { this.className = Array.from(new Set(classes(this).concat(value.split(' ')))).join(' '); }); }
        removeClass(value) { return this.each(function () { this.className = classes(this).filter(name => name !== value).join(' '); }); }
        hasClass(value) { return classes(this[0]).includes(value); }
        parent() { return jquery(this.map(node => node.parentNode).filter(Boolean)); }
        appendTo(parent) { parent[0].appendChild(this[0]); return this; }
        append(node) { return this.each(function () { this.appendChild(node); }); }
        show() { return this.css('display', 'block'); }
        hide() { return this.css('display', 'none'); }
        scrollTop() { return this; }
        has(node) { return jquery(this.filter(parent => node.closest && node.closest('#' + parent.getAttribute('id')))); }
        css(name, value) {
            if (typeof name === 'string' && value === undefined) return (this[0].style || {})[name];
            const values = typeof name === 'object' ? name : {[name]: value};
            return this.each(function () { this.style = Object.assign(this.style || {}, values); });
        }
    }
    const jquery = value => {
        const nodes = Array.isArray(value) ? value : value ? [value] : [];
        const collection = new Collection();
        collection.push(...nodes);
        return collection;
    };
    jquery.fn = Collection.prototype;
    jquery.support = {transition: false};
    jquery.extend = (...values) => Object.assign(...values);
    jquery.proxy = (callback, context) => callback.bind(context);
    jquery.Event = (type, values = {}) => Object.assign({type, defaultPrevented: false,
        preventDefault() { this.defaultPrevented = true; }, isDefaultPrevented() { return this.defaultPrevented; }}, values);
    const bootstrap = fs.readFileSync(path.join(__dirname, '../resources/plugins/bootstrap/js/bootstrap.js'), 'utf8');
    const start = bootstrap.indexOf('+function ($)', bootstrap.indexOf('Bootstrap: modal.js'));
    const end = bootstrap.indexOf('}(jQuery);', start) + '}(jQuery);'.length;
    vm.runInNewContext(bootstrap.slice(start, end), {jQuery: jquery, window, document});
    assert.equal(jquery.fn.modal.Constructor.VERSION, '3.3.7');
    modal.setAttribute('id', 'cte_documents_modal');
    modal.className = 'modal fade';
    modal.style = {display: 'none'};
    const dialog = document.createElement('div');
    dialog.className = 'modal-dialog';
    modal.appendChild(dialog);
    document.body.style = {};
    const tag = fs.readFileSync(path.join(__dirname, '../resources/views/cte/index.blade.php'), 'utf8')
        .match(/<div[^>]*id="cte_documents_modal"[^>]*>/)[0];
    for (const attr of tag.matchAll(/([\w-]+)="([^"]*)"/g)) modal.setAttribute(attr[1], attr[2]);
    return Object.assign(fixture, {jquery, window});
}

test('I2: modal visível com Bootstrap 3.3.7 real não fica oculto para acessibilidade', () => {
    const {jquery, modal} = bootstrapModalFixture();
    assert.equal(modal.style.display, 'none');
    jquery(modal).modal({show: false, backdrop: false});
    jquery(modal).modal('show');
    assert.equal(modal.style.display, 'block');
    assert.notEqual(modal.getAttribute('aria-hidden'), 'true');
    jquery(modal).modal('hide');
    assert.equal(modal.style.display, 'none');
});

function focusInteractionFixture() {
    const fixture = bootstrapModalFixture();
    const {document, modal, jquery} = fixture;
    const tableElement = document.createElement('table');
    document.body.appendChild(tableElement);
    const row = document.createElement('tr');
    const trigger = document.createElement('button');
    trigger.className = 'cte-documentos-button';
    row.appendChild(trigger);
    tableElement.appendChild(row);
    let rowData = {numero_cte: 1, documentos: documents([item(), item({categoria: 'cte_anterior', chave: keyCte})])};
    const table = {row: element => { assert.equal(element, row); return {data: () => rowData}; }};
    jquery.fn.dataTable = {defaults: {buttons: []}};
    jquery.fn.ready = callback => { callback(); };
    jquery.fn.DataTable = function () { return table; };
    const inline = fs.readFileSync(path.join(__dirname, '../resources/views/cte/index.blade.php'), 'utf8')
        .match(/<script type="text\/javascript">([\s\S]*?)<\/script>/)[1];
    document.getElementById = id => id === 'users_table' ? tableElement : modal;
    jquery(modal).modal({show: false, backdrop: false});
    new vm.Script(inline).runInNewContext({$: jquery, document, CteListFrontend: frontend});
    function activate() {
        trigger.focus();
        // Botão nativo: a ativação via Enter/Espaço é entregue pelo DOM como click detail=0.
        const event = new Event('click', {cancelable: true});
        Object.defineProperty(event, 'target', {value: trigger});
        Object.defineProperty(event, 'detail', {value: 0});
        tableElement.dispatchEvent(event);
        assert.equal(event.defaultPrevented, true);
        assert.equal(document.activeElement, modal);
        assert.equal(modal.style.display, 'block');
    }
    function closeByEscape() { jquery(modal).trigger(jquery.Event('keydown.dismiss.bs.modal', {which: 27})); }
    return Object.assign(fixture, {tableElement, row, trigger, activate, closeByEscape,
        replaceData: value => { rowData = value; }});
}

test('M1: Escape restaura foco no botão conectado após ativação por teclado', () => {
    const fixture = focusInteractionFixture();
    fixture.activate();
    fixture.closeByEscape();
    assert.equal(fixture.modal.style.display, 'none');
    assert.ok(fixture.document.activeElement === fixture.trigger, 'Foco deve retornar ao trigger conectado e visível');
    const other = fixture.document.createElement('button');
    fixture.document.body.appendChild(other);
    other.focus();
    fixture.jquery(fixture.modal).trigger('hidden.bs.modal');
    assert.equal(fixture.document.activeElement, other, 'Consumir o trigger evita restaurar foco novamente');
    fixture.activate();
    fixture.closeByEscape();
    assert.equal(fixture.document.activeElement, fixture.trigger, 'Reabrir preserva restauração por ciclo');
});

test('M1: redraw/unmount não restaura foco em botão desconectado', () => {
    const fixture = focusInteractionFixture();
    fixture.activate();
    fixture.tableElement.removeChild(fixture.row);
    assert.equal(fixture.trigger.isConnected, false);
    const other = fixture.document.createElement('button');
    fixture.document.body.appendChild(other);
    other.focus();
    fixture.closeByEscape();
    assert.equal(fixture.document.activeElement, other);
});

test('M1: trigger conectado mas oculto após redraw não recebe foco', () => {
    const fixture = focusInteractionFixture();
    fixture.activate();
    fixture.row.style.display = 'none';
    const other = fixture.document.createElement('button');
    fixture.document.body.appendChild(other);
    other.focus();
    fixture.closeByEscape();
    assert.equal(fixture.document.activeElement, other);
});

test('campos textuais legados neutralizam fórmulas nas exportações sem alterar display', () => {
    const columns = frontend.buildColumns();
    ['remetente', 'destinatario', 'produto_predominante'].forEach(name => {
        const column = columns.find(candidate => candidate.data === name);
        assert.equal(typeof column.render, 'function', name + ' precisa de proteção local');
        ['=1+1', '+SUM(A1)', '-1+1', '@SUM(A1)', '  =1+1'].forEach(value => {
            assert.equal(column.render(value, 'cte-spreadsheet'), "'" + value);
            assert.equal(column.render(value, 'cte-excel'), "'" + value);
            assert.equal(column.render(value, 'display'), value);
        });
        assert.equal(column.render('&lt;b&gt;literal&lt;/b&gt;', 'export'), '&lt;b&gt;literal&lt;/b&gt;');
    });
});

test('asset fornece renderizadores e configuração específicos da listagem', () => {
    ['renderDocuments', 'renderCell', 'buildColumns', 'buildButtons', 'fillDocumentsModal', 'bindDocumentsModal']
        .forEach(name => assert.equal(typeof frontend[name], 'function', name + ' ainda não implementado'));
});

test('zero, um e múltiplos documentos têm apresentações distintas', () => {
    assert.equal(frontend.renderDocuments(documents([]), 'display'), '—');
    assert.equal(frontend.renderDocuments(documents([item()]), 'display'), 'NF-e 42');
    const html = frontend.renderDocuments(documents([item(), item({chave: keyCte, categoria: 'cte_anterior'})]), 'display');
    assert.match(html, /<button[^>]+type="button"/);
    assert.match(html, /cte-documentos-button/);
    assert.match(html, />2 documentos<\/button>/);
    assert.doesNotMatch(html, new RegExp(keyNfe));
    assert.equal(frontend.renderDocuments(null, 'display'), '—');
});

test('referência inválida permanece identificável sem derivar número', () => {
    const invalid = item({numero: null, serie: null, chave: '<img src=x onerror=alert(1)>', status: 'malformed',
        export: 'NF-e — referência inválida (<img src=x onerror=alert(1)>)'});
    const html = frontend.renderDocuments(documents([invalid]), 'display');
    assert.match(html, /referência inválida/);
    assert.match(html, /&lt;img/);
    assert.doesNotMatch(html, /<img/);
    const fixture = modalFixture();
    frontend.fillDocumentsModal(fixture.modal, {numero_cte: 9, documentos: documents([invalid])});
    assert.match(fixture.body.textContent, /Referência inválida/);
    assert.match(fixture.body.textContent, /<img src=x onerror=alert\(1\)>/);
    assert.doesNotMatch(fixture.body.textContent, /Número42/);
    assert.equal(fixture.body.querySelectorAll('img').length, 0);
});

test('documento único malformado sinaliza inconsistência mesmo quando display não contém aviso', () => {
    const invalid = item({categoria: 'outros_carga', tipo: 'Outros', numero: '-1', serie: null,
        chave: null, descricao: 'Descrição cadastrada', status: 'malformed',
        raw: {tpDoc: '99', nDoc: -1, descOutros: 'Descrição cadastrada', vDocFisc: '0.00'},
        export: 'Outros -1 / Descrição cadastrada'});
    const html = frontend.renderDocuments(documents([invalid]), 'display');
    assert.match(html, /referência inválida/);
    assert.match(html, /Descrição cadastrada/);
    assert.match(html, /text-warning/);
    assert.equal(buttonsText(frontend.renderDocuments(documents([invalid]), 'export')), invalid.export);
});

test('cidade, peso e tomador usam texto seguro e preservam precisão/status do servidor', () => {
    assert.equal(frontend.renderCell(cell('1.234,5678', '1234.5678', 'single'), 'display'), '1.234,5678');
    ['Múltiplas medidas', 'Medida inválida', 'Remetente — contato ausente', 'Tomador inválido', '—']
        .forEach(display => assert.equal(frontend.renderCell(cell(display), 'display'), display));
    assert.equal(frontend.renderCell(cell('<script>alert(1)</script> — Outros'), 'display'), '&lt;script&gt;alert(1)&lt;/script&gt; — Outros');
    assert.equal(frontend.renderCell(null, 'display'), '—');
});

test('DataTables envia os nomes exatos e não usa índice fixo da coluna Ação', () => {
    const columns = frontend.buildColumns();
    assert.equal(columns.length, 15);
    ['documentos', 'cidade_destino', 'peso_kg', 'tomador_servico'].forEach(name => {
        const column = columns.find(column => column.data === name);
        assert.equal(column.name, name);
        assert.equal(column.searchable, true);
        assert.equal(column.orderable, true);
        assert.equal(typeof column.render, 'function');
        assert.equal(column.defaultContent, '—');
    });
    const action = columns.find(column => column.data === 'action');
    assert.equal(action.name, 'action');
    assert.equal(action.className, 'cte-action');
    assert.equal(action.searchable, false);
    assert.equal(action.orderable, false);
    assert.deepEqual(columns.filter(column => !['documentos', 'cidade_destino', 'peso_kg', 'tomador_servico'].includes(column.data)).map(column => column.data),
        ['numero_cte', 'remetente', 'destinatario', 'valor_transporte', 'valor_receber', 'valor_carga', 'produto_predominante', 'data_previsata_entrega', 'estado', 'created_at', 'action']);
});

test('modal único categoriza vínculos mistos e reutiliza o DOM sem conteúdo obsoleto', () => {
    const fixture = modalFixture();
    const items = [item(), item({categoria: 'outros_carga', tipo: 'Outros', chave: null, serie: null,
        numero: null, descricao: '0', raw: {vDocFisc: '1.00'}, export: 'Outros / 0 / valor 1.00'}),
        item({categoria: 'cte_anterior', tipo: 'CT-e anterior', chave: keyCte, serie: '8', emitente: '<svg onload=alert(1)>',
            export: 'CT-e anterior 42 / série 8 / chave ' + keyCte + ' / emitente <svg onload=alert(1)>'})];
    frontend.fillDocumentsModal(fixture.modal, {numero_cte: '<b>18</b>', documentos: documents(items)});
    assert.equal(fixture.title.textContent, 'Documentos do CT-e <b>18</b>');
    assert.deepEqual(fixture.body.querySelectorAll('h4').map(heading => heading.textContent),
        ['NF-e da carga', 'Outros documentos da carga', 'CT-e anteriores']);
    [keyNfe, keyCte, 'Descrição0', 'Valor informado1,00', 'Emitente<svg onload=alert(1)>']
        .forEach(text => assert.ok(fixture.body.textContent.includes(text), text));
    assert.equal(fixture.body.querySelectorAll('svg').length, 0);
    frontend.fillDocumentsModal(fixture.modal, {numero_cte: 19, documentos: documents([])});
    assert.equal(fixture.title.textContent, 'Documentos do CT-e 19');
    assert.equal(fixture.body.textContent, 'Nenhum documento vinculado.');
    assert.equal(fixture.body.querySelectorAll('h4').length, 0);
});

test('evento delegado consulta os dados atuais da linha após redraw sem nova requisição', () => {
    const fixture = modalFixture();
    const target = new EventTarget();
    let rowData = {numero_cte: 1, documentos: documents([item(), item({categoria: 'cte_anterior', chave: keyCte})])};
    let shown = 0;
    const row = fixture.document.createElement('tr');
    const button = fixture.document.createElement('button');
    button.className = 'cte-documentos-button';
    const inner = fixture.document.createElement('span');
    button.appendChild(inner);
    row.appendChild(button);
    const table = {row: element => { assert.equal(element, row); return {data: () => rowData}; }};
    frontend.bindDocumentsModal(target, table, fixture.modal, () => { shown++; });
    function click() {
        const event = new Event('click', {cancelable: true});
        Object.defineProperty(event, 'target', {value: inner});
        target.dispatchEvent(event);
        assert.equal(event.defaultPrevented, true);
    }
    click();
    assert.equal(fixture.title.textContent, 'Documentos do CT-e 1');
    rowData = {numero_cte: 2, documentos: documents([item(), item({categoria: 'cte_anterior', chave: keyCte, emitente: 'Novo emitente'})])};
    click();
    assert.equal(fixture.title.textContent, 'Documentos do CT-e 2');
    assert.match(fixture.body.textContent, /Novo emitente/);
    assert.equal(shown, 2);
});

test('exportação mantém detalhes de todos os documentos e texto literal seguro para impressão', () => {
    const text = '<script>texto literal</script> & descrição';
    const data = documents([item(), item({categoria: 'outros_carga', chave: null, tipo: 'Outros', descricao: text, export: 'Outros / ' + text})]);
    const encoded = frontend.renderDocuments(data, 'export');
    assert.equal(buttonsText(encoded), data.export);
    assert.match(buttonsText(encoded), new RegExp(keyNfe));
    assert.ok(buttonsText(encoded).includes(text));
    assert.doesNotMatch(buttonsText(encoded), /^2 documentos$/);
    assert.doesNotMatch(buttonsText(encoded, false), /<script>/);
    assert.match(buttonsText(encoded, false), /&lt;script&gt;/);
    assert.equal(frontend.renderDocuments(documents([]), 'export'), '');
    assert.equal(frontend.renderCell(cell('—', null, 'missing', ''), 'export'), '');
});

test('CSV/Excel neutralizam fórmulas nos envelopes textuais novos', () => {
    const data = cell('=HYPERLINK("x") — Outros');
    assert.equal(buttonsText(frontend.renderCell(data, 'cte-spreadsheet')), "'" + data.export);
    assert.equal(buttonsText(frontend.renderCell(data, 'export')), data.export);
    assert.equal(buttonsText(frontend.renderCell(cell('1.234,5678'), 'cte-spreadsheet')), '1.234,5678');
});

test('modal mostra valor de outros documentos em pt-BR sem alterar raw', () => {
    const fixture = modalFixture();
    const other = item({categoria:'outros_carga',tipo:'Outros',chave:null,numero:null,serie:null,
        raw:{tpDoc:'99',nDoc:0,vDocFisc:'1234.50'},export:'Outros / valor 1.234,50'});
    frontend.fillDocumentsModal(fixture.modal,{numero_cte:1,documentos:documents([other])});
    assert.ok(fixture.body.textContent.includes('1.234,50'));
    assert.ok(!fixture.body.textContent.includes('1234.50'));
    assert.equal(other.raw.vDocFisc,'1234.50');
});

test('valores monetários seguem pt-BR e CSV usa separador brasileiro', () => {
    ['valor_transporte','valor_receber','valor_carga'].forEach(name => {
        const column = frontend.buildColumns().find(candidate => candidate.data === name);
        assert.equal(typeof column.render, 'function');
        assert.equal(column.render('1234567,80','display'),'1.234.567,80');
        assert.equal(column.render('1.234.567,80','cte-excel'),'1234567.80');
        assert.equal(column.render('1234567,80','cte-spreadsheet'),'1.234.567,80');
        assert.equal(column.render('0,00','cte-excel'),'0.00');
    });
    const csv = frontend.buildButtons([{extend:'csv'}])[0];
    assert.equal(csv.fieldSeparator,';');
    assert.equal(csv.bom,true,'UTF-8 reconhecido pelo Excel brasileiro');
});

test('botões locais preservam customizations globais, visibilidade e alcance da página atual', () => {
    const customize = () => 'customização de impressão comum';
    const body = value => value;
    const defaults = ['csv', {extend: 'excel', text: 'Excel', footer: true, exportOptions: {columns: ':visible', format: {body}}},
        {extend: 'print', className: 'btn-sm', footer: true, customize, exportOptions: {columns: ':visible', stripHtml: true}},
        {extend: 'colvis', text: 'Colunas'}, {extend: 'pdf', footer: true, exportOptions: {columns: ':visible'}}];
    const buttons = frontend.buildButtons(defaults);
    assert.deepEqual(defaults[0], 'csv');
    assert.equal(defaults[1].exportOptions.orthogonal, undefined);
    buttons.filter(button => button.extend !== 'colvis').forEach(button => {
        assert.equal(button.exportOptions.modifier.page, 'current');
        assert.equal(button.exportOptions.modifier.search, 'applied');
        assert.equal(button.exportOptions.modifier.order, 'applied');
        assert.equal(button.exportOptions.columns, ':visible');
    });
    assert.equal(buttons[0].exportOptions.orthogonal, 'cte-spreadsheet');
    assert.equal(buttons[1].exportOptions.orthogonal, 'cte-excel');
    assert.equal(buttons[1].exportOptions.format.body, body);
    assert.equal(buttons[2].exportOptions.orthogonal, 'export');
    assert.equal(buttons[2].exportOptions.decodeEntities, false);
    assert.equal(buttons[2].customize, customize);
    assert.equal(buttons[2].footer, true);
    assert.equal(buttons[2].className, 'btn-sm');
    assert.deepEqual(buttons[3], defaults[3]);
    assert.equal(buttons[4].exportOptions.orthogonal, 'export');
});

test('referências com o mesmo número em categorias/séries distintas não somem no frontend', () => {
    const nfe = item();
    const previous = item({categoria: 'cte_anterior', tipo: 'CT-e anterior', serie: '8', chave: keyCte,
        export: 'CT-e anterior 42 / série 8 / chave ' + keyCte});
    const otherSeries = item({serie: '9', chave: keyNfe.replace('55007', '55009'),
        export: 'NF-e 42 / série 9 / chave distinta'});
    const fixture = modalFixture();
    const data = documents([nfe, previous, otherSeries]);
    frontend.fillDocumentsModal(fixture.modal, {numero_cte: 1, documentos: data});
    assert.equal(fixture.body.querySelectorAll('.cte-documento').length, 3);
    assert.match(frontend.renderDocuments(data, 'display'), />3 documentos<\/button>/);
    assert.equal(buttonsText(frontend.renderDocuments(data, 'export')), data.export);
});

test('documento de categoria desconhecida é visível, e valor/número zero não desaparecem', () => {
    const fixture = modalFixture();
    frontend.fillDocumentsModal(fixture.modal, {numero_cte: 0, documentos: documents([item({
        categoria: 'futura', tipo: 'Novo tipo', numero: '0', serie: '0', descricao: '0', chave: null,
        raw: {vDocFisc: '0.00'}, export: 'Novo tipo 0 / 0',
    })])});
    assert.equal(fixture.title.textContent, 'Documentos do CT-e 0');
    ['Documentos de categoria não reconhecida', 'Número0', 'Série0', 'Descrição0', 'Valor informado0,00']
        .forEach(value => assert.ok(fixture.body.textContent.includes(value), value));
});

test('sintaxe do JS embutido e nomes enviados são compatíveis com DataTables serverSide', () => {
    const blade = fs.readFileSync(path.join(__dirname, '../resources/views/cte/index.blade.php'), 'utf8');
    const inline = blade.match(/<script type="text\/javascript">([\s\S]*?)<\/script>/)[1];
    const modal = modalFixture().modal;
    const tableElement = new EventTarget();
    const table = {row: () => ({data: () => null})};
    let options;
    const jquery = target => ({
        ready: callback => callback(),
        DataTable: dataTableOptions => { assert.equal(target, tableElement); options = dataTableOptions; return table; },
        modal: () => {},
        on: () => {},
    });
    jquery.fn = {dataTable: {defaults: {buttons: [{extend: 'csv', exportOptions: {columns: ':visible'}}]}}};
    new vm.Script(inline).runInNewContext({
        $: jquery, CteListFrontend: frontend,
        document: {getElementById: id => id === 'users_table' ? tableElement : modal},
    });
    assert.equal(options.serverSide, true);
    assert.equal(options.ajax, '/cte');
    assert.deepEqual(options.columns.slice(10, 14).map(column => [column.data, column.name]),
        ['documentos', 'cidade_destino', 'peso_kg', 'tomador_servico'].map(name => [name, name]));
    assert.equal(options.buttons[0].exportOptions.modifier.page, 'current');
    // O ramo sem permissão/tabela não deve inicializar DataTables ou modal.
    new vm.Script(inline).runInNewContext({$: jquery, document: {getElementById: () => null}});
});

test('pipeline local do Buttons 1.4.2 exporta todos os detalhes da página sem executar HTML', () => {
    const registrations = {};
    const jquery = selector => {
        if (selector === '<textarea/>') {
            const textarea = {value: ''};
            Object.defineProperty(textarea, 'innerHTML', {set: html => { textarea.value = buttonsText(html); }});
            return [textarea];
        }
        return {on: () => {}};
    };
    jquery.extend = function merge() {
        const args = Array.from(arguments);
        if (typeof args[0] === 'boolean') args.shift();
        const target = args.shift();
        args.forEach(source => Object.keys(source || {}).forEach(key => {
            const value = source[key];
            target[key] = value && Object.prototype.toString.call(value) === '[object Object]' ?
                merge({}, target[key], value) : value;
        }));
        return target;
    };
    function Api() { return dt; }
    Api.register = (name, callback) => { registrations[name] = callback; };
    Api.registerPlural = (plural, singular, callback) => {
        registrations[plural] = callback;
        registrations[singular] = callback;
    };
    jquery.fn = {dataTable: {Api, ext: {buttons: {}, feature: []}}, DataTable: {}};
    vm.runInNewContext(fs.readFileSync(path.join(__dirname,
        '../resources/plugins/AdminLTE/plugins/DataTables/Buttons-1.4.2/js/dataTables.buttons.js'), 'utf8'),
    {jQuery: jquery, window: {}, document: {}, setTimeout});

    const literal = '<script>texto literal</script> & <img onerror=x>';
    const rows = [{documentos: documents([item(), item({categoria: 'outros_carga', descricao: literal,
        export: 'Outros / ' + literal})]), cidade_destino: cell('Cidade / SP')}];
    const columns = frontend.buildColumns().filter(column => ['documentos', 'cidade_destino'].includes(column.data));
    let modifier;
    const toArray = array => ({toArray: () => array});
    const dt = {
        columns: () => ({indexes: () => ({map: callback => toArray(columns.map((_, index) => callback(index)))})}),
        column: index => ({header: () => ({innerHTML: columns[index].name})}),
        table: () => ({footer: () => null}),
        rows: (selector, dataModifier) => { modifier = dataModifier; return {indexes: () => toArray([0])}; },
        cells: () => ({
            render: type => toArray(rows.flatMap(row => columns.map(column => column.render(row[column.data], type)))),
            nodes: () => toArray(columns.map(() => ({}))),
        }),
    };
    const buttons = frontend.buildButtons(['csv', 'excel', 'pdf', 'print']);
    buttons.forEach(button => {
        const data = registrations['buttons.exportData()'].call({context: [{}]}, button.exportOptions);
        assert.equal(modifier.page, 'current');
        assert.equal(data.body.length, 1);
        assert.match(data.body[0][0], new RegExp(keyNfe));
        assert.doesNotMatch(data.body[0][0], /^2 documentos$/);
        assert.equal(data.body[0][1], 'Cidade / SP');
        if (button.extend === 'print') {
            assert.doesNotMatch(data.body[0][0], /<script>|<img/);
            assert.match(data.body[0][0], /&lt;script&gt;/);
        } else {
            assert.equal(data.body[0][0], rows[0].documentos.export);
        }
    });
});

test('Blade integra somente este asset, conserva serverSide e contém um modal acessível', () => {
    const blade = fs.readFileSync(path.join(__dirname, '../resources/views/cte/index.blade.php'), 'utf8');
    ['Documentos', 'Cidade Destino', 'Peso KG', 'Tomador do Serviço'].forEach(header => assert.ok(blade.includes('<th>' + header + '</th>'), header));
    assert.equal((blade.match(/id="cte_documents_modal"/g) || []).length, 1);
    assert.match(blade, /aria-labelledby="cte_documents_modal_title"/);
    assert.match(blade, /js\/cte-list\.js\?v=/);
    assert.match(blade, /serverSide:\s*true/);
    assert.match(blade, /ajax:\s*'\/cte'/);
    assert.doesNotMatch(blade, /targets["']?\s*:\s*\[10\]/);
    assert.match(blade, /CteListFrontend\.buildColumns\(/);
    assert.match(blade, /CteListFrontend\.buildButtons\(\$\.fn\.dataTable\.defaults\.buttons\)/);
    assert.match(blade, /CteListFrontend\.bindDocumentsModal\(/);
});

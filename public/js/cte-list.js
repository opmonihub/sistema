(function (root, factory) {
    if (typeof module === 'object' && module.exports) {
        module.exports = factory();
    } else {
        root.CteListFrontend = factory();
    }
}(typeof window !== 'undefined' ? window : this, function () {
    'use strict';

    // Contrato por célula: {display, status, raw, export}. Documentos também traz
    // {count, items}; normalização, deduplicação, busca e ordenação são do servidor.
    function text(value) {
        return value === null || value === undefined ? '' : String(value);
    }

    function escapeHtml(value) {
        return text(value).replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function exportText(value, type) {
        value = text(value);
        if ((type === 'cte-spreadsheet' || type === 'cte-excel') && /^[\s]*[=+@-]/.test(value)) {
            value = "'" + value;
        }
        // Buttons remove HTML antes de decodificar entidades. Na impressão elas
        // ficam codificadas, pois o plugin concatena o resultado como HTML.
        return escapeHtml(value);
    }

    function documentItems(cell) {
        return cell && Array.isArray(cell.items) ? cell.items : [];
    }

    function renderCell(cell, type) {
        if (type === 'export' || type === 'cte-spreadsheet' || type === 'cte-excel') {
            return exportText(cell && cell.export, type);
        }
        return escapeHtml(cell && cell.display !== null && cell.display !== undefined ? cell.display : '—');
    }

    function renderWeight(cell, type) {
        if (type === 'cte-excel' && cell && cell.status === 'single' &&
            typeof cell.raw === 'string' && /^(0|[1-9]\d*)(\.\d{1,4})?$/.test(cell.raw)) {
            // XLSX usa ponto decimal. Texto pt-BR seria interpretado pelo Buttons
            // como milhares (12,3456 -> 123456). Não converter/arredondar via float.
            return cell.raw;
        }
        return renderCell(cell, type);
    }

    function renderDocuments(cell, type) {
        var items = documentItems(cell);
        if (type === 'export' || type === 'cte-spreadsheet' || type === 'cte-excel') {
            var exported = cell && cell.export !== undefined ? cell.export : items.map(function (item) {
                return item.export;
            }).join(' | ');
            return exportText(exported, type);
        }
        if (type !== 'display') {
            return items.length;
        }
        if (!items.length) {
            return '—';
        }
        if (items.length === 1) {
            if (items[0].status === 'malformed') {
                var invalidSummary = items[0].export || cell.display || text(items[0].chave || items[0].raw);
                if (!/referência inválida/i.test(invalidSummary)) {
                    invalidSummary += ' — referência inválida';
                }
                return '<span class="text-warning">' + escapeHtml(invalidSummary) + '</span>';
            }
            return escapeHtml(cell.display || items[0].export || 'Documento sem identificação');
        }
        var warning = cell.status === 'mixed' || cell.status === 'malformed' ? ' — contém referências inválidas' : '';
        return '<button type="button" class="btn btn-link btn-xs cte-documentos-button"' +
            ' aria-haspopup="dialog" aria-controls="cte_documents_modal" aria-label="' +
            escapeHtml('Ver ' + items.length + ' documentos' + warning) + '" title="' +
            escapeHtml('Ver documentos' + warning) + '">' + items.length + ' documentos</button>';
    }

    function renderMoney(value, type) {
        value = text(value);
        if (!/^-?(?:\d+|\d{1,3}(?:\.\d{3})+),\d{2}$/.test(value)) {
            return renderLegacyText(value, type);
        }
        var parts = value.replace(/\./g, '').split(',');
        if (type === 'cte-excel') {
            return parts[0] + '.' + parts[1];
        }
        return parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + parts[1];
    }

    function formatBrazilianXlsx(xlsx, numericColumns) {
        var styles = xlsx.xl['styles.xml'];
        var formats = styles.getElementsByTagName('numFmts')[0];
        var cellFormats = styles.getElementsByTagName('cellXfs')[0];
        var namespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        function node(name, attributes) {
            var element = styles.createElementNS ? styles.createElementNS(namespace, name) : styles.createElement(name);
            Object.keys(attributes).forEach(function (key) { element.setAttribute(key, attributes[key]); });
            return element;
        }
        if (!formats) {
            formats = node('numFmts', {count: 0});
            styles.getElementsByTagName('styleSheet')[0].insertBefore(formats, styles.getElementsByTagName('styleSheet')[0].firstChild);
        }
        var nextId = 164;
        Array.prototype.forEach.call(formats.getElementsByTagName('numFmt'), function (format) {
            nextId = Math.max(nextId, Number(format.getAttribute('numFmtId')) + 1);
        });
        var byPrecision = {};
        Object.keys(numericColumns).forEach(function (column) {
            var precision = numericColumns[column];
            if (byPrecision[precision] === undefined) {
                var id = nextId++;
                formats.appendChild(node('numFmt', {numFmtId: id, formatCode: '[$-416]#,##0.' + new Array(precision + 1).join('0')}));
                byPrecision[precision] = cellFormats.getElementsByTagName('xf').length;
                cellFormats.appendChild(node('xf', {numFmtId: id, fontId: 0, fillId: 0, borderId: 0, xfId: 0, applyNumberFormat: 1}));
            }
        });
        formats.setAttribute('count', formats.getElementsByTagName('numFmt').length);
        cellFormats.setAttribute('count', cellFormats.getElementsByTagName('xf').length);
        Array.prototype.forEach.call(xlsx.xl.worksheets['sheet1.xml'].getElementsByTagName('c'), function (cell) {
            var letters = (cell.getAttribute('r') || '').replace(/\d/g, '');
            var index = 0;
            for (var i = 0; i < letters.length; i++) { index = index * 26 + letters.charCodeAt(i) - 64; }
            var precision = numericColumns[index - 1];
            if (precision !== undefined && cell.getAttribute('t') !== 'inlineStr' && cell.getElementsByTagName('v').length) {
                cell.setAttribute('s', byPrecision[precision]);
            }
        });
    }

    function renderLegacyText(value, type) {
        // O controller já escapa estas strings. Não escapar novamente, mas proteger
        // os prefixos de fórmula também nos campos anteriores às novas colunas.
        value = text(value);
        if ((type === 'cte-spreadsheet' || type === 'cte-excel') && /^[\s]*[=+@-]/.test(value)) {
            return "'" + value;
        }
        return value;
    }

    function buildColumns() {
        var columns = [
            {data: 'numero_cte'},
            {data: 'remetente', render: renderLegacyText},
            {data: 'destinatario', render: renderLegacyText},
            {data: 'valor_transporte', render: renderMoney},
            {data: 'valor_receber', render: renderMoney},
            {data: 'valor_carga', render: renderMoney},
            {data: 'produto_predominante', render: renderLegacyText},
            {data: 'data_previsata_entrega'},
            {data: 'estado'},
            {data: 'created_at'}
        ];
        ['documentos', 'cidade_destino', 'peso_kg', 'tomador_servico'].forEach(function (name) {
            columns.push({
                data: name,
                name: name,
                defaultContent: '—',
                searchable: true,
                orderable: true,
                render: name === 'documentos' ? renderDocuments : name === 'peso_kg' ? renderWeight : renderCell
            });
        });
        columns.push({data: 'action', name: 'action', className: 'cte-action', orderable: false, searchable: false});
        return columns;
    }

    function clone(value) {
        if (Array.isArray(value)) {
            return value.map(clone);
        }
        if (value && Object.prototype.toString.call(value) === '[object Object]') {
            var copy = {};
            Object.keys(value).forEach(function (key) {
                copy[key] = clone(value[key]);
            });
            return copy;
        }
        return value;
    }

    function buildButtons(defaults) {
        return (defaults || []).map(function (original) {
            var button = typeof original === 'string' ? {extend: original} : clone(original);
            if (!button || !/^(csv|excel|pdf|print)(Html5|Flash)?$/.test(button.extend || '')) {
                return button;
            }
            var options = button.exportOptions || {};
            if (options.columns === undefined) {
                options.columns = ':visible';
            }
            // Clonar os padrões mantém textos, footer, formatadores e customize
            // de common.js sem alterar exportações de qualquer outra tela.
            options.orthogonal = /^excel/.test(button.extend) ? 'cte-excel' :
                /^csv/.test(button.extend) ? 'cte-spreadsheet' : 'export';
            options.modifier = options.modifier || {};
            options.modifier.page = 'current';
            options.modifier.search = 'applied';
            options.modifier.order = 'applied';
            if (button.extend === 'print') {
                options.decodeEntities = false;
            }
            if (/^csv/.test(button.extend)) {
                button.fieldSeparator = ';';
                button.bom = true;
            }
            if (/^excel/.test(button.extend)) {
                var numericColumns = {};
                var customizeData = button.customizeData;
                var customize = button.customize;
                button.customizeData = function (data) {
                    if (customizeData) { customizeData.apply(this, arguments); }
                    numericColumns = {};
                    data.header.forEach(function (header, index) {
                        var label = text(header).replace(/\s+/g, ' ').trim().toLowerCase();
                        if (label === 'peso kg') { numericColumns[index] = 4; }
                        if (['valor de transporte','valor a receber','valor carga'].indexOf(label) !== -1) { numericColumns[index] = 2; }
                    });
                };
                button.customize = function (xlsx) {
                    if (customize) { customize.apply(this, arguments); }
                    formatBrazilianXlsx(xlsx, numericColumns);
                };
            }
            button.exportOptions = options;
            return button;
        });
    }

    function appendText(parent, tag, value, className) {
        var element = parent.ownerDocument.createElement(tag);
        if (className) {
            element.className = className;
        }
        element.textContent = text(value);
        parent.appendChild(element);
        return element;
    }

    function appendField(details, label, value) {
        if (value === null || value === undefined || value === '') {
            return;
        }
        appendText(details, 'dt', label);
        appendText(details, 'dd', value);
    }

    function appendDocument(section, item) {
        var article = section.ownerDocument.createElement('div');
        article.className = 'well well-sm cte-documento';
        section.appendChild(article);
        appendText(article, 'strong', item.tipo || 'Documento');
        var malformed = item.status === 'malformed';
        if (malformed) {
            appendText(article, 'p', 'Referência inválida — dados cadastrados preservados.', 'text-warning');
        }
        var details = article.ownerDocument.createElement('dl');
        details.className = 'dl-horizontal';
        article.appendChild(details);
        // Nunca tentar derivar número/série de referência marcada como inválida.
        if (!malformed) {
            appendField(details, 'Número', item.numero);
            appendField(details, 'Série', item.serie);
        }
        appendField(details, malformed ? 'Referência informada' : 'Chave', item.chave);
        appendField(details, 'Descrição', item.descricao);
        appendField(details, 'Emitente', item.emitente);
        if (item.raw && typeof item.raw === 'object') {
            var documentValue = text(item.raw.vDocFisc);
            if (/^-?\d+(?:\.\d{1,2})?$/.test(documentValue)) {
                var amount = documentValue.split('.');
                documentValue = renderMoney(amount[0] + ',' + ((amount[1] || '') + '00').slice(0, 2), 'display');
            }
            appendField(details, 'Valor informado', documentValue);
            if (malformed) {
                appendField(details, 'Tipo informado', item.raw.tpDoc);
                appendField(details, 'Número informado', item.raw.nDoc);
            }
        } else if (malformed && !item.chave) {
            appendField(details, 'Referência informada', item.raw);
        }
    }

    function fillDocumentsModal(modal, row) {
        var title = modal.querySelector('#cte_documents_modal_title');
        var body = modal.querySelector('.modal-body');
        title.textContent = 'Documentos do CT-e' + (row.numero_cte !== null && row.numero_cte !== undefined ? ' ' + text(row.numero_cte) : '');
        while (body.firstChild) {
            body.removeChild(body.firstChild);
        }
        var items = documentItems(row.documentos);
        if (!items.length) {
            appendText(body, 'p', 'Nenhum documento vinculado.');
            return;
        }
        var groups = [
            {name: 'nfe_carga', title: 'NF-e da carga'},
            {name: 'outros_carga', title: 'Outros documentos da carga'},
            {name: 'cte_anterior', title: 'CT-e anteriores'},
            {name: null, title: 'Documentos de categoria não reconhecida'}
        ];
        groups.forEach(function (group) {
            var members = items.filter(function (item) {
                return group.name === null ? ['nfe_carga', 'outros_carga', 'cte_anterior'].indexOf(item.categoria) === -1 : item.categoria === group.name;
            });
            if (!members.length) {
                return;
            }
            var section = body.ownerDocument.createElement('section');
            body.appendChild(section);
            appendText(section, 'h4', group.title);
            members.forEach(function (item) {
                appendDocument(section, item);
            });
        });
    }

    function bindDocumentsModal(tableElement, table, modal, showModal) {
        var trigger = null;
        // Delegação no elemento persistente da tabela: nenhum dado/índice de uma
        // página anterior é guardado no botão, e abrir não dispara AJAX.
        tableElement.addEventListener('click', function (event) {
            var button = event.target.closest ? event.target.closest('.cte-documentos-button') : null;
            if (!button) {
                return;
            }
            event.preventDefault();
            var row = table.row(button.closest('tr')).data();
            if (!row) {
                return;
            }
            fillDocumentsModal(modal, row);
            trigger = button;
            showModal();
        });

        // Bootstrap só restaura foco automaticamente no fluxo data-toggle.
        // O chamador liga este callback ao evento jQuery hidden.bs.modal real.
        return function restoreFocus() {
            var button = trigger;
            trigger = null;
            if (button && button.isConnected && button.getClientRects().length && !button.disabled) {
                button.focus();
            }
        };
    }

    return {
        renderDocuments: renderDocuments,
        renderCell: renderCell,
        buildColumns: buildColumns,
        buildButtons: buildButtons,
        fillDocumentsModal: fillDocumentsModal,
        bindDocumentsModal: bindDocumentsModal
    };
}));

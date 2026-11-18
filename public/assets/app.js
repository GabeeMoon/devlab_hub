(function () {
    'use strict';

    var state = {
        table: '',
        columns: [],
        rows: []
    };

    function $(selector, root) {
        return (root || document).querySelector(selector);
    }

    function $$(selector, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(selector));
    }

    function esc(val) {
        if (val === null || val === undefined) return '';
        return String(val)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function toast(message, isError) {
        var stack = $('#toastStack');
        var el = document.createElement('div');
        el.className = 'toast ' + (isError ? 'err' : 'ok');
        el.textContent = message;
        stack.appendChild(el);
        setTimeout(function () {
            if (el.parentNode) el.parentNode.removeChild(el);
        }, 3200);
    }

    function request(url, method, data, done) {
        var xhr = new XMLHttpRequest();
        xhr.open(method, url, true);
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) return;
            var res;
            try {
                res = JSON.parse(xhr.responseText);
            } catch (e) {
                res = { success: false, message: 'Erro de resposta do servidor.' };
            }
            done(res);
        };
        xhr.send(data || null);
    }

    function dbCall(action, method, payload, done) {
        var url = 'index.php?route=db&action=' + encodeURIComponent(action);
        if (method === 'GET' && typeof payload === 'string') {
            url += '&' + payload;
            payload = null;
        }
        request(url, method, payload, done);
    }

    function hubCall(action, formData, done) {
        request('index.php?route=hub&action=' + encodeURIComponent(action), 'POST', formData, done);
    }

    // Modal Controller
    function openModal(title, htmlContent) {
        $('#modalTitle').textContent = title;
        $('#modalBody').innerHTML = htmlContent;
        $('#appModal').classList.remove('hidden');
    }

    function closeModal() {
        $('#appModal').classList.add('hidden');
        $('#modalBody').innerHTML = '';
    }

    // Navigation
    function switchView(view) {
        $$('.view').forEach(function (el) { el.classList.remove('active'); });
        $$('.nav-btn').forEach(function (btn) { btn.classList.remove('active'); });

        var target = $('#view-' + view);
        var nav = $('.nav-btn[data-view="' + view + '"]');
        if (target) target.classList.add('active');
        if (nav) nav.classList.add('active');

        window.location.hash = view;

        if (view === 'dashboard') loadDashboard();
        if (view === 'database') {
            loadTables();
            loadRoutines();
        }
    }

    function switchSubtab(name) {
        $$('.seg-btn').forEach(function (b) { b.classList.remove('active'); });
        $$('.subtab').forEach(function (s) { s.classList.remove('active'); });

        $('.seg-btn[data-subtab="' + name + '"]').classList.add('active');
        $('#subtab-' + name).classList.add('active');
    }

    // Dashboard
    function loadDashboard() {
        dbCall('dashboard_stats', 'GET', null, function (res) {
            var dot = $('#sidebarDbDot');
            if (!res.success) {
                dot.className = 'status-dot offline';
                $('#dashTablesList').innerHTML = '<div class="empty-inline">' + esc(res.message) + '</div>';
                return;
            }

            dot.className = 'status-dot online';
            $('#kpiTables').textContent = res.data.tables_count;
            $('#kpiRoutines').textContent = res.data.routines_count;
            $('#dashMysqlTag').textContent = 'MySQL ' + res.data.version;

            var tables = res.data.tables_list || [];
            if (tables.length === 0) {
                $('#dashTablesList').innerHTML = '<div class="empty-inline">Nenhuma tabela criada.</div>';
                return;
            }

            $('#dashTablesList').innerHTML = tables.map(function (t) {
                return '<button type="button" class="list-row" data-open-table="' + esc(t) + '">' +
                    '<span class="row-title mono">' + esc(t) + '</span>' +
                    '<span class="muted">&rarr;</span>' +
                '</button>';
            }).join('');
        });
    }

    // Workspace
    function initWorkspace() {
        $('#btnToggleNewFolder').addEventListener('click', function () {
            var bar = $('#newFolderForm');
            bar.classList.toggle('hidden');
            if (!bar.classList.contains('hidden')) $('#newFolderInput').focus();
        });

        $('#btnCancelNewFolder').addEventListener('click', function () {
            $('#newFolderForm').classList.add('hidden');
        });

        $('#newFolderForm').addEventListener('submit', function (e) {
            e.preventDefault();
            var fd = new FormData();
            fd.append('folder_name', $('#newFolderInput').value);

            hubCall('create_module', fd, function (res) {
                if (!res.success) return toast(res.message, true);
                window.location.hash = 'workspace';
                window.location.reload();
            });
        });

        $('#workspaceGrid').addEventListener('click', function (e) {
            var btn = e.target.closest('[data-delete-folder]');
            if (!btn) return;

            var folder = btn.getAttribute('data-delete-folder');
            var count = parseInt(btn.getAttribute('data-file-count'), 10);

            if (count > 0) {
                return toast("Remova os " + count + " arquivo(s) antes de excluir.", true);
            }

            openModal('Excluir diretório',
                '<p class="muted">Confirmar remoção de <strong class="mono">' + esc(folder) + '</strong>?</p>' +
                '<div class="modal-actions">' +
                    '<button type="button" class="btn btn-ghost" id="mCancel">Cancelar</button>' +
                    '<button type="button" class="btn btn-danger-outline" id="mConfirmDelete">Excluir</button>' +
                '</div>'
            );

            $('#mCancel').onclick = closeModal;
            $('#mConfirmDelete').onclick = function () {
                var fd = new FormData();
                fd.append('folder_name', folder);
                hubCall('delete_module', fd, function (res) {
                    closeModal();
                    if (!res.success) return toast(res.message, true);
                    var card = $('.folder-card[data-folder="' + folder + '"]');
                    if (card) card.parentNode.removeChild(card);
                    toast(res.message);
                });
            };
        });
    }

    // Database Schema & CRUD
    function loadTables() {
        dbCall('list_tables', 'GET', null, function (res) {
            var list = $('#schemaTablesList');
            if (!res.success) {
                list.innerHTML = '<div class="empty-inline">' + esc(res.message) + '</div>';
                return;
            }
            if (!res.data || res.data.length === 0) {
                list.innerHTML = '<div class="empty-inline">Sem tabelas.</div>';
                return;
            }

            list.innerHTML = res.data.map(function (t) {
                var active = (state.table === t.name) ? ' active' : '';
                return '<button type="button" class="list-row' + active + '" data-select-table="' + esc(t.name) + '">' +
                    '<span class="mono">' + esc(t.name) + '</span>' +
                    '<span class="mono muted">' + (t.rows_est || 0) + '</span>' +
                '</button>';
            }).join('');
        });
    }

    function inspectTable(tableName) {
        state.table = tableName;
        loadTables();

        var pane = $('#tableInspector');
        pane.innerHTML = '<div class="empty-state">Carregando...</div>';

        dbCall('inspect_table', 'GET', 'table=' + encodeURIComponent(tableName), function (res) {
            if (!res.success) {
                pane.innerHTML = '<div class="empty-state">' + esc(res.message) + '</div>';
                return;
            }

            state.columns = res.data.columns || [];
            state.rows = res.data.rows || [];

            var rowsHtml = '';
            if (state.rows.length === 0) {
                rowsHtml = '<div class="empty-inline">Nenhum registro.</div>';
            } else {
                var headers = state.columns.map(function (c) {
                    return '<th>' + esc(c.Field) + '</th>';
                }).join('');

                var body = state.rows.map(function (row, idx) {
                    var cells = state.columns.map(function (c) {
                        var v = row[c.Field];
                        return '<td>' + (v !== null ? esc(v) : '<span class="muted">NULL</span>') + '</td>';
                    }).join('');

                    return '<tr>' + cells +
                        '<td style="white-space:nowrap;text-align:right;">' +
                            '<button type="button" class="btn btn-xs btn-ghost" data-edit-row="' + idx + '">Editar</button> ' +
                            '<button type="button" class="btn btn-xs btn-danger-outline" data-del-row="' + idx + '">Excluir</button>' +
                        '</td></tr>';
                }).join('');

                rowsHtml = '<div class="table-scroll"><table><thead><tr>' + headers + '<th></th></tr></thead><tbody>' + body + '</tbody></table></div>';
            }

            var colsHtml = state.columns.map(function (c, idx) {
                return '<tr>' +
                    '<td class="mono"><strong>' + esc(c.Field) + '</strong></td>' +
                    '<td class="mono muted">' + esc(c.Type) + '</td>' +
                    '<td class="mono muted">' + esc(c.Null) + '</td>' +
                    '<td class="mono muted">' + esc(c.Key) + '</td>' +
                    '<td class="mono muted">' + (c.Default !== null ? esc(c.Default) : 'NULL') + '</td>' +
                    '<td style="white-space:nowrap;text-align:right;">' +
                        '<button type="button" class="btn btn-xs btn-ghost" data-edit-col="' + idx + '">Editar</button> ' +
                        '<button type="button" class="btn btn-xs btn-danger-outline" data-del-col="' + esc(c.Field) + '">Excluir</button>' +
                    '</td>' +
                '</tr>';
            }).join('');

            pane.innerHTML =
                '<div class="inspector-header">' +
                    '<div>' +
                        '<h2 class="mono" style="font-size:15px;">' + esc(tableName) + '</h2>' +
                        '<span class="mono muted" style="font-size:12px;">' + state.columns.length + ' colunas · ' + state.rows.length + ' linhas</span>' +
                    '</div>' +
                    '<div class="btn-group">' +
                        '<button type="button" class="btn btn-xs btn-primary" id="btnNewRow">+ Registro</button>' +
                        '<button type="button" class="btn btn-xs btn-ghost" id="btnNewCol">+ Coluna</button>' +
                        '<button type="button" class="btn btn-xs btn-ghost" id="btnRenameTable">Renomear</button>' +
                        '<button type="button" class="btn btn-xs btn-danger-outline" id="btnDropTable">Excluir</button>' +
                    '</div>' +
                '</div>' +
                '<div class="section-bar"><h4>Registros</h4></div>' +
                rowsHtml +
                '<div class="section-bar"><h4>Colunas</h4></div>' +
                '<div class="table-scroll"><table><thead><tr><th>Nome</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th></th></tr></thead><tbody>' + colsHtml + '</tbody></table></div>';
        });
    }

    function rowKeys(rowObj) {
        var keys = {};
        var hasPk = false;
        state.columns.forEach(function (c) {
            if (c.Key === 'PRI') {
                keys[c.Field] = rowObj[c.Field];
                hasPk = true;
            }
        });
        if (!hasPk) {
            state.columns.forEach(function (c) { keys[c.Field] = rowObj[c.Field]; });
        }
        return keys;
    }

    function openRowModal(rowIndex) {
        var isEdit = (rowIndex !== null && rowIndex !== undefined);
        var row = isEdit ? state.rows[rowIndex] : null;

        var fieldsHtml = state.columns.map(function (c) {
            var nullable = c.Null === 'YES';
            var autoInc = c.Extra && c.Extra.toLowerCase().indexOf('auto_increment') !== -1;
            var isText = c.Type && c.Type.toLowerCase().indexOf('text') !== -1;
            var val = isEdit ? row[c.Field] : '';
            var isNull = isEdit ? (val === null) : (nullable && c.Default === null && !autoInc);

            var nullBox = nullable
                ? '<label style="display:inline-flex;align-items:center;gap:4px;cursor:pointer;"><input type="checkbox" class="f-null"' + (isNull ? ' checked' : '') + '> NULL</label>'
                : '';

            var ph = (!isEdit && autoInc) ? 'AUTO_INCREMENT' : '';
            var control = isText
                ? '<textarea class="textarea f-val" rows="3" placeholder="' + ph + '"' + (isNull ? ' disabled' : '') + '>' + (val !== null ? esc(val) : '') + '</textarea>'
                : '<input type="text" class="input f-val" value="' + (val !== null ? esc(val) : '') + '" placeholder="' + ph + '"' + (isNull ? ' disabled' : '') + '>';

            return '<div class="form-row" data-field="' + esc(c.Field) + '">' +
                '<div class="form-label"><span class="mono"><strong>' + esc(c.Field) + '</strong> · ' + esc(c.Type) + '</span>' + nullBox + '</div>' +
                control +
            '</div>';
        }).join('');

        openModal(isEdit ? 'Editar Registro' : 'Novo Registro',
            '<form id="rowForm">' + fieldsHtml +
                '<div class="modal-actions">' +
                    '<button type="button" class="btn btn-ghost" id="mCancel">Cancelar</button>' +
                    '<button type="submit" class="btn btn-primary">Salvar</button>' +
                '</div>' +
            '</form>'
        );

        $$('.f-null', $('#modalBody')).forEach(function (chk) {
            chk.addEventListener('change', function () {
                var input = chk.closest('.form-row').querySelector('.f-val');
                input.disabled = chk.checked;
                if (chk.checked) input.value = '';
            });
        });

        $('#mCancel').onclick = closeModal;
        $('#rowForm').onsubmit = function (e) {
            e.preventDefault();
            var values = {};
            $$('.form-row', $('#rowForm')).forEach(function (r) {
                var field = r.getAttribute('data-field');
                var chk = r.querySelector('.f-null');
                var inp = r.querySelector('.f-val');
                values[field] = (chk && chk.checked) ? null : inp.value;
            });

            var fd = new FormData();
            fd.append('table_name', state.table);
            fd.append('values', JSON.stringify(values));
            if (isEdit) fd.append('keys', JSON.stringify(rowKeys(row)));

            dbCall(isEdit ? 'update_row' : 'insert_row', 'POST', fd, function (res) {
                if (!res.success) return toast(res.message, true);
                closeModal();
                toast(res.message);
                inspectTable(state.table);
            });
        };
    }

    function openColumnModal(colObj) {
        var isEdit = !!colObj;
        var match = isEdit ? colObj.Type.match(/^([a-zA-Z]+)(?:\(([^)]+)\))?/) : null;
        var curType = match ? match[1].toUpperCase() : 'VARCHAR';
        var curLen = (match && match[2]) ? match[2] : (isEdit ? '' : '100');
        var curNull = isEdit ? (colObj.Null === 'YES' ? '1' : '0') : '1';

        var types = ['VARCHAR', 'INT', 'BIGINT', 'TEXT', 'DECIMAL', 'DATE', 'DATETIME', 'TIMESTAMP', 'TINYINT'];
        var opts = types.map(function (t) {
            return '<option value="' + t + '"' + (t === curType ? ' selected' : '') + '>' + t + '</option>';
        }).join('');

        openModal(isEdit ? 'Editar Coluna' : 'Nova Coluna',
            '<form id="colForm">' +
                '<div class="form-row"><label class="form-label">Nome</label><input type="text" id="cName" class="input" value="' + (isEdit ? esc(colObj.Field) : '') + '" required></div>' +
                '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0.6rem;">' +
                    '<div class="form-row"><label class="form-label">Tipo</label><select id="cType" class="select">' + opts + '</select></div>' +
                    '<div class="form-row"><label class="form-label">Tamanho</label><input type="text" id="cLen" class="input" value="' + esc(curLen) + '"></div>' +
                    '<div class="form-row"><label class="form-label">Null</label><select id="cNull" class="select"><option value="1"' + (curNull === '1' ? ' selected' : '') + '>YES</option><option value="0"' + (curNull === '0' ? ' selected' : '') + '>NO</option></select></div>' +
                '</div>' +
                '<div class="form-row"><label class="form-label">Default</label><input type="text" id="cDef" class="input" value="' + (isEdit && colObj.Default !== null ? esc(colObj.Default) : '') + '"></div>' +
                '<div class="modal-actions">' +
                    '<button type="button" class="btn btn-ghost" id="mCancel">Cancelar</button>' +
                    '<button type="submit" class="btn btn-primary">Salvar</button>' +
                '</div>' +
            '</form>'
        );

        $('#mCancel').onclick = closeModal;
        $('#colForm').onsubmit = function (e) {
            e.preventDefault();
            var fd = new FormData();
            fd.append('table_name', state.table);
            if (isEdit) {
                fd.append('old_col_name', colObj.Field);
                fd.append('new_col_name', $('#cName').value);
                fd.append('extra', colObj.Extra || '');
            } else {
                fd.append('col_name', $('#cName').value);
            }
            fd.append('type', $('#cType').value);
            fd.append('length', $('#cLen').value);
            fd.append('nullable', $('#cNull').value);
            fd.append('default_val', $('#cDef').value);

            dbCall(isEdit ? 'edit_column' : 'add_column', 'POST', fd, function (res) {
                if (!res.success) return toast(res.message, true);
                closeModal();
                toast(res.message);
                inspectTable(state.table);
            });
        };
    }

    function openNewTableModal() {
        openModal('Nova Tabela',
            '<form id="newTableForm">' +
                '<div class="form-row"><label class="form-label">Nome da Tabela</label><input type="text" id="ntName" class="input" placeholder="ex: usuarios" required></div>' +
                '<div class="section-bar" style="margin-top:0.5rem;"><h4>Colunas</h4><button type="button" class="btn btn-xs btn-ghost" id="btnAddNtCol">+ Coluna</button></div>' +
                '<div id="ntCols" style="display:flex;flex-direction:column;gap:0.45rem;"></div>' +
                '<div class="modal-actions">' +
                    '<button type="button" class="btn btn-ghost" id="mCancel">Cancelar</button>' +
                    '<button type="submit" class="btn btn-primary">Criar Tabela</button>' +
                '</div>' +
            '</form>'
        );

        function addColRow(name, type, len, extra, pk, def) {
            var row = document.createElement('div');
            row.className = 'nt-col-row';
            row.style.cssText = 'display:grid;grid-template-columns:1.3fr 1fr 0.6fr 0.8fr 0.5fr auto;gap:0.4rem;align-items:center;';
            row.dataset.def = def || '';
            row.innerHTML =
                '<input type="text" class="input n-name" placeholder="coluna" value="' + (name || '') + '">' +
                '<select class="select n-type">' +
                    '<option value="INT"' + (type === 'INT' ? ' selected' : '') + '>INT</option>' +
                    '<option value="VARCHAR"' + (type === 'VARCHAR' ? ' selected' : '') + '>VARCHAR</option>' +
                    '<option value="TEXT"' + (type === 'TEXT' ? ' selected' : '') + '>TEXT</option>' +
                    '<option value="DECIMAL"' + (type === 'DECIMAL' ? ' selected' : '') + '>DECIMAL</option>' +
                    '<option value="DATETIME"' + (type === 'DATETIME' ? ' selected' : '') + '>DATETIME</option>' +
                    '<option value="TIMESTAMP"' + (type === 'TIMESTAMP' ? ' selected' : '') + '>TIMESTAMP</option>' +
                '</select>' +
                '<input type="text" class="input n-len" placeholder="tam" value="' + (len || '') + '">' +
                '<select class="select n-ext"><option value="">—</option><option value="AUTO_INCREMENT"' + (extra === 'AUTO_INCREMENT' ? ' selected' : '') + '>AI</option></select>' +
                '<label class="mono muted" style="font-size:11px;display:flex;align-items:center;gap:3px;"><input type="checkbox" class="n-pk"' + (pk ? ' checked' : '') + '>PK</label>' +
                '<button type="button" class="icon-btn danger" onclick="this.parentNode.remove()">&times;</button>';
            $('#ntCols').appendChild(row);
        }

        addColRow('id', 'INT', '11', 'AUTO_INCREMENT', true, '');
        addColRow('nome', 'VARCHAR', '150', '', false, '');
        addColRow('criado_em', 'TIMESTAMP', '', '', false, 'CURRENT_TIMESTAMP');

        $('#btnAddNtCol').onclick = function () { addColRow('', 'VARCHAR', '100', '', false, ''); };
        $('#mCancel').onclick = closeModal;

        $('#newTableForm').onsubmit = function (e) {
            e.preventDefault();
            var cols = [];
            $$('.nt-col-row', $('#ntCols')).forEach(function (r) {
                var n = r.querySelector('.n-name').value.trim();
                if (!n) return;
                cols.push({
                    name: n,
                    type: r.querySelector('.n-type').value,
                    length: r.querySelector('.n-len').value,
                    extra: r.querySelector('.n-ext').value,
                    primary: r.querySelector('.n-pk').checked,
                    nullable: '0',
                    default_val: r.dataset.def
                });
            });

            var tableName = $('#ntName').value.trim();
            var fd = new FormData();
            fd.append('table_name', tableName);
            fd.append('columns', JSON.stringify(cols));

            dbCall('create_table', 'POST', fd, function (res) {
                if (!res.success) return toast(res.message, true);
                closeModal();
                toast(res.message);
                switchView('database');
                switchSubtab('schema');
                inspectTable(tableName);
            });
        };
    }

    // SQL Console & Routines
    function runSql() {
        var sql = $('#sqlInput').value;
        var fb = $('#sqlFeedback');
        var grid = $('#sqlResultGrid');

        fb.className = 'status-line';
        fb.classList.remove('hidden');
        fb.textContent = 'Executando...';
        grid.innerHTML = '';

        var fd = new FormData();
        fd.append('sql', sql);

        dbCall('run_sql', 'POST', fd, function (res) {
            if (!res.success) {
                fb.className = 'status-line err';
                fb.textContent = res.message;
                return;
            }

            var rows = res.data.rows || [];
            fb.className = 'status-line ok';
            fb.textContent = res.data.elapsed_ms + ' ms · ' + (rows.length || res.data.affected_rows) + ' linha(s)';

            if (rows.length > 0) {
                var keys = Object.keys(rows[0]);
                var th = keys.map(function (k) { return '<th>' + esc(k) + '</th>'; }).join('');
                var tb = rows.map(function (r) {
                    return '<tr>' + keys.map(function (k) {
                        return '<td>' + (r[k] !== null ? esc(r[k]) : '<span class="muted">NULL</span>') + '</td>';
                    }).join('') + '</tr>';
                }).join('');
                grid.innerHTML = '<table><thead><tr>' + th + '</tr></thead><tbody>' + tb + '</tbody></table>';
            }

            loadTables();
            loadRoutines();
        });
    }

    function loadRoutines() {
        dbCall('list_routines', 'GET', null, function (res) {
            var wrap = $('#routinesGrid');
            if (!res.success || !res.data || res.data.length === 0) {
                wrap.innerHTML = '<div class="empty-inline">Nenhuma routine encontrada.</div>';
                return;
            }

            var body = res.data.map(function (rt) {
                return '<tr>' +
                    '<td class="mono"><strong>' + esc(rt.name) + '</strong></td>' +
                    '<td class="mono muted">' + esc(rt.type) + '</td>' +
                    '<td class="mono muted">' + esc(rt.returns || 'VOID') + '</td>' +
                    '<td style="text-align:right;white-space:nowrap;">' +
                        '<button type="button" class="btn btn-xs btn-ghost" data-view-rt="' + esc(rt.name) + '" data-rt-type="' + esc(rt.type) + '">SQL</button> ' +
                        '<button type="button" class="btn btn-xs btn-danger-outline" data-del-rt="' + esc(rt.name) + '" data-rt-type="' + esc(rt.type) + '">Excluir</button>' +
                    '</td>' +
                '</tr>';
            }).join('');

            wrap.innerHTML = '<table><thead><tr><th>Nome</th><th>Tipo</th><th>Retorno</th><th></th></tr></thead><tbody>' + body + '</tbody></table>';
        });
    }

    // Event Wiring
    document.addEventListener('DOMContentLoaded', function () {
        $$('.nav-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                switchView(btn.getAttribute('data-view'));
            });
        });

        $$('[data-jump]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                switchView(btn.getAttribute('data-jump'));
            });
        });

        $$('.seg-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                switchSubtab(btn.getAttribute('data-subtab'));
            });
        });

        $('#btnCloseModal').addEventListener('click', closeModal);
        $('#appModal').addEventListener('click', function (e) {
            if (e.target === $('#appModal')) closeModal();
        });

        $('#btnRefreshDash').addEventListener('click', loadDashboard);
        $('#dashTablesList').addEventListener('click', function (e) {
            var btn = e.target.closest('[data-open-table]');
            if (!btn) return;
            var tbl = btn.getAttribute('data-open-table');
            switchView('database');
            switchSubtab('schema');
            inspectTable(tbl);
        });

        initWorkspace();

        $('#btnOpenNewTable').addEventListener('click', openNewTableModal);
        $('#btnReloadTables').addEventListener('click', loadTables);
        $('#btnReloadRoutines').addEventListener('click', loadRoutines);

        $('#schemaTablesList').addEventListener('click', function (e) {
            var btn = e.target.closest('[data-select-table]');
            if (btn) inspectTable(btn.getAttribute('data-select-table'));
        });

        $('#tableInspector').addEventListener('click', function (e) {
            var t = e.target;
            if (t.id === 'btnNewRow') return openRowModal(null);
            if (t.id === 'btnNewCol') return openColumnModal(null);

            if (t.id === 'btnRenameTable') {
                openModal('Renomear Tabela',
                    '<form id="renForm">' +
                        '<div class="form-row"><label class="form-label">Novo nome</label><input type="text" id="renInput" class="input" value="' + esc(state.table) + '" required></div>' +
                        '<div class="modal-actions"><button type="button" class="btn btn-ghost" id="mCancel">Cancelar</button><button type="submit" class="btn btn-primary">Salvar</button></div>' +
                    '</form>'
                );
                $('#mCancel').onclick = closeModal;
                $('#renForm').onsubmit = function (ev) {
                    ev.preventDefault();
                    var next = $('#renInput').value.trim();
                    var fd = new FormData();
                    fd.append('old_name', state.table);
                    fd.append('new_name', next);
                    dbCall('rename_table', 'POST', fd, function (res) {
                        if (!res.success) return toast(res.message, true);
                        closeModal();
                        toast(res.message);
                        inspectTable(next);
                    });
                };
                return;
            }

            if (t.id === 'btnDropTable') {
                openModal('Excluir Tabela',
                    '<p class="muted">Excluir permanentemente <strong class="mono">' + esc(state.table) + '</strong>?</p>' +
                    '<div class="modal-actions"><button type="button" class="btn btn-ghost" id="mCancel">Cancelar</button><button type="button" class="btn btn-danger-outline" id="mDrop">Excluir</button></div>'
                );
                $('#mCancel').onclick = closeModal;
                $('#mDrop').onclick = function () {
                    var fd = new FormData();
                    fd.append('table_name', state.table);
                    dbCall('drop_table', 'POST', fd, function (res) {
                        closeModal();
                        if (!res.success) return toast(res.message, true);
                        state.table = '';
                        toast(res.message);
                        loadTables();
                        $('#tableInspector').innerHTML = '<div class="empty-state">Selecione uma tabela à esquerda.</div>';
                    });
                };
                return;
            }

            if (t.hasAttribute('data-edit-row')) {
                return openRowModal(parseInt(t.getAttribute('data-edit-row'), 10));
            }

            if (t.hasAttribute('data-del-row')) {
                var idx = parseInt(t.getAttribute('data-del-row'), 10);
                var fd = new FormData();
                fd.append('table_name', state.table);
                fd.append('keys', JSON.stringify(rowKeys(state.rows[idx])));
                dbCall('delete_row', 'POST', fd, function (res) {
                    if (!res.success) return toast(res.message, true);
                    toast(res.message);
                    inspectTable(state.table);
                });
                return;
            }

            if (t.hasAttribute('data-edit-col')) {
                var cIdx = parseInt(t.getAttribute('data-edit-col'), 10);
                return openColumnModal(state.columns[cIdx]);
            }

            if (t.hasAttribute('data-del-col')) {
                var colName = t.getAttribute('data-del-col');
                var fdCol = new FormData();
                fdCol.append('table_name', state.table);
                fdCol.append('col_name', colName);
                dbCall('drop_column', 'POST', fdCol, function (res) {
                    if (!res.success) return toast(res.message, true);
                    toast(res.message);
                    inspectTable(state.table);
                });
            }
        });

        // SQL Editor
        var templates = {
            select: "SELECT * FROM logs_estudo ORDER BY id DESC LIMIT 20;",
            procedure: "CREATE PROCEDURE sp_exemplo(IN p_valor VARCHAR(100))\nBEGIN\n    SELECT p_valor AS resultado;\nEND",
            function: "CREATE FUNCTION fn_exemplo(p_num INT)\nRETURNS INT\nDETERMINISTIC\nBEGIN\n    RETURN p_num * 2;\nEND"
        };

        $$('[data-tpl]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                $('#sqlInput').value = templates[btn.getAttribute('data-tpl')] || '';
                $('#sqlInput').focus();
            });
        });

        $('#btnClearSql').addEventListener('click', function () {
            $('#sqlInput').value = '';
            $('#sqlResultGrid').innerHTML = '';
            $('#sqlFeedback').classList.add('hidden');
        });

        $('#btnRunSql').addEventListener('click', runSql);
        $('#sqlInput').addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && (e.key === 'Enter' || e.keyCode === 13)) {
                e.preventDefault();
                runSql();
            }
        });

        $('#routinesGrid').addEventListener('click', function (e) {
            var viewBtn = e.target.closest('[data-view-rt]');
            if (viewBtn) {
                var name = viewBtn.getAttribute('data-view-rt');
                var type = viewBtn.getAttribute('data-rt-type');
                dbCall('get_routine_code', 'GET', 'name=' + encodeURIComponent(name) + '&type=' + encodeURIComponent(type), function (res) {
                    if (!res.success) return toast(res.message, true);
                    $('#sqlInput').value = res.code;
                });
                return;
            }

            var delBtn = e.target.closest('[data-del-rt]');
            if (delBtn) {
                var fd = new FormData();
                fd.append('name', delBtn.getAttribute('data-del-rt'));
                fd.append('type', delBtn.getAttribute('data-rt-type'));
                dbCall('drop_routine', 'POST', fd, function (res) {
                    if (!res.success) return toast(res.message, true);
                    toast(res.message);
                    loadRoutines();
                });
            }
        });

        var initialHash = window.location.hash.replace('#', '');
        if (initialHash === 'workspace' || initialHash === 'database' || initialHash === 'dashboard') {
            switchView(initialHash);
        } else {
            loadDashboard();
        }
    });
})();
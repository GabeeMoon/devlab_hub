(function () {
    'use strict';

    var state = {
        file: '',
        ext: 'php',
        dirty: false,
        reloadOnClose: false
    };

    function $(sel, root) { return (root || document).querySelector(sel); }
    function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

    function esc(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function escAttr(str) {
        return esc(str).replace(/"/g, '&quot;');
    }

    function toast(msg, isError) {
        var stack = $('#toastStack');
        if (!stack) return;
        var el = document.createElement('div');
        el.className = 'toast ' + (isError ? 'err' : 'ok');
        el.textContent = msg;
        stack.appendChild(el);
        setTimeout(function () {
            if (el.parentNode) el.parentNode.removeChild(el);
        }, 3200);
    }

    function api(action, method, body, done) {
        var url = 'index.php?ide_action=' + encodeURIComponent(action);
        if (method === 'GET' && typeof body === 'string' && body !== '') {
            url += '&' + body;
            body = null;
        }

        var xhr = new XMLHttpRequest();
        xhr.open(method, url, true);
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) return;
            var res;
            try {
                res = JSON.parse(xhr.responseText);
            } catch (e) {
                var cleanErr = (xhr.responseText || '').replace(/<[^>]+>/g, ' ').trim().slice(0, 140);
                res = {
                    success: false,
                    message: cleanErr ? 'Erro PHP: ' + cleanErr : 'Resposta inválida do servidor.'
                };
            }
            if (typeof done === 'function') done(res);
        };
        xhr.send(body || null);
    }

    function confirmDialog(title, message, onConfirm) {
        var modal = $('#confirmModal');
        if (!modal) {
            if (window.confirm(message) && typeof onConfirm === 'function') onConfirm();
            return;
        }

        $('#confirmTitle').textContent = title;
        $('#confirmMessage').textContent = message;
        modal.classList.remove('hidden');

        function cleanup() {
            modal.classList.add('hidden');
            $('#btnConfirmOk').onclick = null;
            $('#btnConfirmCancel').onclick = null;
            $('#btnCloseConfirm').onclick = null;
        }

        $('#btnConfirmCancel').onclick = cleanup;
        $('#btnCloseConfirm').onclick = cleanup;
        $('#btnConfirmOk').onclick = function () {
            cleanup();
            if (typeof onConfirm === 'function') onConfirm();
        };
    }

    // ==========================================
    // SYNTAX HIGHLIGHTER POR LINGUAGEM
    // ==========================================
    function highlightCode(raw, ext) {
        var code = esc(raw);

        if (ext === 'sql') {
            return code.replace(
                /(--[^\n]*|\/\*[\s\S]*?\*\/)|("(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*')|\b(SELECT|FROM|WHERE|INSERT|INTO|VALUES|UPDATE|SET|DELETE|CREATE|TABLE|ALTER|DROP|ADD|COLUMN|PRIMARY|KEY|FOREIGN|REFERENCES|JOIN|LEFT|RIGHT|INNER|ON|GROUP|BY|ORDER|ASC|DESC|LIMIT|HAVING|AS|AND|OR|NOT|NULL|IS|IN|LIKE|BETWEEN|PROCEDURE|FUNCTION|BEGIN|END|RETURNS|RETURN|DECLARE|IF|ELSE|THEN|DETERMINISTIC|ENGINE|DEFAULT|CHARSET|AUTO_INCREMENT|CALL|SHOW|DESCRIBE)\b|\b(INT|BIGINT|TINYINT|VARCHAR|CHAR|TEXT|DECIMAL|FLOAT|DOUBLE|DATE|DATETIME|TIMESTAMP|BOOLEAN)\b|\b(\d+(?:\.\d+)?)\b/gi,
                function (m, comment, str, kw, dtype, num) {
                    if (comment) return '<span class="tok-comment">' + comment + '</span>';
                    if (str) return '<span class="tok-string">' + str + '</span>';
                    if (kw) return '<span class="tok-sql-kw">' + kw + '</span>';
                    if (dtype) return '<span class="tok-type">' + dtype + '</span>';
                    if (num) return '<span class="tok-number">' + num + '</span>';
                    return m;
                }
            );
        }

        if (ext === 'css') {
            return code.replace(
                /(\/\*[\s\S]*?\*\/)|("(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*')|([a-z-]+)(?=\s*:)|(#[0-9a-fA-F]{3,8}\b|\b\d+(?:\.\d+)?(?:px|rem|em|%|vh|vw|s|ms)?\b)|(@[a-z-]+)/gi,
                function (m, comment, str, prop, val, atRule) {
                    if (comment) return '<span class="tok-comment">' + comment + '</span>';
                    if (str) return '<span class="tok-string">' + str + '</span>';
                    if (prop) return '<span class="tok-prop">' + prop + '</span>';
                    if (val) return '<span class="tok-number">' + val + '</span>';
                    if (atRule) return '<span class="tok-keyword">' + atRule + '</span>';
                    return m;
                }
            );
        }

        if (ext === 'html') {
            return code.replace(
                /(&lt;!--[\s\S]*?--&gt;)|("(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*')|(&lt;\/?)([a-zA-Z0-9-]+)|(\s[a-zA-Z-:]+)(?==)/g,
                function (m, comment, str, bracket, tag, attr) {
                    if (comment) return '<span class="tok-comment">' + comment + '</span>';
                    if (str) return '<span class="tok-string">' + str + '</span>';
                    if (tag) return bracket + '<span class="tok-tag">' + tag + '</span>';
                    if (attr) return '<span class="tok-attr">' + attr + '</span>';
                    return m;
                }
            );
        }

        if (ext === 'js') {
            return code.replace(
                /(\/\/[^\n]*|\/\*[\s\S]*?\*\/)|("(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*'|`[\s\S]*?`)|\b(const|let|var|function|return|if|else|for|while|do|switch|case|break|continue|new|this|typeof|instanceof|try|catch|finally|throw|async|await|class|extends|import|export|from|true|false|null|undefined)\b|\b([a-zA-Z_$][\w$]*)(?=\s*\()|\b(\d+(?:\.\d+)?)\b/g,
                function (m, comment, str, kw, fn, num) {
                    if (comment) return '<span class="tok-comment">' + comment + '</span>';
                    if (str) return '<span class="tok-string">' + str + '</span>';
                    if (kw) return '<span class="tok-keyword">' + kw + '</span>';
                    if (fn) return '<span class="tok-func">' + fn + '</span>';
                    if (num) return '<span class="tok-number">' + num + '</span>';
                    return m;
                }
            );
        }

        // PHP + HTML misto
        return code.replace(
            /(\/\/[^\n]*|\/\*[\s\S]*?\*\/)|("(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*')|(&lt;\?php|&lt;\?=|\?&gt;)|(&lt;\/?[a-zA-Z][a-zA-Z0-9-]*)|(\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)|\b(namespace|use|class|interface|trait|abstract|final|extends|implements|public|protected|private|static|function|return|if|elseif|else|switch|case|default|break|continue|for|foreach|as|while|do|try|catch|finally|throw|new|clone|instanceof|echo|print|require|require_once|include|include_once|define|const|true|false|null|array)\b|\b(PDO|PDOException|Exception|RuntimeException|InvalidArgumentException|stdClass|DateTime|int|string|float|bool|void)\b|\b([a-zA-Z_]\w*)(?=\s*\()|\b(\d+(?:\.\d+)?)\b/g,
            function (m, comment, str, phpTag, htmlTag, variable, kw, typeName, fn, num) {
                if (comment) return '<span class="tok-comment">' + comment + '</span>';
                if (str) return '<span class="tok-string">' + str + '</span>';
                if (phpTag) return '<span class="tok-php-tag">' + phpTag + '</span>';
                if (htmlTag) return '<span class="tok-tag">' + htmlTag + '</span>';
                if (variable) return '<span class="tok-var">' + variable + '</span>';
                if (kw) return '<span class="tok-keyword">' + kw + '</span>';
                if (typeName) return '<span class="tok-type">' + typeName + '</span>';
                if (fn) return '<span class="tok-func">' + fn + '</span>';
                if (num) return '<span class="tok-number">' + num + '</span>';
                return m;
            }
        );
    }

    function renderEditorSyntax() {
        var editor = $('#ideEditor');
        var codeLayer = $('#ideCodeLayer');
        var gutter = $('#ideGutter');
        if (!editor) return;

        var val = editor.value;
        if (codeLayer) {
            try {
                var rendered = highlightCode(val, state.ext);
                if (val.slice(-1) === '\n') rendered += ' ';
                codeLayer.innerHTML = rendered;
                editor.classList.remove('no-highlight');
            } catch (e) {
                editor.classList.add('no-highlight');
            }
        }

        if (gutter) {
            var lines = val.split('\n').length;
            var out = [];
            for (var i = 1; i <= lines; i++) out.push(i);
            gutter.textContent = out.join('\n');
        }
    }

    function syncScroll() {
        var editor = $('#ideEditor');
        var pre = $('#ideHighlight');
        var gutter = $('#ideGutter');
        if (!editor) return;
        if (pre) {
            pre.scrollTop = editor.scrollTop;
            pre.scrollLeft = editor.scrollLeft;
        }
        if (gutter) {
            gutter.scrollTop = editor.scrollTop;
        }
    }

    function updateCursorStatus() {
        var editor = $('#ideEditor');
        var info = $('#ideCursorInfo');
        if (!editor || !info) return;

        var pos = editor.selectionStart || 0;
        var before = editor.value.substring(0, pos);
        var lines = before.split('\n');
        var ln = lines.length;
        var col = lines[lines.length - 1].length + 1;
        info.textContent = 'Ln ' + ln + ', Col ' + col + ' · ' + state.ext.toUpperCase();
    }

    function insertAtCursor(snippet) {
        var editor = $('#ideEditor');
        if (!editor) return;

        var start = editor.selectionStart || 0;
        var end = editor.selectionEnd || 0;
        var text = editor.value;

        editor.value = text.substring(0, start) + snippet + text.substring(end);
        editor.selectionStart = editor.selectionEnd = start + snippet.length;
        editor.focus();

        setDirty(true);
        renderEditorSyntax();
        syncScroll();
        updateCursorStatus();
    }

    function buildMediaSnippet(media) {
        var url = media.raw;
        var alt = media.name;

        if (state.ext === 'css') {
            return "url('" + url + "')";
        }
        if (state.ext === 'js' || state.ext === 'sql') {
            return "'" + url + "'";
        }

        var tag = '';
        if (media.kind === 'image') {
            tag = '<img src="' + url + '" alt="' + alt + '" style="max-width:100%;border-radius:8px;">';
        } else if (media.kind === 'video') {
            tag = '<video src="' + url + '" controls style="max-width:100%;border-radius:8px;"></video>';
        } else {
            tag = '<audio src="' + url + '" controls></audio>';
        }

        if (state.ext === 'php') {
            return "\necho '" + tag + "';\n";
        }
        return "\n" + tag + "\n";
    }

    // ==========================================
    // MEDIA MANAGER (_assets/)
    // ==========================================
    function loadMediaGallery() {
        var grid = $('#mediaGallery');
        if (!grid) return;
        grid.innerHTML = '<div class="empty-inline">Carregando...</div>';

        api('list_media', 'GET', '', function (res) {
            if (!res.success) {
                grid.innerHTML = '<div class="empty-inline">' + esc(res.message) + '</div>';
                return;
            }
            var list = res.data || [];
            if (list.length === 0) {
                grid.innerHTML = '<div class="empty-inline">Nenhuma mídia anexada neste módulo.</div>';
                return;
            }

            grid.innerHTML = list.map(function (item, idx) {
                var thumb = '';
                if (item.kind === 'image') {
                    thumb = '<img src="' + escAttr(item.url) + '" alt="' + escAttr(item.name) + '">';
                } else if (item.kind === 'video') {
                    thumb = '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2"></rect></svg>';
                } else {
                    thumb = '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 18V5l12-2v13"></path><circle cx="6" cy="18" r="3"></circle><circle cx="18" cy="16" r="3"></circle></svg>';
                }

                return '<div class="media-card">' +
                    '<div class="media-thumb">' + thumb + '</div>' +
                    '<div class="media-meta">' +
                        '<div class="media-name mono" title="' + escAttr(item.name) + '">' + esc(item.name) + '</div>' +
                        '<div class="media-actions">' +
                            '<button type="button" class="btn btn-xs btn-primary" data-insert-media="' + idx + '">Inserir</button>' +
                            '<button type="button" class="icon-btn danger" data-del-media="' + escAttr(item.name) + '" title="Remover">&times;</button>' +
                        '</div>' +
                    '</div>' +
                '</div>';
            }).join('');

            grid._items = list;
        });
    }

    function uploadMediaFile(fileObj) {
        if (!fileObj) return;
        var fd = new FormData();
        fd.append('media', fileObj);

        toast('Enviando ' + fileObj.name + '...');
        api('upload_media', 'POST', fd, function (res) {
            if (!res.success) return toast(res.message, true);
            toast(res.message);
            var modal = $('#mediaModal');
            if (modal) modal.classList.add('hidden');
            insertAtCursor(buildMediaSnippet(res.media));
        });
    }

    // ==========================================
    // IDE CORE
    // ==========================================
    function reloadOutput() {
        var frame = $('#ideFrame');
        if (!state.file || !frame) return;
        frame.src = encodeURIComponent(state.file) + '?_t=' + Date.now();
    }

    function setDirty(flag) {
        state.dirty = flag;
        var dot = $('#ideDirtyDot');
        var status = $('#ideStatus');
        if (flag) {
            if (dot) dot.classList.remove('hidden');
            if (status) status.textContent = 'Modificado';
        } else {
            if (dot) dot.classList.add('hidden');
        }
    }

    function applyLangBadge(ext) {
        var badge = $('#ideLangBadge');
        if (!badge) return;
        badge.textContent = ext.toUpperCase();
        badge.className = 'ext-tag mono lang-' + ext.toLowerCase();
    }

    function openIde(filename) {
        if (!filename) return;
        state.file = filename;
        state.ext = (filename.split('.').pop() || 'php').toLowerCase();
        setDirty(false);

        applyLangBadge(state.ext);
        $('#ideFilename').textContent = filename;
        $('#ideExternalLink').href = encodeURIComponent(filename);
        $('#ideEditor').value = '';
        $('#ideCodeLayer').innerHTML = '';
        $('#ideStatus').textContent = 'Carregando...';
        $('#ideOverlay').classList.remove('hidden');

        $('#ideRightPane').classList.remove('hidden');
        $('#ideDivider').classList.remove('hidden');
        $('#btnIdeToggleSplit').textContent = 'Ocultar Preview';
        reloadOutput();

        api('view_source', 'GET', 'file=' + encodeURIComponent(filename), function (res) {
            if (!res.success) {
                $('#ideStatus').textContent = 'Erro ao carregar';
                toast(res.message, true);
                return;
            }
            state.ext = res.ext || state.ext;
            applyLangBadge(state.ext);
            $('#ideEditor').value = res.code;
            renderEditorSyntax();
            updateCursorStatus();
            $('#ideStatus').textContent = 'Pronto';
            $('#ideEditor').focus();
        });
    }

    function saveIde() {
        if (!state.file) return;
        $('#ideStatus').textContent = 'Salvando...';

        var fd = new FormData();
        fd.append('file', state.file);
        fd.append('code', $('#ideEditor').value);

        api('save_source', 'POST', fd, function (res) {
            if (!res.success) {
                $('#ideStatus').textContent = 'Erro ao salvar';
                return toast(res.message, true);
            }
            setDirty(false);
            state.reloadOnClose = true;
            $('#ideStatus').textContent = res.message;
            reloadOutput();
        });
    }

    function closeIde() {
        function performClose() {
            $('#ideFrame').src = 'about:blank';
            $('#ideOverlay').classList.add('hidden');
            state.file = '';
            setDirty(false);
            if (state.reloadOnClose) window.location.reload();
        }

        if (state.dirty) {
            confirmDialog('Descartar alterações?', 'Existem alterações não salvas em ' + state.file + '.', performClose);
        } else {
            performClose();
        }
    }

    function deleteFile(filename, insideIde) {
        if (!filename) return;
        confirmDialog('Excluir arquivo', 'Remover permanentemente "' + filename + '"?', function () {
            var fd = new FormData();
            fd.append('file', filename);

            api('delete_file', 'POST', fd, function (res) {
                if (!res.success) return toast(res.message, true);
                toast(res.message);

                if (insideIde) {
                    state.dirty = false;
                    state.reloadOnClose = true;
                    closeIde();
                } else {
                    var row = $('.file-row[data-file="' + filename + '"]');
                    if (row) row.parentNode.removeChild(row);
                }
            });
        });
    }

    function on(el, event, handler) {
        if (el) el.addEventListener(event, handler);
    }

    document.addEventListener('DOMContentLoaded', function () {
        on($('#btnToggleNewFile'), 'click', function () {
            var bar = $('#newFileForm');
            if (!bar) return;
            bar.classList.toggle('hidden');
            if (!bar.classList.contains('hidden') && $('#newFilename')) $('#newFilename').focus();
        });

        on($('#btnCancelNewFile'), 'click', function () {
            if ($('#newFileForm')) $('#newFileForm').classList.add('hidden');
        });

        on($('#newFileForm'), 'submit', function (e) {
            e.preventDefault();
            var fd = new FormData();
            fd.append('filename', $('#newFilename').value);
            fd.append('template', $('#newFileTemplate').value);

            api('create_file', 'POST', fd, function (res) {
                if (!res.success) return toast(res.message, true);
                state.reloadOnClose = true;
                $('#newFilename').value = '';
                $('#newFileForm').classList.add('hidden');
                toast(res.message);
                openIde(res.filename);
            });
        });

        on($('#filterInput'), 'input', function () {
            var term = this.value.toLowerCase();
            $$('.file-row').forEach(function (row) {
                var hay = row.getAttribute('data-search') || '';
                row.style.display = hay.indexOf(term) !== -1 ? 'flex' : 'none';
            });
        });

        on($('#filesList'), 'click', function (e) {
            var openBtn = e.target.closest('[data-open-ide]');
            if (openBtn) return openIde(openBtn.getAttribute('data-open-ide'));

            var delBtn = e.target.closest('[data-delete-file]');
            if (delBtn) return deleteFile(delBtn.getAttribute('data-delete-file'), false);
        });

        // IDE Buttons
        on($('#btnIdeSave'), 'click', saveIde);
        on($('#btnIdeReload'), 'click', reloadOutput);
        on($('#btnFrameReload'), 'click', reloadOutput);
        on($('#btnIdeClose'), 'click', closeIde);
        on($('#btnIdeDelete'), 'click', function () {
            if (state.file) deleteFile(state.file, true);
        });

        on($('#btnIdeToggleSplit'), 'click', function () {
            var right = $('#ideRightPane');
            var div = $('#ideDivider');
            if (!right || !div) return;
            right.classList.toggle('hidden');
            div.classList.toggle('hidden');
            this.textContent = right.classList.contains('hidden') ? 'Mostrar Preview' : 'Ocultar Preview';
            if (!right.classList.contains('hidden')) reloadOutput();
        });

        on($('#btnToggleCanvas'), 'click', function () {
            if ($('#ideFrame')) $('#ideFrame').classList.toggle('dark');
        });

        // Media Modal
        on($('#btnIdeMedia'), 'click', function () {
            if ($('#mediaModal')) $('#mediaModal').classList.remove('hidden');
            loadMediaGallery();
        });

        on($('#btnCloseMedia'), 'click', function () {
            if ($('#mediaModal')) $('#mediaModal').classList.add('hidden');
        });

        on($('#mediaModal'), 'click', function (e) {
            if (e.target === $('#mediaModal')) $('#mediaModal').classList.add('hidden');
        });

        var fileInput = $('#mediaFileInput');
        var dropZone = $('#mediaDropZone');

        on(fileInput, 'change', function () {
            if (this.files && this.files[0]) {
                uploadMediaFile(this.files[0]);
                this.value = '';
            }
        });

        on(dropZone, 'dragover', function (e) {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });

        on(dropZone, 'dragleave', function () {
            dropZone.classList.remove('dragover');
        });

        on(dropZone, 'drop', function (e) {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]) {
                uploadMediaFile(e.dataTransfer.files[0]);
            }
        });

        on($('#mediaGallery'), 'click', function (e) {
            var insBtn = e.target.closest('[data-insert-media]');
            if (insBtn) {
                var idx = parseInt(insBtn.getAttribute('data-insert-media'), 10);
                var items = $('#mediaGallery')._items || [];
                if (items[idx]) {
                    $('#mediaModal').classList.add('hidden');
                    insertAtCursor(buildMediaSnippet(items[idx]));
                }
                return;
            }

            var delBtn = e.target.closest('[data-del-media]');
            if (delBtn) {
                var name = delBtn.getAttribute('data-del-media');
                var fd = new FormData();
                fd.append('name', name);
                api('delete_media', 'POST', fd, function (res) {
                    if (!res.success) return toast(res.message, true);
                    toast(res.message);
                    loadMediaGallery();
                });
            }
        });

        // Editor Sync, Paste Media & Shortcuts
        var editor = $('#ideEditor');
        on(editor, 'input', function () {
            setDirty(true);
            renderEditorSyntax();
            syncScroll();
            updateCursorStatus();
        });

        on(editor, 'scroll', syncScroll);
        on(editor, 'click', updateCursorStatus);
        on(editor, 'keyup', updateCursorStatus);

        on(editor, 'paste', function (e) {
            if (!e.clipboardData || !e.clipboardData.items) return;
            var items = e.clipboardData.items;
            for (var i = 0; i < items.length; i++) {
                if (items[i].type && items[i].type.indexOf('image/') === 0) {
                    e.preventDefault();
                    var blob = items[i].getAsFile();
                    if (blob) {
                        var ext = (items[i].type.split('/')[1] || 'png').replace('jpeg', 'jpg');
                        var namedFile = new File([blob], 'paste_' + Date.now() + '.' + ext, { type: items[i].type });
                        uploadMediaFile(namedFile);
                    }
                    break;
                }
            }
        });

        on(editor, 'keydown', function (e) {
            if (e.key === 'Tab' || e.keyCode === 9) {
                e.preventDefault();
                insertAtCursor('    ');
            }

            if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S' || e.keyCode === 83)) {
                e.preventDefault();
                saveIde();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                if ($('#mediaModal') && !$('#mediaModal').classList.contains('hidden')) {
                    $('#mediaModal').classList.add('hidden');
                    return;
                }
                if ($('#ideOverlay') && !$('#ideOverlay').classList.contains('hidden')) {
                    closeIde();
                }
            }
        });

        // Split Divider Resizer
        var dragging = false;
        var divider = $('#ideDivider');
        var leftPane = $('#ideLeftPane');
        var split = $('#ideSplit');

        on(divider, 'mousedown', function () {
            dragging = true;
            divider.classList.add('dragging');
            if ($('#ideFrame')) $('#ideFrame').style.pointerEvents = 'none';
            document.body.style.userSelect = 'none';
        });

        document.addEventListener('mousemove', function (e) {
            if (!dragging || !split || !leftPane) return;
            var rect = split.getBoundingClientRect();
            var pct = ((e.clientX - rect.left) / rect.width) * 100;
            if (pct > 20 && pct < 80) {
                leftPane.style.flex = '0 0 ' + pct + '%';
            }
        });

        document.addEventListener('mouseup', function () {
            if (!dragging) return;
            dragging = false;
            if (divider) divider.classList.remove('dragging');
            if ($('#ideFrame')) $('#ideFrame').style.pointerEvents = 'auto';
            document.body.style.userSelect = '';
        });

        window.DevLabIDE = { open: openIde, save: saveIde, close: closeIde };
    });
})();
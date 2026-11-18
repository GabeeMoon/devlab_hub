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
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function toast(msg, isError) {
        var stack = $('#toastStack');
        var el = document.createElement('div');
        el.className = 'toast ' + (isError ? 'err' : 'ok');
        el.textContent = msg;
        stack.appendChild(el);
        setTimeout(function () {
            if (el.parentNode) el.parentNode.removeChild(el);
        }, 3000);
    }

    function api(action, method, body, done) {
        var url = 'index.php?ide_action=' + encodeURIComponent(action);
        if (method === 'GET' && typeof body === 'string') {
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
                res = { success: false, message: 'Resposta inválida do servidor.' };
            }
            done(res);
        };
        xhr.send(body || null);
    }

    function confirmDialog(title, message, onConfirm) {
        $('#confirmTitle').textContent = title;
        $('#confirmMessage').textContent = message;
        $('#confirmModal').classList.remove('hidden');

        function cleanup() {
            $('#confirmModal').classList.add('hidden');
            $('#btnConfirmOk').onclick = null;
            $('#btnConfirmCancel').onclick = null;
            $('#btnCloseConfirm').onclick = null;
        }

        $('#btnConfirmCancel').onclick = cleanup;
        $('#btnCloseConfirm').onclick = cleanup;
        $('#btnConfirmOk').onclick = function () {
            cleanup();
            onConfirm();
        };
    }

    // ==========================================
    // SYNTAX HIGHLIGHTER POR LINGUAGEM
    // ==========================================
    function highlightCode(raw, ext) {
        var code = esc(raw);

        if (ext === 'sql') {
            return code.replace(
                /(--[^\n]*|\/\*[\s\S]*?\*\/)|(&quot;(?:\\.|[^&])*?&quot;|'(?:\\.|[^'])*?')|\b(SELECT|FROM|WHERE|INSERT|INTO|VALUES|UPDATE|SET|DELETE|CREATE|TABLE|ALTER|DROP|ADD|COLUMN|PRIMARY|KEY|FOREIGN|REFERENCES|JOIN|LEFT|RIGHT|INNER|ON|GROUP|BY|ORDER|ASC|DESC|LIMIT|HAVING|AS|AND|OR|NOT|NULL|IS|IN|LIKE|BETWEEN|PROCEDURE|FUNCTION|BEGIN|END|RETURNS|RETURN|DECLARE|IF|ELSE|THEN|DETERMINISTIC|ENGINE|DEFAULT|CHARSET|AUTO_INCREMENT|CALL|SHOW|DESCRIBE)\b|\b(INT|BIGINT|TINYINT|VARCHAR|CHAR|TEXT|DECIMAL|FLOAT|DOUBLE|DATE|DATETIME|TIMESTAMP|BOOLEAN)\b|\b(\d+(?:\.\d+)?)\b/gi,
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
                /(\/\*[\s\S]*?\*\/)|(&quot;(?:\\.|[^&])*?&quot;|'(?:\\.|[^'])*?')|([a-z-]+)(?=\s*:)|(#[0-9a-fA-F]{3,8}\b|\b\d+(?:\.\d+)?(?:px|rem|em|%|vh|vw|s|ms)?\b)|(@[a-z-]+)/gi,
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
                /(&lt;!--[\s\S]*?--&gt;)|(&quot;(?:\\.|[^&])*?&quot;|'(?:\\.|[^'])*?')|(&lt;\/?)([a-zA-Z0-9-]+)|(\s[a-zA-Z-:]+)(?==)/g,
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
                /(\/\/[^\n]*|\/\*[\s\S]*?\*\/)|(&quot;(?:\\.|[^&])*?&quot;|'(?:\\.|[^'])*?'|`[\s\S]*?`)|\b(const|let|var|function|return|if|else|for|while|do|switch|case|break|continue|new|this|typeof|instanceof|try|catch|finally|throw|async|await|class|extends|import|export|from|true|false|null|undefined)\b|\b([a-zA-Z_$][\w$]*)(?=\s*\()|\b(\d+(?:\.\d+)?)\b/g,
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

        // PHP (padrão)
        return code.replace(
            /(\/\/[^\n]*|#[^\n]*|\/\*[\s\S]*?\*\/)|(&quot;(?:\\.|[^&])*?&quot;|'(?:\\.|[^'])*?')|(&lt;\?php|&lt;\?=|\?&gt;)|(\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)|\b(namespace|use|class|interface|trait|abstract|final|extends|implements|public|protected|private|static|function|return|if|elseif|else|switch|case|default|break|continue|for|foreach|as|while|do|try|catch|finally|throw|new|clone|instanceof|echo|print|require|require_once|include|include_once|define|const|true|false|null|array)\b|\b(PDO|PDOException|Exception|RuntimeException|InvalidArgumentException|stdClass|DateTime|int|string|float|bool|void)\b|\b([a-zA-Z_]\w*)(?=\s*\()|\b(\d+(?:\.\d+)?)\b/g,
            function (m, comment, str, phpTag, variable, kw, typeName, fn, num) {
                if (comment) return '<span class="tok-comment">' + comment + '</span>';
                if (str) return '<span class="tok-string">' + str + '</span>';
                if (phpTag) return '<span class="tok-php-tag">' + phpTag + '</span>';
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
        var val = editor.value;
        // Garante quebra de linha final visível no <pre>
        var rendered = highlightCode(val, state.ext);
        if (val.slice(-1) === '\n') rendered += ' ';
        $('#ideCodeLayer').innerHTML = rendered;

        var lines = val.split('\n').length;
        var out = [];
        for (var i = 1; i <= lines; i++) out.push(i);
        $('#ideGutter').textContent = out.join('\n');
    }

    function syncScroll() {
        var editor = $('#ideEditor');
        var pre = $('#ideHighlight');
        var gutter = $('#ideGutter');
        pre.scrollTop = editor.scrollTop;
        pre.scrollLeft = editor.scrollLeft;
        gutter.scrollTop = editor.scrollTop;
    }

    function updateCursorStatus() {
        var editor = $('#ideEditor');
        var pos = editor.selectionStart || 0;
        var before = editor.value.substring(0, pos);
        var lines = before.split('\n');
        var ln = lines.length;
        var col = lines[lines.length - 1].length + 1;
        $('#ideCursorInfo').textContent = 'Ln ' + ln + ', Col ' + col + ' · ' + state.ext.toUpperCase();
    }

    function insertAtCursor(snippet) {
        var editor = $('#ideEditor');
        var start = editor.selectionStart;
        var end = editor.selectionEnd;
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

        // HTML ou PHP
        var tag = '';
        if (media.kind === 'image') {
            tag = '<img src="' + url + '" alt="' + alt + '" style="max-width:100%;border-radius:8px;">';
        } else if (media.kind === 'video') {
            tag = '<video src="' + url + '" controls style="max-width:100%;border-radius:8px;"></video>';
        } else {
            tag = '<audio src="' + url + '" controls></audio>';
        }

        if (state.ext === 'php') {
            return "\necho '" . tag . "';\n";
        }
        return "\n" + tag + "\n";
    }

    // ==========================================
    // MEDIA MANAGER (_assets/)
    // ==========================================
    function loadMediaGallery() {
        var grid = $('#mediaGallery');
        grid.innerHTML = '<div class="empty-inline">Carregando...</div>';

        api('list_media', 'GET', null, function (res) {
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
                    thumb = '<img src="' + esc(item.url) + '" alt="' + esc(item.name) + '">';
                } else if (item.kind === 'video') {
                    thumb = '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2"></rect></svg>';
                } else {
                    thumb = '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 18V5l12-2v13"></path><circle cx="6" cy="18" r="3"></circle><circle cx="18" cy="16" r="3"></circle></svg>';
                }

                return '<div class="media-card" data-media-idx="' + idx + '">' +
                    '<div class="media-thumb">' + thumb + '</div>' +
                    '<div class="media-meta">' +
                        '<div class="media-name mono" title="' + esc(item.name) + '">' + esc(item.name) + '</div>' +
                        '<div class="media-actions">' +
                            '<button type="button" class="btn btn-xs btn-primary" data-insert-media="' + idx + '">Inserir</button>' +
                            '<button type="button" class="icon-btn danger" data-del-media="' + esc(item.name) + '" title="Remover">&times;</button>' +
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
            $('#mediaModal').classList.add('hidden');
            insertAtCursor(buildMediaSnippet(res.media));
        });
    }

    // ==========================================
    // IDE CORE
    // ==========================================
    function reloadOutput() {
        if (!state.file) return;
        $('#ideFrame').src = encodeURIComponent(state.file) + '?_t=' + Date.now();
    }

    function setDirty(flag) {
        state.dirty = flag;
        if (flag) {
            $('#ideDirtyDot').classList.remove('hidden');
            $('#ideStatus').textContent = 'Modificado';
        } else {
            $('#ideDirtyDot').classList.add('hidden');
        }
    }

    function applyLangBadge(ext) {
        var badge = $('#ideLangBadge');
        badge.textContent = ext.toUpperCase();
        badge.className = 'ext-tag mono lang-' + ext.toLowerCase();
    }

    function openIde(filename) {
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

    document.addEventListener('DOMContentLoaded', function () {
        $('#btnToggleNewFile').addEventListener('click', function () {
            var bar = $('#newFileForm');
            bar.classList.toggle('hidden');
            if (!bar.classList.contains('hidden')) $('#newFilename').focus();
        });

        $('#btnCancelNewFile').addEventListener('click', function () {
            $('#newFileForm').classList.add('hidden');
        });

        $('#newFileForm').addEventListener('submit', function (e) {
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

        $('#filterInput').addEventListener('input', function () {
            var term = this.value.toLowerCase();
            $$('.file-row').forEach(function (row) {
                var hay = row.getAttribute('data-search') || '';
                row.style.display = hay.indexOf(term) !== -1 ? 'flex' : 'none';
            });
        });

        $('#filesList').addEventListener('click', function (e) {
            var openBtn = e.target.closest('[data-open-ide]');
            if (openBtn) return openIde(openBtn.getAttribute('data-open-ide'));

            var delBtn = e.target.closest('[data-delete-file]');
            if (delBtn) return deleteFile(delBtn.getAttribute('data-delete-file'), false);
        });

        // IDE Buttons
        $('#btnIdeSave').addEventListener('click', saveIde);
        $('#btnIdeReload').addEventListener('click', reloadOutput);
        $('#btnFrameReload').addEventListener('click', reloadOutput);
        $('#btnIdeClose').addEventListener('click', closeIde);
        $('#btnIdeDelete').addEventListener('click', function () {
            if (state.file) deleteFile(state.file, true);
        });

        $('#btnIdeToggleSplit').addEventListener('click', function () {
            var right = $('#ideRightPane');
            var div = $('#ideDivider');
            right.classList.toggle('hidden');
            div.classList.toggle('hidden');
            this.textContent = right.classList.contains('hidden') ? 'Mostrar Preview' : 'Ocultar Preview';
            if (!right.classList.contains('hidden')) reloadOutput();
        });

        $('#btnToggleCanvas').addEventListener('click', function () {
            $('#ideFrame').classList.toggle('dark');
        });

        // Media Modal Events
        $('#btnIdeMedia').addEventListener('click', function () {
            $('#mediaModal').classList.remove('hidden');
            loadMediaGallery();
        });

        $('#btnCloseMedia').addEventListener('click', function () {
            $('#mediaModal').classList.add('hidden');
        });

        $('#mediaModal').addEventListener('click', function (e) {
            if (e.target === $('#mediaModal')) $('#mediaModal').classList.add('hidden');
        });

        var fileInput = $('#mediaFileInput');
        var dropZone = $('#mediaDropZone');

        fileInput.addEventListener('change', function () {
            if (this.files && this.files[0]) {
                uploadMediaFile(this.files[0]);
                this.value = '';
            }
        });

        dropZone.addEventListener('dragover', function (e) {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });

        dropZone.addEventListener('dragleave', function () {
            dropZone.classList.remove('dragover');
        });

        dropZone.addEventListener('drop', function (e) {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]) {
                uploadMediaFile(e.dataTransfer.files[0]);
            }
        });

        $('#mediaGallery').addEventListener('click', function (e) {
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

        // Editor Sync & Shortcuts
        var editor = $('#ideEditor');
        editor.addEventListener('input', function () {
            setDirty(true);
            renderEditorSyntax();
            syncScroll();
            updateCursorStatus();
        });

        editor.addEventListener('scroll', syncScroll);
        editor.addEventListener('click', updateCursorStatus);
        editor.addEventListener('keyup', updateCursorStatus);

        editor.addEventListener('keydown', function (e) {
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
                if (!$('#mediaModal').classList.contains('hidden')) {
                    $('#mediaModal').classList.add('hidden');
                    return;
                }
                if (!$('#ideOverlay').classList.contains('hidden')) {
                    closeIde();
                }
            }
        });

        // Split Divider Resizer
        var dragging = false;
        var divider = $('#ideDivider');
        var leftPane = $('#ideLeftPane');
        var split = $('#ideSplit');

        divider.addEventListener('mousedown', function () {
            dragging = true;
            divider.classList.add('dragging');
            $('#ideFrame').style.pointerEvents = 'none';
            document.body.style.userSelect = 'none';
        });

        document.addEventListener('mousemove', function (e) {
            if (!dragging) return;
            var rect = split.getBoundingClientRect();
            var pct = ((e.clientX - rect.left) / rect.width) * 100;
            if (pct > 20 && pct < 80) {
                leftPane.style.flex = '0 0 ' + pct + '%';
            }
        });

        document.addEventListener('mouseup', function () {
            if (!dragging) return;
            dragging = false;
            divider.classList.remove('dragging');
            $('#ideFrame').style.pointerEvents = 'auto';
            document.body.style.userSelect = '';
        });
    });
})();
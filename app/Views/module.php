<?php
$title = isset($title) ? $title : 'Módulo';
$phpVersion = isset($phpVersion) ? $phpVersion : PHP_VERSION;
$files = isset($files) && is_array($files) ? $files : array();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?> · DevLab</title>
    <link rel="stylesheet" href="../public/assets/app.css">
    <link rel="stylesheet" href="../public/assets/ide.css">
</head>
<body class="module-body">

<div class="module-shell">
    <header class="module-topbar">
        <div class="breadcrumb">
            <a href="../index.php#workspace" class="crumb-link">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
                Workspace
            </a>
            <span class="crumb-sep">/</span>
            <h1 class="crumb-current"><?php echo htmlspecialchars($title); ?></h1>
            <span class="version-pill mono">PHP <?php echo htmlspecialchars($phpVersion); ?></span>
        </div>

        <div class="topbar-actions">
            <div class="search-wrap">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input type="text" id="filterInput" class="input search-input" placeholder="Filtrar arquivos...">
            </div>

            <button type="button" class="btn btn-primary" id="btnToggleNewFile">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Novo Arquivo
            </button>
        </div>
    </header>

    <form id="newFileForm" class="inline-bar hidden">
        <input type="text" id="newFilename" class="input" style="flex:1.4;" placeholder="06_traits.php (ou .html, .css, .js, .sql)" required>
        <select id="newFileTemplate" class="select" style="flex:1;">
            <option value="oop_basic">Classe PHP</option>
            <option value="oop_db">Classe + PDO MySQL</option>
            <option value="blank">Arquivo em branco</option>
        </select>
        <button type="submit" class="btn btn-primary">Criar</button>
        <button type="button" class="btn btn-ghost" id="btnCancelNewFile">Cancelar</button>
    </form>

    <section class="file-list" id="filesList">
        <?php if (count($files) > 0): ?>
            <?php foreach ($files as $file): ?>
                <?php $searchIndex = strtolower($file['filename'] . ' ' . $file['title'] . ' ' . $file['desc']); ?>
                <article class="file-row" data-file="<?php echo htmlspecialchars($file['filename']); ?>" data-search="<?php echo htmlspecialchars($searchIndex); ?>">
                    <div class="file-main">
                        <span class="ext-tag lang-<?php echo strtolower(htmlspecialchars($file['ext'])); ?> mono"><?php echo htmlspecialchars($file['ext']); ?></span>
                        <div class="file-text">
                            <div class="file-name-line">
                                <strong><?php echo htmlspecialchars($file['title']); ?></strong>
                                <span class="mono muted file-raw"><?php echo htmlspecialchars($file['filename']); ?></span>
                            </div>
                            <?php if ($file['desc'] !== ''): ?>
                                <p class="file-desc"><?php echo htmlspecialchars($file['desc']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="file-right">
                        <span class="mono muted file-stats"><?php echo $file['size']; ?> · <?php echo $file['modified']; ?></span>
                        <div class="btn-group">
                            <button type="button" class="btn btn-xs btn-primary" data-open-ide="<?php echo htmlspecialchars($file['filename']); ?>">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                    <polyline points="16 18 22 12 16 6"></polyline>
                                    <polyline points="8 6 2 12 8 18"></polyline>
                                </svg>
                                IDE
                            </button>
                            <a href="<?php echo rawurlencode($file['filename']); ?>" class="icon-btn" target="_blank" title="Abrir em nova aba">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                    <polyline points="15 3 21 3 21 9"></polyline>
                                    <line x1="10" y1="14" x2="21" y2="3"></line>
                                </svg>
                            </a>
                            <button type="button" class="icon-btn danger" data-delete-file="<?php echo htmlspecialchars($file['filename']); ?>" title="Excluir">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <polyline points="3 6 5 6 21 6"></polyline>
                                    <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">Nenhum arquivo neste diretório.</div>
        <?php endif; ?>
    </section>
</div>

<!-- SPLIT IDE OVERLAY -->
<div class="ide-overlay hidden" id="ideOverlay">
    <div class="ide-shell">
        <div class="ide-bar">
            <div class="ide-bar-left">
                <span class="ext-tag mono" id="ideLangBadge">PHP</span>
                <span class="mono ide-title" id="ideFilename">arquivo.php</span>
                <span class="dirty-dot hidden" id="ideDirtyDot" title="Alterações não salvas"></span>
            </div>

            <div class="btn-group">
                <button type="button" class="btn btn-xs btn-primary" id="btnIdeSave">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
                        <polygon points="5 3 19 12 5 21 5 3"></polygon>
                    </svg>
                    Salvar &amp; Rodar
                </button>

                <button type="button" class="btn btn-xs btn-ghost" id="btnIdeMedia" title="Anexar imagem, GIF, vídeo ou áudio">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path>
                    </svg>
                    Anexar
                </button>

                <button type="button" class="btn btn-xs btn-ghost" id="btnIdeReload" title="Reexecutar">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="23 4 23 10 17 10"></polyline>
                        <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                    </svg>
                </button>

                <button type="button" class="btn btn-xs btn-ghost" id="btnIdeToggleSplit">Ocultar Preview</button>

                <a href="#" id="ideExternalLink" class="icon-btn" target="_blank" title="Abrir em nova aba">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                        <polyline points="15 3 21 3 21 9"></polyline>
                        <line x1="10" y1="14" x2="21" y2="3"></line>
                    </svg>
                </a>

                <button type="button" class="icon-btn danger" id="btnIdeDelete" title="Excluir arquivo">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path>
                    </svg>
                </button>

                <button type="button" class="icon-btn" id="btnIdeClose" title="Fechar (Esc)">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
        </div>

        <div class="ide-split" id="ideSplit">
            <div class="ide-editor-pane" id="ideLeftPane">
                <div class="ide-gutter mono" id="ideGutter">1</div>
                <div class="ide-code-stage">
                    <pre class="ide-highlight mono" id="ideHighlight" aria-hidden="true"><code id="ideCodeLayer"></code></pre>
                    <textarea id="ideEditor" class="ide-textarea mono" spellcheck="false" autocomplete="off" autocorrect="off" autocapitalize="off"></textarea>
                </div>
            </div>

            <div class="ide-divider" id="ideDivider"></div>

            <div class="ide-preview-pane" id="ideRightPane">
                <div class="preview-head">
                    <span class="mono muted" style="font-size:11.5px;">OUTPUT</span>
                    <div class="btn-group">
                        <button type="button" class="btn btn-xs btn-ghost" id="btnToggleCanvas">Tema Canvas</button>
                        <button type="button" class="icon-btn" id="btnFrameReload" title="Recarregar output">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="23 4 23 10 17 10"></polyline>
                                <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                <iframe id="ideFrame" class="ide-frame" src="about:blank"></iframe>
            </div>
        </div>

        <div class="ide-footer mono">
            <span id="ideStatus">Pronto</span>
            <span class="muted" id="ideCursorInfo">Ln 1, Col 1 · Ctrl+S Salvar · Ctrl+V Colar Mídia</span>
        </div>
    </div>
</div>

<!-- MEDIA MANAGER MODAL -->
<div class="modal-overlay hidden" id="mediaModal">
    <div class="modal-box" style="max-width:700px;">
        <div class="modal-head">
            <h3>Mídias &amp; Anexos (<span class="mono">_assets/</span>)</h3>
            <button type="button" class="icon-btn" id="btnCloseMedia">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        <label class="drop-zone" id="mediaDropZone">
            <input type="file" id="mediaFileInput" accept="image/*,video/*,audio/*,.gif" class="hidden">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="17 8 12 3 7 8"></polyline>
                <line x1="12" y1="3" x2="12" y2="15"></line>
            </svg>
            <span>Selecionar ou arrastar imagem, GIF, vídeo ou áudio</span>
            <span class="mono muted" style="font-size:11px;">PNG, JPG, GIF, WEBP, SVG, MP4, WEBM, MP3, WAV, OGG</span>
        </label>

        <div id="mediaGallery" class="media-grid"></div>
    </div>
</div>

<!-- CONFIRM MODAL -->
<div class="modal-overlay hidden" id="confirmModal">
    <div class="modal-box" style="max-width:420px;">
        <div class="modal-head">
            <h3 id="confirmTitle">Confirmar</h3>
            <button type="button" class="icon-btn" id="btnCloseConfirm">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <p class="muted" id="confirmMessage" style="font-size:13.5px;"></p>
        <div class="modal-actions">
            <button type="button" class="btn btn-ghost" id="btnConfirmCancel">Cancelar</button>
            <button type="button" class="btn btn-danger-outline" id="btnConfirmOk">Confirmar</button>
        </div>
    </div>
</div>

<div id="toastStack" class="toast-stack"></div>

<script src="../public/assets/ide.js"></script>
</body>
</html>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DevLab Hub</title>
    <link rel="stylesheet" href="public/assets/app.css">
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-top">
        <div class="brand">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="16 18 22 12 16 6"></polyline>
                <polyline points="8 6 2 12 8 18"></polyline>
            </svg>
            <span>DevLab</span>
            <span class="version-pill">PHP</span>
        </div>

        <nav class="nav">
            <button type="button" class="nav-btn active" data-view="dashboard">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <rect x="3" y="3" width="7" height="7" rx="1"></rect>
                    <rect x="14" y="3" width="7" height="7" rx="1"></rect>
                    <rect x="14" y="14" width="7" height="7" rx="1"></rect>
                    <rect x="3" y="14" width="7" height="7" rx="1"></rect>
                </svg>
                <span>Dashboard</span>
            </button>

            <button type="button" class="nav-btn" data-view="workspace">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                </svg>
                <span>Workspace</span>
            </button>

            <button type="button" class="nav-btn" data-view="database">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
                    <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path>
                    <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path>
                </svg>
                <span>Database</span>
            </button>
        </nav>
    </div>

    <div class="sidebar-meta">
        <div class="meta-row">
            <span class="status-dot" id="sidebarDbDot"></span>
            <span class="mono"><?php echo htmlspecialchars($dbName); ?></span>
        </div>
        <div class="meta-sub mono"><?php echo htmlspecialchars($dbHost); ?></div>
    </div>
</aside>

<main class="viewport">

    <!-- DASHBOARD -->
    <section id="view-dashboard" class="view active">
        <header class="topbar">
            <h1>Dashboard</h1>
            <button type="button" class="btn btn-ghost" id="btnRefreshDash">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="23 4 23 10 17 10"></polyline>
                    <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                </svg>
                Sync
            </button>
        </header>

        <div class="kpi-grid">
            <div class="kpi">
                <span class="kpi-label">Diretórios</span>
                <strong class="kpi-value"><?php echo count($modules); ?></strong>
            </div>
            <div class="kpi">
                <span class="kpi-label">Arquivos</span>
                <strong class="kpi-value"><?php echo (int) $totalFiles; ?></strong>
            </div>
            <div class="kpi">
                <span class="kpi-label">Tabelas</span>
                <strong class="kpi-value" id="kpiTables">—</strong>
            </div>
            <div class="kpi">
                <span class="kpi-label">Routines</span>
                <strong class="kpi-value" id="kpiRoutines">—</strong>
            </div>
        </div>

        <div class="split-2">
            <div class="card">
                <div class="card-head">
                    <h2>Diretórios</h2>
                    <button type="button" class="link-btn" data-jump="workspace">Ver todos</button>
                </div>
                <div class="list-stack">
                    <?php if (count($modules) > 0): ?>
                        <?php foreach ($modules as $mod): ?>
                            <a href="<?php echo htmlspecialchars($mod['url']); ?>" class="list-row">
                                <span class="row-title">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                                    </svg>
                                    <?php echo htmlspecialchars($mod['title']); ?>
                                </span>
                                <span class="mono muted"><?php echo (int) $mod['count']; ?></span>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-inline">Nenhum diretório encontrado.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-head">
                    <h2>Tabelas Ativas</h2>
                    <span class="mono muted" id="dashMysqlTag">MySQL</span>
                </div>
                <div class="list-stack" id="dashTablesList">
                    <div class="empty-inline">Carregando...</div>
                </div>
            </div>
        </div>
    </section>

    <!-- WORKSPACE -->
    <section id="view-workspace" class="view">
        <header class="topbar">
            <h1>Workspace</h1>
            <button type="button" class="btn btn-primary" id="btnToggleNewFolder">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Novo Diretório
            </button>
        </header>

        <form id="newFolderForm" class="inline-bar hidden">
            <input type="text" id="newFolderInput" class="input" placeholder="Nome do diretório (ex: SOLID, Design_Patterns)" required>
            <button type="submit" class="btn btn-primary">Criar</button>
            <button type="button" class="btn btn-ghost" id="btnCancelNewFolder">Cancelar</button>
        </form>

        <div class="folder-grid" id="workspaceGrid">
            <?php if (count($modules) > 0): ?>
                <?php foreach ($modules as $mod): ?>
                    <?php $isEmpty = ($mod['count'] === 0); ?>
                    <article class="folder-card" data-folder="<?php echo htmlspecialchars($mod['folder']); ?>">
                        <div class="folder-top">
                            <a href="<?php echo htmlspecialchars($mod['url']); ?>" class="folder-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                                </svg>
                            </a>
                            <button
                                type="button"
                                class="icon-btn danger <?php echo $isEmpty ? '' : 'disabled'; ?>"
                                data-delete-folder="<?php echo htmlspecialchars($mod['folder']); ?>"
                                data-file-count="<?php echo (int) $mod['count']; ?>"
                                title="<?php echo $isEmpty ? 'Excluir diretório' : 'Remova os arquivos internos antes de excluir'; ?>">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <polyline points="3 6 5 6 21 6"></polyline>
                                    <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path>
                                    <path d="M10 11v6"></path>
                                    <path d="M14 11v6"></path>
                                </svg>
                            </button>
                        </div>

                        <a href="<?php echo htmlspecialchars($mod['url']); ?>" class="folder-body">
                            <h3><?php echo htmlspecialchars($mod['title']); ?></h3>
                            <div class="folder-meta">
                                <span class="mono"><?php echo (int) $mod['count']; ?> arquivo(s)</span>
                                <span class="arrow">&rarr;</span>
                            </div>
                        </a>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-inline">Nenhum diretório criado.</div>
            <?php endif; ?>
        </div>
    </section>

    <!-- DATABASE STUDIO -->
    <section id="view-database" class="view">
        <header class="topbar">
            <div class="topbar-left">
                <h1>Database</h1>
                <div class="segmented">
                    <button type="button" class="seg-btn active" data-subtab="schema">Tabelas &amp; Dados</button>
                    <button type="button" class="seg-btn" data-subtab="console">SQL &amp; Routines</button>
                </div>
            </div>
            <button type="button" class="btn btn-primary" id="btnOpenNewTable">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Nova Tabela
            </button>
        </header>

        <!-- SUBVIEW: TABELAS, COLUNAS E REGISTROS -->
        <div id="subtab-schema" class="subtab active">
            <div class="studio-split">
                <aside class="card sidebar-tables">
                    <div class="card-head">
                        <h2>Schema</h2>
                        <button type="button" class="icon-btn" id="btnReloadTables" title="Atualizar">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="23 4 23 10 17 10"></polyline>
                                <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                            </svg>
                        </button>
                    </div>
                    <div id="schemaTablesList" class="list-stack"></div>
                </aside>

                <div class="card inspector-pane" id="tableInspector">
                    <div class="empty-state">Selecione uma tabela à esquerda.</div>
                </div>
            </div>
        </div>

        <!-- SUBVIEW: CONSOLE SQL & ROUTINES -->
        <div id="subtab-console" class="subtab">
            <div class="card" style="margin-bottom: 1.25rem;">
                <div class="card-head">
                    <h2>Query Editor</h2>
                    <div class="btn-group">
                        <button type="button" class="btn btn-xs btn-ghost" data-tpl="select">SELECT</button>
                        <button type="button" class="btn btn-xs btn-ghost" data-tpl="procedure">PROCEDURE</button>
                        <button type="button" class="btn btn-xs btn-ghost" data-tpl="function">FUNCTION</button>
                    </div>
                </div>

                <textarea id="sqlInput" class="code-box" spellcheck="false" placeholder="SELECT * FROM logs_estudo LIMIT 20;"></textarea>

                <div class="editor-bar">
                    <span class="mono muted">Ctrl + Enter</span>
                    <div class="btn-group">
                        <button type="button" class="btn btn-ghost" id="btnClearSql">Limpar</button>
                        <button type="button" class="btn btn-primary" id="btnRunSql">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor">
                                <polygon points="5 3 19 12 5 21 5 3"></polygon>
                            </svg>
                            Executar
                        </button>
                    </div>
                </div>

                <div id="sqlFeedback" class="status-line hidden"></div>
                <div id="sqlResultGrid" class="table-scroll"></div>
            </div>

            <div class="card">
                <div class="card-head">
                    <h2>Procedures &amp; Functions</h2>
                    <button type="button" class="icon-btn" id="btnReloadRoutines" title="Atualizar">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="23 4 23 10 17 10"></polyline>
                            <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                        </svg>
                    </button>
                </div>
                <div id="routinesGrid" class="table-scroll"></div>
            </div>
        </div>
    </section>

</main>

<!-- MODAL GENÉRICO -->
<div class="modal-overlay hidden" id="appModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3 id="modalTitle">Modal</h3>
            <button type="button" class="icon-btn" id="btnCloseModal">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <div id="modalBody"></div>
    </div>
</div>

<!-- TOASTS -->
<div id="toastStack" class="toast-stack"></div>

<script src="public/assets/app.js"></script>
</body>
</html>
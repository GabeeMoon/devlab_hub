<?php
// DevLab Hub Principal - Menu Lateral + CRUD Completo de Registros - PHP 5.6
require_once __DIR__ . '/config/db.php';

if (isset($_GET['ajax_action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['ajax_action'];
    $response = array('success' => false, 'message' => '');
    $reservedFolders = array('config', 'assets', 'vendor', '.git', '', '.', '..');

    if ($action === 'create_module') {
        $rawFolder = isset($_POST['folder_name']) ? trim($_POST['folder_name']) : '';
        $cleanFolder = preg_replace('/[^a-zA-Z0-9_\-]/', '_', str_replace(' ', '_', $rawFolder));
        $cleanFolder = preg_replace('/_+/', '_', trim($cleanFolder, '_'));

        if (in_array(strtolower($cleanFolder), $reservedFolders)) {
            $response['message'] = 'Nome de pasta inválido ou reservado pelo sistema.';
            echo json_encode($response);
            exit;
        }

        $targetDir = __DIR__ . DIRECTORY_SEPARATOR . $cleanFolder;
        if (file_exists($targetDir)) {
            $response['message'] = "A matéria '$cleanFolder' já existe!";
            echo json_encode($response);
            exit;
        }

        if (!is_writable(__DIR__)) {
            $response['message'] = 'Sem permissão de escrita na pasta /Estudos. Rode chmod -R 777 na pasta.';
            echo json_encode($response);
            exit;
        }

        if (mkdir($targetDir, 0775)) {
            $stubCode = "<?php\ndefine('MODULE_DIR', __DIR__);\nrequire_once __DIR__ . '/../config/module_template.php';\n";
            file_put_contents($targetDir . '/index.php', $stubCode);

            $response['success'] = true;
            $response['message'] = "Matéria '$cleanFolder' criada com sucesso!";
        } else {
            $response['message'] = 'Não foi possível criar o diretório no servidor.';
        }

        echo json_encode($response);
        exit;
    }

    if ($action === 'delete_module') {
        $folderName = isset($_POST['folder_name']) ? basename(trim($_POST['folder_name'])) : '';

        if (in_array(strtolower($folderName), $reservedFolders)) {
            $response['message'] = 'Esta pasta é protegida e não pode ser excluída.';
            echo json_encode($response);
            exit;
        }

        $targetDir = __DIR__ . DIRECTORY_SEPARATOR . $folderName;
        if (!is_dir($targetDir)) {
            $response['message'] = "A pasta '$folderName' não foi encontrada.";
            echo json_encode($response);
            exit;
        }

        $contents = scandir($targetDir);
        $userFiles = array();
        foreach ($contents as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === 'index.php') {
                continue;
            }
            $userFiles[] = $entry;
        }

        if (count($userFiles) > 0) {
            $response['message'] = "Bloqueado: a pasta '$folderName' ainda contém " . count($userFiles) . " arquivo(s). Exclua os arquivos dentro dela primeiro.";
            echo json_encode($response);
            exit;
        }

        $stubFile = $targetDir . DIRECTORY_SEPARATOR . 'index.php';
        if (is_file($stubFile) && !@unlink($stubFile)) {
            $response['message'] = 'Não foi possível remover o stub index.php interno.';
            echo json_encode($response);
            exit;
        }

        if (@rmdir($targetDir)) {
            $response['success'] = true;
            $response['message'] = "Matéria '$folderName' removida com sucesso.";
        } else {
            $response['message'] = 'Não foi possível remover a pasta no servidor.';
        }

        echo json_encode($response);
        exit;
    }
}

$baseDir = __DIR__;
$ignoredFolders = array('.', '..', 'config', 'assets', '.git', 'vendor');
$modulos = array();
$totalExperimentos = 0;

$items = scandir($baseDir);
foreach ($items as $item) {
    if (in_array($item, $ignoredFolders)) {
        continue;
    }
    $fullPath = $baseDir . DIRECTORY_SEPARATOR . $item;
    if (is_dir($fullPath)) {
        if (!file_exists($fullPath . '/index.php') && is_writable($fullPath)) {
            $stubCode = "<?php\ndefine('MODULE_DIR', __DIR__);\nrequire_once __DIR__ . '/../config/module_template.php';\n";
            file_put_contents($fullPath . '/index.php', $stubCode);
        }

        $dirEntries = scandir($fullPath);
        $userItemCount = 0;
        foreach ($dirEntries as $entry) {
            if ($entry !== '.' && $entry !== '..' && $entry !== 'index.php') {
                $userItemCount++;
            }
        }
        $totalExperimentos += $userItemCount;

        $modulos[] = array(
            'folder' => $item,
            'title'  => str_replace(array('_', '-'), ' ', $item),
            'count'  => $userItemCount,
            'url'    => rawurlencode($item) . '/'
        );
    }
}

$metaCatalog = array(
    'POO' => array(
        'tag'  => 'Em andamento',
        'desc' => 'Classes, Objetos, Encapsulamento, Herança, Polimorfismo, Interfaces e Repository Pattern.',
        'icon' => '{ }'
    ),
    'PDO' => array(
        'tag'  => 'Banco de Dados',
        'desc' => 'Prepared Statements, transações, tratamento de exceções e padrões de acesso a dados.',
        'icon' => 'DB'
    )
);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DevLab Hub | Workspace &amp; SQL Studio</title>
    <style>
        :root {
            --bg-main: #0b0f19;
            --bg-sidebar: #0e1320;
            --bg-surface: #131a2a;
            --bg-surface-hover: #1a2338;
            --bg-input: #080b12;
            --border-color: #232e47;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --accent: #38bdf8;
            --accent-glow: rgba(56, 189, 248, 0.16);
            --success: #10b981;
            --success-bg: rgba(16, 185, 129, 0.12);
            --danger: #ef4444;
            --danger-bg: rgba(239, 68, 68, 0.12);
            --warning: #f59e0b;
            --sidebar-width: 250px;
            --radius: 12px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--bg-main);
            color: var(--text-primary);
            line-height: 1.55;
            min-height: 100vh;
            display: flex;
        }

        /* Sidebar Lateral */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--bg-sidebar);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 1.5rem 1rem;
        }

        .sidebar-brand {
            padding: 0 0.5rem 1.25rem;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 1.25rem;
        }

        .sidebar-brand h1 {
            font-size: 1.3rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .badge-php {
            font-size: 0.7rem;
            background: var(--accent-glow);
            color: var(--accent);
            border: 1px solid rgba(56, 189, 248, 0.35);
            padding: 0.12rem 0.5rem;
            border-radius: 999px;
        }

        .sidebar-brand span.sub {
            display: block;
            font-size: 0.78rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        .nav-menu { display: flex; flex-direction: column; gap: 0.45rem; }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 0.9rem;
            border-radius: 9px;
            color: var(--text-secondary);
            background: transparent;
            border: 1px solid transparent;
            cursor: pointer;
            font-size: 0.92rem;
            font-weight: 600;
            text-align: left;
            transition: all 0.15s ease;
        }
        .nav-item:hover { background: var(--bg-surface); color: var(--text-primary); }
        .nav-item.active {
            background: var(--accent-glow);
            color: var(--accent);
            border-color: rgba(56, 189, 248, 0.3);
        }

        .nav-icon { font-family: monospace; font-size: 1rem; width: 22px; text-align: center; }

        .sidebar-footer {
            border-top: 1px solid var(--border-color);
            padding-top: 1rem;
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-family: monospace;
        }
        .sidebar-footer div { margin-bottom: 0.3rem; }

        /* Conteúdo Principal */
        .main-content {
            margin-left: var(--sidebar-width);
            flex: 1;
            padding: 2rem 2.25rem;
            max-width: calc(100vw - var(--sidebar-width));
        }

        .view-section { display: none; }
        .view-section.active { display: block; }

        .page-header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            padding-bottom: 1.25rem;
            margin-bottom: 1.75rem;
            border-bottom: 1px solid var(--border-color);
        }

        .page-header h2 { font-size: 1.6rem; font-weight: 700; }
        .page-header p { color: var(--text-secondary); font-size: 0.9rem; }

        /* Dashboard */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.15rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 1.35rem;
        }

        .stat-label {
            font-size: 0.82rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 0.45rem;
        }

        .stat-value { font-size: 1.85rem; font-weight: 700; color: var(--text-primary); }
        .stat-sub { font-size: 0.8rem; color: var(--accent); margin-top: 0.35rem; font-family: monospace; }

        .dash-panels { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
        @media (max-width: 900px) { .dash-panels { grid-template-columns: 1fr; } }

        .panel-box {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 1.4rem;
        }

        .panel-box h3 {
            font-size: 1.05rem;
            margin-bottom: 1rem;
            padding-bottom: 0.65rem;
            border-bottom: 1px solid var(--border-color);
        }

        .quick-list { display: flex; flex-direction: column; gap: 0.65rem; }

        .quick-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.65rem 0.85rem;
            background: var(--bg-main);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            text-decoration: none;
            color: var(--text-primary);
            font-size: 0.9rem;
        }
        .quick-row:hover { border-color: var(--accent); color: var(--accent); }

        /* Área de Trabalho */
        .new-module-bar {
            display: none;
            background: var(--bg-surface);
            border: 1px solid var(--accent);
            border-radius: var(--radius);
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            gap: 0.75rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .new-module-bar.active { display: flex; }

        .grid-modules {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
            gap: 1.25rem;
        }

        .card-module {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 1.4rem;
            transition: transform 0.16s ease, border-color 0.16s ease;
        }
        .card-module:hover {
            transform: translateY(-3px);
            border-color: var(--accent);
            background: var(--bg-surface-hover);
        }

        .card-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .module-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            background: var(--accent-glow);
            color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: monospace;
            font-weight: 700;
            text-decoration: none;
            border: 1px solid rgba(56, 189, 248, 0.25);
        }

        .card-link-area { text-decoration: none; color: inherit; display: block; flex: 1; }
        .card-module h3 { font-size: 1.15rem; margin-bottom: 0.4rem; }
        .card-module p { font-size: 0.88rem; color: var(--text-secondary); margin-bottom: 1.2rem; }

        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 0.8rem;
            border-top: 1px solid rgba(35, 46, 71, 0.7);
            font-size: 0.82rem;
            color: var(--text-secondary);
            text-decoration: none;
        }
        .card-footer span.cta { color: var(--accent); font-weight: 600; }

        /* SQL Studio */
        .subtabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 0.75rem;
            flex-wrap: wrap;
        }

        .subtab-btn {
            cursor: pointer;
            padding: 0.55rem 1.1rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background: var(--bg-surface);
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 0.86rem;
        }
        .subtab-btn.active {
            background: var(--accent);
            color: #06101d;
            border-color: var(--accent);
        }

        .db-subview { display: none; }
        .db-subview.active { display: block; }

        .db-layout {
            display: grid;
            grid-template-columns: 260px 1fr;
            gap: 1.25rem;
            align-items: start;
        }
        @media (max-width: 960px) { .db-layout { grid-template-columns: 1fr; } }

        .table-sidebar-list {
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
            margin-top: 0.85rem;
        }

        .table-item-btn {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            text-align: left;
            padding: 0.65rem 0.85rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background: var(--bg-main);
            color: var(--text-primary);
            font-family: monospace;
            font-size: 0.86rem;
            cursor: pointer;
        }
        .table-item-btn:hover, .table-item-btn.active {
            border-color: var(--accent);
            background: var(--accent-glow);
            color: var(--accent);
        }

        /* Botões & Inputs */
        .btn {
            cursor: pointer;
            border: 1px solid var(--border-color);
            background: var(--bg-main);
            color: var(--text-primary);
            padding: 0.52rem 0.95rem;
            border-radius: 8px;
            font-size: 0.84rem;
            font-weight: 600;
            transition: all 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        .btn:hover { border-color: var(--accent); color: var(--accent); }

        .btn-sm { padding: 0.28rem 0.58rem; font-size: 0.77rem; }

        .btn-primary { background: var(--accent); color: #06101d; border-color: var(--accent); }
        .btn-primary:hover { background: #7dd3fc; color: #06101d; }

        .btn-success { background: var(--success); color: #04130d; border-color: var(--success); }
        .btn-success:hover { background: #34d399; color: #04130d; }

        .btn-danger { background: var(--danger-bg); color: #fca5a5; border-color: rgba(239, 68, 68, 0.35); }
        .btn-danger:hover { background: var(--danger); color: #ffffff; border-color: var(--danger); }
        .btn-danger.locked { opacity: 0.4; cursor: not-allowed; }

        .input, .select, .textarea-field {
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 0.55rem 0.8rem;
            border-radius: 8px;
            font-size: 0.86rem;
            outline: none;
        }
        .input:focus, .select:focus, .textarea-field:focus { border-color: var(--accent); }

        .sql-editor {
            width: 100%;
            min-height: 210px;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            color: #e2e8f0;
            font-family: "Fira Code", Consolas, Monaco, monospace;
            font-size: 0.88rem;
            line-height: 1.55;
            padding: 1rem;
            border-radius: 10px;
            resize: vertical;
            outline: none;
            tab-size: 4;
        }
        .sql-editor:focus { border-color: var(--accent); }

        .console-feedback {
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-family: monospace;
            font-size: 0.84rem;
            color: var(--text-secondary);
            margin: 1rem 0;
            white-space: pre-wrap;
        }

        .table-wrapper { overflow-x: auto; margin-top: 0.75rem; }
        table { width: 100%; border-collapse: collapse; font-size: 0.84rem; }
        th, td { text-align: left; padding: 0.6rem 0.75rem; border-bottom: 1px solid var(--border-color); }
        th { color: var(--text-secondary); font-weight: 600; background: rgba(8, 11, 18, 0.6); }

        .inline-form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 0.6rem;
            background: var(--bg-main);
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            margin: 1rem 0;
        }

        /* Modal */
        .modal-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(4, 7, 15, 0.85);
            z-index: 999;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .modal-backdrop.active { display: flex; }

        .modal-card {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            width: 100%;
            max-width: 780px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 1.5rem;
        }

        .row-field-group {
            margin-bottom: 0.9rem;
            background: var(--bg-main);
            border: 1px solid var(--border-color);
            padding: 0.75rem 0.9rem;
            border-radius: 8px;
        }

        .row-field-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.4rem;
            font-size: 0.84rem;
        }

        @media (max-width: 768px) {
            body { flex-direction: column; }
            .sidebar { position: static; width: 100%; flex-direction: row; flex-wrap: wrap; align-items: center; }
            .main-content { margin-left: 0; max-width: 100vw; padding: 1.25rem; }
        }
    </style>
</head>
<body>

<!-- Menu Lateral -->
<aside class="sidebar">
    <div>
        <div class="sidebar-brand">
            <h1>DevLab Hub</span></h1>
            <span class="sub">Ambiente Local de Estudos</span>
        </div>

        <nav class="nav-menu">
            <button type="button" class="nav-item active" id="nav-dashboard" onclick="switchMainView('dashboard')">
                <span class="nav-icon">&#9638;</span> Home &amp; Dashboard
            </button>
            <button type="button" class="nav-item" id="nav-workspace" onclick="switchMainView('workspace')">
                <span class="nav-icon">&#128193;</span> Área de Trabalho
            </button>
            <button type="button" class="nav-item" id="nav-database" onclick="switchMainView('database')">
                <span class="nav-icon">&#9921;</span> Banco de Dados
            </button>
        </nav>
    </div>

    <div class="sidebar-footer">
        <div>Host: <strong>10.13.13.115:3306</strong></div>
        <div>Banco: <strong><?php echo DB_NAME; ?></strong></div>
        <div>Docker: <strong>mysql-local</strong></div>
    </div>
</aside>

<!-- Área Principal -->
<main class="main-content">

    <!-- 1. ABA HOME & DASHBOARD -->
    <section id="view-dashboard" class="view-section active">
        <div class="page-header">
            <div>
                <h2>Resumo do Ambiente</h2>
                <p>Visão geral das suas matérias, arquivos de laboratório e status do MySQL no Docker.</p>
            </div>
            <button type="button" class="btn btn-primary" onclick="loadDashboardStats()">&#8635; Atualizar Status</button>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Matérias Criadas</div>
                <div class="stat-value"><?php echo count($modulos); ?></div>
                <div class="stat-sub">/var/www/trunk/Estudos/</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Arquivos de Estudo</div>
                <div class="stat-value"><?php echo $totalExperimentos; ?></div>
                <div class="stat-sub">Prontos na Split IDE</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Tabelas no MySQL</div>
                <div class="stat-value" id="dashTablesCount">...</div>
                <div class="stat-sub" id="dashDbStatus">Verificando conexão...</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Functions &amp; Procedures</div>
                <div class="stat-value" id="dashRoutinesCount">...</div>
                <div class="stat-sub" id="dashMysqlVersion">MySQL 8.0</div>
            </div>
        </div>

        <div class="dash-panels">
            <div class="panel-box">
                <h3>Acesso Rápido às Pastas</h3>
                <div class="quick-list">
                    <?php if (count($modulos) > 0): ?>
                        <?php foreach ($modulos as $mod): ?>
                            <a href="<?php echo htmlspecialchars($mod['url']); ?>" class="quick-row">
                                <span><strong><?php echo htmlspecialchars($mod['title']); ?></strong></span>
                                <span style="color: var(--text-secondary); font-size: 0.82rem;"><?php echo $mod['count']; ?> arquivo(s) &rarr;</span>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: var(--text-secondary); font-size: 0.9rem;">Nenhuma pasta criada ainda.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="panel-box">
                <h3>Tabelas Ativas em <code><?php echo DB_NAME; ?></code></h3>
                <div class="quick-list" id="dashTablesList">
                    <span style="color: var(--text-secondary); font-size: 0.88rem;">Carregando tabelas...</span>
                </div>
            </div>
        </div>
    </section>

    <!-- 2. ABA ÁREA DE TRABALHO (PASTAS) -->
    <section id="view-workspace" class="view-section">
        <div class="page-header">
            <div>
                <h2>Área de Trabalho</h2>
                <p>Gerencie suas pastas de matérias e abra a Split IDE para codificar e testar.</p>
            </div>
            <button type="button" class="btn btn-primary" onclick="toggleNovaMateria()">+ Nova Matéria / Pasta</button>
        </div>

        <form class="new-module-bar" id="newModuleBar" onsubmit="criarNovaMateria(event)">
            <input type="text" id="inputFolderName" class="input" style="flex: 1; min-width: 240px;" placeholder="Nome da matéria (ex: Design Patterns, API REST, SOLID)" required>
            <button type="submit" class="btn btn-success">Criar Pasta com Split IDE</button>
            <button type="button" class="btn" onclick="toggleNovaMateria()">Cancelar</button>
        </form>

        <div class="grid-modules">
            <?php if (count($modulos) > 0): ?>
                <?php foreach ($modulos as $mod): ?>
                    <?php
                        $folderKey = $mod['folder'];
                        $desc = isset($metaCatalog[$folderKey]) ? $metaCatalog[$folderKey]['desc'] : 'Diretório de exercícios, testes práticos e editor de código integrado.';
                        $icon = isset($metaCatalog[$folderKey]) ? $metaCatalog[$folderKey]['icon'] : strtoupper(substr($folderKey, 0, 3));
                        $isEmpty = ($mod['count'] === 0);
                    ?>
                    <article class="card-module" id="module-card-<?php echo htmlspecialchars($folderKey); ?>">
                        <div>
                            <div class="card-top">
                                <a href="<?php echo htmlspecialchars($mod['url']); ?>" class="module-icon"><?php echo htmlspecialchars($icon); ?></a>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-danger <?php echo $isEmpty ? '' : 'locked'; ?>"
                                    title="<?php echo $isEmpty ? 'Excluir pasta vazia' : 'Esvazie os ' . $mod['count'] . ' arquivo(s) antes de excluir'; ?>"
                                    onclick="excluirMateria('<?php echo htmlspecialchars($folderKey, ENT_QUOTES); ?>', <?php echo (int) $mod['count']; ?>)">
                                    Excluir
                                </button>
                            </div>
                            <a href="<?php echo htmlspecialchars($mod['url']); ?>" class="card-link-area">
                                <h3><?php echo htmlspecialchars($mod['title']); ?></h3>
                                <p><?php echo htmlspecialchars($desc); ?></p>
                            </a>
                        </div>
                        <a href="<?php echo htmlspecialchars($mod['url']); ?>" class="card-footer">
                            <span><?php echo $mod['count']; ?> experimento(s)</span>
                            <span class="cta">Abrir pasta &rarr;</span>
                        </a>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="card-module">
                    <h3>Nenhuma pasta encontrada</h3>
                    <p>Clique em <strong>+ Nova Matéria / Pasta</strong> para criar seu primeiro módulo.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- 3. ABA BANCO DE DADOS (SQL STUDIO) -->
    <section id="view-database" class="view-section">
        <div class="page-header">
            <div>
                <h2>SQL Studio &amp; Gerenciador MySQL</h2>
                <p>Gerencie tabelas, colunas e registros (CRUD), execute queries SQL e crie Functions e Procedures.</p>
            </div>
            <button type="button" class="btn btn-success" onclick="abrirModalNovaTabela()">+ Nova Tabela</button>
        </div>

        <div class="subtabs">
            <button type="button" class="subtab-btn active" id="subtab-btn-tables" onclick="switchDbSubtab('tables')">
                Tabelas, Colunas &amp; Registros
            </button>
            <button type="button" class="subtab-btn" id="subtab-btn-sql" onclick="switchDbSubtab('sql')">
                Console SQL, Functions &amp; Procedures
            </button>
        </div>

        <!-- SUB-ABA A: GERENCIADOR DE TABELAS, COLUNAS E REGISTROS -->
        <div id="db-subview-tables" class="db-subview active">
            <div class="db-layout">
                <div class="panel-box">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <h3 style="border:none;margin:0;padding:0;">Tabelas</h3>
                        <button type="button" class="btn btn-sm" onclick="carregarListaTabelas()">&#8635;</button>
                    </div>
                    <div class="table-sidebar-list" id="dbTablesList">
                        <span style="color:var(--text-secondary);font-size:0.85rem;">Carregando...</span>
                    </div>
                </div>

                <div class="panel-box" id="tableInspectorPanel">
                    <div style="text-align:center;padding:3rem 1rem;color:var(--text-secondary);">
                        Selecione uma tabela ao lado para gerenciar suas colunas e registros ou clique em <strong>+ Nova Tabela</strong>.
                    </div>
                </div>
            </div>
        </div>

        <!-- SUB-ABA B: CONSOLE SQL, FUNCTIONS & PROCEDURES -->
        <div id="db-subview-sql" class="db-subview">
            <div class="panel-box" style="margin-bottom:1.5rem;">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem;margin-bottom:0.85rem;">
                    <h3 style="border:none;margin:0;padding:0;">Editor SQL Livre (Queries, DDL, Functions &amp; Procedures)</h3>
                    <div style="display:flex;gap:0.45rem;flex-wrap:wrap;">
                        <button type="button" class="btn btn-sm" onclick="inserirTemplateSql('select')">Template SELECT</button>
                        <button type="button" class="btn btn-sm" onclick="inserirTemplateSql('procedure')">Template PROCEDURE</button>
                        <button type="button" class="btn btn-sm" onclick="inserirTemplateSql('function')">Template FUNCTION</button>
                    </div>
                </div>

                <textarea id="sqlConsoleInput" class="sql-editor" placeholder="Digite seu comando SQL aqui (ex: SELECT * FROM logs_estudo; ou CREATE PROCEDURE ...)&#10;Atalho: Ctrl + Enter para executar"></textarea>

                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:0.85rem;flex-wrap:wrap;gap:0.5rem;">
                    <span style="font-size:0.8rem;color:var(--text-secondary);font-family:monospace;">
                        Dica: Para Procedures/Functions, não é obrigatório usar DELIMITER.
                    </span>
                    <div style="display:flex;gap:0.5rem;">
                        <button type="button" class="btn" onclick="document.getElementById('sqlConsoleInput').value=''">Limpar</button>
                        <button type="button" class="btn btn-primary" onclick="executarSqlLivre()">Executar SQL (Ctrl+Enter)</button>
                    </div>
                </div>

                <div id="sqlConsoleStatus" class="console-feedback" style="display:none;"></div>
                <div class="table-wrapper" id="sqlConsoleResultTable"></div>
            </div>

            <div class="panel-box">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.75rem;">
                    <h3 style="border:none;margin:0;padding:0;">Stored Procedures &amp; Functions Salvas</h3>
                    <button type="button" class="btn btn-sm" onclick="carregarRoutines()">&#8635; Atualizar Lista</button>
                </div>
                <div class="table-wrapper" id="routinesTableWrapper">
                    <span style="color:var(--text-secondary);font-size:0.85rem;">Carregando routines...</span>
                </div>
            </div>
        </div>
    </section>

</main>

<!-- Modal 1: Criar Nova Tabela -->
<div class="modal-backdrop" id="modalNewTable">
    <div class="modal-card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
            <h3>Criar Nova Tabela no MySQL</h3>
            <button type="button" class="btn btn-sm" onclick="fecharModalNovaTabela()">Fechar</button>
        </div>

        <div style="margin-bottom:1rem;">
            <label style="display:block;font-size:0.85rem;color:var(--text-secondary);margin-bottom:0.35rem;">Nome da Tabela</label>
            <input type="text" id="newTableName" class="input" style="width:100%;" placeholder="ex: clientes_estudo">
        </div>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;">
            <strong style="font-size:0.9rem;">Colunas</strong>
            <button type="button" class="btn btn-sm btn-primary" onclick="adicionarLinhaColunaModal()">+ Adicionar Coluna</button>
        </div>

        <div id="newTableColumnsContainer" style="display:flex;flex-direction:column;gap:0.6rem;margin-bottom:1.25rem;"></div>

        <div style="display:flex;justify-content:flex-end;gap:0.6rem;">
            <button type="button" class="btn" onclick="fecharModalNovaTabela()">Cancelar</button>
            <button type="button" class="btn btn-success" onclick="salvarNovaTabela()">Criar Tabela</button>
        </div>
    </div>
</div>

<!-- Modal 2: Inserir / Editar Registro (Linha da Tabela) -->
<div class="modal-backdrop" id="modalRowEditor">
    <div class="modal-card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.1rem;padding-bottom:0.75rem;border-bottom:1px solid var(--border-color);">
            <h3 id="rowModalTitle">Inserir Registro</h3>
            <button type="button" class="btn btn-sm" onclick="fecharModalRegistro()">Fechar</button>
        </div>

        <form id="rowEditorForm" onsubmit="salvarRegistroTabela(event)">
            <div id="rowFieldsContainer"></div>

            <div style="display:flex;justify-content:flex-end;gap:0.6rem;margin-top:1.25rem;">
                <button type="button" class="btn" onclick="fecharModalRegistro()">Cancelar</button>
                <button type="submit" class="btn btn-success" id="btnSubmitRow">Salvar Registro</button>
            </div>
        </form>
    </div>
</div>

<script>
    var selectedTable = '';
    var currentTableColumns = [];
    var currentTableRows = [];
    var editingRowIndex = null;

    // Navegação do Menu Lateral
    function switchMainView(viewName) {
        var views = document.querySelectorAll('.view-section');
        var navs = document.querySelectorAll('.nav-item');

        for (var i = 0; i < views.length; i++) views[i].classList.remove('active');
        for (var j = 0; j < navs.length; j++) navs[j].classList.remove('active');

        document.getElementById('view-' + viewName).classList.add('active');
        document.getElementById('nav-' + viewName).classList.add('active');
        window.location.hash = viewName;

        if (viewName === 'dashboard') {
            loadDashboardStats();
        } else if (viewName === 'database') {
            carregarListaTabelas();
            carregarRoutines();
        }
    }

    function switchDbSubtab(subtab) {
        document.getElementById('subtab-btn-tables').classList.remove('active');
        document.getElementById('subtab-btn-sql').classList.remove('active');
        document.getElementById('db-subview-tables').classList.remove('active');
        document.getElementById('db-subview-sql').classList.remove('active');

        document.getElementById('subtab-btn-' + subtab).classList.add('active');
        document.getElementById('db-subview-' + subtab).classList.add('active');
    }

    // Helper AJAX
    function dbApiRequest(action, method, formData, callback) {
        var xhr = new XMLHttpRequest();
        var url = 'config/db_api.php?db_action=' + encodeURIComponent(action);
        if (method === 'GET' && typeof formData === 'string') {
            url += '&' + formData;
        }
        xhr.open(method, url, true);
        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4) {
                try {
                    callback(JSON.parse(xhr.responseText));
                } catch (e) {
                    callback({ success: false, message: 'Resposta inválida: ' + xhr.responseText });
                }
            }
        };
        xhr.send(method === 'POST' ? formData : null);
    }

    // 1. DASHBOARD
    function loadDashboardStats() {
        dbApiRequest('dashboard_stats', 'GET', null, function (res) {
            if (res.success) {
                document.getElementById('dashTablesCount').textContent = res.data.tables_count;
                document.getElementById('dashRoutinesCount').textContent = res.data.routines_count;
                document.getElementById('dashDbStatus').style.color = '#10b981';
                document.getElementById('dashDbStatus').textContent = '● MySQL Online';
                document.getElementById('dashMysqlVersion').textContent = 'Versão ' + res.data.version;

                var listHtml = '';
                if (res.data.tables_list && res.data.tables_list.length > 0) {
                    for (var i = 0; i < res.data.tables_list.length; i++) {
                        var t = res.data.tables_list[i];
                        listHtml += '<a href="#database" class="quick-row" onclick="abrirTabelaDireto(\'' + escapeHtml(t) + '\')">' +
                            '<span><code>' + escapeHtml(t) + '</code></span>' +
                            '<span style="color:var(--accent);font-size:0.82rem;">Gerenciar &rarr;</span>' +
                        '</a>';
                    }
                } else {
                    listHtml = '<span style="color:var(--text-secondary);font-size:0.88rem;">Nenhuma tabela criada ainda no banco.</span>';
                }
                document.getElementById('dashTablesList').innerHTML = listHtml;
            } else {
                document.getElementById('dashDbStatus').style.color = '#ef4444';
                document.getElementById('dashDbStatus').textContent = '● Offline (' + res.message + ')';
            }
        });
    }

    function abrirTabelaDireto(tableName) {
        switchMainView('database');
        switchDbSubtab('tables');
        setTimeout(function () { inspecionarTabela(tableName); }, 150);
    }

    // 2. ÁREA DE TRABALHO
    function toggleNovaMateria() {
        var bar = document.getElementById('newModuleBar');
        bar.classList.toggle('active');
        if (bar.classList.contains('active')) {
            document.getElementById('inputFolderName').focus();
        }
    }

    function criarNovaMateria(event) {
        event.preventDefault();
        var folderName = document.getElementById('inputFolderName').value;
        var formData = new FormData();
        formData.append('folder_name', folderName);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'index.php?ajax_action=create_module', true);
        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4) {
                var res = JSON.parse(xhr.responseText);
                if (res.success) {
                    window.location.hash = 'workspace';
                    window.location.reload();
                } else {
                    alert(res.message);
                }
            }
        };
        xhr.send(formData);
    }

    function excluirMateria(folderName, fileCount) {
        if (fileCount > 0) {
            alert("Proteção ativada: a pasta '" + folderName + "' ainda possui " + fileCount + " arquivo(s).\nExclua os arquivos dentro dela antes de apagar a matéria.");
            return;
        }
        if (!confirm("Deseja excluir a pasta vazia '" + folderName + "'?")) return;

        var formData = new FormData();
        formData.append('folder_name', folderName);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'index.php?ajax_action=delete_module', true);
        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4) {
                var res = JSON.parse(xhr.responseText);
                if (res.success) {
                    var card = document.getElementById('module-card-' + folderName);
                    if (card) card.parentNode.removeChild(card);
                } else {
                    alert(res.message);
                }
            }
        };
        xhr.send(formData);
    }

    // 3. SQL STUDIO - TABELAS, COLUNAS E REGISTROS
    function carregarListaTabelas() {
        dbApiRequest('list_tables', 'GET', null, function (res) {
            var container = document.getElementById('dbTablesList');
            if (!res.success) {
                container.innerHTML = '<span style="color:#ef4444;font-size:0.85rem;">' + escapeHtml(res.message) + '</span>';
                return;
            }
            if (!res.data || res.data.length === 0) {
                container.innerHTML = '<span style="color:var(--text-secondary);font-size:0.85rem;">Nenhuma tabela encontrada.</span>';
                return;
            }

            var html = '';
            for (var i = 0; i < res.data.length; i++) {
                var t = res.data[i];
                var activeClass = (selectedTable === t.name) ? 'active' : '';
                html += '<button type="button" class="table-item-btn ' + activeClass + '" onclick="inspecionarTabela(\'' + escapeHtml(t.name) + '\')">' +
                    '<span>' + escapeHtml(t.name) + '</span>' +
                    '<span style="font-size:0.75rem;color:var(--text-secondary);">~' + (t.rows_est || 0) + '</span>' +
                '</button>';
            }
            container.innerHTML = html;
        });
    }

    function inspecionarTabela(tableName) {
        selectedTable = tableName;
        carregarListaTabelas();

        var panel = document.getElementById('tableInspectorPanel');
        panel.innerHTML = '<p style="color:var(--text-secondary);">Carregando estrutura e registros de <strong>' + escapeHtml(tableName) + '</strong>...</p>';

        dbApiRequest('describe_table', 'GET', 'table=' + encodeURIComponent(tableName), function (res) {
            if (!res.success) {
                panel.innerHTML = '<p style="color:#ef4444;">' + escapeHtml(res.message) + '</p>';
                return;
            }

            currentTableColumns = res.columns || [];
            currentTableRows = res.rows || [];

            var html = '<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.75rem;margin-bottom:1rem;padding-bottom:0.85rem;border-bottom:1px solid var(--border-color);">' +
                '<div>' +
                    '<h3 style="border:none;margin:0;padding:0;font-size:1.2rem;">Tabela: <code>' + escapeHtml(tableName) + '</code></h3>' +
                    '<span style="font-size:0.8rem;color:var(--text-secondary);">' + currentTableColumns.length + ' coluna(s) &bull; ' + currentTableRows.length + ' registro(s) carregado(s)</span>' +
                '</div>' +
                '<div style="display:flex;gap:0.5rem;flex-wrap:wrap;">' +
                    '<button type="button" class="btn btn-sm btn-success" onclick="abrirModalNovoRegistro()">+ Novo Registro</button>' +
                    '<button type="button" class="btn btn-sm btn-primary" onclick="toggleFormNovaColuna()">+ Nova Coluna</button>' +
                    '<button type="button" class="btn btn-sm" onclick="renomearTabelaPrompt(\'' + escapeHtml(tableName) + '\')">Renomear</button>' +
                    '<button type="button" class="btn btn-sm btn-danger" onclick="excluirTabela(\'' + escapeHtml(tableName) + '\')">Excluir Tabela</button>' +
                '</div>' +
            '</div>';

            // Formulário Oculto para Adicionar Nova Coluna
            html += '<form id="formAddCol" class="inline-form-grid" style="display:none;" onsubmit="salvarNovaColuna(event)">' +
                '<input type="text" id="addColName" class="input" placeholder="Nome da coluna" required>' +
                '<select id="addColType" class="select">' +
                    '<option value="VARCHAR">VARCHAR</option>' +
                    '<option value="INT">INT</option>' +
                    '<option value="BIGINT">BIGINT</option>' +
                    '<option value="TEXT">TEXT</option>' +
                    '<option value="DECIMAL">DECIMAL</option>' +
                    '<option value="DATE">DATE</option>' +
                    '<option value="DATETIME">DATETIME</option>' +
                    '<option value="TIMESTAMP">TIMESTAMP</option>' +
                    '<option value="TINYINT">TINYINT</option>' +
                '</select>' +
                '<input type="text" id="addColLen" class="input" placeholder="Tamanho (ex: 100)" value="100">' +
                '<select id="addColNull" class="select"><option value="1">NULL</option><option value="0">NOT NULL</option></select>' +
                '<input type="text" id="addColDefault" class="input" placeholder="Default (opcional)">' +
                '<button type="submit" class="btn btn-success">Adicionar</button>' +
            '</form>';

            // Seção 1: Registros da Tabela (com Editar e Excluir por linha)
            html += '<div style="display:flex;justify-content:space-between;align-items:center;margin-top:0.25rem;">' +
                '<h4 style="font-size:0.95rem;">Registros da Tabela (Últimos 50)</h4>' +
                '<button type="button" class="btn btn-sm btn-success" onclick="abrirModalNovoRegistro()">+ Inserir Linha</button>' +
            '</div>';

            if (currentTableRows.length === 0) {
                html += '<p style="color:var(--text-secondary);font-size:0.85rem;margin:0.75rem 0 1.5rem;">Tabela vazia (0 registros). Clique em <strong>+ Inserir Linha</strong> para adicionar dados.</p>';
            } else {
                html += '<div class="table-wrapper" style="margin-bottom:1.75rem;"><table><thead><tr>';
                for (var cIdx = 0; cIdx < currentTableColumns.length; cIdx++) {
                    html += '<th>' + escapeHtml(currentTableColumns[cIdx].Field) + '</th>';
                }
                html += '<th style="width:130px;">Ações</th></tr></thead><tbody>';

                for (var r = 0; r < currentTableRows.length; r++) {
                    html += '<tr>';
                    for (var cIdx2 = 0; cIdx2 < currentTableColumns.length; cIdx2++) {
                        var colName = currentTableColumns[cIdx2].Field;
                        var val = currentTableRows[r][colName];
                        html += '<td>' + (val !== null ? escapeHtml(val) : '<span style="color:#64748b;font-style:italic;">NULL</span>') + '</td>';
                    }
                    html += '<td style="white-space:nowrap;">' +
                        '<button type="button" class="btn btn-sm" onclick="abrirModalEditarRegistro(' + r + ')">Editar</button> ' +
                        '<button type="button" class="btn btn-sm btn-danger" onclick="excluirRegistro(' + r + ')">Excluir</button>' +
                    '</td></tr>';
                }
                html += '</tbody></table></div>';
            }

            // Seção 2: Estrutura de Colunas
            html += '<h4 style="font-size:0.95rem;margin-top:1.25rem;">Estrutura de Colunas</h4>' +
                '<div class="table-wrapper"><table><thead><tr>' +
                '<th>Coluna</th><th>Tipo</th><th>Nulo</th><th>Chave</th><th>Default</th><th>Extra</th><th>Ações</th>' +
                '</tr></thead><tbody>';

            for (var i = 0; i < currentTableColumns.length; i++) {
                var c = currentTableColumns[i];
                html += '<tr>' +
                    '<td><strong>' + escapeHtml(c.Field) + '</strong></td>' +
                    '<td><code>' + escapeHtml(c.Type) + '</code></td>' +
                    '<td>' + escapeHtml(c.Null) + '</td>' +
                    '<td>' + escapeHtml(c.Key) + '</td>' +
                    '<td>' + (c.Default !== null ? escapeHtml(c.Default) : '<span style="color:#64748b;">NULL</span>') + '</td>' +
                    '<td>' + escapeHtml(c.Extra) + '</td>' +
                    '<td style="white-space:nowrap;">' +
                        '<button type="button" class="btn btn-sm" onclick="editarColunaPrompt(\'' + escapeHtml(c.Field) + '\', \'' + escapeHtml(c.Type) + '\', \'' + escapeHtml(c.Null) + '\', \'' + escapeHtml(c.Extra) + '\')">Editar</button> ' +
                        '<button type="button" class="btn btn-sm btn-danger" onclick="excluirColuna(\'' + escapeHtml(c.Field) + '\')">Excluir</button>' +
                    '</td>' +
                '</tr>';
            }
            html += '</tbody></table></div>';

            panel.innerHTML = html;
        });
    }

    // CRUD DE REGISTROS (Modal Inserir / Editar e Excluir Linha)
    function construirChavesLinha(rowObj) {
        var keys = {};
        var hasPrimary = false;

        for (var i = 0; i < currentTableColumns.length; i++) {
            var col = currentTableColumns[i];
            if (col.Key === 'PRI') {
                keys[col.Field] = rowObj[col.Field];
                hasPrimary = true;
            }
        }

        // Fallback caso a tabela não tenha PRIMARY KEY definida
        if (!hasPrimary) {
            for (var j = 0; j < currentTableColumns.length; j++) {
                var f = currentTableColumns[j].Field;
                keys[f] = rowObj[f];
            }
        }
        return keys;
    }

    function renderizarFormularioRegistro(rowData) {
        var container = document.getElementById('rowFieldsContainer');
        container.innerHTML = '';

        for (var i = 0; i < currentTableColumns.length; i++) {
            var col = currentTableColumns[i];
            var fieldName = col.Field;
            var isNullable = (col.Null === 'YES');
            var isAutoInc = (col.Extra && col.Extra.toLowerCase().indexOf('auto_increment') !== -1);
            var isTextType = (col.Type && col.Type.toLowerCase().indexOf('text') !== -1);

            var currentVal = rowData ? rowData[fieldName] : '';
            var isNullChecked = rowData ? (currentVal === null) : (isNullable && col.Default === null && !isAutoInc);

            var group = document.createElement('div');
            group.className = 'row-field-group';
            group.dataset.field = fieldName;

            var hint = col.Type;
            if (col.Key === 'PRI') hint += ' • PK';
            if (isAutoInc) hint += ' • AUTO_INCREMENT';

            var nullToggleHtml = '';
            if (isNullable) {
                nullToggleHtml = '<label style="font-size:0.78rem;color:var(--text-secondary);display:flex;align-items:center;gap:0.35rem;cursor:pointer;">' +
                    '<input type="checkbox" class="row-null-chk"' + (isNullChecked ? ' checked' : '') + ' onchange="alternarCampoNull(this)"> NULL' +
                '</label>';
            }

            var placeholder = '';
            if (!rowData && isAutoInc) {
                placeholder = 'Deixe vazio para gerar automaticamente';
            } else if (!rowData && col.Default !== null) {
                placeholder = 'Default: ' + col.Default;
            }

            var inputHtml = '';
            if (isTextType) {
                inputHtml = '<textarea class="textarea-field row-val-input" style="width:100%;min-height:75px;" placeholder="' + escapeHtml(placeholder) + '"' + (isNullChecked ? ' disabled' : '') + '>' + (currentVal !== null ? escapeHtml(currentVal) : '') + '</textarea>';
            } else {
                inputHtml = '<input type="text" class="input row-val-input" style="width:100%;" value="' + (currentVal !== null ? escapeHtml(currentVal) : '') + '" placeholder="' + escapeHtml(placeholder) + '"' + (isNullChecked ? ' disabled' : '') + '>';
            }

            group.innerHTML =
                '<div class="row-field-header">' +
                    '<div><strong>' + escapeHtml(fieldName) + '</strong> <span style="color:var(--text-secondary);font-family:monospace;font-size:0.76rem;">(' + escapeHtml(hint) + ')</span></div>' +
                    nullToggleHtml +
                '</div>' +
                inputHtml;

            container.appendChild(group);
        }
    }

    function alternarCampoNull(checkbox) {
        var group = checkbox.closest('.row-field-group');
        var input = group.querySelector('.row-val-input');
        input.disabled = checkbox.checked;
        if (checkbox.checked) {
            input.value = '';
        }
    }

    function abrirModalNovoRegistro() {
        editingRowIndex = null;
        document.getElementById('rowModalTitle').textContent = 'Inserir Novo Registro em ' + selectedTable;
        document.getElementById('btnSubmitRow').textContent = 'Inserir Registro';
        renderizarFormularioRegistro(null);
        document.getElementById('modalRowEditor').classList.add('active');
    }

    function abrirModalEditarRegistro(rowIndex) {
        editingRowIndex = rowIndex;
        var rowData = currentTableRows[rowIndex];
        document.getElementById('rowModalTitle').textContent = 'Editar Registro em ' + selectedTable;
        document.getElementById('btnSubmitRow').textContent = 'Salvar Alterações';
        renderizarFormularioRegistro(rowData);
        document.getElementById('modalRowEditor').classList.add('active');
    }

    function fecharModalRegistro() {
        document.getElementById('modalRowEditor').classList.remove('active');
        editingRowIndex = null;
    }

    function salvarRegistroTabela(event) {
        event.preventDefault();
        var groups = document.querySelectorAll('#rowFieldsContainer .row-field-group');
        var valuesObj = {};

        for (var i = 0; i < groups.length; i++) {
            var field = groups[i].dataset.field;
            var nullChk = groups[i].querySelector('.row-null-chk');
            var input = groups[i].querySelector('.row-val-input');

            if (nullChk && nullChk.checked) {
                valuesObj[field] = null;
            } else {
                valuesObj[field] = input.value;
            }
        }

        var fd = new FormData();
        fd.append('table_name', selectedTable);
        fd.append('values', JSON.stringify(valuesObj));

        var action = 'insert_row';
        if (editingRowIndex !== null) {
            action = 'update_row';
            var originalRow = currentTableRows[editingRowIndex];
            fd.append('keys', JSON.stringify(construirChavesLinha(originalRow)));
        }

        dbApiRequest(action, 'POST', fd, function (res) {
            if (res.success) {
                fecharModalRegistro();
                inspecionarTabela(selectedTable);
            } else {
                alert('Erro: ' + res.message);
            }
        });
    }

    function excluirRegistro(rowIndex) {
        var rowObj = currentTableRows[rowIndex];
        var keys = construirChavesLinha(rowObj);
        var resumoChave = JSON.stringify(keys);

        if (!confirm('Deseja excluir permanentemente este registro?\nIdentificador: ' + resumoChave)) {
            return;
        }

        var fd = new FormData();
        fd.append('table_name', selectedTable);
        fd.append('keys', resumoChave);

        dbApiRequest('delete_row', 'POST', fd, function (res) {
            if (res.success) {
                inspecionarTabela(selectedTable);
            } else {
                alert('Erro ao excluir registro: ' + res.message);
            }
        });
    }

    // GERENCIAMENTO DE COLUNAS E TABELAS
    function toggleFormNovaColuna() {
        var f = document.getElementById('formAddCol');
        f.style.display = (f.style.display === 'none') ? 'grid' : 'none';
    }

    function salvarNovaColuna(e) {
        e.preventDefault();
        var fd = new FormData();
        fd.append('table_name', selectedTable);
        fd.append('col_name', document.getElementById('addColName').value);
        fd.append('type', document.getElementById('addColType').value);
        fd.append('length', document.getElementById('addColLen').value);
        fd.append('nullable', document.getElementById('addColNull').value);
        fd.append('default_val', document.getElementById('addColDefault').value);

        dbApiRequest('add_column', 'POST', fd, function (res) {
            if (res.success) {
                inspecionarTabela(selectedTable);
            } else {
                alert('Erro: ' + res.message);
            }
        });
    }

    function editarColunaPrompt(oldName, fullType, isNull, extra) {
        var newName = prompt('Novo nome da coluna:', oldName);
        if (!newName) return;

        var typeMatch = fullType.match(/^([a-zA-Z]+)(?:\(([^)]+)\))?/);
        var defaultType = typeMatch ? typeMatch[1].toUpperCase() : 'VARCHAR';
        var defaultLen = (typeMatch && typeMatch[2]) ? typeMatch[2] : '';

        var newType = prompt('Tipo de dado (ex: VARCHAR, INT, TEXT, DECIMAL, DATETIME):', defaultType);
        if (!newType) return;

        var newLen = prompt('Tamanho (deixe vazio se não aplicável, ex: 150 ou 10,2):', defaultLen);
        var nullable = confirm('Permitir NULL nesta coluna?\nOK = Sim (NULL) | Cancelar = Não (NOT NULL)') ? '1' : '0';

        var fd = new FormData();
        fd.append('table_name', selectedTable);
        fd.append('old_col_name', oldName);
        fd.append('new_col_name', newName);
        fd.append('type', newType);
        fd.append('length', newLen || '');
        fd.append('nullable', nullable);
        fd.append('extra', extra || '');

        dbApiRequest('edit_column', 'POST', fd, function (res) {
            if (res.success) {
                inspecionarTabela(selectedTable);
            } else {
                alert('Erro ao editar coluna: ' + res.message);
            }
        });
    }

    function excluirColuna(colName) {
        if (!confirm("Excluir permanentemente a coluna '" + colName + "' da tabela '" + selectedTable + "'?")) return;
        var fd = new FormData();
        fd.append('table_name', selectedTable);
        fd.append('col_name', colName);

        dbApiRequest('drop_column', 'POST', fd, function (res) {
            if (res.success) {
                inspecionarTabela(selectedTable);
            } else {
                alert('Erro: ' + res.message);
            }
        });
    }

    function renomearTabelaPrompt(oldName) {
        var newName = prompt('Novo nome para a tabela:', oldName);
        if (!newName || newName === oldName) return;

        var fd = new FormData();
        fd.append('old_name', oldName);
        fd.append('new_name', newName);

        dbApiRequest('rename_table', 'POST', fd, function (res) {
            if (res.success) {
                selectedTable = newName;
                carregarListaTabelas();
                inspecionarTabela(newName);
            } else {
                alert('Erro: ' + res.message);
            }
        });
    }

    function excluirTabela(tableName) {
        if (!confirm("ATENÇÃO: Deseja excluir permanentemente a tabela '" + tableName + "' e todos os seus dados?")) return;
        var fd = new FormData();
        fd.append('table_name', tableName);

        dbApiRequest('drop_table', 'POST', fd, function (res) {
            if (res.success) {
                selectedTable = '';
                carregarListaTabelas();
                document.getElementById('tableInspectorPanel').innerHTML = '<div style="text-align:center;padding:3rem 1rem;color:var(--text-secondary);">Tabela excluída. Selecione outra tabela ao lado.</div>';
            } else {
                alert('Erro: ' + res.message);
            }
        });
    }

    // Modal Criar Tabela
    function abrirModalNovaTabela() {
        document.getElementById('newTableName').value = '';
        var container = document.getElementById('newTableColumnsContainer');
        container.innerHTML = '';
        adicionarLinhaColunaModal('id', 'INT', '11', '0', '', 'AUTO_INCREMENT', true);
        adicionarLinhaColunaModal('nome', 'VARCHAR', '150', '0', '', '', false);
        adicionarLinhaColunaModal('criado_em', 'TIMESTAMP', '', '0', 'CURRENT_TIMESTAMP', '', false);
        document.getElementById('modalNewTable').classList.add('active');
    }

    function fecharModalNovaTabela() {
        document.getElementById('modalNewTable').classList.remove('active');
    }

    function adicionarLinhaColunaModal(name, type, len, nullable, defVal, extra, isPk) {
        var container = document.getElementById('newTableColumnsContainer');
        var row = document.createElement('div');
        row.className = 'col-builder-row';
        row.style.cssText = 'display:grid;grid-template-columns:1.3fr 1fr 0.7fr 0.8fr 0.7fr auto;gap:0.45rem;align-items:center;';

        row.innerHTML =
            '<input type="text" class="input cb-name" placeholder="Nome" value="' + (name || '') + '">' +
            '<select class="select cb-type">' +
                '<option value="INT"' + (type === 'INT' ? ' selected' : '') + '>INT</option>' +
                '<option value="VARCHAR"' + (type === 'VARCHAR' ? ' selected' : '') + '>VARCHAR</option>' +
                '<option value="TEXT"' + (type === 'TEXT' ? ' selected' : '') + '>TEXT</option>' +
                '<option value="DECIMAL"' + (type === 'DECIMAL' ? ' selected' : '') + '>DECIMAL</option>' +
                '<option value="DATETIME"' + (type === 'DATETIME' ? ' selected' : '') + '>DATETIME</option>' +
                '<option value="TIMESTAMP"' + (type === 'TIMESTAMP' ? ' selected' : '') + '>TIMESTAMP</option>' +
                '<option value="TINYINT"' + (type === 'TINYINT' ? ' selected' : '') + '>TINYINT</option>' +
            '</select>' +
            '<input type="text" class="input cb-len" placeholder="Tam" value="' + (len || '') + '">' +
            '<select class="select cb-extra">' +
                '<option value="">Normal</option>' +
                '<option value="AUTO_INCREMENT"' + (extra === 'AUTO_INCREMENT' ? ' selected' : '') + '>AUTO_INC</option>' +
            '</select>' +
            '<label style="font-size:0.78rem;display:flex;align-items:center;gap:0.3rem;color:var(--text-secondary);">' +
                '<input type="checkbox" class="cb-pk"' + (isPk ? ' checked' : '') + '> PK' +
            '</label>' +
            '<button type="button" class="btn btn-sm btn-danger" onclick="this.parentNode.remove()">X</button>';

        row.dataset.nullable = nullable || '0';
        row.dataset.defval = defVal || '';
        container.appendChild(row);
    }

    function salvarNovaTabela() {
        var tableName = document.getElementById('newTableName').value;
        var rows = document.querySelectorAll('.col-builder-row');
        var cols = [];

        for (var i = 0; i < rows.length; i++) {
            var r = rows[i];
            var cName = r.querySelector('.cb-name').value.trim();
            if (!cName) continue;
            cols.push({
                name: cName,
                type: r.querySelector('.cb-type').value,
                length: r.querySelector('.cb-len').value,
                extra: r.querySelector('.cb-extra').value,
                primary: r.querySelector('.cb-pk').checked,
                nullable: r.dataset.nullable,
                default_val: r.dataset.defval
            });
        }

        var fd = new FormData();
        fd.append('table_name', tableName);
        fd.append('columns', JSON.stringify(cols));

        dbApiRequest('create_table', 'POST', fd, function (res) {
            if (res.success) {
                fecharModalNovaTabela();
                switchMainView('database');
                switchDbSubtab('tables');
                carregarListaTabelas();
                inspecionarTabela(tableName);
            } else {
                alert('Erro: ' + res.message);
            }
        });
    }

    // 4. CONSOLE SQL, FUNCTIONS & PROCEDURES
    function inserirTemplateSql(tipo) {
        var box = document.getElementById('sqlConsoleInput');
        if (tipo === 'select') {
            box.value = "SELECT * FROM logs_estudo ORDER BY id DESC LIMIT 10;";
        } else if (tipo === 'procedure') {
            box.value = "CREATE PROCEDURE sp_registrar_estudo(IN p_modulo VARCHAR(100), IN p_desc VARCHAR(255))\nBEGIN\n    INSERT INTO logs_estudo (modulo, descricao) VALUES (p_modulo, p_desc);\n    SELECT LAST_INSERT_ID() AS novo_id;\nEND";
        } else if (tipo === 'function') {
            box.value = "CREATE FUNCTION fn_saudacao_dev(p_nome VARCHAR(100))\nRETURNS VARCHAR(150)\nDETERMINISTIC\nBEGIN\n    RETURN CONCAT('Olá, ', p_nome, '! Bem-vindo ao MySQL 8.0.');\nEND";
        }
        box.focus();
    }

    function executarSqlLivre() {
        var sql = document.getElementById('sqlConsoleInput').value;
        var statusBox = document.getElementById('sqlConsoleStatus');
        var tableContainer = document.getElementById('sqlConsoleResultTable');

        statusBox.style.display = 'block';
        statusBox.style.borderColor = '#38bdf8';
        statusBox.style.color = '#38bdf8';
        statusBox.textContent = 'Executando comando no MySQL...';
        tableContainer.innerHTML = '';

        var fd = new FormData();
        fd.append('sql', sql);

        dbApiRequest('run_sql', 'POST', fd, function (res) {
            if (res.success) {
                statusBox.style.borderColor = '#10b981';
                statusBox.style.color = '#10b981';
                statusBox.textContent = '[OK] ' + res.message + ' | Linhas afetadas/retornadas: ' + (res.rows.length || res.affected_rows);

                if (res.rows && res.rows.length > 0) {
                    var keys = Object.keys(res.rows[0]);
                    var html = '<table><thead><tr>';
                    for (var i = 0; i < keys.length; i++) html += '<th>' + escapeHtml(keys[i]) + '</th>';
                    html += '</tr></thead><tbody>';
                    for (var r = 0; r < res.rows.length; r++) {
                        html += '<tr>';
                        for (var k = 0; k < keys.length; k++) {
                            var val = res.rows[r][keys[k]];
                            html += '<td>' + (val !== null ? escapeHtml(val) : '<span style="color:#64748b;">NULL</span>') + '</td>';
                        }
                        html += '</tr>';
                    }
                    html += '</tbody></table>';
                    tableContainer.innerHTML = html;
                }
                carregarRoutines();
                carregarListaTabelas();
            } else {
                statusBox.style.borderColor = '#ef4444';
                statusBox.style.color = '#fca5a5';
                statusBox.textContent = '[ERRO SQL] ' + res.message;
            }
        });
    }

    function carregarRoutines() {
        dbApiRequest('list_routines', 'GET', null, function (res) {
            var wrap = document.getElementById('routinesTableWrapper');
            if (!res.success) {
                wrap.innerHTML = '<span style="color:#ef4444;font-size:0.85rem;">' + escapeHtml(res.message) + '</span>';
                return;
            }
            if (!res.data || res.data.length === 0) {
                wrap.innerHTML = '<p style="color:var(--text-secondary);font-size:0.85rem;">Nenhuma Procedure ou Function criada ainda. Use os botões de Template acima para testar.</p>';
                return;
            }

            var html = '<table><thead><tr><th>Nome</th><th>Tipo</th><th>Retorno</th><th>Criada em</th><th>Ações</th></tr></thead><tbody>';
            for (var i = 0; i < res.data.length; i++) {
                var rt = res.data[i];
                html += '<tr>' +
                    '<td><strong><code>' + escapeHtml(rt.name) + '</code></strong></td>' +
                    '<td>' + escapeHtml(rt.type) + '</td>' +
                    '<td>' + escapeHtml(rt.returns || 'VOID') + '</td>' +
                    '<td>' + escapeHtml(rt.created_at) + '</td>' +
                    '<td>' +
                        '<button type="button" class="btn btn-sm" onclick="carregarCodigoRoutine(\'' + escapeHtml(rt.name) + '\', \'' + escapeHtml(rt.type) + '\')">Ver SQL</button> ' +
                        '<button type="button" class="btn btn-sm btn-danger" onclick="excluirRoutine(\'' + escapeHtml(rt.name) + '\', \'' + escapeHtml(rt.type) + '\')">Excluir</button>' +
                    '</td>' +
                '</tr>';
            }
            html += '</tbody></table>';
            wrap.innerHTML = html;
        });
    }

    function carregarCodigoRoutine(name, type) {
        dbApiRequest('get_routine_code', 'GET', 'name=' + encodeURIComponent(name) + '&type=' + encodeURIComponent(type), function (res) {
            if (res.success) {
                document.getElementById('sqlConsoleInput').value = res.code;
                document.getElementById('sqlConsoleInput').focus();
            } else {
                alert(res.message);
            }
        });
    }

    function excluirRoutine(name, type) {
        if (!confirm("Excluir " + type + " '" + name + "'?")) return;
        var fd = new FormData();
        fd.append('name', name);
        fd.append('type', type);

        dbApiRequest('drop_routine', 'POST', fd, function (res) {
            if (res.success) {
                carregarRoutines();
            } else {
                alert(res.message);
            }
        });
    }

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    window.addEventListener('DOMContentLoaded', function () {
        var sqlBox = document.getElementById('sqlConsoleInput');
        sqlBox.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && (e.key === 'Enter' || e.keyCode === 13)) {
                e.preventDefault();
                executarSqlLivre();
            }
        });

        var hash = window.location.hash.replace('#', '');
        if (hash === 'workspace' || hash === 'database' || hash === 'dashboard') {
            switchMainView(hash);
        } else {
            loadDashboardStats();
        }
    });
</script>

</body>
</html>
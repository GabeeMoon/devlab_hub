<?php
// Motor Único de Módulo com Split IDE + Exclusão - Compatível com PHP 5.6
if (!defined('MODULE_DIR')) {
    header('HTTP/1.1 403 Forbidden');
    exit('Acesso direto ao template não permitido.');
}

$dirAtual = MODULE_DIR;
$materiaAtual = str_replace(array('_', '-'), ' ', basename($dirAtual));

// Endpoints AJAX da Mini-IDE (Ler, Salvar, Criar e Excluir Arquivos)
if (isset($_GET['ide_action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['ide_action'];
    $response = array('success' => false, 'message' => '');
    $allowedExts = array('php', 'html', 'css', 'js', 'sql');

    // 1. Ler código-fonte
    if ($action === 'view_source') {
        $requestedFile = isset($_GET['file']) ? basename($_GET['file']) : '';
        $targetPath = $dirAtual . DIRECTORY_SEPARATOR . $requestedFile;
        $ext = strtolower(pathinfo($requestedFile, PATHINFO_EXTENSION));

        if ($requestedFile !== 'index.php' && in_array($ext, $allowedExts) && is_file($targetPath)) {
            $response['success'] = true;
            $response['filename'] = $requestedFile;
            $response['code'] = file_get_contents($targetPath);
        } else {
            $response['message'] = 'Arquivo não encontrado ou protegido.';
        }
        echo json_encode($response);
        exit;
    }

    // 2. Salvar alterações no código-fonte
    if ($action === 'save_source') {
        $requestedFile = isset($_POST['file']) ? basename($_POST['file']) : '';
        $code = isset($_POST['code']) ? $_POST['code'] : '';
        $targetPath = $dirAtual . DIRECTORY_SEPARATOR . $requestedFile;
        $ext = strtolower(pathinfo($requestedFile, PATHINFO_EXTENSION));

        if ($requestedFile === 'index.php' || !in_array($ext, $allowedExts) || !is_file($targetPath)) {
            $response['message'] = 'Arquivo inválido ou protegido contra edição.';
        } elseif (!is_writable($targetPath)) {
            $response['message'] = 'Sem permissão de escrita no arquivo. Rode chmod -R 777 na pasta Estudos.';
        } else {
            $bytes = file_put_contents($targetPath, $code);
            if ($bytes !== false) {
                $response['success'] = true;
                $response['message'] = 'Salvo e executado às ' . date('H:i:s') . ' (' . $bytes . ' bytes)';
            } else {
                $response['message'] = 'Falha ao gravar arquivo no disco.';
            }
        }
        echo json_encode($response);
        exit;
    }

    // 3. Criar novo arquivo de estudo dentro da matéria
    if ($action === 'create_file') {
        $rawName = isset($_POST['filename']) ? trim($_POST['filename']) : '';
        $template = isset($_POST['template']) ? $_POST['template'] : 'oop_basic';

        $cleanName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $rawName);
        if ($cleanName === '') {
            $response['message'] = 'Informe um nome válido para o arquivo.';
            echo json_encode($response);
            exit;
        }

        if (pathinfo($cleanName, PATHINFO_EXTENSION) === '') {
            $cleanName .= '.php';
        }

        if ($cleanName === 'index.php') {
            $response['message'] = 'O nome index.php é reservado pelo sistema.';
            echo json_encode($response);
            exit;
        }

        $targetPath = $dirAtual . DIRECTORY_SEPARATOR . $cleanName;
        if (file_exists($targetPath)) {
            $response['message'] = "O arquivo '$cleanName' já existe nesta pasta.";
            echo json_encode($response);
            exit;
        }

        if (!is_writable($dirAtual)) {
            $response['message'] = 'A pasta não tem permissão de escrita. Rode chmod -R 777 na pasta Estudos.';
            echo json_encode($response);
            exit;
        }

        if ($template === 'oop_db') {
            $initialCode = "<?php\n// Desc: Exercício de POO integrado ao MySQL via PDO\nerror_reporting(E_ALL);\nini_set('display_errors', 1);\n\nrequire_once __DIR__ . '/../config/db.php';\n\nclass RepositorioTeste {\n    private \$pdo;\n\n    public function __construct(PDO \$pdo) {\n        \$this->pdo = \$pdo;\n    }\n\n    public function testar() {\n        \$stmt = \$this->pdo->query('SELECT NOW() AS agora, VERSION() AS versao');\n        return \$stmt->fetch();\n    }\n}\n\n\$pdo = getDbConnection();\n\$repo = new RepositorioTeste(\$pdo);\necho '<pre>';\nprint_r(\$repo->testar());\necho '</pre>';\n";
        } elseif ($template === 'oop_basic') {
            $initialCode = "<?php\n// Desc: Laboratório de Classes, Atributos e Métodos em PHP 5.6\nerror_reporting(E_ALL);\nini_set('display_errors', 1);\n\nclass Exemplo {\n    private \$nome;\n\n    public function __construct(\$nome) {\n        \$this->nome = \$nome;\n    }\n\n    public function apresentar() {\n        return 'Executando estudo: ' . \$this->nome;\n    }\n}\n\n\$obj = new Exemplo('" . addslashes($cleanName) . "');\necho '<h3>' . \$obj->apresentar() . '</h3>';\n";
        } else {
            $initialCode = "<?php\n// Desc: Novo experimento em PHP 5.6\nerror_reporting(E_ALL);\nini_set('display_errors', 1);\n\n";
        }

        if (file_put_contents($targetPath, $initialCode) !== false) {
            $response['success'] = true;
            $response['filename'] = $cleanName;
            $response['message'] = "Arquivo '$cleanName' criado com sucesso!";
        } else {
            $response['message'] = 'Erro ao criar o arquivo no servidor.';
        }

        echo json_encode($response);
        exit;
    }

    // 4. Excluir arquivo de estudo
    if ($action === 'delete_file') {
        $requestedFile = isset($_POST['file']) ? basename($_POST['file']) : '';
        $targetPath = $dirAtual . DIRECTORY_SEPARATOR . $requestedFile;
        $ext = strtolower(pathinfo($requestedFile, PATHINFO_EXTENSION));

        if ($requestedFile === '' || $requestedFile === 'index.php' || !in_array($ext, $allowedExts)) {
            $response['message'] = 'Arquivo inválido ou protegido contra exclusão.';
        } elseif (!is_file($targetPath)) {
            $response['message'] = 'O arquivo não existe mais no servidor.';
        } elseif (!is_writable($targetPath) && !is_writable($dirAtual)) {
            $response['message'] = 'Sem permissão para excluir este arquivo. Verifique permissões na VM.';
        } else {
            if (@unlink($targetPath)) {
                $response['success'] = true;
                $response['message'] = "Arquivo '$requestedFile' excluído com sucesso.";
            } else {
                $response['message'] = 'Não foi possível excluir o arquivo no disco.';
            }
        }
        echo json_encode($response);
        exit;
    }
}

function extrairDescricaoArquivo($filePath) {
    $handle = @fopen($filePath, 'r');
    if (!$handle) {
        return 'Arquivo de laboratório e testes práticos.';
    }
    $descricao = 'Arquivo de laboratório e testes práticos.';
    $lineCount = 0;
    while (($line = fgets($handle)) !== false && $lineCount < 8) {
        $lineCount++;
        if (preg_match('/^\s*(?:\/\/|#|\*)\s*Desc:\s*(.+)$/i', $line, $matches)) {
            $descricao = trim($matches[1]);
            break;
        }
    }
    fclose($handle);
    return $descricao;
}

$arquivosEstudo = array();
$itens = scandir($dirAtual);
foreach ($itens as $item) {
    if ($item === '.' || $item === '..' || $item === 'index.php') {
        continue;
    }
    $caminhoCompleto = $dirAtual . DIRECTORY_SEPARATOR . $item;
    if (is_file($caminhoCompleto)) {
        $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
        if (in_array($ext, array('php', 'html', 'css', 'js', 'sql'))) {
            $arquivosEstudo[] = array(
                'filename' => $item,
                'title'    => str_replace(array('_', '-'), ' ', pathinfo($item, PATHINFO_FILENAME)),
                'ext'      => strtoupper($ext),
                'size'     => number_format(filesize($caminhoCompleto) / 1024, 2, ',', '.') . ' KB',
                'modified' => date('d/m/Y H:i', filemtime($caminhoCompleto)),
                'mtime'    => filemtime($caminhoCompleto),
                'desc'     => extrairDescricaoArquivo($caminhoCompleto)
            );
        }
    }
}

usort($arquivosEstudo, function ($a, $b) {
    if ($a['mtime'] == $b['mtime']) return 0;
    return ($a['mtime'] > $b['mtime']) ? -1 : 1;
});
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($materiaAtual); ?> | DevLab IDE</title>
    <style>
        :root {
            --bg-main: #0b0f19;
            --bg-surface: #131a2a;
            --bg-surface-hover: #1a2338;
            --bg-code: #080b12;
            --bg-gutter: #0d121d;
            --border-color: #232e47;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --accent: #38bdf8;
            --accent-glow: rgba(56, 189, 248, 0.16);
            --success: #10b981;
            --danger: #ef4444;
            --danger-bg: rgba(239, 68, 68, 0.12);
            --radius: 12px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--bg-main);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
            padding: 2rem 1.5rem;
        }

        .container { max-width: 1140px; margin: 0 auto; }

        .top-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            color: var(--text-secondary);
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.88rem;
            font-weight: 500;
            transition: all 0.15s ease;
        }
        .btn-back:hover { color: var(--accent); border-color: var(--accent); }

        .header {
            padding-bottom: 1.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }

        .header h1 { font-size: 1.8rem; font-weight: 700; }
        .header p { color: var(--text-secondary); font-size: 0.9rem; margin-top: 0.2rem; }

        .header-actions { display: flex; gap: 0.75rem; flex-wrap: wrap; }

        .search-box, .input-field, .select-field {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 0.55rem 0.9rem;
            border-radius: 8px;
            font-size: 0.88rem;
            outline: none;
        }
        .search-box:focus, .input-field:focus, .select-field:focus { border-color: var(--accent); }

        .new-file-bar {
            display: none;
            background: var(--bg-surface);
            border: 1px solid var(--accent);
            border-radius: var(--radius);
            padding: 1.1rem 1.25rem;
            margin-bottom: 1.5rem;
            gap: 0.75rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .new-file-bar.active { display: flex; }

        .files-list { display: flex; flex-direction: column; gap: 0.9rem; }

        .file-card {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 1.25rem 1.5rem;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            transition: border-color 0.15s ease, background 0.15s ease;
        }
        .file-card:hover { border-color: var(--accent); background: var(--bg-surface-hover); }

        .file-info { flex: 1; min-width: 260px; }
        .file-title-row { display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.3rem; }

        .ext-badge {
            font-family: monospace;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 0.15rem 0.5rem;
            border-radius: 6px;
            background: var(--accent-glow);
            color: var(--accent);
            border: 1px solid rgba(56, 189, 248, 0.3);
        }

        .file-info h3 { font-size: 1.08rem; color: var(--text-primary); }
        .file-desc { font-size: 0.88rem; color: var(--text-secondary); margin-bottom: 0.45rem; }
        .file-meta { font-size: 0.78rem; color: var(--text-secondary); font-family: monospace; }
        .file-actions { display: flex; gap: 0.55rem; flex-wrap: wrap; }

        .btn {
            cursor: pointer;
            text-decoration: none;
            padding: 0.5rem 0.9rem;
            border-radius: 8px;
            font-size: 0.83rem;
            font-weight: 600;
            border: 1px solid var(--border-color);
            background: var(--bg-main);
            color: var(--text-primary);
            transition: all 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        .btn:hover { border-color: var(--accent); color: var(--accent); }

        .btn-primary {
            background: var(--accent);
            color: #06101d;
            border-color: var(--accent);
        }
        .btn-primary:hover { background: #7dd3fc; color: #06101d; }

        .btn-success {
            background: var(--success);
            color: #04130d;
            border-color: var(--success);
        }
        .btn-success:hover { background: #34d399; color: #04130d; }

        .btn-danger {
            background: var(--danger-bg);
            color: #fca5a5;
            border-color: rgba(239, 68, 68, 0.35);
        }
        .btn-danger:hover {
            background: var(--danger);
            color: #ffffff;
            border-color: var(--danger);
        }

        .empty-box {
            background: var(--bg-surface);
            border: 1px dashed var(--border-color);
            border-radius: var(--radius);
            padding: 3rem 1.5rem;
            text-align: center;
            color: var(--text-secondary);
        }

        /* Mini-IDE Modal Split View */
        .modal-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(4, 7, 15, 0.9);
            z-index: 999;
            align-items: center;
            justify-content: center;
            padding: 0.75rem;
        }
        .modal-backdrop.active { display: flex; }

        .ide-window {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            width: 98vw;
            max-width: 1520px;
            height: 92vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.85);
        }

        .ide-header {
            padding: 0.7rem 1.1rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 0.75rem;
            background: #0f1523;
        }

        .ide-file-info {
            display: flex;
            align-items: center;
            gap: 0.65rem;
        }

        .ide-filename {
            font-family: monospace;
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--accent);
        }

        .ide-dirty-dot {
            display: none;
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: #f59e0b;
        }
        .ide-dirty-dot.visible { display: inline-block; }

        .ide-toolbar { display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center; }

        .ide-workspace {
            flex: 1;
            display: flex;
            overflow: hidden;
            position: relative;
        }

        .ide-pane-left {
            flex: 1;
            min-width: 240px;
            display: flex;
            background: var(--bg-code);
            overflow: hidden;
        }

        .ide-gutter {
            width: 50px;
            background: var(--bg-gutter);
            border-right: 1px solid var(--border-color);
            color: #475569;
            font-family: "Fira Code", Consolas, Monaco, monospace;
            font-size: 0.86rem;
            line-height: 1.55;
            padding: 1rem 0.6rem 1rem 0;
            text-align: right;
            user-select: none;
            overflow: hidden;
            white-space: pre;
        }

        .ide-textarea {
            flex: 1;
            background: transparent;
            border: none;
            color: #e2e8f0;
            font-family: "Fira Code", Consolas, Monaco, monospace;
            font-size: 0.86rem;
            line-height: 1.55;
            padding: 1rem;
            resize: none;
            outline: none;
            white-space: pre;
            overflow: auto;
            tab-size: 4;
        }

        .ide-resizer {
            width: 6px;
            background: var(--border-color);
            cursor: col-resize;
            transition: background 0.15s ease;
            flex-shrink: 0;
        }
        .ide-resizer:hover, .ide-resizer.dragging {
            background: var(--accent);
        }

        .ide-pane-right {
            flex: 1;
            min-width: 240px;
            display: flex;
            flex-direction: column;
            background: #ffffff;
            overflow: hidden;
        }

        .ide-pane-right.hidden { display: none; }

        .preview-topbar {
            background: #131a2a;
            border-bottom: 1px solid var(--border-color);
            padding: 0.4rem 0.85rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-family: monospace;
        }

        .preview-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            color: var(--success);
            font-weight: 600;
        }

        .preview-actions { display: flex; gap: 0.4rem; }

        .btn-mini {
            cursor: pointer;
            background: var(--bg-main);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            padding: 0.2rem 0.55rem;
            border-radius: 5px;
            font-size: 0.74rem;
            font-family: inherit;
        }
        .btn-mini:hover {
            border-color: var(--accent);
            color: var(--accent);
        }

        .ide-iframe {
            flex: 1;
            width: 100%;
            border: none;
            background: #ffffff;
            transition: background 0.2s ease;
        }

        .ide-iframe.dark-canvas {
            background: #0b0f19;
            color-scheme: dark;
        }

        .ide-statusbar {
            padding: 0.5rem 1.1rem;
            border-top: 1px solid var(--border-color);
            background: #0f1523;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-family: monospace;
        }

        @media (max-width: 820px) {
            .ide-workspace { flex-direction: column; }
            .ide-resizer { width: 100%; height: 6px; cursor: row-resize; }
        }
    </style>
</head>
<body>

<div class="container">
    <nav class="top-nav">
        <a href="../index.php" class="btn-back">&larr; Voltar ao DevLab Hub</a>
        <span style="font-size: 0.82rem; color: var(--text-secondary); font-family: monospace;">
            /Estudos/<?php echo htmlspecialchars(basename($dirAtual)); ?>/ (Motor Único)
        </span>
    </nav>

    <header class="header">
        <div>
            <h1>Módulo: <?php echo htmlspecialchars($materiaAtual); ?></h1>
            <p>Edite o código na esquerda e acompanhe a execução do PHP ao vivo na direita.</p>
        </div>
        <div class="header-actions">
            <input type="text" id="filterInput" class="search-box" placeholder="Filtrar arquivos..." onkeyup="filtrarArquivos()">
            <button type="button" class="btn btn-primary" onclick="toggleNovoArquivo()">+ Novo Arquivo</button>
        </div>
    </header>

    <form class="new-file-bar" id="newFileBar" onsubmit="criarNovoArquivo(event)">
        <input type="text" id="newFilename" class="input-field" style="flex: 1; min-width: 220px;" placeholder="Nome do arquivo (ex: 02_encapsulamento.php)" required>
        <select id="newFileTemplate" class="select-field">
            <option value="oop_basic">Modelo: Classe POO Básica (PHP 5.6)</option>
            <option value="oop_db">Modelo: Classe POO + Conexão MySQL (PDO)</option>
            <option value="blank">Modelo: Arquivo PHP em Branco</option>
        </select>
        <button type="submit" class="btn btn-success">Criar e Abrir Split IDE</button>
        <button type="button" class="btn" onclick="toggleNovoArquivo()">Cancelar</button>
    </form>

    <section class="files-list" id="filesList">
        <?php if (count($arquivosEstudo) > 0): ?>
            <?php foreach ($arquivosEstudo as $arq): ?>
                <article class="file-card" id="card-<?php echo htmlspecialchars($arq['filename']); ?>" data-search="<?php echo htmlspecialchars(strtolower($arq['filename'] . ' ' . $arq['title'] . ' ' . $arq['desc'])); ?>">
                    <div class="file-info">
                        <div class="file-title-row">
                            <span class="ext-badge"><?php echo htmlspecialchars($arq['ext']); ?></span>
                            <h3><?php echo htmlspecialchars($arq['title']); ?></h3>
                        </div>
                        <p class="file-desc"><?php echo htmlspecialchars($arq['desc']); ?></p>
                        <div class="file-meta">
                            Arquivo: <?php echo htmlspecialchars($arq['filename']); ?> &bull;
                            Tamanho: <?php echo $arq['size']; ?> &bull;
                            Modificado: <?php echo $arq['modified']; ?>
                        </div>
                    </div>
                    <div class="file-actions">
                        <button type="button" class="btn btn-primary" onclick="abrirIDE('<?php echo htmlspecialchars($arq['filename'], ENT_QUOTES); ?>')">
                            Abrir Split IDE
                        </button>
                        <a href="<?php echo rawurlencode($arq['filename']); ?>" class="btn" target="_blank">
                            Nova Aba &rarr;
                        </a>
                        <button type="button" class="btn btn-danger" onclick="excluirArquivo('<?php echo htmlspecialchars($arq['filename'], ENT_QUOTES); ?>', false)">
                            Excluir
                        </button>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-box">
                <h3 style="color: var(--text-primary); margin-bottom: 0.5rem;">Nenhum experimento criado nesta pasta ainda</h3>
                <p>Clique no botão <strong>+ Novo Arquivo</strong> acima para criar seu primeiro exercício.</p>
            </div>
        <?php endif; ?>
    </section>
</div>

<!-- Mini-IDE Modal Split View -->
<div class="modal-backdrop" id="ideModal">
    <div class="ide-window">
        <div class="ide-header">
            <div class="ide-file-info">
                <span class="ext-badge">SPLIT IDE</span>
                <span class="ide-filename" id="ideFilename">arquivo.php</span>
                <span class="ide-dirty-dot" id="ideDirtyDot" title="Alterações não salvas"></span>
            </div>
            <div class="ide-toolbar">
                <button type="button" class="btn btn-success" onclick="salvarCodigo()">Salvar e Executar (Ctrl+S)</button>
                <button type="button" class="btn" onclick="recarregarPreview()">Rodar Novamente</button>
                <button type="button" class="btn" id="btnToggleSplit" onclick="alternarModoSplit()">Ocultar Saída</button>
                <a href="#" id="ideRunLink" class="btn" target="_blank">Nova Aba &rarr;</a>
                <button type="button" class="btn btn-danger" onclick="excluirArquivoAtualIDE()">Excluir Arquivo</button>
                <button type="button" class="btn" onclick="fecharIDE()">Fechar</button>
            </div>
        </div>

        <div class="ide-workspace" id="ideWorkspace">
            <div class="ide-pane-left" id="idePaneLeft">
                <div class="ide-gutter" id="ideGutter">1</div>
                <textarea id="ideTextarea" class="ide-textarea" spellcheck="false"></textarea>
            </div>

            <div class="ide-resizer" id="ideResizer" title="Arraste para redimensionar os painéis"></div>

            <div class="ide-pane-right" id="idePaneRight">
                <div class="preview-topbar">
                    <span class="preview-badge">&#9679; Saída PHP ao Vivo</span>
                    <div class="preview-actions">
                        <button type="button" class="btn-mini" onclick="alternarFundoPreview()">Alternar Fundo Claro/Escuro</button>
                        <button type="button" class="btn-mini" onclick="recarregarPreview()">&#8635; Atualizar</button>
                    </div>
                </div>
                <iframe id="idePreviewFrame" class="ide-iframe" src="about:blank"></iframe>
            </div>
        </div>

        <div class="ide-statusbar">
            <span id="ideStatusMsg">Pronto.</span>
            <span>Tab = 4 espaços &bull; Ctrl+S = Salvar e Executar &bull; Arraste a divisória central para ajustar largura</span>
        </div>
    </div>
</div>

<script>
    var currentEditingFile = '';
    var hasUnsavedChanges = false;
    var needsReloadOnClose = false;

    function toggleNovoArquivo() {
        var bar = document.getElementById('newFileBar');
        bar.classList.toggle('active');
        if (bar.classList.contains('active')) {
            document.getElementById('newFilename').focus();
        }
    }

    function criarNovoArquivo(event) {
        event.preventDefault();
        var filename = document.getElementById('newFilename').value;
        var template = document.getElementById('newFileTemplate').value;

        var formData = new FormData();
        formData.append('filename', filename);
        formData.append('template', template);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'index.php?ide_action=create_file', true);
        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4) {
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        needsReloadOnClose = true;
                        document.getElementById('newFilename').value = '';
                        toggleNovoArquivo();
                        abrirIDE(res.filename);
                    } else {
                        alert(res.message);
                    }
                } catch (e) {
                    alert('Erro inesperado ao criar arquivo.');
                }
            }
        };
        xhr.send(formData);
    }

    function excluirArquivo(filename, fromIDE) {
        if (!filename) return;
        if (!confirm("Tem certeza que deseja excluir permanentemente o arquivo '" + filename + "'?\nEsta ação não pode ser desfeita.")) {
            return;
        }

        var formData = new FormData();
        formData.append('file', filename);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'index.php?ide_action=delete_file', true);
        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4) {
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        if (fromIDE) {
                            hasUnsavedChanges = false;
                            document.getElementById('idePreviewFrame').src = 'about:blank';
                            document.getElementById('ideModal').className = 'modal-backdrop';
                            window.location.reload();
                        } else {
                            var card = document.getElementById('card-' + filename);
                            if (card) {
                                card.parentNode.removeChild(card);
                            } else {
                                window.location.reload();
                            }
                        }
                    } else {
                        alert('Erro ao excluir: ' + res.message);
                    }
                } catch (e) {
                    alert('Erro inesperado ao excluir o arquivo.');
                }
            }
        };
        xhr.send(formData);
    }

    function excluirArquivoAtualIDE() {
        excluirArquivo(currentEditingFile, true);
    }

    function filtrarArquivos() {
        var termo = document.getElementById('filterInput').value.toLowerCase();
        var cards = document.querySelectorAll('.file-card');
        for (var i = 0; i < cards.length; i++) {
            var texto = cards[i].getAttribute('data-search');
            cards[i].style.display = texto.indexOf(termo) !== -1 ? 'flex' : 'none';
        }
    }

    function atualizarNumerosLinhas() {
        var textarea = document.getElementById('ideTextarea');
        var gutter = document.getElementById('ideGutter');
        var linesCount = textarea.value.split('\n').length;
        var numbers = [];
        for (var i = 1; i <= linesCount; i++) {
            numbers.push(i);
        }
        gutter.textContent = numbers.join('\n');
    }

    function recarregarPreview() {
        if (!currentEditingFile) return;
        var iframe = document.getElementById('idePreviewFrame');
        iframe.src = encodeURIComponent(currentEditingFile) + '?_exec=' + new Date().getTime();
    }

    function alternarFundoPreview() {
        var iframe = document.getElementById('idePreviewFrame');
        iframe.classList.toggle('dark-canvas');
    }

    function alternarModoSplit() {
        var rightPane = document.getElementById('idePaneRight');
        var resizer = document.getElementById('ideResizer');
        var btn = document.getElementById('btnToggleSplit');

        rightPane.classList.toggle('hidden');
        if (rightPane.classList.contains('hidden')) {
            resizer.style.display = 'none';
            btn.textContent = 'Mostrar Saída';
        } else {
            resizer.style.display = 'block';
            btn.textContent = 'Ocultar Saída';
            recarregarPreview();
        }
    }

    function abrirIDE(filename) {
        currentEditingFile = filename;
        hasUnsavedChanges = false;

        var modal = document.getElementById('ideModal');
        var title = document.getElementById('ideFilename');
        var textarea = document.getElementById('ideTextarea');
        var statusMsg = document.getElementById('ideStatusMsg');
        var runLink = document.getElementById('ideRunLink');

        title.textContent = filename;
        runLink.href = encodeURIComponent(filename);
        textarea.value = 'Carregando código-fonte...';
        statusMsg.style.color = '#94a3b8';
        statusMsg.textContent = 'Carregando...';
        document.getElementById('ideDirtyDot').className = 'ide-dirty-dot';
        modal.className = 'modal-backdrop active';

        document.getElementById('idePaneRight').classList.remove('hidden');
        document.getElementById('ideResizer').style.display = 'block';
        document.getElementById('btnToggleSplit').textContent = 'Ocultar Saída';
        recarregarPreview();

        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'index.php?ide_action=view_source&file=' + encodeURIComponent(filename), true);
        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4) {
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        textarea.value = res.code;
                        atualizarNumerosLinhas();
                        statusMsg.textContent = 'Editando ' + filename;
                        textarea.focus();
                    } else {
                        textarea.value = res.message;
                        statusMsg.textContent = 'Erro ao abrir.';
                    }
                } catch (e) {
                    textarea.value = 'Erro ao carregar código.';
                }
            }
        };
        xhr.send();
    }

    function salvarCodigo() {
        if (!currentEditingFile) return;

        var code = document.getElementById('ideTextarea').value;
        var statusMsg = document.getElementById('ideStatusMsg');
        statusMsg.style.color = '#38bdf8';
        statusMsg.textContent = 'Salvando e executando...';

        var formData = new FormData();
        formData.append('file', currentEditingFile);
        formData.append('code', code);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'index.php?ide_action=save_source', true);
        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4) {
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        hasUnsavedChanges = false;
                        needsReloadOnClose = true;
                        document.getElementById('ideDirtyDot').className = 'ide-dirty-dot';
                        statusMsg.style.color = '#10b981';
                        statusMsg.textContent = res.message;
                        recarregarPreview();
                    } else {
                        statusMsg.style.color = '#ef4444';
                        statusMsg.textContent = 'Erro: ' + res.message;
                    }
                } catch (e) {
                    statusMsg.style.color = '#ef4444';
                    statusMsg.textContent = 'Erro ao salvar no servidor.';
                }
            }
        };
        xhr.send(formData);
    }

    function fecharIDE() {
        if (hasUnsavedChanges) {
            if (!confirm('Existem alterações não salvas. Deseja fechar mesmo assim?')) {
                return;
            }
        }
        document.getElementById('idePreviewFrame').src = 'about:blank';
        document.getElementById('ideModal').className = 'modal-backdrop';
        if (needsReloadOnClose) {
            window.location.reload();
        }
    }

    window.addEventListener('DOMContentLoaded', function () {
        var textarea = document.getElementById('ideTextarea');
        var gutter = document.getElementById('ideGutter');
        var resizer = document.getElementById('ideResizer');
        var leftPane = document.getElementById('idePaneLeft');
        var workspace = document.getElementById('ideWorkspace');

        textarea.addEventListener('input', function () {
            hasUnsavedChanges = true;
            document.getElementById('ideDirtyDot').className = 'ide-dirty-dot visible';
            document.getElementById('ideStatusMsg').style.color = '#f59e0b';
            document.getElementById('ideStatusMsg').textContent = 'Modificado (pressione Ctrl+S para salvar e executar)';
            atualizarNumerosLinhas();
        });

        textarea.addEventListener('scroll', function () {
            gutter.scrollTop = textarea.scrollTop;
        });

        textarea.addEventListener('keydown', function (e) {
            if (e.key === 'Tab' || e.keyCode === 9) {
                e.preventDefault();
                var start = this.selectionStart;
                var end = this.selectionEnd;
                this.value = this.value.substring(0, start) + '    ' + this.value.substring(end);
                this.selectionStart = this.selectionEnd = start + 4;
                atualizarNumerosLinhas();
            }

            if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S' || e.keyCode === 83)) {
                e.preventDefault();
                salvarCodigo();
            }
        });

        var isResizing = false;

        resizer.addEventListener('mousedown', function () {
            isResizing = true;
            resizer.classList.add('dragging');
            document.getElementById('idePreviewFrame').style.pointerEvents = 'none';
            document.body.style.userSelect = 'none';
        });

        document.addEventListener('mousemove', function (e) {
            if (!isResizing) return;
            var rect = workspace.getBoundingClientRect();
            var offsetX = e.clientX - rect.left;
            var percentage = (offsetX / rect.width) * 100;
            if (percentage > 20 && percentage < 80) {
                leftPane.style.flex = '0 0 ' + percentage + '%';
            }
        });

        document.addEventListener('mouseup', function () {
            if (isResizing) {
                isResizing = false;
                resizer.classList.remove('dragging');
                document.getElementById('idePreviewFrame').style.pointerEvents = 'auto';
                document.body.style.userSelect = '';
            }
        });
    });
</script>

</body>
</html>
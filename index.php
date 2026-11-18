<?php
// DevLab Hub Principal - Compatível com PHP 5.6
require_once __DIR__ . '/config/db.php';

if (isset($_GET['ajax_action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['ajax_action'];
    $response = array('success' => false, 'message' => '', 'data' => array());

    // Ação 1: Criar nova pasta de matéria com a Mini-IDE já instalada
    if ($action === 'create_module') {
        $rawFolder = isset($_POST['folder_name']) ? trim($_POST['folder_name']) : '';
        // Converte espaços em underscore e remove caracteres especiais
        $cleanFolder = preg_replace('/[^a-zA-Z0-9_\-]/', '_', str_replace(' ', '_', $rawFolder));
        $cleanFolder = preg_replace('/_+/', '_', trim($cleanFolder, '_'));

        $reserved = array('config', 'assets', 'vendor', '.git', '');
        if (in_array(strtolower($cleanFolder), $reserved)) {
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
            $response['message'] = 'Sem permissão de escrita na pasta /Estudos. Rode chmod o+w na pasta.';
            echo json_encode($response);
            exit;
        }

        if (mkdir($targetDir, 0775)) {
            $templatePath = __DIR__ . '/config/module_template.php';
            if (is_file($templatePath)) {
                copy($templatePath, $targetDir . '/index.php');
            }
            $response['success'] = true;
            $response['message'] = "Matéria '$cleanFolder' criada com sucesso!";
        } else {
            $response['message'] = 'Não foi possível criar o diretório no servidor.';
        }

        echo json_encode($response);
        exit;
    }

    // Ações do Laboratório MySQL
    try {
        $pdo = getDbConnection();

        if ($action === 'status') {
            $stmt = $pdo->query("SELECT VERSION() AS version, DATABASE() AS db_name, NOW() AS server_time");
            $info = $stmt->fetch();
            $response['success'] = true;
            $response['message'] = 'Conectado com sucesso ao MySQL!';
            $response['data'] = $info;
        } elseif ($action === 'init_table') {
            $sql = "CREATE TABLE IF NOT EXISTS logs_estudo (
                id INT AUTO_INCREMENT PRIMARY KEY,
                modulo VARCHAR(100) NOT NULL,
                descricao VARCHAR(255) NOT NULL,
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
            $pdo->exec($sql);
            $response['success'] = true;
            $response['message'] = "Tabela 'logs_estudo' verificada/criada com sucesso.";
        } elseif ($action === 'insert_test') {
            $modulo = isset($_POST['modulo']) && trim($_POST['modulo']) !== '' ? trim($_POST['modulo']) : 'POO';
            $descricao = isset($_POST['descricao']) && trim($_POST['descricao']) !== '' ? trim($_POST['descricao']) : 'Teste de conexão PHP 5.6 + Docker MySQL';

            $stmt = $pdo->prepare("INSERT INTO logs_estudo (modulo, descricao) VALUES (:modulo, :descricao)");
            $stmt->execute(array(':modulo' => $modulo, ':descricao' => $descricao));

            $response['success'] = true;
            $response['message'] = 'Registro inserido com ID #' . $pdo->lastInsertId();
        } elseif ($action === 'list_records') {
            $check = $pdo->query("SHOW TABLES LIKE 'logs_estudo'");
            if ($check->rowCount() === 0) {
                $response['success'] = true;
                $response['message'] = "Tabela 'logs_estudo' ainda não existe. Clique em 'Criar Tabela Teste'.";
                $response['data'] = array();
            } else {
                $stmt = $pdo->query("SELECT id, modulo, descricao, criado_em FROM logs_estudo ORDER BY id DESC LIMIT 10");
                $response['success'] = true;
                $response['data'] = $stmt->fetchAll();
            }
        }
    } catch (Exception $e) {
        $response['success'] = false;
        $response['message'] = 'Erro PDO: ' . $e->getMessage();
    }

    echo json_encode($response);
    exit;
}

// Escaneia automaticamente as pastas de matérias dentro de /Estudos
$baseDir = __DIR__;
$ignoredFolders = array('.', '..', 'config', 'assets', '.git', 'vendor');
$modulos = array();

$items = scandir($baseDir);
foreach ($items as $item) {
    if (in_array($item, $ignoredFolders)) {
        continue;
    }
    $fullPath = $baseDir . DIRECTORY_SEPARATOR . $item;
    if (is_dir($fullPath)) {
        // Garante que qualquer pasta antiga sem index.php receba o template
        if (!file_exists($fullPath . '/index.php') && is_file($baseDir . '/config/module_template.php') && is_writable($fullPath)) {
            copy($baseDir . '/config/module_template.php', $fullPath . '/index.php');
        }

        $files = glob($fullPath . '/*.{php,html}', GLOB_BRACE);
        $fileCount = 0;
        if (is_array($files)) {
            foreach ($files as $f) {
                if (basename($f) !== 'index.php') {
                    $fileCount++;
                }
            }
        }

        $modulos[] = array(
            'folder' => $item,
            'title'  => str_replace(array('_', '-'), ' ', $item),
            'count'  => $fileCount,
            'url'    => rawurlencode($item) . '/'
        );
    }
}

$metaCatalog = array(
    'POO' => array(
        'tag'  => 'Em andamento',
        'desc' => 'Classes, Objetos, Encapsulamento, Herança, Polimorfismo, Interfaces e Traits em PHP.',
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
    <title>DevLab Hub | Estudos &amp; Laboratório PHP</title>
    <style>
        :root {
            --bg-main: #0b0f19;
            --bg-surface: #131a2a;
            --bg-surface-hover: #1a2338;
            --bg-input: #090d16;
            --border-color: #232e47;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --accent: #38bdf8;
            --accent-glow: rgba(56, 189, 248, 0.18);
            --success: #10b981;
            --success-bg: rgba(16, 185, 129, 0.12);
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

        .header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            padding-bottom: 1.75rem;
            margin-bottom: 2rem;
            border-bottom: 1px solid var(--border-color);
        }

        .brand h1 {
            font-size: 1.75rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .brand h1 span.badge-php {
            font-size: 0.75rem;
            background: var(--accent-glow);
            color: var(--accent);
            border: 1px solid rgba(56, 189, 248, 0.35);
            padding: 0.2rem 0.6rem;
            border-radius: 999px;
            font-weight: 600;
        }

        .brand p { color: var(--text-secondary); font-size: 0.95rem; margin-top: 0.25rem; }

        .env-pills { display: flex; gap: 0.6rem; flex-wrap: wrap; }

        .pill {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            padding: 0.45rem 0.85rem;
            border-radius: 8px;
            font-size: 0.82rem;
            color: var(--text-secondary);
            font-family: monospace;
        }
        .pill strong { color: var(--text-primary); }

        .section-header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.25rem;
        }

        .section-header h2 { font-size: 1.15rem; font-weight: 600; }

        /* Barra de Nova Matéria */
        .new-module-bar {
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
        .new-module-bar.active { display: flex; }

        .grid-modules {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2.75rem;
        }

        .card-module {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 1.5rem;
            text-decoration: none;
            color: inherit;
            transition: transform 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease;
        }

        .card-module:hover {
            transform: translateY(-3px);
            border-color: var(--accent);
            background: var(--bg-surface-hover);
            box-shadow: 0 10px 25px -10px rgba(56, 189, 248, 0.25);
        }

        .card-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .module-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            background: var(--accent-glow);
            color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: monospace;
            font-weight: 700;
            font-size: 1.05rem;
            border: 1px solid rgba(56, 189, 248, 0.25);
        }

        .module-tag {
            font-size: 0.75rem;
            padding: 0.2rem 0.6rem;
            border-radius: 999px;
            background: rgba(148, 163, 184, 0.12);
            color: var(--text-secondary);
            font-weight: 500;
        }

        .card-module h3 { font-size: 1.2rem; margin-bottom: 0.45rem; }
        .card-module p { font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 1.25rem; }

        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 0.85rem;
            border-top: 1px solid rgba(35, 46, 71, 0.7);
            font-size: 0.82rem;
            color: var(--text-secondary);
        }
        .card-footer span.cta { color: var(--accent); font-weight: 600; }

        /* DB Tester Panel */
        .db-panel {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 1.75rem;
        }

        .db-header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.25rem;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 0.85rem;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 600;
            background: rgba(148, 163, 184, 0.12);
            color: var(--text-secondary);
        }
        .status-badge.online { background: var(--success-bg); color: var(--success); border: 1px solid rgba(16, 185, 129, 0.3); }
        .status-badge.offline { background: var(--danger-bg); color: var(--danger); border: 1px solid rgba(239, 68, 68, 0.3); }

        .dot { width: 8px; height: 8px; border-radius: 50%; background: currentColor; }

        .db-controls { display: flex; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1.5rem; }

        .btn {
            cursor: pointer;
            border: 1px solid var(--border-color);
            background: var(--bg-main);
            color: var(--text-primary);
            padding: 0.55rem 1rem;
            border-radius: 8px;
            font-size: 0.88rem;
            font-weight: 500;
            transition: all 0.15s ease;
        }
        .btn:hover { border-color: var(--accent); color: var(--accent); }

        .btn-primary {
            background: var(--accent);
            color: #06101d;
            border-color: var(--accent);
            font-weight: 600;
        }
        .btn-primary:hover { background: #7dd3fc; color: #06101d; }

        .btn-success {
            background: var(--success);
            color: #04130d;
            border-color: var(--success);
            font-weight: 600;
        }
        .btn-success:hover { background: #34d399; color: #04130d; }

        .test-form {
            display: grid;
            grid-template-columns: 180px 1fr auto;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }
        @media (max-width: 700px) { .test-form { grid-template-columns: 1fr; } }

        .input {
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 0.6rem 0.85rem;
            border-radius: 8px;
            font-size: 0.9rem;
            outline: none;
        }
        .input:focus { border-color: var(--accent); }

        .console-feedback {
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 0.85rem 1rem;
            font-family: monospace;
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-bottom: 1.25rem;
            white-space: pre-wrap;
        }

        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
        th, td { text-align: left; padding: 0.7rem 0.85rem; border-bottom: 1px solid var(--border-color); }
        th { color: var(--text-secondary); font-weight: 600; background: rgba(9, 13, 22, 0.5); }
        .empty-state { text-align: center; padding: 1.5rem; color: var(--text-secondary); font-size: 0.9rem; }
    </style>
</head>
<body>

<div class="container">
    <header class="header">
        <div class="brand">
            <h1>DevLab Hub <span class="badge-php">PHP <?php echo PHP_VERSION; ?></span></h1>
            <p>Central de estudos, laboratórios práticos e Mini-IDE integrada.</p>
        </div>
        <div class="env-pills">
            <div class="pill">VM: <strong>10.13.13.115</strong></div>
            <div class="pill">DB: <strong><?php echo DB_NAME; ?></strong></div>
            <div class="pill">Container: <strong>mysql-local</strong></div>
        </div>
    </header>

    <div class="section-header">
        <h2>Matérias &amp; Projetos de Estudo</h2>
        <button type="button" class="btn btn-primary" onclick="toggleNovaMateria()">+ Nova Matéria / Pasta</button>
    </div>

    <!-- Formulário para criar nova pasta de matéria pelo navegador -->
    <form class="new-module-bar" id="newModuleBar" onsubmit="criarNovaMateria(event)">
        <input type="text" id="inputFolderName" class="input" style="flex: 1; min-width: 240px;" placeholder="Nome da matéria (ex: Design Patterns, API REST, SOLID)" required>
        <button type="submit" class="btn btn-success">Criar Pasta com Mini-IDE</button>
        <button type="button" class="btn" onclick="toggleNovaMateria()">Cancelar</button>
    </form>

    <section class="grid-modules">
        <?php if (count($modulos) > 0): ?>
            <?php foreach ($modulos as $mod): ?>
                <?php
                    $folderKey = $mod['folder'];
                    $tag = isset($metaCatalog[$folderKey]) ? $metaCatalog[$folderKey]['tag'] : 'Módulo de Estudo';
                    $desc = isset($metaCatalog[$folderKey]) ? $metaCatalog[$folderKey]['desc'] : 'Diretório de exercícios, testes práticos e editor de código integrado.';
                    $icon = isset($metaCatalog[$folderKey]) ? $metaCatalog[$folderKey]['icon'] : strtoupper(substr($folderKey, 0, 3));
                ?>
                <a href="<?php echo htmlspecialchars($mod['url']); ?>" class="card-module">
                    <div>
                        <div class="card-top">
                            <div class="module-icon"><?php echo htmlspecialchars($icon); ?></div>
                            <span class="module-tag"><?php echo htmlspecialchars($tag); ?></span>
                        </div>
                        <h3><?php echo htmlspecialchars($mod['title']); ?></h3>
                        <p><?php echo htmlspecialchars($desc); ?></p>
                    </div>
                    <div class="card-footer">
                        <span><?php echo $mod['count']; ?> experimento(s)</span>
                        <span class="cta">Abrir pasta &rarr;</span>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="card-module">
                <h3>Nenhuma pasta de matéria encontrada</h3>
                <p>Clique em <strong>+ Nova Matéria / Pasta</strong> acima para criar seu primeiro módulo.</p>
            </div>
        <?php endif; ?>
    </section>

    <section class="db-panel">
        <div class="db-header">
            <div>
                <h2 style="font-size: 1.15rem;">Laboratório de Conexão MySQL (Docker)</h2>
                <p style="font-size: 0.88rem; color: var(--text-secondary);">
                    Teste direto via PDO (PHP 5.6) apontando para <code><?php echo DB_HOST . ':' . DB_PORT; ?></code>
                </p>
            </div>
            <div id="dbStatusBadge" class="status-badge">
                <span class="dot"></span>
                <span id="dbStatusText">Verificando conexão...</span>
            </div>
        </div>

        <div class="db-controls">
            <button type="button" class="btn" onclick="checkDbStatus()">Testar Conexão</button>
            <button type="button" class="btn" onclick="initTestTable()">1. Criar Tabela Teste (logs_estudo)</button>
            <button type="button" class="btn" onclick="loadRecords()">Atualizar Registros</button>
        </div>

        <form class="test-form" onsubmit="insertTestRecord(event)">
            <input type="text" id="inputModulo" class="input" placeholder="Matéria (ex: POO)" value="POO" required>
            <input type="text" id="inputDescricao" class="input" placeholder="Descrição do teste (ex: Teste de Herança e PDO)" required>
            <button type="submit" class="btn btn-primary">2. Gravar no Banco</button>
        </form>

        <div id="consoleOutput" class="console-feedback">Aguardando teste de conexão com o banco de dados...</div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th style="width: 70px;">ID</th>
                        <th style="width: 150px;">Matéria</th>
                        <th>Descrição do Teste</th>
                        <th style="width: 190px;">Data / Hora</th>
                    </tr>
                </thead>
                <tbody id="recordsTableBody">
                    <tr>
                        <td colspan="4" class="empty-state">Nenhum registro carregado ainda.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</div>

<script>
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

        apiRequest('create_module', formData, function (res) {
            if (res.success) {
                window.location.reload();
            } else {
                alert(res.message);
            }
        });
    }

    function apiRequest(action, formData, callback) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'index.php?ajax_action=' + encodeURIComponent(action), true);

        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4) {
                var res;
                try {
                    res = JSON.parse(xhr.responseText);
                } catch (e) {
                    res = {
                        success: false,
                        message: 'Resposta inválida do servidor: ' + xhr.responseText
                    };
                }
                callback(res);
            }
        };

        xhr.send(formData || null);
    }

    function updateConsole(text, isError) {
        var box = document.getElementById('consoleOutput');
        box.textContent = text;
        box.style.borderColor = isError ? '#ef4444' : '#232e47';
        box.style.color = isError ? '#fca5a5' : '#94a3b8';
    }

    function checkDbStatus() {
        var badge = document.getElementById('dbStatusBadge');
        var statusText = document.getElementById('dbStatusText');

        apiRequest('status', null, function (res) {
            if (res.success) {
                badge.className = 'status-badge online';
                statusText.textContent = 'MySQL Online (' + res.data.version + ')';
                updateConsole('[OK] ' + res.message + ' | Banco ativo: ' + res.data.db_name + ' | Hora servidor: ' + res.data.server_time, false);
                loadRecords();
            } else {
                badge.className = 'status-badge offline';
                statusText.textContent = 'Falha na Conexão';
                updateConsole('[ERRO] ' + res.message, true);
            }
        });
    }

    function initTestTable() {
        apiRequest('init_table', null, function (res) {
            updateConsole((res.success ? '[OK] ' : '[ERRO] ') + res.message, !res.success);
            if (res.success) {
                loadRecords();
            }
        });
    }

    function insertTestRecord(event) {
        event.preventDefault();
        var modulo = document.getElementById('inputModulo').value;
        var descricao = document.getElementById('inputDescricao').value;

        var formData = new FormData();
        formData.append('modulo', modulo);
        formData.append('descricao', descricao);

        apiRequest('insert_test', formData, function (res) {
            updateConsole((res.success ? '[OK] ' : '[ERRO] ') + res.message, !res.success);
            if (res.success) {
                document.getElementById('inputDescricao').value = '';
                loadRecords();
            }
        });
    }

    function loadRecords() {
        apiRequest('list_records', null, function (res) {
            var tbody = document.getElementById('recordsTableBody');
            if (!res.success) {
                tbody.innerHTML = '<tr><td colspan="4" class="empty-state">' + res.message + '</td></tr>';
                return;
            }

            if (!res.data || res.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="empty-state">' + (res.message || 'Nenhum registro encontrado na tabela logs_estudo.') + '</td></tr>';
                return;
            }

            var html = '';
            for (var i = 0; i < res.data.length; i++) {
                var row = res.data[i];
                html += '<tr>' +
                    '<td>#' + row.id + '</td>' +
                    '<td><strong>' + escapeHtml(row.modulo) + '</strong></td>' +
                    '<td>' + escapeHtml(row.descricao) + '</td>' +
                    '<td>' + escapeHtml(row.criado_em) + '</td>' +
                '</tr>';
            }
            tbody.innerHTML = html;
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    window.addEventListener('DOMContentLoaded', checkDbStatus);
</script>

</body>
</html>
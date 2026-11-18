<?php
// Desc: Exercício 05 - Padrão Repository + Entidade POO persistindo no MySQL Docker via PDO
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/db.php';

// 1. Camada de Domínio (Entidade pura, não sabe o que é SQL)
class TarefaEstudo {
    private $id;
    private $titulo;
    private $concluida;

    public function __construct($titulo, $concluida = false, $id = null) {
        $this->titulo = $titulo;
        $this->concluida = (bool) $concluida;
        $this->id = $id;
    }

    public function getId() { return $this->id; }
    public function setId($id) { $this->id = (int) $id; }
    public function getTitulo() { return $this->titulo; }
    public function isConcluida() { return $this->concluida; }

    public function marcarComoConcluida() {
        $this->concluida = true;
    }
}

// 2. Camada de Infraestrutura / Persistência (Isola todo o SQL e PDO)
class TarefaRepository {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function inicializarTabela() {
        $sql = "CREATE TABLE IF NOT EXISTS poo_tarefas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            titulo VARCHAR(150) NOT NULL,
            concluida TINYINT(1) NOT NULL DEFAULT 0,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sql);
    }

    public function salvar(TarefaEstudo $tarefa) {
        if ($tarefa->getId() === null) {
            $stmt = $this->pdo->prepare("INSERT INTO poo_tarefas (titulo, concluida) VALUES (:titulo, :concluida)");
            $stmt->execute(array(
                ':titulo'    => $tarefa->getTitulo(),
                ':concluida' => $tarefa->isConcluida() ? 1 : 0
            ));
            $tarefa->setId($this->pdo->lastInsertId());
        } else {
            $stmt = $this->pdo->prepare("UPDATE poo_tarefas SET titulo = :titulo, concluida = :concluida WHERE id = :id");
            $stmt->execute(array(
                ':titulo'    => $tarefa->getTitulo(),
                ':concluida' => $tarefa->isConcluida() ? 1 : 0,
                ':id'        => $tarefa->getId()
            ));
        }
        return $tarefa;
    }

    public function listarTodas() {
        $stmt = $this->pdo->query("SELECT id, titulo, concluida FROM poo_tarefas ORDER BY id DESC LIMIT 10");
        $tarefas = array();
        while ($row = $stmt->fetch()) {
            $tarefas[] = new TarefaEstudo($row['titulo'], (bool) $row['concluida'], (int) $row['id']);
        }
        return $tarefas;
    }
}

// 3. Execução / Fluxo da Aplicação
echo "<pre style='font-family:monospace;line-height:1.6;'>";
try {
    $pdo = getDbConnection();
    $repo = new TarefaRepository($pdo);
    $repo->inicializarTabela();

    // Cria e salva uma nova tarefa
    $novaTarefa = new TarefaEstudo('Dominar Repository Pattern em PHP 5.6 - ' . date('H:i:s'));
    $repo->salvar($novaTarefa);
    echo "[INSERT] Tarefa criada com ID #" . $novaTarefa->getId() . "\n";

    // Atualiza o estado do objeto e persiste no banco
    $novaTarefa->marcarComoConcluida();
    $repo->salvar($novaTarefa);
    echo "[UPDATE] Tarefa #" . $novaTarefa->getId() . " marcada como concluída.\n\n";

    echo "=== ÚLTIMAS TAREFAS NO BANCO (poo_tarefas) ===\n";
    foreach ($repo->listarTodas() as $t) {
        $status = $t->isConcluida() ? '[X]' : '[ ]';
        echo sprintf("#%d %s %s\n", $t->getId(), $status, $t->getTitulo());
    }
} catch (Exception $e) {
    echo "[ERRO] " . $e->getMessage();
}
echo "</pre>";
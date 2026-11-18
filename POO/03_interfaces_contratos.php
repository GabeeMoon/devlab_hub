<?php
// Desc: Exercício 03 - Interfaces e contratos aplicados a entidades distintas
error_reporting(E_ALL);
ini_set('display_errors', 1);

interface ExportavelInterface {
    public function toArray();
    public function toJson();
}

class UsuarioSistema implements ExportavelInterface {
    private $id;
    private $nome;
    private $perfil;

    public function __construct($id, $nome, $perfil) {
        $this->id = $id;
        $this->nome = $nome;
        $this->perfil = $perfil;
    }

    public function toArray() {
        return array(
            'tipo'   => 'usuario',
            'id'     => $this->id,
            'nome'   => $this->nome,
            'perfil' => $this->perfil
        );
    }

    public function toJson() {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}

class FaturaServico implements ExportavelInterface {
    private $codigo;
    private $valor;
    private $pago;

    public function __construct($codigo, $valor, $pago) {
        $this->codigo = $codigo;
        $this->valor = (float) $valor;
        $this->pago = (bool) $pago;
    }

    public function toArray() {
        return array(
            'tipo'   => 'fatura',
            'codigo' => $this->codigo,
            'valor'  => $this->valor,
            'status' => $this->pago ? 'PAGO' : 'PENDENTE'
        );
    }

    public function toJson() {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}

// Função desacoplada: aceita QUALQUER objeto que assine ExportavelInterface
function renderizarPayloadApi(ExportavelInterface $entidade) {
    echo $entidade->toJson() . "\n\n";
}

$usuario = new UsuarioSistema(101, 'Gabriel', 'Engenheiro Backend');
$fatura  = new FaturaServico('FAT-2026-09', 450.00, true);

echo "<pre style='font-family:monospace;line-height:1.6;'>";
renderizarPayloadApi($usuario);
renderizarPayloadApi($fatura);
echo "</pre>";
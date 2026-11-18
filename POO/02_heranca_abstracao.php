<?php
// Desc: Exercício 02 - Classe abstrata, herança com extends, protected e parent::__construct
error_reporting(E_ALL);
ini_set('display_errors', 1);

abstract class ItemCatalogo {
    protected $titulo;
    protected $precoBase;

    public function __construct($titulo, $precoBase) {
        $this->titulo = $titulo;
        $this->precoBase = (float) $precoBase;
    }

    public function getTitulo() {
        return $this->titulo;
    }

    // Toda classe filha é obrigada a implementar este método
    abstract public function calcularPrecoFinal();
    abstract public function getDetalhesEntrega();
}

class ProdutoFisico extends ItemCatalogo {
    private $pesoKg;

    public function __construct($titulo, $precoBase, $pesoKg) {
        parent::__construct($titulo, $precoBase);
        $this->pesoKg = (float) $pesoKg;
    }

    public function calcularPrecoFinal() {
        $frete = $this->pesoKg * 12.50; // R$ 12,50 por kg
        return $this->precoBase + $frete;
    }

    public function getDetalhesEntrega() {
        return "Envio via transportadora ({$this->pesoKg} kg)";
    }
}

class CursoDigital extends ItemCatalogo {
    private $urlAcesso;

    public function __construct($titulo, $precoBase, $urlAcesso) {
        parent::__construct($titulo, $precoBase);
        $this->urlAcesso = $urlAcesso;
    }

    public function calcularPrecoFinal() {
        return $this->precoBase; // Sem frete
    }

    public function getDetalhesEntrega() {
        return "Acesso imediato em: {$this->urlAcesso}";
    }
}

$itens = array(
    new ProdutoFisico('Monitor 27" IPS', 1200.00, 4.2),
    new CursoDigital('Arquitetura PHP & Docker', 197.00, 'https://devlab.local/curso-php')
);

echo "<pre style='font-family:monospace;line-height:1.6;'>";
foreach ($itens as $item) {
    echo "Item: " . $item->getTitulo() . "\n";
    echo "Entrega: " . $item->getDetalhesEntrega() . "\n";
    echo "Total: R$ " . number_format($item->calcularPrecoFinal(), 2, ',', '.') . "\n";
    echo str_repeat('-', 50) . "\n";
}
echo "</pre>";
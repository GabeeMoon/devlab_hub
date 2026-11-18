<?php
// Desc: Exercício 01 - Classes, visibilidade private/public, getters/setters e validação de estado
error_reporting(E_ALL);
ini_set('display_errors', 1);

class Produto {
    private $nome;
    private $preco;
    private $estoque;

    public function __construct($nome, $preco, $estoque) {
        $this->setNome($nome);
        $this->setPreco($preco);
        $this->setEstoque($estoque);
    }

    public function setNome($nome) {
        if (trim($nome) === '') {
            throw new InvalidArgumentException('O nome do produto não pode ser vazio.');
        }
        $this->nome = trim($nome);
    }

    public function setPreco($preco) {
        if (!is_numeric($preco) || $preco <= 0) {
            throw new InvalidArgumentException('O preço deve ser maior que zero.');
        }
        $this->preco = (float) $preco;
    }

    public function setEstoque($estoque) {
        if (!is_int($estoque) || $estoque < 0) {
            throw new InvalidArgumentException('O estoque deve ser um número inteiro não negativo.');
        }
        $this->estoque = $estoque;
    }

    public function vender($quantidade) {
        if ($quantidade <= 0) {
            throw new InvalidArgumentException('Quantidade de venda inválida.');
        }
        if ($quantidade > $this->estoque) {
            throw new RuntimeException("Estoque insuficiente para '{$this->nome}'. Disponível: {$this->estoque}.");
        }
        $this->estoque -= $quantidade;
    }

    public function getResumo() {
        return sprintf(
            'Produto: %s | Preço: R$ %s | Estoque: %d un.',
            $this->nome,
            number_format($this->preco, 2, ',', '.'),
            $this->estoque
        );
    }
}

echo "<pre style='font-family:monospace;line-height:1.6;'>";
try {
    $teclado = new Produto('Teclado Mecânico', 349.90, 10);
    echo $teclado->getResumo() . "\n";

    $teclado->vender(3);
    echo "Após venda de 3 unidades -> " . $teclado->getResumo() . "\n\n";

    // Forçando erro de encapsulamento para testar a proteção da classe
    echo "Tentando vender 20 unidades...\n";
    $teclado->vender(20);
} catch (Exception $e) {
    echo "[EXCEÇÃO CAPTURADA] " . $e->getMessage() . "\n";
}
echo "</pre>";
<?php
// Desc: Exercício 04 - Polimorfismo na prática eliminando condicionais com Strategy Pattern
error_reporting(E_ALL);
ini_set('display_errors', 1);

interface MetodoPagamentoInterface {
    public function getNome();
    public function calcularValorFinal($valorOriginal);
    public function processar($valorOriginal);
}

class PagamentoPix implements MetodoPagamentoInterface {
    public function getNome() {
        return 'PIX Instantâneo (10% OFF)';
    }

    public function calcularValorFinal($valorOriginal) {
        return $valorOriginal * 0.90;
    }

    public function processar($valorOriginal) {
        $final = $this->calcularValorFinal($valorOriginal);
        return "QR Code gerado. Valor liquidado via PIX: R$ " . number_format($final, 2, ',', '.');
    }
}

class PagamentoCartaoCredito implements MetodoPagamentoInterface {
    private $parcelas;

    public function __construct($parcelas) {
        $this->parcelas = (int) $parcelas;
    }

    public function getNome() {
        return "Cartão de Crédito ({$this->parcelas}x)";
    }

    public function calcularValorFinal($valorOriginal) {
        // Acima de 3x aplica 5% de acréscimo
        return ($this->parcelas > 3) ? $valorOriginal * 1.05 : $valorOriginal;
    }

    public function processar($valorOriginal) {
        $final = $this->calcularValorFinal($valorOriginal);
        $valorParcela = $final / $this->parcelas;
        return sprintf(
            "Transação aprovada em %dx de R$ %s (Total: R$ %s)",
            $this->parcelas,
            number_format($valorParcela, 2, ',', '.'),
            number_format($final, 2, ',', '.')
        );
    }
}

class CheckoutService {
    private $metodoPagamento;

    // Injeção de dependência pela interface, não pela classe concreta
    public function __construct(MetodoPagamentoInterface $metodoPagamento) {
        $this->metodoPagamento = $metodoPagamento;
    }

    public function finalizarCompra($valorCarrinho) {
        echo "Método selecionado: " . $this->metodoPagamento->getNome() . "\n";
        echo "Resultado: " . $this->metodoPagamento->processar($valorCarrinho) . "\n";
        echo str_repeat('-', 55) . "\n";
    }
}

$valorCompra = 500.00;

echo "<pre style='font-family:monospace;line-height:1.6;'>";
$checkoutPix = new CheckoutService(new PagamentoPix());
$checkoutPix->finalizarCompra($valorCompra);

$checkoutCartao = new CheckoutService(new PagamentoCartaoCredito(5));
$checkoutCartao->finalizarCompra($valorCompra);
echo "</pre>";
<?php
require_once __DIR__ . "/../config/database.php";

// Forca o navegador a fazer o download do arquivo em formato de texto puro
header("Content-Type: text/plain; charset=utf-8");
header("Content-Disposition: attachment; filename=relatorio_vendas_concluidas_" . date('Ymd_His') . ".txt");

// Consulta SQL ajustada com o filtro WHERE para trazer estritamente pedidos com status Finalizado
$sql = "
    SELECT p.id AS pedido_id, c.nome AS nome_cliente, p.data AS data_pedido, p.status AS status_pedido,
           GROUP_CONCAT(CONCAT(pr.nome, ' (', pi.quantidade, ' un)') SEPARATOR ', ') AS produtos_detalhe
    FROM pedidos p
    JOIN clientes c ON p.cliente_id = c.id
    JOIN pedido_itens pi ON pi.pedido_id = p.id
    JOIN produtos pr ON pi.produto_id = pr.id
    WHERE p.status = 'Finalizado'
    GROUP BY p.id 
    ORDER BY p.id DESC
";

try {
    $pedidos = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    // Escreve o cabecalho do relatorio de texto
    echo "==================================================\n";
    echo "       RELATORIO DE VENDAS CONCLUIDAS (CAIXA)     \n";
    echo "         Gerado em: " . date('d/m/Y H:i:s') . "  \n";
    echo "==================================================\n\n";

    if (!empty($pedidos)) {
        foreach ($pedidos as $p) {
            $data = !empty($p["data_pedido"]) ? date('d/m/Y H:i', strtotime($p["data_pedido"])) : "Nao registrada";
            
            echo "Pedido: #" . $p["pedido_id"] . "\n";
            echo "Cliente: " . $p["nome_cliente"] . "\n";
            echo "Itens: " . $p["produtos_detalhe"] . "\n";
            echo "Data: " . $data . "\n";
            echo "Situacao: [" . $p["status_pedido"] . "]\n";
            echo "--------------------------------------------------\n";
        }
    } else {
        echo "Nenhuma encomenda concluida ou finalizada no sistema ate o momento.\n";
    }
} catch (Exception $e) {
    echo "Erro ao gerar relatorio tecnico: " . $e->getMessage();
}
exit;
?>

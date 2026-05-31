<?php
require_once __DIR__ . "/../config/database.php";

$mensagem = "";
$tipoMensagem = "sucesso";
$linkWhatsAltera = "";

// REQUISITO FIXO DA PADARIA: Status imutáveis definidos direto na regra de negócio
$statusDisponiveis = ['Pendente', 'Em Preparo', 'Pronto para Entrega', 'Finalizado', 'Cancelado'];

// Captura o status selecionado pelo usuario no filtro
$filtroStatus = trim($_GET["filtro_status"] ?? "");

# =====================================
# GATILHO: ALTERAR STATUS DO PEDIDO
# =====================================
if (isset($_GET["alterar_status"]) && isset($_GET["novo_status"])) {
    $pedido_id = (int)$_GET["alterar_status"];
    $novo_status = trim($_GET["novo_status"]);

    if ($pedido_id > 0 && in_array($novo_status, $statusDisponiveis)) {
        try {
            $pdo->beginTransaction();

            $stmtUpdate = $pdo->prepare("UPDATE pedidos SET status = ? WHERE id = ?");
            $stmtUpdate->execute([$novo_status, $pedido_id]);

            if ($novo_status === "Finalizado") {
                // 1. Busca o ID do cliente dono deste pedido de forma isolada
                $stmtCli = $pdo->prepare("SELECT cliente_id FROM pedidos WHERE id = ?");
                $stmtCli->execute([$pedido_id]);
                $cliente_id = (int)$stmtCli->fetchColumn();

                if ($cliente_id > 0) {
                    // 2. SOMA DIRETA: Busca a quantidade real de itens somando direto na tabela de vinculo
                    $stmtQtd = $pdo->prepare("SELECT SUM(quantidade) FROM pedido_itens WHERE pedido_id = ?");
                    $stmtQtd->execute([$pedido_id]);
                    $quantidadeVendida = (int)$stmtQtd->fetchColumn();

                    // 3. Captura o saldo residual que o cliente ja tinha acumulado
                    $stmtSaldo = $pdo->prepare("SELECT saldo_itens FROM clientes WHERE id = ?");
                    $stmtSaldo->execute([$cliente_id]);
                    $saldoAtual = (int)$stmtSaldo->fetchColumn();

                    // 4. Executa a matematica exata de divisao e resto de fidelidade
                    $totalAcumulado = $saldoAtual + $quantidadeVendida;
                    $bonusGerados = intdiv($totalAcumulado, 10); // A cada 10 vira 1 bonus inteiro
                    $novoSaldoRestante = $totalAcumulado % 10;   // O resto continua na conta do cliente

                    // 5. Atualiza o saldo de itens do cliente de forma persistente
                    $stmtUpSaldo = $pdo->prepare("UPDATE clientes SET saldo_itens = ? WHERE id = ?");
                    $stmtUpSaldo->execute([$novoSaldoRestante, $cliente_id]);

                    // 6. Injeta os registros de cupons de direito na tabela de bonus
                    if ($bonusGerados > 0) {
                        $stmtBonus = $pdo->prepare("INSERT INTO bonus (cliente_id, descricao, data) VALUES (?, ?, NOW())");
                        for ($i = 0; $i < $bonusGerados; $i++) {
                            $stmtBonus->execute([$cliente_id, "Bônus por completar 10 produtos"]);
                        }
                    }
                }
            }

            $stmtWhats = $pdo->prepare("
                SELECT c.nome, c.telefone, pr.nome AS nome_produto, pi.quantidade
                FROM pedidos p 
                JOIN clientes c ON p.cliente_id = c.id 
                JOIN pedido_itens pi ON pi.pedido_id = p.id
                JOIN produtos pr ON pi.produto_id = pr.id
                WHERE p.id = ?
                LIMIT 1
            ");
            $stmtWhats->execute([$pedido_id]);
            $clienteWhats = $stmtWhats->fetch(PDO::FETCH_ASSOC);

            if ($clienteWhats) {
                $telefoneLimpo = preg_replace('/[^0-9]/', '', $clienteWhats["telefone"]);
                
                $nomeCliente = trim($clienteWhats["nome"]);
                $nomeProduto = trim($clienteWhats["nome_produto"]);
                $qtd = (int)$clienteWhats["quantidade"];

                if ($novo_status === "Cancelado") {
                    $mensagemTxt = "Olá " . $nomeCliente . "!\n\nPassando para te avisar que o seu pedido de *" . $nomeProduto . "* (Quantidade: " . $qtd . " un.) infelizmente precisou ser *cancelado*.\n\nSe tiver qualquer dúvida ou quiser refazer a encomenda, é só nos chamar por aqui!";
                } elseif ($novo_status === "Finalizado") {
                    $mensagemTxt = "Olá " . $nomeCliente . "!\n\nÓtima notícia! O seu pedido de *" . $nomeProduto . "* (" . $qtd . " un.) já foi *finalizado* e está prontinho.\n\nAgradecemos muito pela preferência!";
                } else {
                    $mensagemTxt = "Olá " . $nomeCliente . "!\n\nPassando para avisar que o seu pedido de *" . $nomeProduto . "* (" . $qtd . " un.) já mudou de situação e agora está: *" . $novo_status . "*.\n\nEstamos cuidando de tudo com muito carinho!";
                }
                
                $linkWhatsAltera = "https:" . DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR . "wa.me" . DIRECTORY_SEPARATOR . trim($telefoneLimpo) . "?text=" . urlencode($mensagemTxt);
            }

            $mensagem = "Situação do pedido #" . $pedido_id . " atualizada com sucesso.";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $mensagem = "Erro ao alterar status: " . $e->getMessage();
            $tipoMensagem = "erro";
        }
    }
}

# =====================================
# SELEÇÃO DOS PEDIDOS NA TABELA GERAL
# =====================================
$sqlPedidos = "
    SELECT p.id AS pedido_id, c.nome AS nome_cliente, p.data AS data_pedido, p.status AS status_pedido, SUM(pi.quantidade) AS total_produtos
    FROM pedidos p
    JOIN clientes c ON p.cliente_id = c.id
    JOIN pedido_itens pi ON pi.pedido_id = p.id
    WHERE 1=1
";

$params = [];
if ($filtroStatus !== "") {
    $sqlPedidos .= " AND p.status = ? ";
    $params[] = $filtroStatus;
}
$sqlPedidos .= " GROUP BY p.id ORDER BY p.id DESC ";

$stmt = $pdo->prepare($sqlPedidos);
$stmt->execute($params);
$listaPedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalPedidosExibidos = count($listaPedidos);
$totalItensVendidos = array_sum(array_column($listaPedidos, 'total_produtos'));
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizar Pedidos - Sistema Loja</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f4f6f9; color: #333; display: flex; overflow-x: hidden; max-width: 100%; }
        
        .sidebar { width: 250px; height: 100vh; background-color: #2c3e50; color: white; padding: 20px; position: fixed; }
        .sidebar h2 { text-align: center; margin-bottom: 30px; font-size: 22px; letter-spacing: 1px; }
        .sidebar ul { list-style: none; }
        .sidebar ul li { margin-bottom: 15px; }
        .sidebar ul li a { color: #ecf0f1; text-decoration: none; display: block; padding: 12px; border-radius: 5px; transition: 0.3s; }
        .sidebar ul li a:hover { background-color: #34495e; padding-left: 20px; }
        
        .main-content { margin-left: 250px; padding: 40px; width: calc(100% - 250px); }
        .header { margin-bottom: 30px; border-bottom: 2px solid #e0e0e0; padding-bottom: 15px; }
        .header h1 { color: #2c3e50; }
        
        .card-panel { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .card-panel h3 { color: #2c3e50; margin-bottom: 20px; font-size: 20px; border-left: 4px solid #3498db; padding-left: 10px; }
        
        label { margin-bottom: 8px; font-weight: 600; color: #34495e; font-size: 15px; }
        select { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 5px; font-size: 15px; background: white; }
        
        .btn { padding: 12px 25px; border: none; border-radius: 5px; cursor: pointer; font-size: 15px; font-weight: 600; transition: 0.3s; display: inline-block; text-decoration: none; text-align: center; }
        .btn-primary { background: #3498db; color: white; }
        .btn-primary:hover { background: #2980b9; }
        .btn-success-whats { background: #2ecc71; color: white; width: 100%; text-decoration: none; margin-bottom: 20px; font-weight: 600; }
        .btn-success-whats:hover { background: #27ae60; }
        
        .badge-status { font-weight: bold; padding: 6px 12px; border-radius: 4px; font-size: 13px; display: inline-block; background: #e0e0e0; color: #333; }
        .select-acao { padding: 10px 12px; font-size: 14px; border: 1px solid #ced4da; border-radius: 5px; background-color: #fff; cursor: pointer; width: 100%; min-width: 160px; color: #495057; }
        
        .status-Finalizado { background: #e2fbe8; color: #1e7e34; }
        .status-Cancelado { background: #f8d7da; color: #721c24; }
        .status-Em-Preparo { background: #fff3cd; color: #856404; }
        .status-Pronto-para-Entrega { background: #d1ecf1; color: #0c5460; }
        .status-Pendente { background: #e2e3e5; color: #383d41; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; background: white; }
        table th, table td { padding: 14px; text-align: left; border-bottom: 1px solid #e0e0e0; font-size: 15px; }
        table th { background: #f8f9fa; color: #34495e; font-weight: 600; }
        table tr:hover { background-color: #fcfcfc; }
        .sem-dados { text-align: center; padding: 20px; color: #7f8c8d; font-style: italic; }
        .mensagem { padding: 15px; border-radius: 5px; margin-bottom: 25px; border-left: 5px solid #2ecc71; font-weight: 500; background: #d4edda; color: #155724; }
    </style>
</head>
<body>

    <!-- Menu Lateral de Navegacao Unificado -->
    <nav class="sidebar">
        <h2>Gerenciamento</h2>
        <ul>
            <!-- Modulo de Clientes -->
            <li style="padding-top: 10px; font-weight: bold; color: #a6b8c7; font-size: 12px; text-transform: uppercase; list-style: none; margin-bottom: 5px;">Clientes</li>
            <li><a href="http://localhost:8000/public/clientes.php">Gerenciar Clientes</a></li>
            <li><a href="http://localhost:8000/public/historico_cliente.php">Historico de Clientes</a></li>
            
            <!-- Modulo de Produtos -->
            <li style="padding-top: 10px; font-weight: bold; color: #a6b8c7; font-size: 12px; text-transform: uppercase; list-style: none; margin-bottom: 5px;">Produtos</li>
            <li><a href="http://localhost:8000/public/cadastrar_produto.php">Cadastrar Produto</a></li>
            <li><a href="http://localhost:8000/public/visualizar_produtos.php">Visualizar Produtos</a></li>
            
            <!-- Modulo de Pedidos e Vendas -->
            <li style="padding-top: 10px; font-weight: bold; color: #a6b8c7; font-size: 12px; text-transform: uppercase; list-style: none; margin-bottom: 5px;">Pedidos</li>
            <li><a href="http://localhost:8000/public/criar_pedido.php">Criar Pedido</a></li>
            <li><a href="http://localhost:8000/public/visualizar_pedidos.php">Visualizar Pedidos</a></li>
         <li><a href="http://localhost:8000/public/visualizar_bonus.php">Visualizar Bônus</a></li>

        </ul>
    </nav>

    <!-- Area de Conteudo Principal -->
    <main class="main-content">
        <div class="header">
            <h1>Controle de Encomendas</h1>
            <p>Monitore os pedidos e faca a gestao do fluxo de entrega de forma dinamica.</p>
        </div>

        <!-- Alerta de Feedback Posicionado no Vao Central -->
        <?php if (!empty($mensagem)): ?>
            <div class="mensagem" style="margin-bottom: 25px; <?= $tipoMensagem === 'erro' ? 'background: #f8d7da; color: #721c24; border-left-color: #dc3545;' : '' ?>">
                <?= htmlspecialchars($mensagem) ?>
            </div>
        <?php endif; ?>

        <!-- Botao Dinamico para Notificacao de Alteracao de Status -->
        <?php if (!empty($linkWhatsAltera)): ?>
            <div style="margin-bottom: 30px;">
                <a href="<?= $linkWhatsAltera ?>" target="_blank" class="btn btn-success-whats">
                    Enviar Notificacao de Alteracao via WhatsApp Web
                </a>
            </div>
        <?php endif; ?>

        <div class="card-panel">
            <h3>Filtros de Encomendas</h3>
            <form method="GET" style="display: flex; gap: 10px; align-items: flex-end; max-width: 400px;">
                <div class="form-group" style="flex: 1; display: flex; flex-direction: column;">
                    <label for="filtro_status">Filtrar por Situação:</label>
                    <select name="filtro_status" id="filtro_status">
                        <option value="">-- Todos os pedidos registrados --</option>
                        <?php foreach ($statusDisponiveis as $stNome): ?>
                            <option value="<?= htmlspecialchars($stNome) ?>" <?= $filtroStatus === $stNome ? 'selected' : '' ?>>
                                <?= htmlspecialchars($stNome) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Filtrar</button>
            </form>
        </div>

        <div class="card-panel">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
                <h3 style="margin-bottom: 0;">Situação do pedido</h3>
                <a href="http://localhost:8000/public/exportar_relatorio.php" class="btn" style="background-color: #2ecc71; color: white; text-decoration: none; font-weight: 600; padding: 10px 20px; font-size: 14px; border-radius: 5px;">
                    Baixar Relatório (.TXT)
                </a>
            </div>

            <!-- Totalizadores Gerenciais Dinâmicos -->
            <div style="display: flex; gap: 15px; margin-bottom: 25px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 180px; background: #f8f9fa; padding: 15px; border-radius: 6px; border-left: 4px solid #3498db;">
                    <span style="font-size: 12px; color: #7f8c8d; text-transform: uppercase; font-weight: 600; display: block; margin-bottom: 5px;">Pedidos Listados</span>
                    <strong style="font-size: 20px; color: #2c3e50;"><?= $totalPedidosExibidos ?> registros</strong>
                </div>
                
                <div style="flex: 1; min-width: 180px; background: #f8f9fa; padding: 15px; border-radius: 6px; border-left: 4px solid #e67e22;">
                    <span style="font-size: 12px; color: #7f8c8d; text-transform: uppercase; font-weight: 600; display: block; margin-bottom: 5px;">Volume de Itens</span>
                    <strong style="font-size: 20px; color: #2c3e50;"><?= $totalItensVendidos ?> un.</strong>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th width="12%">Pedido</th>
                        <th width="30%">Cliente / Comprador</th>
                        <th width="23%">Data do Pedido</th>
                        <th width="15%">Qtd. Itens</th>
                        <th width="20%">Situação Atual</th>
                        <th width="20%">Alterar Situação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (is_array($listaPedidos) && !empty($listaPedidos)): ?>
                        <?php foreach ($listaPedidos as $p): ?>
                            <tr>
                                <td><strong>#<?= $p["pedido_id"] ?></strong></td>
                                <td><?= htmlspecialchars($p["nome_cliente"] ?? "") ?></td>
                                <td><?= !empty($p["data_pedido"]) ? date('d/m/Y H:i', strtotime($p["data_pedido"])) : '<span style="color:#bbb;">Não salva</span>' ?></td>
                                <td><?= (int)$p["total_produtos"] ?> un.</td>
                                <td>
                                    <?php $classeStatus = str_replace(' ', '-', $p["status_pedido"]); ?>
                                    <span class="badge-status status-<?= $classeStatus ?>">
                                        <?= htmlspecialchars($p["status_pedido"]) ?>
                                    </span>
                                </td>
                                <td>
                                    <select class="select-acao" onchange="const status = encodeURIComponent(this.value); window.location.href = '?alterar_status=<?= $p['pedido_id'] ?>&filtro_status=<?= urlencode($filtroStatus) ?>&novo_status=' + status;">
                                        <?php foreach ($statusDisponiveis as $stOpcao): ?>
                                            <option value="<?= htmlspecialchars($stOpcao) ?>" <?= $p["status_pedido"] === $stOpcao ? 'selected' : '' ?>>
                                                Mudar para: <?= htmlspecialchars($stOpcao) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="sem-dados">Nenhuma encomenda registrada ou encontrada para este filtro.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

</body>
</html>

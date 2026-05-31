<?php
require_once __DIR__ . "/../config/database.php";

$mensagem = "";
$tipoMensagem = "sucesso";

/* Buscar clientes para o select principal */
$clientes = $pdo->query("SELECT id, nome FROM clientes ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

$clienteSelecionado = $_GET['cliente_id'] ?? null;
$termoBusca = trim($_GET['buscar'] ?? '');
$pedidos = [];
$bonus = [];
$totalProdutos = 0;
$faltamProdutos = 0;

# =====================================
# GATILHO: CONFIRMAR RESGATE DE BONIFICAÇÃO (RF5)
# =====================================
if (isset($_GET["resgatar_bonus"])) {
    $bonus_id = (int)$_GET["resgatar_bonus"];
    $url_cliente = (int)($_GET["cliente_id"] ?? 0);

    if ($bonus_id > 0 && $url_cliente > 0) {
        try {
            // Remove o prêmio entregue para dar baixa no sistema [RF5]
            $stmtResgate = $pdo->prepare("DELETE FROM bonus WHERE id = ? AND cliente_id = ?");
            $stmtResgate->execute([$bonus_id, $url_cliente]);

            header("Location: historico_cliente.php?cliente_id=" . $url_cliente . "&sucesso=resgatado");
            exit;
        } catch (PDOException $e) {
            header("Location: historico_cliente.php?cliente_id=" . $url_cliente . "&erro=sistema");
            exit;
        }
    }
}

# =====================================
# PROCESSAMENTO DE ALERTAS DE RESGATE
# =====================================
if (isset($_GET["sucesso"]) && $_GET["sucesso"] === "resgatado") {
    $mensagem = "🎁 Prêmio entregue com sucesso! O bônus foi debitado da conta do cliente (RF5).";
    $tipoMensagem = "sucesso";
} elseif (isset($_GET["erro"])) {
    $tipoMensagem = "erro";
    $mensagem = "Erro interno: Não foi possível processar o resgate do prêmio.";
}

# =====================================
# BUSCA DE HISTÓRICO CONSOLIDADO (GERAL OU FILTRADO)
# =====================================

// Query principal adaptada para buscar todas as vendas do sistema por padrão
$sqlPedidos = "
    SELECT p.id AS pedido_id, p.data AS data_pedido, SUM(pi.quantidade) AS total_produtos, c.nome AS nome_cliente, p.cliente_id
    FROM pedidos p
    JOIN pedido_itens pi ON pi.pedido_id = p.id
    JOIN clientes c ON p.cliente_id = c.id
    WHERE 1=1
";

$paramsPedidos = [];

if ($clienteSelecionado) {
    $sqlPedidos .= " AND p.cliente_id = ? ";
    $paramsPedidos[] = $clienteSelecionado;
}

if ($termoBusca !== "") {
    $sqlPedidos .= " AND (c.nome LIKE ? OR p.id = ?) ";
    $paramsPedidos[] = "%" . $termoBusca . "%";
    $paramsPedidos[] = (int)$termoBusca;
}

$sqlPedidos .= " GROUP BY p.id ORDER BY p.id DESC";

$stmtPedidos = $pdo->prepare($sqlPedidos);
$stmtPedidos->execute($paramsPedidos);
$pedidos = $stmtPedidos->fetchAll(PDO::FETCH_ASSOC);


// Busca informações de bônus e progresso se um comprador específico for selecionado
if ($clienteSelecionado) {
    /* Calcular total de produtos comprados */
    $sqlTotalProdutos = "
        SELECT SUM(pi.quantidade) AS total
        FROM pedidos p
        JOIN pedido_itens pi ON pi.pedido_id = p.id
        WHERE p.cliente_id = ?
    ";
    $stmtTotal = $pdo->prepare($sqlTotalProdutos);
    $stmtTotal->execute([$clienteSelecionado]);
    $resTotal = $stmtTotal->fetch(PDO::FETCH_ASSOC);
    $totalProdutos = (int)($resTotal['total'] ?? 0);

    /* Buscar bônus ainda ativos/disponíveis para resgate */
    $sqlBonus = "SELECT id, descricao, data FROM bonus WHERE cliente_id = ? ORDER BY data DESC";
    $stmtBonus = $pdo->prepare($sqlBonus);
    $stmtBonus->execute([$clienteSelecionado]);
    $bonus = $stmtBonus->fetchAll(PDO::FETCH_ASSOC);

    /* Calcular quanto falta para o próximo bônus dinâmico */
    $resto = $totalProdutos % 10;
    $faltamProdutos = ($resto === 0) ? 0 : (10 - $resto);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico de Vendas Geral - Sistema Loja</title>
    <style>
        /* CSS Unificado do Painel */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f4f6f9; color: #333; display: flex; overflow-x: hidden; max-width: 100%; }
        
        /* Menu Lateral (Sidebar) */
        .sidebar { width: 250px; height: 100vh; background-color: #2c3e50; color: white; padding: 20px; position: fixed; }
        .sidebar h2 { text-align: center; margin-bottom: 30px; font-size: 22px; letter-spacing: 1px; }
        .sidebar ul { list-style: none; }
        .sidebar ul li { margin-bottom: 15px; }
        .sidebar ul li a { color: #ecf0f1; text-decoration: none; display: block; padding: 12px; border-radius: 5px; transition: 0.3s; }
        .sidebar ul li a:hover { background-color: #34495e; padding-left: 20px; }
        
        /* Área de Conteúdo */
        .main-content { margin-left: 250px; padding: 40px; width: calc(100% - 250px); }
        .header { margin-bottom: 30px; border-bottom: 2px solid #e0e0e0; padding-bottom: 15px; }
        .header h1 { color: #2c3e50; }
        
        /* Blocos Card Panel */
        .card-panel { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .card-panel h3 { color: #2c3e50; margin-bottom: 20px; font-size: 20px; border-left: 4px solid #3498db; padding-left: 10px; }
        .card-panel.detalhes-bloco h3 { border-left-color: #e67e22; }
        
        /* Formulários e Seleção */
        .busca-container { display: flex; gap: 15px; margin-bottom: 10px; align-items: flex-end; flex-wrap: wrap; }
        .form-group { display: flex; flex-direction: column; flex: 1; min-width: 200px; }
        label { margin-bottom: 8px; font-weight: 600; color: #34495e; font-size: 15px; }
        select, input[type="text"] { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 5px; font-size: 15px; background: white; transition: 0.2s; }
        select:focus, input[type="text"]:focus { border-color: #3498db; outline: none; }
        
        /* Botões */
        .btn { padding: 12px 25px; border: none; border-radius: 5px; cursor: pointer; font-size: 15px; font-weight: 600; transition: 0.3s; display: inline-block; text-decoration: none; text-align: center; }
        .btn-primary { background: #3498db; color: white; }
        .btn-primary:hover { background: #2980b9; }
        .btn-secondary { background: #95a5a6; color: white; text-decoration: none; }
        .btn-secondary:hover { background: #7f8c8d; }
        .btn-resgate { background: #2ecc71; color: white; text-decoration: none; padding: 6px 12px; border-radius: 4px; font-size: 13px; font-weight: bold; float: right; margin-top: -5px; }
        .btn-resgate:hover { background: #27ae60; }
        
        /* Tabelas */
        table { width: 100%; border-collapse: collapse; margin-top: 15px; background: white; }
        table th, table td { padding: 14px; text-align: left; border-bottom: 1px solid #e0e0e0; font-size: 15px; }
        table th { background: #f8f9fa; color: #34495e; font-weight: 600; }
        table tr:hover { background-color: #fcfcfc; }
        
        /* Destaques de Informações */
        .info-box { background: #e6fffa; color: #006d5b; padding: 15px; border-radius: 6px; margin-bottom: 25px; border-left: 5px solid #2ecc71; font-weight: 600; font-size: 16px; }
        .bonus-box { background: #fff7ed; color: #c2410c; padding: 15px; border-radius: 6px; margin-bottom: 15px; border-left: 5px solid #f97316; font-size: 15px; overflow: hidden; }
        .bonus-box small { color: #7c2d12; display: block; margin-top: 4px; font-weight: 500; }
        .sem-dados { color: #7f8c8d; font-style: italic; margin-top: 10px; text-align: center; padding: 20px 0; }
        .mensagem { padding: 15px; border-radius: 5px; margin-bottom: 25px; border-left: 5px solid #2ecc71; font-weight: 500; background: #d4edda; color: #155724; }

        @media (max-width: 768px) {
            body { flex-direction: column; }
            .sidebar { width: 100%; height: auto; position: relative; }
            .main-content { margin-left: 0; width: 100%; padding: 20px; }
        }
    </style>
</head>
<body>

    <!-- Menu Lateral de Navegação Unificado -->
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
            <h1>Historico de Vendas Geral</h1>
            <p>Consulte o relatorio consolidado de todas as encomendas realizadas e filtre os resultados por cliente ou palavra-chave.</p>
        </div>

        <!-- Painel de Selecao e Busca Avancada -->
        <div class="card-panel">
            <h3>Pesquisa e Filtros</h3>
            <form method="GET">
                <div class="busca-container">
                    <div class="form-group">
                        <label for="cliente_id">Filtrar por Cliente:</label>
                        <select name="cliente_id" id="cliente_id">
                            <option value="">-- Todos os clientes --</option>
                            <?php foreach ($clientes as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= ($clienteSelecionado == $c['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="buscar">Buscar por Nome ou Codigo:</label>
                        <input type="text" name="buscar" id="buscar" placeholder="Digite o nome do cliente ou ID do pedido..." value="<?= htmlspecialchars($termoBusca) ?>">
                    </div>

                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <?php if ($clienteSelecionado || $termoBusca !== ""): ?>
                        <a href="historico_cliente.php" class="btn btn-secondary">Limpar</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Alerta de Feedback e Resgates Posicionado no Vao Central -->
        <?php if (!empty($mensagem)): ?>
            <div class="mensagem" style="margin-bottom: 30px; <?= $tipoMensagem === 'erro' ? 'background: #f8d7da; color: #721c24; border-left-color: #dc3545;' : '' ?>">
                <?= htmlspecialchars($mensagem) ?>
            </div>
        <?php endif; ?>

        <!-- Bloco de Listagem Geral de Encomendas -->
        <div class="card-panel detalhes-bloco">
            <h3>Encomendas Registradas</h3>
            <table>
                <thead>
                    <tr>
                        <th width="15%">Codigo</th>
                        <th width="35%">Cliente</th>
                        <th width="25%">Data / Hora do Pedido</th>
                        <th width="25%">Total de Produtos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($pedidos)): ?>
                        <?php foreach ($pedidos as $p): ?>
                            <tr>
                                <td><strong>#<?= $p['pedido_id'] ?></strong></td>
                                <td><?= htmlspecialchars($p['nome_cliente']) ?></td>
                                <td>
                                    <?php 
                                    if (!empty($p['data_pedido'])) {
                                        echo date('d/m/Y H:i:s', strtotime($p['data_pedido']));
                                    } else {
                                        echo '<span style="color:#bbb; font-style:italic;">Data nao registrada</span>';
                                    }
                                    ?>
                                </td>
                                <td><?= $p['total_produtos'] ?> un.</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="sem-dados">Nenhuma encomenda foi encontrada com os filtros aplicados.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

</body>
</html>

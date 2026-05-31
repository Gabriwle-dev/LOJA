<?php
require_once __DIR__ . "/../config/database.php";

$clientes = $pdo->query("SELECT id, nome, telefone FROM clientes ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$produtos = $pdo->query("SELECT id, nome FROM produtos ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$statusDisponiveis = $pdo->query("SELECT nome FROM status_pedido ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);

$msg = "";
$msgBonus = "";
$tipoMensagem = "sucesso";
$linkWhatsFinal = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $cliente_id = (int)($_POST["cliente_id"] ?? 0);
    $produto_id = (int)($_POST["produto_id"] ?? 0);
    $quantidade = (int)($_POST["quantidade"] ?? 0);
    $status_inicial = trim($_POST["status"] ?? "");

    if ($cliente_id <= 0 || $produto_id <= 0 || $quantidade <= 0 || !in_array($status_inicial, $statusDisponiveis)) {
        $msg = "Atenção: Selecione o cliente, o produto e informe uma quantidade válida.";
        $tipoMensagem = "erro";
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO pedidos (cliente_id, status) VALUES (?, ?)");
            $stmt->execute([$cliente_id, $status_inicial]);
            $pedido_id = $pdo->lastInsertId();

            $stmt = $pdo->prepare("INSERT INTO pedido_itens (pedido_id, produto_id, quantidade) VALUES (?, ?, ?)");
            $stmt->execute([$pedido_id, $produto_id, $quantidade]);

            if ($status_inicial === "Finalizado") {
                $stmt = $pdo->prepare("SELECT saldo_itens FROM clientes WHERE id = ?");
                $stmt->execute([$cliente_id]);
                $saldoAtual = (int)$stmt->fetchColumn();

                $totalAgora = $saldoAtual + $quantidade;
                $bonusGerados = intdiv($totalAgora, 10);
                $novoSaldo = $totalAgora % 10;

                $stmt = $pdo->prepare("UPDATE clientes SET saldo_itens = ? WHERE id = ?");
                $stmt->execute([$novoSaldo, $cliente_id]);

                if ($bonusGerados > 0) {
                    $stmt = $pdo->prepare("INSERT INTO bonus (cliente_id, descricao) VALUES (?, ?)");
                    for ($i = 0; $i < $bonusGerados; $i++) {
                        $stmt->execute([$cliente_id, "Bônus por completar 10 produtos"]);
                    }
                    $msgBonus = "Cliente ganhou {$bonusGerados} bônus.";
                }
            } else {
                $stmt = $pdo->prepare("SELECT saldo_itens FROM clientes WHERE id = ?");
                $stmt->execute([$cliente_id]);
                $novoSaldo = (int)$stmt->fetchColumn();
                $bonusGerados = 0;
            }

            $pdo->commit();

            $stmt = $pdo->prepare("SELECT nome, telefone FROM clientes WHERE id = ?");
            $stmt->execute([$cliente_id]);
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
            $telefoneLimpo = preg_replace('/[^0-9]/', '', $cliente["telefone"]);

            $faltam = 10 - $novoSaldo;
            $mensagemTxt = "Olá " . $cliente["nome"] . "!\n\nSeu pedido foi registrado!\nSituação Atual: " . $status_inicial . "\nQuantidade: " . $quantidade . " un.\nSaldo atual: " . $novoSaldo . " produtos.\n\n";

            if ($status_inicial === "Finalizado" && $bonusGerados > 0) {
                $mensagemTxt .= "Parabéns! Você ganhou " . $bonusGerados . " bônus!\n";
            } elseif ($status_inicial !== "Cancelado") {
                $mensagemTxt .= "Faltam apenas " . $faltam . " produtos para ganhar seu próximo bônus.\n";
            }
            $mensagemTxt .= "\nObrigado pela preferência!";

            // Geração do link direto e infalível com a barra corrigida
    $linkWhats = "https://wa.me" . trim($telefoneLimpo) . "?text=" . urlencode(trim($mensagemTxt));
            $msg = "Pedido gravado com sucesso no sistema.";

        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = "Erro no sistema: " . $e->getMessage();
            $tipoMensagem = "erro";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Pedido - Sistema Loja</title>
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
        .card-panel h3 { color: #2c3e50; margin-bottom: 20px; font-size: 20px; border-left: 4px solid #e67e22; padding-left: 10px; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .form-group { display: flex; flex-direction: column; }
        label { margin-bottom: 8px; font-weight: 600; color: #34495e; font-size: 15px; }
        select, input { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 5px; font-size: 15px; background: white; transition: 0.2s; }
        select:focus, input:focus { border-color: #e67e22; outline: none; box-shadow: 0 0 5px rgba(230, 126, 34, 0.3); }
        .btn { padding: 12px 25px; border: none; border-radius: 5px; cursor: pointer; font-size: 15px; font-weight: 600; transition: 0.3s; display: inline-block; text-decoration: none; text-align: center; }
        .btn-warning { background: #e67e22; color: white; width: 100%; }
        .btn-warning:hover { background: #d35400; }
        .btn-success-whats { background: #2ecc71; color: white; width: 100%; text-decoration: none; margin-top: 20px; }
        .btn-success-whats:hover { background: #27ae60; }
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

    <!-- Área de Conteúdo Principal -->
    <main class="main-content">
        <div class="header">
            <h1>Registrar Vendas e Pedidos</h1>
            <p>Abra uma nova encomenda no sistema vinculando o comprador aos bônus fidelidade.</p>
        </div>

        <?php if (!empty($msg)): ?>
            <div class="mensagem" style="margin-bottom: 30px; <?= $tipoMensagem === 'erro' ? 'background: #f8d7da; color: #721c24; border-left-color: #dc3545;' : '' ?>">
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($msgBonus)): ?>
            <div class="mensagem" style="margin-bottom: 30px;">
                <?= htmlspecialchars($msgBonus) ?>
            </div>
        <?php endif; ?>

        <!-- Botão de Ação Direta para Disparo Nativo (Substituindo a dependência da API) -->
        <?php if (!empty($linkWhatsFinal)): ?>
            <div style="margin-bottom: 30px;">
                <a href="<?= $linkWhatsFinal ?>" target="_blank" class="btn btn-success-whats">
                    Enviar Notificação via WhatsApp Web
                </a>
            </div>
        <?php endif; ?>

        <div class="card-panel">
            <h3>Novo Pedido por Encomenda</h3>
            
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="cliente_id">Cliente:</label>
                        <select name="cliente_id" id="cliente_id" required>
                            <option value="" disabled selected>Selecione um cliente</option>
                            <?php foreach ($clientes as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="produto_id">Produto:</label>
                        <select name="produto_id" id="produto_id" required>
                            <option value="" disabled selected>Selecione um produto</option>
                            <?php foreach ($produtos as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="quantidade">Quantidade:</label>
                        <input type="number" name="quantidade" id="quantidade" placeholder="Ex: 2" min="1" required>
                    </div>

                    <div class="form-group">
                        <label for="status">Situação Inicial:</label>
                        <select name="status" id="status" required>
                            <?php foreach ($statusDisponiveis as $nomeStatus): ?>
                                <option value="<?= htmlspecialchars($nomeStatus) ?>" <?= $nomeStatus === 'Em Preparo' ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($nomeStatus) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn btn-warning">Finalizar e Registrar Pedido</button>
            </form>
        </div>
    </main>

</body>
</html>

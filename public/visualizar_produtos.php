<?php
require_once __DIR__ . "/../config/database.php";

$mensagem = "";
$tipoMensagem = "sucesso";

# =====================================
# GATILHO: EXCLUIR PRODUTO DO CATÁLOGO
# =====================================
if (isset($_GET["excluir"])) {
    $id_excluir = (int)$_GET["excluir"];
    if ($id_excluir > 0) {
        try {
            // Regra de Integridade Referencial: impede deletar se houver pedidos vinculados
            $stmtCheck = $pdo->prepare("SELECT id FROM pedido_itens WHERE produto_id = ? LIMIT 1");
            $stmtCheck->execute([$id_excluir]);
            
            if ($stmtCheck->fetch()) {
                $mensagem = "Atenção: Não é possível excluir este produto porque ele já foi vendido em pedidos anteriores!";
                $tipoMensagem = "erro";
            } else {
                $stmtDel = $pdo->prepare("DELETE FROM produtos WHERE id = ?");
                $stmtDel->execute([$id_excluir]);
                $mensagem = "Produto removido do catálogo com sucesso.";
                $tipoMensagem = "sucesso";
            }
        } catch (Exception $e) {
            $mensagem = "Erro ao tentar excluir produto.";
            $tipoMensagem = "erro";
        }
    }
}

# =====================================
# SELEÇÃO DOS PRODUTOS ATIVOS
# =====================================
$stmt = $pdo->query("SELECT * FROM produtos ORDER BY id DESC");
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?><!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizar Produtos - Sistema Loja</title>
    <style>
        /* CSS Base Unificado do Painel */
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
        .main-content { margin-left: 250px; padding: 40px; width: calc(100% - 250px); min-height: 100vh; }
        .header { margin-bottom: 30px; border-bottom: 2px solid #e0e0e0; padding-bottom: 15px; }
        .header h1 { color: #2c3e50; }
        
        /* Box da Tabela */
        .card-panel { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .card-panel h3 { color: #2c3e50; margin-bottom: 20px; font-size: 20px; border-left: 4px solid #2ecc71; padding-left: 10px; }
        
        /* Tabelas Estilizadas */
        table { width: 100%; border-collapse: collapse; margin-top: 10px; background: white; }
        table th, table td { padding: 14px; text-align: left; border-bottom: 1px solid #e0e0e0; font-size: 15px; }
        table th { background: #f8f9fa; color: #34495e; font-weight: 600; }
        table tr:hover { background-color: #fcfcfc; }
        
        .sem-dados { text-align: center; padding: 30px; color: #7f8c8d; font-style: italic; }
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
    <div class="main-content">
        <div class="header">
            <h1>Catálogo de Produtos</h1>
        </div>

        <!-- Exibição de Alertas Estilizados -->
        <?php if (!empty($mensagem)): ?>
            <div class="mensagem" style="margin-bottom: 30px; <?= $tipoMensagem === 'erro' ? 'background: #f8d7da; color: #721c24; border-left-color: #dc3545;' : '' ?>">
                <?= htmlspecialchars($mensagem) ?>
            </div>
        <?php endif; ?>

        <div class="card-panel">
            <h3>Produtos Cadastrados</h3>
            <table>
                <thead>
                    <tr>
                        <th width="15%">Código ID</th>
                        <th width="50%">Nome do Produto</th>
                        <th width="20%">Preço Unitário</th>
                        <th width="15%">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (is_array($produtos) && !empty($produtos)): ?>
                        <?php foreach ($produtos as $produto): ?>
                            <tr>
                                <td><strong>#<?= $produto["id"] ?></strong></td>
                                <td><?= htmlspecialchars($produto["nome"] ?? "") ?></td>
                                <td>R$ <?= number_format($produto["preco"], 2, ',', '.') ?></td>
                                <td>
                                    <a class="editar" href="cadastrar_produto.php?editar=<?= $produto["id"] ?>" style="color: #3498db; text-decoration: none; font-weight: bold; margin-right: 8px;">Editar</a>
                                    |
                                    <a class="excluir" href="?excluir=<?= $produto["id"] ?>" onclick="return confirm('Deseja realmente excluir este produto?')" style="color: #e74c3c; text-decoration: none; font-weight: bold; margin-left: 8px;">Excluir</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="sem-dados">Nenhum produto cadastrado no catálogo.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>

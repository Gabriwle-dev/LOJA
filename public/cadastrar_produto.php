<?php
require_once __DIR__ . "/../config/database.php";

$mensagem = "";
$tipoMensagem = "sucesso";

// 1. LÓGICA DE BUSCA: Se a URL tiver ?editar=ID, busca os dados para preencher o formulário
$produtoEditar = null;
if (isset($_GET["editar"])) {
    $id_editar = (int)$_GET["editar"];
    if ($id_editar > 0) {
        $stmt = $pdo->prepare("SELECT * FROM produtos WHERE id = ?");
        $stmt->execute([$id_editar]);
        $produtoEditar = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// 2. PROCESSAMENTO DO FORMULÁRIO (SALVAR CADASTRO OU EDIÇÃO)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nome  = trim($_POST["nome"] ?? "");
    $preco = $_POST["preco"] ?? "";

    if ($nome === "" || !is_numeric($preco) || $preco <= 0) {
        $tipoMensagem = "erro";
        $mensagem = "Informe um nome válido e um preço maior que zero.";
    } else {
        $preco = (float) $preco;

        if (isset($_POST["salvar_edicao"])) {
            # =====================================
            # AÇÃO: ATUALIZAR PRODUTO EXISTENTE
            # =====================================
            $id = (int)($_POST["id"] ?? 0);
            
            // Valida duplicidade ignorando maiúsculas/minúsculas e o próprio ID que está sendo editado
            $sqlCheck = "SELECT id FROM produtos WHERE LOWER(nome) = LOWER(?) AND id <> ?";
            $stmtCheck = $pdo->prepare($sqlCheck);
            $stmtCheck->execute([$nome, $id]);
            
            if ($stmtCheck->fetch()) {
                $tipoMensagem = "erro";
                $mensagem = "Atenção: Já existe outro produto cadastrado com este nome!";
            } else {
                $sql = "UPDATE produtos SET nome = ?, preco = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nome, $preco, $id]);

                header("Location: visualizar_produtos.php?sucesso=editado");
                exit;
            }
        } else {
            # =====================================
            # AÇÃO: CADASTRAR NOVO PRODUTO
            # =====================================
            // Valida duplicidade de forma inteligente ignorando maiúsculas/minúsculas
            $sqlCheck = "SELECT id FROM produtos WHERE LOWER(nome) = LOWER(?)";
            $stmtCheck = $pdo->prepare($sqlCheck);
            $stmtCheck->execute([$nome]);
            
            if ($stmtCheck->fetch()) {
                $tipoMensagem = "erro";
                $mensagem = "Atenção: Já existe um produto cadastrado com este nome!";
            } else {
                $sql = "INSERT INTO produtos (nome, preco) VALUES (?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nome, $preco]);

                $mensagem = "Produto cadastrado com sucesso!";
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar Produto - Sistema Loja</title>
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
        .main-content { margin-left: 250px; padding: 40px; width: calc(100% - 250px); }
        .header { margin-bottom: 30px; border-bottom: 2px solid #e0e0e0; padding-bottom: 15px; }
        .header h1 { color: #2c3e50; }
        
        /* Box do Formulário */
        .card-panel { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .card-panel h3 { color: #2c3e50; margin-bottom: 20px; font-size: 20px; border-left: 4px solid #2ecc71; padding-left: 10px; }
        
        /* Formulários e Inputs */
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .form-group { display: flex; flex-direction: column; }
        label { margin-bottom: 8px; font-weight: 600; color: #34495e; font-size: 15px; }
        input { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 5px; font-size: 15px; background: white; transition: 0.2s; }
        input:focus { border-color: #2ecc71; outline: none; box-shadow: 0 0 5px rgba(46, 204, 113, 0.3); }
        
        /* Botões */
        .btn { padding: 12px 25px; border: none; border-radius: 5px; cursor: pointer; font-size: 15px; font-weight: 600; transition: 0.3s; display: inline-block; text-decoration: none; text-align: center; }
        .btn-success { background: #2ecc71; color: white; width: 100%; }
        .btn-success:hover { background: #27ae60; }
        
        /* Alertas no vão central */
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
            <h1><?= $produtoEditar ? "Editar Produto" : "Cadastrar Produto" ?></h1>
            <p>Cadastre novos itens e para disponibilizá-los em "vendas".</p>
        </div>

        <!-- Exibição de Alertas Estilizados Movido para o Vão Central -->
        <?php if (!empty($mensagem)): ?>
            <div class="mensagem" style="margin-bottom: 30px; <?= $tipoMensagem === 'erro' ? 'background: #f8d7da; color: #721c24; border-left-color: #dc3545;' : '' ?>">
                <?= htmlspecialchars($mensagem) ?>
            </div>
        <?php endif; ?>

        <!-- Box Envelopando o Formulário -->
        <div class="card-panel">
            <h3><?= $produtoEditar ? "Editar Dados do Produto" : "Novo Produto" ?></h3>
            
            <form method="POST">
                <!-- Injeta o ID de forma oculta apenas se a variável de edição estiver ativa -->
                <?php if ($produtoEditar): ?>
                    <input type="hidden" name="id" value="<?= $produtoEditar["id"] ?>">
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label for="nome">Nome do Produto:</label>
                        <input type="text" name="nome" id="nome" placeholder="Ex: Bolo de morango" required value="<?= htmlspecialchars($produtoEditar["nome"] ?? "") ?>">
                    </div>

                    <div class="form-group">
                        <label for="preco">Preço de Venda (R$):</label>
                        <input type="number" name="preco" id="preco" placeholder="0,00" step="0.01" min="0.01" required value="<?= htmlspecialchars($produtoEditar["preco"] ?? "") ?>">
                    </div>
                </div>

                <!-- Alterna o gatilho e o nome do botão baseado no estado da página -->
                <?php if ($produtoEditar): ?>
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" name="salvar_edicao" class="btn btn-success" style="flex: 3;">Salvar Alterações</button>
                        <a href="visualizar_produtos.php" class="btn" style="background-color: #7f8c8d; color: white; flex: 1; text-decoration: none;">Cancelar</a>
                    </div>
                <?php else: ?>
                    <button type="submit" name="cadastrar" class="btn btn-success">Cadastrar Produto</button>
                <?php endif; ?>
            </form>
        </div>
    </main>

</body>
</html>

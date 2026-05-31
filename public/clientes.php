<?php
require_once __DIR__ . "/../config/database.php";

$mensagem = "";
$tipoMensagem = "sucesso";

# =====================================
# EDITAR CLIENTE
# =====================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["salvar_edicao"])) {
    $id = (int) ($_POST["id"] ?? 0);
    $nome = trim($_POST["nome"] ?? "");
    $telefone = trim($_POST["telefone"] ?? "");
    $cidade = trim($_POST["cidade"] ?? "");
    $cep = trim($_POST["cep"] ?? "");
    $rua = trim($_POST["rua"] ?? "");
    $numero = trim($_POST["numero"] ?? "");
    $bairro = trim($_POST["bairro"] ?? "");

    if ($id > 0 && $nome && $telefone && $cidade) {
        $sql = "    
            UPDATE clientes
            SET nome = ?, telefone = ?, cidade = ?, cep = ?, rua = ?, numero = ?, bairro = ?
            WHERE id = ?
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nome, $telefone, $cidade, $cep, $rua, $numero, $bairro, $id]);

        header("Location: clientes.php?sucesso=editado");
        exit;
    }
}

# =====================================
# CADASTRAR CLIENTE
# =====================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["cadastrar"])) {
    $nome = trim($_POST["nome"] ?? "");
    $telefone = trim($_POST["telefone"] ?? "");
    $cidade = trim($_POST["cidade"] ?? "");
    $cep = trim($_POST["cep"] ?? "");
    $rua = trim($_POST["rua"] ?? "");
    $numero = trim($_POST["numero"] ?? "");
    $bairro = trim($_POST["bairro"] ?? "");

    if ($nome && $telefone && $cidade) {
        $sqlCheck = "SELECT id FROM clientes WHERE telefone = ?";
        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->execute([$telefone]);
        
        if ($stmtCheck->fetch()) {
            header("Location: clientes.php?erro=duplicado");
            exit;
        }

        $sql = "INSERT INTO clientes (nome, telefone, cidade, cep, rua, numero, bairro) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nome, $telefone, $cidade, $cep, $rua, $numero, $bairro]);

        header("Location: clientes.php?sucesso=cadastrado");
        exit;
    }
}
    
# =====================================
# EXCLUIR CLIENTE
# =====================================
if (isset($_GET["excluir"])) {
    $id = (int) $_GET["excluir"];
    if ($id > 0) {
        try {
            $sql = "DELETE FROM clientes WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            
            header("Location: clientes.php?sucesso=excluido");
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                header("Location: clientes.php?erro=possui_pedidos");
                exit;
            } else {
                header("Location: clientes.php?erro=sistema");
                exit;
            }
        }
    }
}

# =====================================
# MENSAGENS
# =====================================
if (isset($_GET["sucesso"])) {
    if ($_GET["sucesso"] === "cadastrado") $mensagem = "Cliente cadastrado com sucesso.";
    if ($_GET["sucesso"] === "editado") $mensagem = "Cliente atualizado com sucesso.";
    if ($_GET["sucesso"] === "excluido") $mensagem = "Cliente excluido com sucesso.";
} elseif (isset($_GET["erro"])) {
    $tipoMensagem = "erro";
    if ($_GET["erro"] === "duplicado") $mensagem = "Atencao: Ja existe um cliente cadastrado com este numero de telefone.";
    if ($_GET["erro"] === "possui_pedidos") $mensagem = "Atencao: Nao e possivel excluir este cliente porque ele possui pedidos vinculados.";
    if ($_GET["erro"] === "sistema") $mensagem = "Erro interno ao processar a exclusao.";
}

$clienteEditar = null;
if (isset($_GET["editar"])) {
    $id = (int) $_GET["editar"];
    if ($id > 0) {
        $sql = "SELECT * FROM clientes WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $clienteEditar = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

# =====================================
# BUSCAR CLIENTES
# =====================================
$busca = trim($_GET["buscar"] ?? "");
if ($busca !== "") {
    $sql = "SELECT * FROM clientes WHERE nome LIKE ? OR telefone LIKE ? OR cidade LIKE ? OR bairro LIKE ? ORDER BY id DESC";
    $stmt = $pdo->prepare($sql);
    $pesquisa = "%{$busca}%";
    $stmt->execute([$pesquisa, $pesquisa, $pesquisa, $pesquisa]);
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $sql = "SELECT * FROM clientes ORDER BY id DESC";
    $clientes = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Clientes - Sistema Loja</title>
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
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px; }
        input { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 5px; font-size: 15px; transition: 0.2s; }
        .btn { padding: 12px 25px; border: none; border-radius: 5px; cursor: pointer; font-size: 15px; font-weight: 600; transition: 0.3s; display: inline-block; text-decoration: none; text-align: center; }
        .btn-primary { background: #3498db; color: white; }
        .btn-success { background: #2ecc71; color: white; }
        .btn-secondary { background: #95a5a6; color: white; }
        .btn-search { background: #34495e; color: white; padding: 12px 20px; margin-left: 10px; }
        .busca-container { display: flex; margin-bottom: 25px; }
        .busca-container input { flex: 1; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; background: white; }
        table th, table td { padding: 14px; text-align: left; border-bottom: 1px solid #e0e0e0; font-size: 15px; }
        table th { background: #f8f9fa; color: #34495e; font-weight: 600; }
        .actions-links a { text-decoration: none; font-weight: 600; font-size: 14px; padding: 4px 8px; border-radius: 4px; transition: 0.2s; }
        .actions-links .editar { color: #3498db; }
        .actions-links .whatsapp { color: #2ecc71; }
        .actions-links .excluir { color: #e74c3c; }
        .mensagem { padding: 15px; border-radius: 5px; margin-bottom: 25px; border-left: 5px solid #2ecc71; font-weight: 500; background: #d4edda; color: #155724; }
        .sem-clientes { text-align: center; padding: 30px; color: #7f8c8d; font-style: italic; }
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
            <li style="padding-top: 10px; font-weight: bold; color: #a6b8c7; font-size: 12px; text-transform: uppercase; list-style: none; margin-bottom: 5px;">Clientes</li>
            <li><a href="http://localhost:8000/public/clientes.php">Gerenciar Clientes</a></li>
            <li><a href="http://localhost:8000/public/historico_cliente.php">Historico de Clientes</a></li>
            
            <li style="padding-top: 10px; font-weight: bold; color: #a6b8c7; font-size: 12px; text-transform: uppercase; list-style: none; margin-bottom: 5px;">Produtos</li>
            <li><a href="http://localhost:8000/public/cadastrar_produto.php">Cadastrar Produto</a></li>
            <li><a href="http://localhost:8000/public/visualizar_produtos.php">Visualizar Produtos</a></li>
            
            <li style="padding-top: 10px; font-weight: bold; color: #a6b8c7; font-size: 12px; text-transform: uppercase; list-style: none; margin-bottom: 5px;">Pedidos</li>
            <li><a href="http://localhost:8000/public/criar_pedido.php">Criar Pedido</a></li>
            <li><a href="http://localhost:8000/public/visualizar_pedidos.php">Visualizar Pedidos</a></li>
            <li><a href="http://localhost:8000/public/visualizar_bonus.php">Visualizar Bônus</a></li>
        </ul>


    </nav>

    <!-- Conteúdo da Página -->
    <main class="main-content">
        <div class="header">
            <h1>Gerenciar Clientes</h1>
            <p>Consulte, cadastre e altere os dados dos clientes da loja.</p>
        </div>

        <!-- Bloco do Formulário (Cadastro ou Edição) -->
        <div class="card-panel">
            <h3><?= $clienteEditar ? "Editar Dados do Cliente" : "Cadastrar Novo Cliente" ?></h3>
            
            <form method="POST">
                <?php if ($clienteEditar): ?>
                    <input type="hidden" name="id" value="<?= $clienteEditar["id"] ?>">
                <?php endif; ?>

                <div class="form-row">
                    <input type="text" name="nome" placeholder="Nome Completo" required value="<?= htmlspecialchars($clienteEditar["nome"] ?? "") ?>">
                    <input type="text" name="telefone" placeholder="Telefone (com DDD)" required value="<?= htmlspecialchars($clienteEditar["telefone"] ?? "") ?>">
                    <input type="text" name="cidade" placeholder="Cidade" required value="<?= htmlspecialchars($clienteEditar["cidade"] ?? "") ?>">
                </div>

                <div class="form-row">
                    <input type="text" name="cep" placeholder="CEP" maxlength="9" value="<?= htmlspecialchars($clienteEditar["cep"] ?? "") ?>">
                    <input type="text" name="rua" placeholder="Rua / Logradouro" value="<?= htmlspecialchars($clienteEditar["rua"] ?? "") ?>">
                    <input type="text" name="numero" placeholder="Número" value="<?= htmlspecialchars($clienteEditar["numero"] ?? "") ?>">
                    <input type="text" name="bairro" placeholder="Bairro" value="<?= htmlspecialchars($clienteEditar["bairro"] ?? "") ?>">
                </div>

                <?php if ($clienteEditar): ?>
                    <button type="submit" name="salvar_edicao" class="btn btn-success">Salvar Alterações</button>
                    <a href="clientes.php" class="btn btn-secondary">Cancelar Edição</a>
                <?php else: ?>
                    <button type="submit" name="cadastrar" class="btn btn-primary">Cadastrar Cliente</button>
                <?php endif; ?>
            </form>
        </div>

        <!-- Alerta de Feedback Posicionado no Vão Central -->
        <?php if (!empty($mensagem)): ?>
            <div class="mensagem" style="margin-bottom: 30px; <?= $tipoMensagem === 'erro' ? 'background: #f8d7da; color: #721c24; border-left-color: #dc3545;' : '' ?>">
                <?= htmlspecialchars($mensagem) ?>
            </div>
        <?php endif; ?>

        <!-- Bloco de Listagem e Busca -->
        <div class="card-panel">
            <h3>Lista de Clientes Ativos</h3>
            
            <form method="GET" class="busca-container">
                <input type="text" name="buscar" placeholder="Buscar por nome, telefone, cidade ou bairro..." value="<?= htmlspecialchars($_GET["buscar"] ?? "") ?>">
                <button type="submit" class="btn btn-search">Buscar</button>
                <?php if ($busca !== ""): ?>
                    <a href="clientes.php" class="btn btn-secondary" style="margin-left: 5px; padding: 12px 15px;">Limpar</a>
                <?php endif; ?>
            </form>

            <table>
                <thead>
                    <tr>
                        <th width="8%">ID</th>
                        <th width="22%">Nome</th>
                        <th width="18%">Telefone</th>
                        <th width="15%">Cidade</th>
                        <th width="22%">Endereço Completo</th>
                        <th width="15%">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($clientes) > 0): ?>
                        <?php foreach ($clientes as $cliente): ?>
                            <tr>
                                <td><strong>#<?= $cliente["id"] ?></strong></td>
                                <td><?= htmlspecialchars($cliente["nome"] ?? "") ?></td>
                                <td><?= htmlspecialchars($cliente["telefone"] ?? "") ?></td>
                                <td><?= htmlspecialchars($cliente["cidade"] ?? "") ?></td>
                                <td>
                                    <?php 
                                    if (!empty($cliente["rua"])) {
                                        echo htmlspecialchars($cliente["rua"] . ", " . $cliente["numero"] . " - " . $cliente["bairro"]);
                                        if (!empty($cliente["cep"])) echo " (CEP: " . htmlspecialchars($cliente["cep"]) . ")";
                                    } else {
                                        echo "<span style='color: #bbb; font-style: italic;'>Não informado</span>";
                                    }
                                    ?>
                                </td>
                                <td class="actions-links">
                                    <a class="editar" href="?editar=<?= $cliente["id"] ?>">Editar</a>

                                    |
<a class="whatsapp" target="_blank" href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $cliente['telefone']) ?>?text=Olá%20<?= urlencode($cliente['nome']) ?>">WhatsApp</a>

                                    <a class="excluir" href="?excluir=<?= $cliente["id"] ?>" onclick="return confirm('Tem certeza que deseja excluir este cliente?')">Excluir</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="sem-clientes">Nenhum cliente cadastrado ou encontrado.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

</body>
</html>

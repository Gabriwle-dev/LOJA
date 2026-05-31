<?php
require_once __DIR__ . "/../config/database.php";

$mensagem = "";
$tipoMensagem = "sucesso";
$statusEditar = null;

# =====================================
# REGRAS DE EXCLUSÃO (PROTEÇÃO DE HISTÓRICO)
# =====================================
if (isset($_GET["excluir"])) {
    $idExcluir = (int)$_GET["excluir"];
    if ($idExcluir > 0) {
        try {
            // 1. Buscar o nome do status para checar restrições críticas
            $stmt = $pdo->prepare("SELECT nome FROM status_pedido WHERE id = ?");
            $stmt->execute([$idExcluir]);
            $statusNome = $stmt->fetchColumn();

            // Proteção Hipótese C: Impedir remoção de status vitais para o bônus
            if (in_array($statusNome, ['Finalizado', 'Cancelado', 'Em Preparo'])) {
                header("Location: cadastrar_status.php?erro=critico");
                exit;
            }

            // Proteção Hipótese A: Verificar se existem pedidos usando este status
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE status = ?");
            $stmtCheck->execute([$statusNome]);
            $emUso = (int)$stmtCheck->fetchColumn();

            if ($emUso > 0) {
                header("Location: cadastrar_status.php?erro=vinculado&qtd=" . $emUso);
                exit;
            }

            // Se passou pelas travas, deleta com segurança
            $stmtDelete = $pdo->prepare("DELETE FROM status_pedido WHERE id = ?");
            $stmtDelete->execute([$idExcluir]);
            
            header("Location: cadastrar_status.php?sucesso=excluido");
            exit;
        } catch (PDOException $e) {
            header("Location: cadastrar_status.php?erro=sistema");
            exit;
        }
    }
}

# =====================================
# REGRAS DE CADASTRO E EDIÇÃO
# =====================================
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["cadastrar"])) {
        $nomeStatus = trim($_POST["nome"] ?? "");
        if (!empty($nomeStatus)) {
            $stmtCheck = $pdo->prepare("SELECT id FROM status_pedido WHERE nome = ?");
            $stmtCheck->execute([$nomeStatus]);
            if ($stmtCheck->fetch()) {
                $mensagem = "Atenção: Esse status já existe!";
                $tipoMensagem = "erro";
            } else {
                $stmt = $pdo->prepare("INSERT INTO status_pedido (nome) VALUES (?)");
                $stmt->execute([$nomeStatus]);
                header("Location: cadastrar_status.php?sucesso=cadastrado");
                exit;
            }
        }
    }

    if (isset($_POST["salvar_edicao"])) {
        $idEditar = (int)($_POST["id"] ?? 0);
        $novoNome = trim($_POST["nome"] ?? "");
        if ($idEditar > 0 && !empty($novoNome)) {
            // Buscar o nome antigo para atualizar os pedidos em cascata
            $stmt = $pdo->prepare("SELECT nome FROM status_pedido WHERE id = ?");
            $stmt->execute([$idEditar]);
            $nomeAntigo = $stmt->fetchColumn();

            if (!in_array($nomeAntigo, ['Finalizado', 'Cancelado'])) {
                $pdo->beginTransaction();
                // Atualiza o cadastro do status
                $stmt = $pdo->prepare("UPDATE status_pedido SET nome = ? WHERE id = ?");
                $stmt->execute([$novoNome, $idEditar]);
                // Atualiza os pedidos em cascata (Consistência de dados)
                $stmt = $pdo->prepare("UPDATE pedidos SET status = ? WHERE status = ?");
                $stmt->execute([$novoNome, $nomeAntigo]);
                $pdo->commit();
                header("Location: cadastrar_status.php?sucesso=editado");
                exit;
            } else {
                $mensagem = "Status do sistema ('Finalizado' / 'Cancelado') não podem ser renomeados.";
                $tipoMensagem = "erro";
            }
        }
    }
}

# =====================================
# CAPTURAR VARIÁVEL DE EDIÇÃO
# =====================================
if (isset($_GET["editar"])) {
    $idEditarGet = (int)$_GET["editar"];
    $stmt = $pdo->prepare("SELECT * FROM status_pedido WHERE id = ?");
    $stmt->execute([$idEditarGet]);
    $statusEditar = $stmt->fetch(PDO::FETCH_ASSOC);
}

# =====================================
# PROCESSAMENTO DE FEEDBACKS (ALERTAS)
# =====================================
if (isset($_GET["sucesso"])) {
    if ($_GET["sucesso"] === "cadastrado") $mensagem = "Novo status adicionado com sucesso!";
    if ($_GET["sucesso"] === "editado") $mensagem = "Status e encomendas vinculadas atualizados com sucesso!";
    if ($_GET["sucesso"] === "excluido") $mensagem = "Opção de status removida do sistema.";
} elseif (isset($_GET["erro"])) {
    $tipoMensagem = "erro";
    if ($_GET["erro"] === "critico") $mensagem = "⚠️ Bloqueado: Os status base do sistema não podem ser removidos.";
    if ($_GET["erro"] === "vinculado") $mensagem = "⚠️ Bloqueado: Existem " . ($_GET["qtd"] ?? "") . " encomendas utilizando este status atualmente.";
    if ($_GET["erro"] === "sistema") $mensagem = "Erro interno ao processar a operação.";
}

$listaStatus = $pdo->query("SELECT * FROM status_pedido ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Status - Sistema Loja</title>
    <style>
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
        
        /* Box de Painel */
        .card-panel { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .card-panel h3 { color: #2c3e50; margin-bottom: 20px; font-size: 20px; border-left: 4px solid #9b59b6; padding-left: 10px; }
        
        .form-group { display: flex; flex-direction: column; margin-bottom: 20px; }
        label { margin-bottom: 8px; font-weight: 600; color: #34495e; font-size: 15px; }
        input { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 5px; font-size: 15px; background: white; }
        input:focus { border-color: #9b59b6; outline: none; }
        
        /* Botões */
        .btn { padding: 12px 25px; border: none; border-radius: 5px; cursor: pointer; font-size: 15px; font-weight: 600; transition: 0.3s; display: inline-block; text-decoration: none; text-align: center; }
        .btn-purple { background: #9b59b6; color: white; width: 100%; }
        .btn-purple:hover { background: #8e44ad; }
        .btn-success { background: #2ecc71; color: white; }
        .btn-success:hover { background: #27ae60; }
        .btn-secondary { background: #95a5a6; color: white; text-decoration: none; margin-left: 5px; }
        .btn-secondary:hover { background: #7f8c8d; }
        
        /* Tabelas */
        table { width: 100%; border-collapse: collapse; margin-top: 10px; background: white; }
        table th, table td { padding: 14px; text-align: left; border-bottom: 1px solid #e0e0e0; font-size: 15px; }
        table th { background: #f8f9fa; color: #34495e; font-weight: 600; }
        table tr:hover { background-color: #fcfcfc; }
        
        /* Links de Ações */
        .actions-links a { text-decoration: none; font-weight: 600; font-size: 14px; padding: 4px 8px; border-radius: 4px; transition: 0.2s; }
        .actions-links .editar { color: #3498db; }
        .actions-links .editar:hover { background: rgba(52,152,219,0.1); }
        .actions-links .excluir { color: #e74c3c; }
        .actions-links .excluir:hover { background: rgba(231,76,60,0.1); }
        
        /* Banners de Alerta */
        .mensagem { padding: 15px; border-radius: 5px; margin-bottom: 25px; border-left: 5px solid #2ecc71; font-weight: 500; background: #d4edda; color: #155724; }
        .sem-dados { text-align: center; padding: 30px; color: #7f8c8d; font-style: italic; }
        .badge-sistema { font-size: 12px; background: #eee; color: #666; padding: 2px 6px; border-radius: 4px; margin-left: 10px; font-weight: normal; }

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


    <!-- Área de Conteúdo Principal -->
    <main class="main-content">
        <div class="header">
            <h1>Gerenciar Status das Encomendas</h1>
            <p>Cadastre, altere ou remova as situações de acompanhamento da produção dos pudins.</p>
        </div>

        <!-- Box Envelopando o Formulário de Cadastro ou Edição Dinâmica -->
        <div class="card-panel">
            <h3><?= $statusEditar ? "📝 Alterar Nome do Status" : "➕ Criar Opção de Status" ?></h3>
            
            <form method="POST">
                <?php if ($statusEditar): ?>
                    <input type="hidden" name="id" value="<?= $statusEditar["id"] ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="nome">Nome da Situação:</label>
                    <input type="text" name="nome" id="nome" placeholder="Ex: Na Geladeira / Resfriando" required value="<?= htmlspecialchars($statusEditar["nome"] ?? "") ?>">
                </div>

                <?php if ($statusEditar): ?>
                    <button type="submit" name="salvar_edicao" class="btn btn-success">Salvar Alterações</button>
                    <a href="cadastrar_status.php" class="btn btn-secondary">Cancelar Edição</a>
                <?php else: ?>
                    <button type="submit" name="cadastrar" class="btn btn-purple">Salvar Novo Status</button>
                <?php endif; ?>
            </form>
        </div>

        <!-- Alerta de Feedback Posicionado entre os dois blocos -->
        <?php if (!empty($mensagem)): ?>
            <div class="mensagem" style="margin-bottom: 30px; <?= $tipoMensagem === 'erro' ? 'background: #f8d7da; color: #721c24; border-left-color: #dc3545;' : '' ?>">
                <?= htmlspecialchars($mensagem) ?>
            </div>
        <?php endif; ?>

        <!-- Box da Tabela com as Opções Cadastradas -->
        <div class="card-panel">
            <h3>👁️ Opções Ativas no Sistema</h3>
            <table>
                <thead>
                    <tr>
                        <th width="15%">ID</th>
                        <th width="55%">Nome do Status</th>
                        <th width="30%">Ações de Controle</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($listaStatus) > 0): ?>
                        <?php foreach ($listaStatus as $st): ?>
                            <tr>
                                <td><strong>#<?= $st["id"] ?></strong></td>
                                <td>
                                    <?= htmlspecialchars($st["nome"]) ?>
                                    <?php if (in_array($st["nome"], ['Finalizado', 'Cancelado', 'Em Preparo'])): ?>
                                        <span class="badge-sistema">Obrigatório do Sistema</span>
                                    <?php endif; ?>
                                </td>
                                <td class="actions-links">
                                    <?php if (!in_array($st["nome"], ['Finalizado', 'Cancelado'])): ?>
                                        <a class="editar" href="?editar=<?= $st["id"] ?>">Editar</a>
                                    <?php endif; ?>

                                    <?php if (!in_array($st["nome"], ['Finalizado', 'Cancelado', 'Em Preparo'])): ?>
                                        |
                                        <a class="excluir" href="?excluir=<?= $st["id"] ?>" onclick="return confirm('Tem certeza que deseja excluir este status? Pedidos antigos vinculados a ele bloquearão a exclusão.')">Excluir</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="sem-dados">Nenhum status personalizado foi encontrado.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

</body>
</html>

<?php

require_once __DIR__ . "/../config/database.php";

$mensagem = "";

# =====================================
# EDITAR CLIENTE
# =====================================

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["salvar_edicao"])
) {

    $id        = (int) $_POST["id"];

    $nome      = trim($_POST["nome"] ?? "");
    $telefone  = trim($_POST["telefone"] ?? "");
    $cidade    = trim($_POST["cidade"] ?? "");

    if ($nome && $telefone && $cidade) {

        $sql = "
            UPDATE clientes
            SET
                nome = ?,
                telefone = ?,
                cidade = ?
            WHERE id = ?
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            $nome,
            $telefone,
            $cidade,
            $id
        ]);

        header("Location: clientes.php?sucesso=editado");

        exit;
    }
}

# =====================================
# CADASTRAR CLIENTE
# =====================================

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["cadastrar"])
) {

    $nome      = trim($_POST["nome"] ?? "");
    $telefone  = trim($_POST["telefone"] ?? "");
    $cidade    = trim($_POST["cidade"] ?? "");

    if ($nome && $telefone && $cidade) {

        $sql = "
            INSERT INTO clientes
            (nome, telefone, cidade)
            VALUES (?, ?, ?)
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            $nome,
            $telefone,
            $cidade
        ]);

        header("Location: clientes.php?sucesso=cadastrado");

        exit;
    }
}

# =====================================
# EXCLUIR CLIENTE
# =====================================

if (isset($_GET["excluir"])) {

    $id = (int) $_GET["excluir"];

    $sql = "DELETE FROM clientes WHERE id = ?";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([$id]);

    header("Location: clientes.php?sucesso=excluido");

    exit;
}

# =====================================
# MENSAGENS
# =====================================

if (isset($_GET["sucesso"])) {

    if ($_GET["sucesso"] === "cadastrado") {

        $mensagem = "Cliente cadastrado com sucesso!";
    }

    elseif ($_GET["sucesso"] === "editado") {

        $mensagem = "Cliente atualizado com sucesso!";
    }

    elseif ($_GET["sucesso"] === "excluido") {

        $mensagem = "Cliente excluído com sucesso!";
    }
}

# =====================================
# BUSCAR CLIENTE PARA EDIÇÃO
# =====================================

$clienteEditar = null;

if (isset($_GET["editar"])) {

    $id = (int) $_GET["editar"];

    $sql = "SELECT * FROM clientes WHERE id = ?";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([$id]);

    $clienteEditar = $stmt->fetch(PDO::FETCH_ASSOC);
}

# =====================================
# BUSCAR CLIENTES
# =====================================

$busca = trim($_GET["buscar"] ?? "");

if ($busca !== "") {

    $sql = "
        SELECT *
        FROM clientes
        WHERE
            nome LIKE ?
            OR telefone LIKE ?
            OR cidade LIKE ?
        ORDER BY id DESC
    ";

    $stmt = $pdo->prepare($sql);

    $pesquisa = "%$busca%";

    $stmt->execute([
        $pesquisa,
        $pesquisa,
        $pesquisa
    ]);

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

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Gerenciar Clientes</title>

<style>

* {

    margin: 0;

    padding: 0;

    box-sizing: border-box;
}

body {

    font-family: Arial, sans-serif;

    background: #f4f6f9;

    min-height: 100vh;

    padding: 20px;
}

.container {

    width: 100%;

    max-width: 1400px;

    margin: auto;

    background: white;

    padding: 30px;

    border-radius: 12px;

    box-shadow: 0 0 15px rgba(0,0,0,0.1);
}

h2 {

    text-align: center;

    margin-bottom: 30px;

    color: #333;
}

form {

    width: 100%;
}

input {

    width: 100%;

    padding: 12px;

    margin-bottom: 15px;

    border: 1px solid #ccc;

    border-radius: 8px;

    font-size: 15px;
}

button {

    width: 100%;

    padding: 14px;

    background: #007bff;

    color: white;

    border: none;

    border-radius: 8px;

    cursor: pointer;

    font-size: 16px;

    transition: 0.3s;

    margin-bottom: 10px;
}

button:hover {

    background: #0056b3;
}

.cancelar {

    display: block;

    width: 100%;

    text-align: center;

    padding: 14px;

    background: #6c757d;

    color: white;

    text-decoration: none;

    border-radius: 8px;

    margin-bottom: 15px;
}

.cancelar:hover {

    background: #545b62;
}

.mensagem {

    background: #d4edda;

    color: #155724;

    padding: 12px;

    border-radius: 8px;

    margin-bottom: 20px;
}

.busca {

    margin-top: 30px;
}

.table-container {

    width: 100%;

    overflow-x: auto;

    margin-top: 30px;
}

table {

    width: 100%;

    border-collapse: collapse;

    min-width: 700px;
}

table th,
table td {

    border: 1px solid #ddd;

    padding: 14px;

    text-align: left;
}

table th {

    background: #f0f0f0;
}

table tr:nth-child(even) {

    background: #fafafa;
}

.editar {

    color: #007bff;

    text-decoration: none;

    font-weight: bold;
}

.excluir {

    color: red;

    text-decoration: none;

    font-weight: bold;
}

.whatsapp {

    color: green;

    text-decoration: none;

    font-weight: bold;
}

.sem-clientes {

    text-align: center;

    padding: 20px;

    color: #666;
}

@media (max-width: 768px) {

    body {

        padding: 10px;
    }

    .container {

        padding: 15px;
    }

    h2 {

        font-size: 24px;
    }

    input,
    button {

        font-size: 14px;
    }

    table th,
    table td {

        padding: 10px;

        font-size: 14px;
    }
}

</style>

</head>

<body>

<div class="container">

<h2>Gerenciar Clientes</h2>

<?php if ($mensagem): ?>

<div class="mensagem">
    <?= htmlspecialchars($mensagem) ?>
</div>

<?php endif; ?>

<!-- FORMULÁRIO -->

<form method="POST">

<?php if ($clienteEditar): ?>

<input
    type="hidden"
    name="id"
    value="<?= $clienteEditar["id"] ?>"
>

<?php endif; ?>

<input
    type="text"
    name="nome"
    placeholder="Nome"
    required
    value="<?= htmlspecialchars($clienteEditar["nome"] ?? "") ?>"
>

<input
    type="text"
    name="telefone"
    placeholder="Telefone"
    required
    value="<?= htmlspecialchars($clienteEditar["telefone"] ?? "") ?>"
>

<input
    type="text"
    name="cidade"
    placeholder="Cidade"
    required
    value="<?= htmlspecialchars($clienteEditar["cidade"] ?? "") ?>"
>

<?php if ($clienteEditar): ?>

<button
    type="submit"
    name="salvar_edicao"
>
    Salvar Alterações
</button>

<a
    class="cancelar"
    href="clientes.php"
>
    Cancelar edição
</a>

<?php else: ?>

<button
    type="submit"
    name="cadastrar"
>
    Cadastrar
</button>

<?php endif; ?>

</form>

<!-- BUSCA -->

<div class="busca">

<form method="GET">

<input
    type="text"
    name="buscar"
    placeholder="Buscar por nome, telefone ou cidade"
    value="<?= htmlspecialchars($_GET["buscar"] ?? "") ?>"
>

<button type="submit">
    Buscar
</button>

</form>

</div>

<!-- TABELA -->

<div class="table-container">

<table>

<tr>
    <th>ID</th>
    <th>Nome</th>
    <th>Telefone</th>
    <th>Cidade</th>
    <th>Ações</th>
</tr>

<?php if (count($clientes) > 0): ?>

<?php foreach ($clientes as $cliente): ?>

<tr>

<td><?= $cliente["id"] ?></td>

<td><?= htmlspecialchars($cliente["nome"] ?? "") ?></td>

<td><?= htmlspecialchars($cliente["telefone"] ?? "") ?></td>

<td><?= htmlspecialchars($cliente["cidade"] ?? "") ?></td>

<td>

<a
    class="editar"
    href="?editar=<?= $cliente["id"] ?>"
>
    Editar
</a>

|

<a
    class="whatsapp"
    target="_blank"
    href="https://wa.me/55<?= preg_replace('/[^0-9]/', '', $cliente["telefone"]) ?>?text=Olá%20<?= urlencode($cliente["nome"]) ?>"
>
    WhatsApp
</a>

|

<a
    class="excluir"
    href="?excluir=<?= $cliente["id"] ?>"
    onclick="return confirm('Excluir cliente?')"
>
    Excluir
</a>

</td>

</tr>

<?php endforeach; ?>

<?php else: ?>

<tr>

<td
    colspan="5"
    class="sem-clientes"
>
    Nenhum cliente encontrado.
</td>

</tr>

<?php endif; ?>

</table>

</div>

</div>

</body>
</html>
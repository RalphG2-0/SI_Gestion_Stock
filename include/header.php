<?php
require_once("init.php");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?php echo isset($pageTitle) ? $pageTitle : "SI Stock"; ?></title>
    <link href="include/bootstrap-5.0.2-dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="include/style.css" rel="stylesheet">
</head>
<body>
<div class="d-flex">
    <!-- Sidebar -->
    <div class="bg-dark text-white p-3" style="width: 250px; min-height: 100vh;">
        <h4 class="mb-4">SI Stock</h4>
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link text-white" href="dashboard.php">📊 Dashboard</a></li>
            <li class="nav-item"><a class="nav-link text-white" href="produits.php">📦 Produits</a></li>
            <li class="nav-item"><a class="nav-link text-white" href="mouvements.php">🔄 Mouvements</a></li>
            <li class="nav-item"><a class="nav-link text-white" href="commandes.php">📝 Commandes</a></li>
            <li class="nav-item"><a class="nav-link text-white" href="fournisseurs.php">🏢 Fournisseurs</a></li>
            <?php if ($_SESSION['role'] === 'gestionnaire') { ?>
                <li class="nav-item"><a class="nav-link text-white" href="categories.php">📂 Catégories</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="create_users.php">👥 Utilisateurs</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="audit.php">📑 Audit</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="rapports.php">📑 Rapports</a></li>
            <?php } ?>
            <li class="nav-item"><a class="nav-link text-danger" href="logout.php">🚪 Déconnexion</a></li>
            <li class="nav-item mt-3">
                <label class="theme-switch">
    <input type="checkbox" id="toggleDarkMode">
    <span class="slider"></span>
</label>
<span class="ms-2">🌙 Mode sombre</span>

            </li>
        </ul>
    </div>

    <!-- Contenu principal -->
    <div class="flex-grow-1 p-4">

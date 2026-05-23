<?php
$pageTitle = "Tableau de bord";
require_once("init.php");
require_once("include/header.php");
?>

<h1 class="mt-4">Bienvenue <?php echo $_SESSION['nom']; ?> !</h1>
<p>Rôle : <strong><?php echo $_SESSION['role']; ?></strong></p>

<div class="row mt-4">
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title">Gestion des Produits</h5>
                <p class="card-text">Accédez à la liste et au CRUD des produits.</p>
                <a href="produits.php" class="btn btn-primary">Produits</a>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title">Mouvements de Stock</h5>
                <p class="card-text">Suivez les entrées et sorties de stock.</p>
                <a href="mouvements.php" class="btn btn-primary">Mouvements</a>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title">Commandes</h5>
                <p class="card-text">Gérez les commandes et leurs détails.</p>
                <a href="commandes.php" class="btn btn-primary">Commandes</a>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title">Fournisseurs</h5>
                <p class="card-text">Ajoutez et consultez vos fournisseurs.</p>
                <a href="fournisseurs.php" class="btn btn-primary">Fournisseurs</a>
            </div>
        </div>
    </div>

    <?php if ($_SESSION['role'] === 'gestionnaire') { ?>
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title">Catégories</h5>
                <p class="card-text">Gérez les catégories de produits.</p>
                <a href="categories.php" class="btn btn-primary">Catégories</a>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title">Utilisateurs</h5>
                <p class="card-text">Créez et gérez les comptes utilisateurs.</p>
                <a href="create_users.php" class="btn btn-primary">Utilisateurs</a>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title">Rapports</h5>
                <p class="card-text">Consultez les seuils critiques et inventaires.</p>
                <a href="rapports.php" class="btn btn-primary">Rapports</a>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title">Audit</h5>
                <p class="card-text">Consultez l'historique des actions.</p>
                <a href="audit.php" class="btn btn-primary">Audit</a>
            </div>
        </div>
    </div>
    <?php } ?>
</div>

<?php 
require_once("include/footer.php"); 
?>

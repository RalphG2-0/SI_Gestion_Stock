<?php
$pageTitle = "Gestion des Produits";
require_once("init.php");
require_once("include/header.php");
require_once("database.php");

$pdo = getPDO();

// --- CREATE ---
if (isset($_POST['ajouter'])) {
    // Vérifier CSRF token
    if (!isset($_POST[CSRF_TOKEN_NAME]) || !CSRFToken::validate($_POST[CSRF_TOKEN_NAME])) {
        Message::error("Token de sécurité invalide. Veuillez réessayer.");
    } else {
        // Valider les données
        $nom = Validator::validateString($_POST['nom'] ?? '', 1, 100);
        $description = Validator::validateString($_POST['description'] ?? '', 0, 500);
        $categorie_id = Validator::validateInt($_POST['categorie_id'] ?? 0);
        $prix = Validator::validatePositive($_POST['prix_unitaire'] ?? 0);
        $stock = Validator::validateInt($_POST['stock_actuel'] ?? 0);
        $seuil = Validator::validateInt($_POST['seuil_minimum'] ?? 0);
        
        // Vérifications
        $errors = [];
        if ($nom === false) $errors[] = "Nom invalide";
        if ($categorie_id === false || $categorie_id <= 0) $errors[] = "Catégorie invalide";
        if ($prix === false || $prix < 0) $errors[] = "Prix doit être >= 0";
        if ($stock === false || $stock < 0) $errors[] = "Stock doit être >= 0";
        if ($seuil === false || $seuil < 0) $errors[] = "Seuil doit être >= 0";
        
        if (count($errors) > 0) {
            Message::error("Erreurs: " . implode(", ", $errors));
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO produits (nom, description, categorie_id, prix_unitaire, stock_actuel, seuil_minimum) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $nom,
                    $description,
                    $categorie_id,
                    $prix,
                    $stock,
                    $seuil
                ]);
                $newId = $pdo->lastInsertId();
                
                // Audit log
                $audit = $pdo->prepare("INSERT INTO audit_log (utilisateur_id, action) VALUES (?, ?)");
                $audit->execute([$_SESSION['id'], "Ajout produit: " . $nom]);
                
                Message::success("Produit ajouté avec succès!");
            } catch (Exception $e) {
                Message::error("Erreur lors de l'ajout: " . $e->getMessage());
            }
        }
    }
}

// --- UPDATE ---
if (isset($_POST['modifier'])) {
    if (!isset($_POST[CSRF_TOKEN_NAME]) || !CSRFToken::validate($_POST[CSRF_TOKEN_NAME])) {
        Message::error("Token de sécurité invalide.");
    } else {
        $id = Validator::validateInt($_POST['id_produit'] ?? 0);
        $nom = Validator::validateString($_POST['nom'] ?? '', 1, 100);
        $description = Validator::validateString($_POST['description'] ?? '', 0, 500);
        $categorie_id = Validator::validateInt($_POST['categorie_id'] ?? 0);
        $prix = Validator::validatePositive($_POST['prix_unitaire'] ?? 0);
        $stock = Validator::validateInt($_POST['stock_actuel'] ?? 0);
        $seuil = Validator::validateInt($_POST['seuil_minimum'] ?? 0);
        
        $errors = [];
        if ($id === false || $id <= 0) $errors[] = "ID invalide";
        if ($nom === false) $errors[] = "Nom invalide";
        if ($prix === false) $errors[] = "Prix invalide";
        
        if (count($errors) > 0) {
            Message::error("Erreurs: " . implode(", ", $errors));
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE produits SET nom=?, description=?, categorie_id=?, prix_unitaire=?, stock_actuel=?, seuil_minimum=? WHERE id_produit=?");
                $stmt->execute([
                    $nom,
                    $description,
                    $categorie_id,
                    $prix,
                    $stock,
                    $seuil,
                    $id
                ]);
                
                $audit = $pdo->prepare("INSERT INTO audit_log (utilisateur_id, action) VALUES (?, ?)");
                $audit->execute([$_SESSION['id'], "Modification produit: " . $nom]);
                
                Message::success("Produit modifié avec succès!");
            } catch (Exception $e) {
                Message::error("Erreur: " . $e->getMessage());
            }
        }
    }
}

// --- DELETE ---
if (isset($_GET['supprimer'])) {
    $id = Validator::validateInt($_GET['supprimer'] ?? 0);
    if ($id === false || $id <= 0) {
        Message::error("ID invalide");
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM produits WHERE id_produit=?");
            $stmt->execute([$id]);
            
            $audit = $pdo->prepare("INSERT INTO audit_log (utilisateur_id, action) VALUES (?, ?)");
            $audit->execute([$_SESSION['id'], "Suppression produit ID: " . $id]);
            
            Message::success("Produit supprimé!");
            header("Location: produits.php");
            exit();
        } catch (Exception $e) {
            Message::error("Erreur: " . $e->getMessage());
        }
    }
}

// --- READ ---
$stmt = $pdo->query("SELECT p.*, c.nom AS categorie_nom FROM produits p LEFT JOIN categories c ON p.categorie_id = c.id_categorie");
$produits = $stmt->fetchAll();
?>

<h1 class="mt-4">Produits</h1>

<?php echo Message::display(); ?>

<!-- Formulaire d'ajout -->
<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title">Ajouter un produit</h5>
        <form method="post">
            <?php echo CSRFToken::field(); ?>
            <input type="text" name="nom" placeholder="Nom" class="form-control mb-2" required>
            <input type="text" name="description" placeholder="Description" class="form-control mb-2">
            <select name="categorie_id" class="form-control mb-2">
                <?php
                $cats = $pdo->query("SELECT * FROM categories")->fetchAll();
                foreach ($cats as $cat) {
                    echo "<option value='{$cat['id_categorie']}'>{$cat['nom']}</option>";
                }
                ?>
            </select>
            <input type="number" step="0.01" name="prix_unitaire" placeholder="Prix unitaire" class="form-control mb-2" required>
            <input type="number" name="stock_actuel" placeholder="Stock actuel" class="form-control mb-2" required>
            <input type="number" name="seuil_minimum" placeholder="Seuil minimum" class="form-control mb-2" required>
            <button type="submit" name="ajouter" class="btn btn-success">Ajouter</button>
        </form>
    </div>
</div>

<!-- Tableau des produits -->
<table class="table table-bordered">
    <thead class="table-dark">
        <tr>
            <th>ID</th>
            <th>Nom</th>
            <th>Description</th>
            <th>Catégorie</th>
            <th>Prix</th>
            <th>Stock</th>
            <th>Seuil</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($produits as $p) { ?>
        <tr>
            <td><?= $p['id_produit'] ?></td>
            <td><?= htmlspecialchars($p['nom']) ?></td>
            <td><?= htmlspecialchars($p['description']) ?></td>
            <td><?= $p['categorie_nom'] ?></td>
            <td><?= $p['prix_unitaire'] ?> €</td>
            <td><?= $p['stock_actuel'] ?></td>
            <td><?= $p['seuil_minimum'] ?></td>
            <td>
                <!-- Formulaire de modification -->
                <form method="post" style="display:inline-block;">
                    <?php echo CSRFToken::field(); ?>
                    <input type="hidden" name="id_produit" value="<?= $p['id_produit'] ?>">
                    <input type="text" name="nom" value="<?= htmlspecialchars($p['nom']) ?>" class="form-control mb-1" required>
                    <input type="text" name="description" value="<?= htmlspecialchars($p['description']) ?>" class="form-control mb-1">
                    <select name="categorie_id" class="form-control mb-1">
                        <?php
                        $cats = $pdo->query("SELECT * FROM categories")->fetchAll();
                        foreach ($cats as $cat) {
                            $selected = $p['categorie_id'] == $cat['id_categorie'] ? 'selected' : '';
                            echo "<option value='{$cat['id_categorie']}' {$selected}>{$cat['nom']}</option>";
                        }
                        ?>
                    </select>
                    <input type="number" step="0.01" name="prix_unitaire" value="<?= $p['prix_unitaire'] ?>" class="form-control mb-1" required>
                    <input type="number" name="stock_actuel" value="<?= $p['stock_actuel'] ?>" class="form-control mb-1" required>
                    <input type="number" name="seuil_minimum" value="<?= $p['seuil_minimum'] ?>" class="form-control mb-1" required>
                    <button type="submit" name="modifier" class="btn btn-warning btn-sm">Modifier</button>
                </form>
                <a href="produits.php?supprimer=<?= $p['id_produit'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Êtes-vous sûr?');">Supprimer</a>
            </td>
        </tr>
        <?php } ?>
    </tbody>
</table>

<?php require_once("include/footer.php"); ?>

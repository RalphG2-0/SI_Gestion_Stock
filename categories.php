<?php
$pageTitle = "Gestion des Catégories";
require_once("init.php");
require_once("include/header.php");
require_once("database.php");

$pdo = getPDO();

// --- CREATE ---
if (isset($_POST['ajouter'])) {
    if (!isset($_POST[CSRF_TOKEN_NAME]) || !CSRFToken::validate($_POST[CSRF_TOKEN_NAME])) {
        Message::error("Token de sécurité invalide.");
    } else {
        $nom = Validator::validateString($_POST['nom'] ?? '', 1, 100);
        $description = Validator::validateString($_POST['description'] ?? '', 0, 500);
        
        if ($nom === false) {
            Message::error("Nom invalide (1-100 caractères)");
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO categories (nom, description) VALUES (?, ?)");
                $stmt->execute([$nom, $description]);
                $id = $pdo->lastInsertId();
                
                $audit = $pdo->prepare("INSERT INTO audit_log (utilisateur_id, action) VALUES (?, ?)");
                $audit->execute([$_SESSION['id'], "Création catégorie: " . $nom]);
                
                Message::success("Catégorie créée!");
            } catch (Exception $e) {
                Message::error("Erreur: " . $e->getMessage());
            }
        }
    }
}

// --- UPDATE ---
if (isset($_POST['modifier'])) {
    if (!isset($_POST[CSRF_TOKEN_NAME]) || !CSRFToken::validate($_POST[CSRF_TOKEN_NAME])) {
        Message::error("Token invalide");
    } else {
        $id = Validator::validateInt($_POST['id_categorie'] ?? 0);
        $nom = Validator::validateString($_POST['nom'] ?? '', 1, 100);
        $description = Validator::validateString($_POST['description'] ?? '', 0, 500);
        
        $errors = [];
        if ($id === false || $id <= 0) $errors[] = "ID invalide";
        if ($nom === false) $errors[] = "Nom invalide";
        
        if (count($errors) > 0) {
            Message::error(implode(", ", $errors));
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE categories SET nom=?, description=? WHERE id_categorie=?");
                $stmt->execute([$nom, $description, $id]);
                
                $audit = $pdo->prepare("INSERT INTO audit_log (utilisateur_id, action) VALUES (?, ?)");
                $audit->execute([$_SESSION['id'], "Modification catégorie: " . $nom]);
                
                Message::success("Catégorie modifiée!");
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
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id_categorie=?");
            $stmt->execute([$id]);
            
            $audit = $pdo->prepare("INSERT INTO audit_log (utilisateur_id, action) VALUES (?, ?)");
            $audit->execute([$_SESSION['id'], "Suppression catégorie ID: " . $id]);
            
            Message::success("Catégorie supprimée!");
            header("Location: categories.php");
            exit();
        } catch (Exception $e) {
            Message::error("Erreur: " . $e->getMessage());
        }
    }
}

// --- READ ---
$stmt = $pdo->query("SELECT * FROM categories ORDER BY nom");
$categories = $stmt->fetchAll();
?>

<h1 class="mt-4">Catégories</h1>

<?php echo Message::display(); ?>

<!-- Formulaire d'ajout -->
<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title">Ajouter une catégorie</h5>
        <form method="post">
            <?php echo CSRFToken::field(); ?>
            <input type="text" name="nom" placeholder="Nom" class="form-control mb-2" required>
            <input type="text" name="description" placeholder="Description" class="form-control mb-2">
            <button type="submit" name="ajouter" class="btn btn-success">Ajouter</button>
        </form>
    </div>
</div>

<!-- Tableau des catégories -->
<table class="table table-bordered">
    <thead class="table-dark">
        <tr>
            <th>ID</th>
            <th>Nom</th>
            <th>Description</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($categories as $c) { ?>
        <tr>
            <td><?= $c['id_categorie'] ?></td>
            <td><?= htmlspecialchars($c['nom']) ?></td>
            <td><?= htmlspecialchars($c['description']) ?></td>
            <td>
                <!-- Formulaire de modification -->
                <form method="post" style="display:inline-block;">
                    <?php echo CSRFToken::field(); ?>
                    <input type="hidden" name="id_categorie" value="<?= $c['id_categorie'] ?>">
                    <input type="text" name="nom" value="<?= htmlspecialchars($c['nom']) ?>" class="form-control mb-1" required>
                    <input type="text" name="description" value="<?= htmlspecialchars($c['description']) ?>" class="form-control mb-1">
                    <button type="submit" name="modifier" class="btn btn-warning btn-sm">Modifier</button>
                </form>
                <a href="categories.php?supprimer=<?= $c['id_categorie'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Êtes-vous sûr?');">Supprimer</a>
            </td>
        </tr>
        <?php } ?>
    </tbody>
</table>

<?php require_once("include/footer.php"); ?>

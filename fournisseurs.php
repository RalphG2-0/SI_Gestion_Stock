<?php
$pageTitle = "Gestion des Fournisseurs";
require_once("init.php");
require_once("include/header.php");
require_once("database.php");

$pdo = getPDO();

// CREATE
if (isset($_POST['ajouter'])) {
    if (!isset($_POST[CSRF_TOKEN_NAME]) || !CSRFToken::validate($_POST[CSRF_TOKEN_NAME])) {
        Message::error("Token invalide");
    } else {
        $nom = Validator::validateString($_POST['nom'] ?? '', 1, 100);
        $contact = Validator::validateString($_POST['contact'] ?? '', 0, 100);
        $adresse = Validator::validateString($_POST['adresse'] ?? '', 0, 200);
        
        if ($nom === false) {
            Message::error("Nom invalide");
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO fournisseurs (nom, contact, adresse) VALUES (?, ?, ?)");
                $stmt->execute([$nom, $contact, $adresse]);
                
                $audit = $pdo->prepare("INSERT INTO audit_log (utilisateur_id, action) VALUES (?, ?)");
                $audit->execute([$_SESSION['id'], "Création fournisseur: " . $nom]);
                
                Message::success("Fournisseur ajouté!");
            } catch (Exception $e) {
                Message::error("Erreur: " . $e->getMessage());
            }
        }
    }
}

// UPDATE
if (isset($_POST['modifier'])) {
    if (!isset($_POST[CSRF_TOKEN_NAME]) || !CSRFToken::validate($_POST[CSRF_TOKEN_NAME])) {
        Message::error("Token invalide");
    } else {
        $id = Validator::validateInt($_POST['id_fournisseur'] ?? 0);
        $nom = Validator::validateString($_POST['nom'] ?? '', 1, 100);
        $contact = Validator::validateString($_POST['contact'] ?? '', 0, 100);
        $adresse = Validator::validateString($_POST['adresse'] ?? '', 0, 200);
        
        $errors = [];
        if ($id === false || $id <= 0) $errors[] = "ID invalide";
        if ($nom === false) $errors[] = "Nom invalide";
        
        if (count($errors) > 0) {
            Message::error(implode(", ", $errors));
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE fournisseurs SET nom=?, contact=?, adresse=? WHERE id_fournisseur=?");
                $stmt->execute([$nom, $contact, $adresse, $id]);
                
                $audit = $pdo->prepare("INSERT INTO audit_log (utilisateur_id, action) VALUES (?, ?)");
                $audit->execute([$_SESSION['id'], "Modification fournisseur: " . $nom]);
                
                Message::success("Fournisseur modifié!");
            } catch (Exception $e) {
                Message::error("Erreur: " . $e->getMessage());
            }
        }
    }
}

// DELETE
if (isset($_GET['supprimer'])) {
    $id = Validator::validateInt($_GET['supprimer'] ?? 0);
    if ($id === false || $id <= 0) {
        Message::error("ID invalide");
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM fournisseurs WHERE id_fournisseur=?");
            $stmt->execute([$id]);
            
            $audit = $pdo->prepare("INSERT INTO audit_log (utilisateur_id, action) VALUES (?, ?)");
            $audit->execute([$_SESSION['id'], "Suppression fournisseur ID: " . $id]);
            
            Message::success("Fournisseur supprimé!");
            header("Location: fournisseurs.php");
            exit();
        } catch (Exception $e) {
            Message::error("Erreur: " . $e->getMessage());
        }
    }
}

// READ
$fournisseurs = $pdo->query("SELECT * FROM fournisseurs ORDER BY nom")->fetchAll();
?>

<h1>Fournisseurs</h1>

<?php echo Message::display(); ?>

<!-- Formulaire + tableau comme pour catégories -->
<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title">Ajouter un fournisseur</h5>
        <form method="post">
            <?php echo CSRFToken::field(); ?>
            <input type="text" name="nom" placeholder="Nom" class="form-control mb-2" required>
            <input type="text" name="contact" placeholder="Contact" class="form-control mb-2">
            <input type="text" name="adresse" placeholder="Adresse" class="form-control mb-2">
            <button type="submit" name="ajouter" class="btn btn-success">Ajouter</button>
        </form>
    </div>
</div>

<table class="table table-bordered">
    <thead class="table-dark">
        <tr>
            <th>ID</th>
            <th>Nom</th>
            <th>Contact</th>
            <th>Adresse</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($fournisseurs as $f) { ?>
        <tr>
            <td><?= $f['id_fournisseur'] ?></td>
            <td><?= htmlspecialchars($f['nom']) ?></td>
            <td><?= htmlspecialchars($f['contact']) ?></td>
            <td><?= htmlspecialchars($f['adresse']) ?></td>
            <td>
                <!-- Formulaire de modification -->
                <form method="post" style="display:inline-block;">
                    <?php echo CSRFToken::field(); ?>
                    <input type="hidden" name="id_fournisseur" value="<?= $f['id_fournisseur'] ?>">
                    <input type="text" name="nom" value="<?= htmlspecialchars($f['nom']) ?>" class="form-control mb-1" required>
                    <input type="text" name="contact" value="<?= htmlspecialchars($f['contact']) ?>" class="form-control mb-1">
                    <input type="text" name="adresse" value="<?= htmlspecialchars($f['adresse']) ?>" class="form-control mb-1">
                    <button type="submit" name="modifier" class="btn btn-warning btn-sm">Modifier</button>
                </form>
                <a href="fournisseurs.php?supprimer=<?= $f['id_fournisseur'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Êtes-vous sûr?');">Supprimer</a>
            </td>
        </tr>
        <?php } ?>
    </tbody>
</table>

<?php require_once("include/footer.php"); ?>

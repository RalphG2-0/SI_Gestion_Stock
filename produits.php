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
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : null;
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
<table class="table table-bordered table-hover align-middle">
    <thead class="table-dark">
        <tr>
            <th>#</th>
            <th>Nom</th>
            <th>Description</th>
            <th>Catégorie</th>
            <th class="text-end">Prix unitaire</th>
            <th class="text-center">Stock actuel</th>
            <th class="text-center">Seuil min.</th>
            <th class="text-end">Valeur en stock</th>
            <th class="text-center">Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($produits)): ?>
            <tr><td colspan="9" class="text-center text-muted">Aucun produit enregistré.</td></tr>
        <?php endif; ?>

        <?php foreach ($produits as $p):
            $enAlerte      = $p['stock_actuel'] <= $p['seuil_minimum'];
            $valeurStock   = $p['prix_unitaire'] * $p['stock_actuel'];
            $estEnEdition  = ($editId === (int)$p['id_produit']);
        ?>

        <?php if ($estEnEdition): ?>
        <!-- ── LIGNE EN MODE ÉDITION ─────────────────────────────── -->
        <tr class="table-warning">
            <td><?= $p['id_produit'] ?></td>
            <td colspan="7">
                <form method="post" class="row g-2 align-items-end">
                    <?= CSRFToken::field() ?>
                    <input type="hidden" name="id_produit" value="<?= $p['id_produit'] ?>">
                    <div class="col-md-3">
                        <label class="form-label form-label-sm mb-0">Nom *</label>
                        <input type="text" name="nom"
                               value="<?= htmlspecialchars($p['nom']) ?>"
                               class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label form-label-sm mb-0">Description</label>
                        <input type="text" name="description"
                               value="<?= htmlspecialchars($p['description']) ?>"
                               class="form-control form-control-sm">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label form-label-sm mb-0">Catégorie</label>
                        <select name="categorie_id" class="form-select form-select-sm">
                            <?php foreach ($cats as $cat): ?>
                                <option value="<?= $cat['id_categorie'] ?>"
                                    <?= $p['categorie_id'] == $cat['id_categorie'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['nom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label form-label-sm mb-0">Prix (FCFA) *</label>
                        <input type="number" step="1" min="0" name="prix_unitaire"
                               value="<?= $p['prix_unitaire'] ?>"
                               class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label form-label-sm mb-0">Stock</label>
                        <input type="number" min="0" name="stock_actuel"
                               value="<?= $p['stock_actuel'] ?>"
                               class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label form-label-sm mb-0">Seuil</label>
                        <input type="number" min="0" name="seuil_minimum"
                               value="<?= $p['seuil_minimum'] ?>"
                               class="form-control form-control-sm" required>
                    </div>
                    <div class="col-12 d-flex gap-2 mt-1">
                        <button type="submit" name="modifier" class="btn btn-warning btn-sm">💾 Enregistrer</button>
                        <a href="produits.php" class="btn btn-secondary btn-sm">✖ Annuler</a>
                    </div>
                </form>
            </td>
            <td></td>
        </tr>

        <?php else: ?>
        <!-- ── LIGNE NORMALE ─────────────────────────────────────── -->
        <tr class="<?= $enAlerte ? 'table-danger' : '' ?>">
            <td><?= $p['id_produit'] ?></td>
            <td><?= htmlspecialchars($p['nom']) ?></td>
            <td class="text-muted small"><?= htmlspecialchars($p['description'] ?: '—') ?></td>
            <td><?= htmlspecialchars($p['categorie_nom'] ?: '—') ?></td>

            <!-- Prix unitaire -->
            <td class="text-end text-nowrap fw-semibold">
                <?= number_format($p['prix_unitaire'], 0, ',', ' ') ?> FCFA
            </td>

            <!-- Stock actuel avec badge d'alerte -->
            <td class="text-center">
                <?= $p['stock_actuel'] ?>
                <?php if ($enAlerte): ?>
                    <span class="badge bg-danger ms-1" title="Stock sous le seuil minimum">⚠ Critique</span>
                <?php endif; ?>
            </td>

            <!-- Seuil minimum -->
            <td class="text-center"><?= $p['seuil_minimum'] ?></td>

            <!-- ✅ Valeur totale en stock = prix × quantité -->
            <td class="text-end text-nowrap">
                <?= number_format($valeurStock, 0, ',', ' ') ?> FCFA
            </td>

            <!-- Actions -->
            <td class="text-center text-nowrap">
                <a href="produits.php?edit=<?= $p['id_produit'] ?>"
                   class="btn btn-warning btn-sm">✏ Modifier</a>
                <a href="produits.php?supprimer=<?= $p['id_produit'] ?>"
                   class="btn btn-danger btn-sm"
                   onclick="return confirm('Supprimer « <?= addslashes($p['nom']) ?> » ?');">🗑</a>
            </td>
        </tr>
        <?php endif; ?>

        <?php endforeach; ?>
    </tbody>

    <!-- ✅ Ligne de total général -->
    <?php if (!empty($produits)):
        $totalValeurStock = array_sum(array_map(
            fn($p) => $p['prix_unitaire'] * $p['stock_actuel'],
            $produits
        ));
    ?>
    <tfoot class="table-dark">
        <tr>
            <td colspan="7" class="text-end fw-bold">💰 Valeur totale du stock :</td>
            <td class="text-end fw-bold text-nowrap">
                <?= number_format($totalValeurStock, 0, ',', ' ') ?> FCFA
            </td>
            <td></td>
        </tr>
    </tfoot>
    <?php endif; ?>
</table>

</div>
<?php require_once("include/footer.php"); ?>

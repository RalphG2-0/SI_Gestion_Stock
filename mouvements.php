<?php
$pageTitle = "Mouvements de Stock";
require_once("init.php");
require_once("include/header.php");
require_once("database.php");

$pdo = getPDO();

// CREATE mouvement
if (isset($_POST['ajouter'])) {
    if (!isset($_POST[CSRF_TOKEN_NAME]) || !CSRFToken::validate($_POST[CSRF_TOKEN_NAME])) {
        Message::error("Token de sécurité invalide.");
    } else {
        $produit_id = Validator::validateInt($_POST['produit_id'] ?? 0);
        $type = $_POST['type'] ?? '';
        $quantite = Validator::validateInt($_POST['quantite'] ?? 0);
        
        $errors = [];
        if ($produit_id === false || $produit_id <= 0) $errors[] = "Produit invalide";
        if ($type !== 'entrée' && $type !== 'sortie') $errors[] = "Type invalide";
        if ($quantite === false || $quantite <= 0) $errors[] = "Quantité doit être > 0";
        
        if (count($errors) > 0) {
            Message::error("Erreurs: " . implode(", ", $errors));
        } else {
            try {
                if ($type === 'entrée') {
                    $stmt = $pdo->prepare("INSERT INTO mouvements_stock (produit_id, type, quantite, date_mouvement) VALUES (?, ?, ?, NOW())");
                    $stmt->execute([$produit_id, $type, $quantite]);
                    
                    $pdo->prepare("UPDATE produits SET stock_actuel = stock_actuel + ? WHERE id_produit=?")
                        ->execute([$quantite, $produit_id]);
                    
                    $audit = $pdo->prepare("INSERT INTO audit_log (utilisateur_id, action) VALUES (?, ?)");
                    $audit->execute([$_SESSION['id'], "Mouvement entrée prod#$produit_id qty:$quantite"]);
                    
                    Message::success("Entrée enregistrée!");
                } else {
                    // Vérifier AVANT la sortie que le stock reste >= 0
                    $stmt_check = $pdo->prepare("SELECT stock_actuel, seuil_minimum FROM produits WHERE id_produit=?");
                    $stmt_check->execute([$produit_id]);
                    $product = $stmt_check->fetch();
                    
                    if (!$product) {
                        Message::error("Produit non trouvé");
                    } else {
                        $new_stock = $product['stock_actuel'] - $quantite;
                        
                        if ($new_stock < 0) {
                            Message::error("Sortie impossible : Stock insuffisant! (Actuel: {$product['stock_actuel']}, Demandé: $quantite)");
                        } else {
                            $stmt = $pdo->prepare("INSERT INTO mouvements_stock (produit_id, type, quantite, date_mouvement) VALUES (?, ?, ?, NOW())");
                            $stmt->execute([$produit_id, $type, $quantite]);
                            
                            $pdo->prepare("UPDATE produits SET stock_actuel = ? WHERE id_produit=?")
                                ->execute([$new_stock, $produit_id]);
                            
                            if ($new_stock <= $product['seuil_minimum']) {
                                Message::error("⚠️ Stock faible ! (Seuil: {$product['seuil_minimum']})");
                            } else {
                                Message::success("Sortie enregistrée!");
                            }
                            
                            $audit = $pdo->prepare("INSERT INTO audit_log (utilisateur_id, action) VALUES (?, ?)");
                            $audit->execute([$_SESSION['id'], "Mouvement sortie prod#$produit_id qty:$quantite"]);
                        }
                    }
                }
            } catch (Exception $e) {
                Message::error("Erreur: " . $e->getMessage());
            }
        }
    }
}

// READ mouvements
$mouvements = $pdo->query("SELECT m.*, p.nom AS produit_nom 
                           FROM mouvements_stock m 
                           JOIN produits p ON m.produit_id=p.id_produit
                           ORDER BY m.date_mouvement DESC")->fetchAll();

// READ produits (pour le formulaire)
$produits = $pdo->query("SELECT id_produit, nom FROM produits ORDER BY nom")->fetchAll();
?>

<h1>Mouvements de Stock</h1>

<?php echo Message::display(); ?>

<!-- Formulaire d'ajout -->
<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title">Enregistrer un mouvement</h5>
        <form method="post">
            <?php echo CSRFToken::field(); ?>
            <select name="produit_id" class="form-control mb-2" required>
                <option value="">-- Sélectionner un produit --</option>
                <?php foreach ($produits as $prod) { ?>
                    <option value="<?= $prod['id_produit'] ?>"><?= htmlspecialchars($prod['nom']) ?></option>
                <?php } ?>
            </select>
            <select name="type" class="form-control mb-2" required>
                <option value="">-- Type de mouvement --</option>
                <option value="entrée">📥 Entrée</option>
                <option value="sortie">📤 Sortie</option>
            </select>
            <input type="number" name="quantite" placeholder="Quantité" class="form-control mb-2" min="1" required>
            <button type="submit" name="ajouter" class="btn btn-success">Enregistrer mouvement</button>
        </form>
    </div>
</div>

<!-- Tableau des mouvements -->
<h3>Historique</h3>
<table class="table table-bordered table-sm">
    <thead class="table-dark">
        <tr><th>ID</th><th>Produit</th><th>Type</th><th>Quantité</th><th>Date</th></tr>
    </thead>
    <tbody>
        <?php foreach ($mouvements as $m) { ?>
        <tr>
            <td><?= $m['id_mouvement'] ?></td>
            <td><?= htmlspecialchars($m['produit_nom']) ?></td>
            <td>
                <?php if ($m['type'] === 'entrée') { ?>
                    <span class="badge bg-success">📥 Entrée</span>
                <?php } else { ?>
                    <span class="badge bg-danger">📤 Sortie</span>
                <?php } ?>
            </td>
            <td><?= $m['quantite'] ?></td>
            <td><?= $m['date_mouvement'] ?></td>
        </tr>
        <?php } ?>
    </tbody>
</table>

<?php require_once("include/footer.php"); ?>

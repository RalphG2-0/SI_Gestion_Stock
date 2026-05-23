<?php
require_once("init.php");
require_once("database.php");
require_once("include/header.php");

$pdo = getPDO();

// --- SUPPRESSION COMMANDE ---
if (isset($_GET['delete'])) {
    $id = Validator::validateInt($_GET['delete'] ?? 0);
    if ($id === false || $id <= 0) {
        Message::error("ID invalide");
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM details_commande WHERE commande_id = ?");
            $stmt->execute([$id]);

            $stmt = $pdo->prepare("DELETE FROM commandes WHERE id_commande = ?");
            $stmt->execute([$id]);

            $audit = $pdo->prepare("INSERT INTO audit_log (utilisateur_id, action) VALUES (?, ?)");
            $audit->execute([$_SESSION['id'], "Suppression commande ID: ".$id]);

            Message::success("Commande supprimée!");
            header("Location: commandes.php");
            exit();
        } catch (Exception $e) {
            Message::error("Erreur: " . $e->getMessage());
        }
    }
}

// --- AJOUT COMMANDE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['date_commande'])) {
    if (!isset($_POST[CSRF_TOKEN_NAME]) || !CSRFToken::validate($_POST[CSRF_TOKEN_NAME])) {
        Message::error("Token invalide");
    } else {
        $date_commande = Validator::validateDate($_POST['date_commande'] ?? '');
        $fournisseur_id = Validator::validateInt($_POST['fournisseur_id'] ?? 0);
        $aujourdhui = date('Y-m-d');

        $errors = [];
        if ($date_commande === false) $errors[] = "Date invalide";
        if ($fournisseur_id === false || $fournisseur_id <= 0) $errors[] = "Fournisseur invalide";
        if ($date_commande && $date_commande < $aujourdhui) $errors[] = "Date doit être >= aujourd'hui";

        if (count($errors) > 0) {
            Message::error(implode(", ", $errors));
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO commandes (date_commande, fournisseur_id) VALUES (?, ?)");
                $stmt->execute([$date_commande, $fournisseur_id]);
                $commande_id = $pdo->lastInsertId();

                if (isset($_POST['produits'])) {
                    foreach ($_POST['produits'] as $produit_id => $quantite) {
                        $produit_id = Validator::validateInt($produit_id);
                        $quantite = Validator::validateInt($quantite ?? 0);
                        if ($produit_id !== false && $quantite !== false && $quantite > 0) {
                            $stmt = $pdo->prepare("INSERT INTO details_commande (commande_id, produit_id, quantite) VALUES (?, ?, ?)");
                            $stmt->execute([$commande_id, $produit_id, $quantite]);
                        }
                    }
                }
                
                $audit = $pdo->prepare("INSERT INTO audit_log (utilisateur_id, action) VALUES (?, ?)");
                $audit->execute([$_SESSION['id'], "Création commande ID: ".$commande_id]);

                Message::success("Commande créée!");
                header("Location: commandes.php");
                exit();
            } catch (Exception $e) {
                Message::error("Erreur: " . $e->getMessage());
            }
        }
    }
}

// --- PAGINATION + RECHERCHE ---
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$sql = "SELECT c.*, f.nom AS fournisseur_nom FROM commandes c JOIN fournisseurs f ON c.fournisseur_id=f.id_fournisseur";
$params = [];
if ($search) {
    $sql .= " WHERE f.nom LIKE ?";
    $params[] = "%$search%";
}
// Récupérer toutes les commandes avec fournisseur
$sql = "SELECT c.*, f.nom AS fournisseur_nom 
        FROM commandes c 
        JOIN fournisseurs f ON c.fournisseur_id=f.id_fournisseur 
        ORDER BY c.date_commande DESC 
        LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$commandes = $stmt->fetchAll();

// Récupérer les détails de chaque commande
$details = $pdo->query("SELECT d.*, p.nom AS produit_nom 
                        FROM details_commande d 
                        JOIN produits p ON d.produit_id=p.id_produit")->fetchAll();

// Organiser les détails par commande_id
$details_par_commande = [];
foreach ($details as $d) {
    $details_par_commande[$d['commande_id']][] = $d;
}
$fournisseurs = $pdo->query("SELECT * FROM fournisseurs")->fetchAll();
$produits = $pdo->query("SELECT * FROM produits")->fetchAll();

?>

<div class="container mt-4">
    <h1>Commandes</h1>

    <?php echo Message::display(); ?>

    <form method="post" class="mb-4">
     <?= CSRFToken::field() ?>
    <div class="mb-3">
            <label>Date commande</label>
           <input type="date" name="date_commande" class="form-control" min="<?= date('Y-m-d') ?>">
        </div>
        <div class="mb-3">
            <label>Fournisseur</label>
            <select name="fournisseur_id" class="form-select">
                <?php foreach ($fournisseurs as $f): ?>
                    <option value="<?= $f['id_fournisseur'] ?>"><?= htmlspecialchars($f['nom']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <h4>Produits</h4>
        <?php foreach ($produits as $p): ?>
            <div class="mb-2">
                <label><?= htmlspecialchars($p['nom']) ?></label>
                <input type="number" name="produits[<?= $p['id_produit'] ?>]" placeholder="Quantité" class="form-control">
            </div>
        <?php endforeach; ?>
        <button type="submit" class="btn btn-primary">Créer commande</button>
    </form>

    <form method="get" class="mb-3">
        <input type="text" name="search" placeholder="Rechercher fournisseur" value="<?= htmlspecialchars($search) ?>" class="form-control">
    </form>

    <table class="table table-bordered">
    <thead class="table-dark">
        <tr><th>ID</th><th>Date</th><th>Fournisseur</th><th>Détails</th><th>Actions</th></tr>
    </thead>
    <tbody>
        <?php foreach ($commandes as $c): ?>
        <tr>
            <td><?= $c['id_commande'] ?></td>
            <td><?= $c['date_commande'] ?></td>
            <td><?= htmlspecialchars($c['fournisseur_nom']) ?></td>
            <td>
                <?php if (!empty($details_par_commande[$c['id_commande']])): ?>
                    <ul>
                        <?php foreach ($details_par_commande[$c['id_commande']] as $d): ?>
                            <li><?= htmlspecialchars($d['produit_nom']) ?> — Quantité: <?= $d['quantite'] ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <em>Aucun produit</em>
                <?php endif; ?>
            </td>
            <td>
                <a href="commandes.php?delete=<?= $c['id_commande'] ?>" 
                   class="btn btn-danger" 
                   onclick="return confirm('Supprimer cette commande ?');">Supprimer</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>


<?php require_once("include/footer.php"); ?>
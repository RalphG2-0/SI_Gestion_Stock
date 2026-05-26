<?php
require_once("init.php");
require_once("database.php");
require_once("include/header.php");

$pdo = getPDO();

// --- VALIDATION / ANNULATION COMMANDE (gestionnaire uniquement) ---
if (isset($_GET['valider']) || isset($_GET['annuler'])) {
    checkGestionnaire();

    $action = isset($_GET['valider']) ? 'valider' : 'annuler';
    $id = Validator::validateInt($action === 'valider' ? $_GET['valider'] : $_GET['annuler']);

    if ($id === false || $id <= 0) {
        Message::error("ID invalide");
    } else {
        if (!isset($_GET[CSRF_TOKEN_NAME]) || !CSRFToken::validate($_GET[CSRF_TOKEN_NAME])) {
            Message::error("Token de sécurité invalide. Veuillez réessayer.");
        } else {
            try {
                $stmt = $pdo->prepare("SELECT * FROM commandes WHERE id_commande = ?");
                $stmt->execute([$id]);
                $commande = $stmt->fetch();

                if (!$commande) {
                    Message::error("Commande introuvable.");
                } elseif ($commande['statut'] !== 'en attente') {
                    Message::error("Seules les commandes « en attente » peuvent être modifiées.");
                } else {
                    if ($action === 'valider') {
                        $nouveau_statut = 'validée';
                        $libelle_audit  = 'Validation commande ID: ' . $id;
                        $msg_ok         = "Commande #$id validée avec succès !";
                    } else {
                        $nouveau_statut = 'annulée';
                        $libelle_audit  = 'Annulation commande ID: ' . $id;
                        $msg_ok         = "Commande #$id annulée.";
                    }

                    $upd = $pdo->prepare(
                        "UPDATE commandes SET statut = ?, validé_par = ? WHERE id_commande = ?"
                    );
                    $upd->execute([$nouveau_statut, $_SESSION['id'], $id]);

                    $audit = $pdo->prepare(
                        "INSERT INTO audit_log (utilisateur_id, action) VALUES (?, ?)"
                    );
                    $audit->execute([$_SESSION['id'], $libelle_audit]);

                    Message::success($msg_ok);
                    header("Location: commandes.php");
                    exit();
                }
            } catch (Exception $e) {
                Message::error("Erreur : " . $e->getMessage());
            }
        }
    }
}

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
            $audit->execute([$_SESSION['id'], "Suppression commande ID: " . $id]);

            Message::success("Commande supprimée !");
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
        $date_commande  = Validator::validateDate($_POST['date_commande'] ?? '');
        $fournisseur_id = Validator::validateInt($_POST['fournisseur_id'] ?? 0);
        $aujourdhui     = date('Y-m-d');

        $errors = [];
        if ($date_commande === false)                          $errors[] = "Date invalide";
        if ($fournisseur_id === false || $fournisseur_id <= 0) $errors[] = "Fournisseur invalide";
        if ($date_commande && $date_commande < $aujourdhui)    $errors[] = "Date doit être >= aujourd'hui";

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
                        $quantite   = Validator::validateInt($quantite ?? 0);

                        if ($produit_id !== false && $quantite !== false && $quantite > 0) {
                            // ✅ Récupérer le prix unitaire actuel du produit
                            $prixStmt = $pdo->prepare(
                                "SELECT prix_unitaire FROM produits WHERE id_produit = ?"
                            );
                            $prixStmt->execute([$produit_id]);
                            $produitRow = $prixStmt->fetch();
                            $prix_unitaire = $produitRow ? (float)$produitRow['prix_unitaire'] : 0.00;

                            // ✅ Insérer avec le prix unitaire correctement renseigné
                            $stmt = $pdo->prepare(
                                "INSERT INTO details_commande
                                    (commande_id, produit_id, quantite, prix_unitaire)
                                 VALUES (?, ?, ?, ?)"
                            );
                            $stmt->execute([$commande_id, $produit_id, $quantite, $prix_unitaire]);
                        }
                    }
                }

                $audit = $pdo->prepare("INSERT INTO audit_log (utilisateur_id, action) VALUES (?, ?)");
                $audit->execute([$_SESSION['id'], "Création commande ID: " . $commande_id]);

                Message::success("Commande créée !");
                header("Location: commandes.php");
                exit();
            } catch (Exception $e) {
                Message::error("Erreur: " . $e->getMessage());
            }
        }
    }
}

// --- PAGINATION + RECHERCHE ---
$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit  = 10;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$params = [];
$where  = '';
if ($search) {
    $where    = " WHERE f.nom LIKE ?";
    $params[] = "%$search%";
}

// Récupérer les commandes avec fournisseur et valideur
$sql = "SELECT c.*,
               f.nom AS fournisseur_nom,
               u.nom AS valideur_nom
        FROM commandes c
        JOIN fournisseurs f ON c.fournisseur_id = f.id_fournisseur
        LEFT JOIN utilisateurs u ON c.validé_par = u.id_utilisateur
        $where
        ORDER BY c.date_commande DESC
        LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$commandes = $stmt->fetchAll();

// ✅ Récupérer les détails avec prix_unitaire et sous-total calculé
$details = $pdo->query(
    "SELECT d.*,
            p.nom AS produit_nom,
            p.prix_unitaire AS prix_catalogue,
            (d.quantite * d.prix_unitaire) AS sous_total
     FROM details_commande d
     JOIN produits p ON d.produit_id = p.id_produit"
)->fetchAll();

$details_par_commande = [];
foreach ($details as $d) {
    $details_par_commande[$d['commande_id']][] = $d;
}

$fournisseurs = $pdo->query("SELECT * FROM fournisseurs")->fetchAll();
$produits     = $pdo->query("SELECT * FROM produits")->fetchAll();

// Générer le token CSRF pour les liens GET (validation/annulation)
$csrf_token = CSRFToken::generate();

// Helper : badge Bootstrap selon statut
function badgeStatut(string $statut): string {
    return match($statut) {
        'validée' => '<span class="badge bg-success">✔ Validée</span>',
        'annulée' => '<span class="badge bg-danger">✖ Annulée</span>',
        'livrée'  => '<span class="badge bg-primary">📦 Livrée</span>',
        default   => '<span class="badge bg-warning text-dark">⏳ En attente</span>',
    };
}
?>

<div class="container mt-4">
    <h1>📦 Commandes</h1>

    <?php echo Message::display(); ?>

    <!-- ============================================================
         FORMULAIRE CRÉATION COMMANDE
    ============================================================ -->
    <div class="card mb-4">
        <div class="card-header fw-bold">➕ Nouvelle commande</div>
        <div class="card-body">
            <form method="post">
                <?= CSRFToken::field() ?>
                <div class="mb-3">
                    <label class="form-label">Date commande</label>
                    <input type="date" name="date_commande" class="form-control" min="<?= date('Y-m-d') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Fournisseur</label>
                    <select name="fournisseur_id" class="form-select">
                        <?php foreach ($fournisseurs as $f): ?>
                            <option value="<?= $f['id_fournisseur'] ?>"><?= htmlspecialchars($f['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <h5>Produits à commander</h5>
                <table class="table table-sm table-bordered mb-3">
                    <thead class="table-light">
                        <tr>
                            <th>Produit</th>
                            <th>Prix unitaire</th>
                            <th>Quantité</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produits as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['nom']) ?></td>
                            <td><?= number_format($p['prix_unitaire'], 0, ',', ' ') ?> FCFA</td>
                            <td>
                                <input type="number"
                                       name="produits[<?= $p['id_produit'] ?>]"
                                       placeholder="0"
                                       class="form-control form-control-sm"
                                       min="0">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <button type="submit" class="btn btn-primary">Créer la commande</button>
            </form>
        </div>
    </div>

    <!-- ============================================================
         RECHERCHE
    ============================================================ -->
    <form method="get" class="mb-3 d-flex gap-2">
        <input type="text" name="search" placeholder="Rechercher un fournisseur…"
               value="<?= htmlspecialchars($search) ?>" class="form-control">
        <button class="btn btn-outline-secondary">🔍</button>
    </form>

    <!-- ============================================================
         TABLEAU DES COMMANDES
    ============================================================ -->
    <table class="table table-bordered align-middle">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Fournisseur</th>
                <th>Produits commandés</th>
                <th class="text-end">Total</th>
                <th>Statut</th>
                <?php if ($_SESSION['role'] === 'gestionnaire'): ?>
                <th>Validé par</th>
                <th>Actions</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($commandes)): ?>
                <tr><td colspan="8" class="text-center text-muted">Aucune commande trouvée.</td></tr>
            <?php endif; ?>

            <?php foreach ($commandes as $c):
                $lignes       = $details_par_commande[$c['id_commande']] ?? [];
                $total_general = array_sum(array_column($lignes, 'sous_total'));
            ?>
            <tr>
                <td><?= $c['id_commande'] ?></td>
                <td><?= htmlspecialchars($c['date_commande']) ?></td>
                <td><?= htmlspecialchars($c['fournisseur_nom']) ?></td>

                <!-- ✅ Détails avec prix unitaire et sous-total -->
                <td>
                    <?php if (!empty($lignes)): ?>
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th class="fw-normal text-muted" style="font-size:.85em">Produit</th>
                                    <th class="fw-normal text-muted text-end" style="font-size:.85em">P.U.</th>
                                    <th class="fw-normal text-muted text-center" style="font-size:.85em">Qté</th>
                                    <th class="fw-normal text-muted text-end" style="font-size:.85em">Sous-total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lignes as $d): ?>
                                <tr>
                                    <td><?= htmlspecialchars($d['produit_nom']) ?></td>
                                    <td class="text-end text-nowrap">
                                        <?= number_format($d['prix_unitaire'], 0, ',', ' ') ?> FCFA
                                    </td>
                                    <td class="text-center"><?= $d['quantite'] ?></td>
                                    <td class="text-end text-nowrap">
                                        <?= number_format($d['sous_total'], 0, ',', ' ') ?> FCFA
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <em class="text-muted">Aucun produit</em>
                    <?php endif; ?>
                </td>

                <!-- ✅ Total général de la commande -->
                <td class="text-end fw-bold text-nowrap">
                    <?= $total_general > 0
                        ? number_format($total_general, 0, ',', ' ') . ' FCFA'
                        : '<em class="text-muted fw-normal">—</em>' ?>
                </td>

                <!-- Statut -->
                <td><?= badgeStatut($c['statut']) ?></td>

                <?php if ($_SESSION['role'] === 'gestionnaire'): ?>
                <!-- Valideur -->
                <td>
                    <?= $c['valideur_nom']
                        ? htmlspecialchars($c['valideur_nom'])
                        : '<em class="text-muted">—</em>' ?>
                </td>

                <!-- Actions gestionnaire -->
                <td>
                    <?php if ($c['statut'] === 'en attente'): ?>
                        <a href="commandes.php?valider=<?= $c['id_commande'] ?>&<?= CSRF_TOKEN_NAME ?>=<?= urlencode($csrf_token) ?>"
                           class="btn btn-success btn-sm"
                           onclick="return confirm('Valider la commande #<?= $c['id_commande'] ?> ?');">
                           ✔ Valider
                        </a>
                        <a href="commandes.php?annuler=<?= $c['id_commande'] ?>&<?= CSRF_TOKEN_NAME ?>=<?= urlencode($csrf_token) ?>"
                           class="btn btn-warning btn-sm"
                           onclick="return confirm('Annuler la commande #<?= $c['id_commande'] ?> ?');">
                           ✖ Annuler
                        </a>
                    <?php else: ?>
                        <span class="text-muted small">—</span>
                    <?php endif; ?>

                    <a href="commandes.php?delete=<?= $c['id_commande'] ?>"
                       class="btn btn-danger btn-sm"
                       onclick="return confirm('Supprimer définitivement la commande #<?= $c['id_commande'] ?> ?');">
                       🗑
                    </a>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once("include/footer.php"); ?>

<?php
$pageTitle = "Rapports globaux";
require_once("database.php");
require_once("include\\tcpdf\\tcpdf.php");

$pdo = getPDO();

// --- Gestion des filtres ---
$date_debut = $_GET['date_debut'] ?? null;
$date_fin   = $_GET['date_fin'] ?? null;
$where = "";
$params = [];

if ($date_debut && $date_fin) {
    $where = " WHERE c.date_commande BETWEEN ? AND ?";
    $params = [$date_debut, $date_fin];
}

// --- Récupération des données (utilisées pour affichage et export) ---
$sql = "SELECT c.id_commande, c.date_commande, f.nom AS fournisseur 
        FROM commandes c 
        JOIN fournisseurs f ON c.fournisseur_id=f.id_fournisseur $where
        ORDER BY c.date_commande DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$commandes = $stmt->fetchAll();

$produits = $pdo->query("SELECT * FROM produits")->fetchAll();
$mouvements = $pdo->query("SELECT m.*, p.nom AS produit 
                           FROM mouvements_stock m 
                           JOIN produits p ON m.produit_id=p.id_produit 
                           ORDER BY m.date_mouvement DESC")->fetchAll();

// --- Export PDF ---
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    $pdf = new TCPDF();
    $pdf->SetAuthor('SI Gestion Stock');
    $pdf->SetTitle('Rapport Global');
    $pdf->SetMargins(10, 10, 10);
    $pdf->AddPage();

    $pdf->SetFont('dejavusans','',12); // ✅ police UTF-8

    $html = "<h1 style='text-align:center;'>Rapport Global (filtré)</h1>";

    // --- Commandes ---
    $html .= "<h2 style='color:#2c3e50;'>Commandes</h2>
    <table border='1' cellpadding='6' cellspacing='0' style='width:100%; border-collapse:collapse;'>
    <tr style='background-color:#f2f2f2; font-weight:bold; text-align:center;'>
        <th>ID</th><th>Date</th><th>Fournisseur</th>
    </tr>";
    foreach ($commandes as $c) {
        $html .= "<tr>
            <td align='center'>{$c['id_commande']}</td>
            <td align='center'>{$c['date_commande']}</td>
            <td>{$c['fournisseur']}</td>
        </tr>";
    }
    $html .= "</table><hr style='margin:15px 0;'>";

    // --- Produits ---
    $html .= "<h2 style='color:#27ae60;'>Produits</h2>
    <table border='1' cellpadding='6' cellspacing='0' style='width:100%; border-collapse:collapse;'>
    <tr style='background-color:#f2f2f2; font-weight:bold; text-align:center;'>
        <th>ID</th><th>Nom</th><th>Stock</th><th>Seuil</th>
    </tr>";
    foreach ($produits as $p) {
        $html .= "<tr>
            <td align='center'>{$p['id_produit']}</td>
            <td>{$p['nom']}</td>
            <td align='center'>{$p['stock_actuel']}</td>
            <td align='center'>{$p['seuil_minimum']}</td>
        </tr>";
    }
    $html .= "</table><hr style='margin:15px 0;'>";

    // --- Mouvements ---
    $html .= "<h2 style='color:#e67e22;'>Mouvements</h2>
    <table border='1' cellpadding='6' cellspacing='0' style='width:100%; border-collapse:collapse;'>
    <tr style='background-color:#f2f2f2; font-weight:bold; text-align:center;'>
        <th>ID</th><th>Produit</th><th>Type</th><th>Quantité</th><th>Date</th>
    </tr>";
    foreach ($mouvements as $m) {
        $html .= "<tr>
            <td align='center'>{$m['id_mouvement']}</td>
            <td>{$m['produit']}</td>
            <td align='center'>{$m['type']}</td>
            <td align='center'>{$m['quantite']}</td>
            <td align='center'>{$m['date_mouvement']}</td>
        </tr>";
    }
    $html .= "</table>";

    // --- Graphiques exportés en images ---
    if (file_exists("charts/chartCommandes.png")) {
        $html .= "<h2>Graphique Commandes</h2><img src='charts/chartCommandes.png' width='500'>";
    }
    if (file_exists("charts/chartProduits.png")) {
        $html .= "<h2>Graphique Produits</h2><img src='charts/chartProduits.png' width='500'>";
    }
    if (file_exists("charts/chartMouvements.png")) {
        $html .= "<h2>Graphique Mouvements</h2><img src='charts/chartMouvements.png' width='500'>";
    }

    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output("Rapport_Global.pdf", "D");
    exit();
}




// --- Export CSV ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename=rapport_global.csv');
    $output = fopen("php://output", "w");

    fputcsv($output, ["ID", "Date", "Fournisseur"]);
    $sql = "SELECT c.id_commande, c.date_commande, f.nom AS fournisseur 
            FROM commandes c 
            JOIN fournisseurs f ON c.fournisseur_id=f.id_fournisseur $where
            ORDER BY c.date_commande DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit();
}

// --- Récupération des données pour affichage HTML ---
$sql = "SELECT c.id_commande, c.date_commande, f.nom AS fournisseur 
        FROM commandes c 
        JOIN fournisseurs f ON c.fournisseur_id=f.id_fournisseur $where
        ORDER BY c.date_commande DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$commandes = $stmt->fetchAll();

$produits = $pdo->query("SELECT * FROM produits")->fetchAll();
$mouvements = $pdo->query("SELECT m.*, p.nom AS produit 
                           FROM mouvements_stock m 
                           JOIN produits p ON m.produit_id=p.id_produit 
                           ORDER BY m.date_mouvement DESC")->fetchAll();

require_once("include/header.php");
?>

<div class="container mt-4">
    <h1>Rapports Globaux</h1>

    <!-- Formulaire filtre -->
    <form method="get" class="mb-3 row">
        <div class="col">
            <label>Du</label>
            <input type="date" name="date_debut" class="form-control" value="<?= htmlspecialchars($_GET['date_debut'] ?? '') ?>">
        </div>
        <div class="col">
            <label>Au</label>
            <input type="date" name="date_fin" class="form-control" value="<?= htmlspecialchars($_GET['date_fin'] ?? '') ?>">
        </div>
        <div class="col-auto align-self-end">
            <button type="submit" class="btn btn-primary">Filtrer</button>
        </div>
    </form>

    <!-- Boutons export -->
    <div class="mb-3">
        <a href="rapports.php?export=csv&date_debut=<?= urlencode($date_debut) ?>&date_fin=<?= urlencode($date_fin) ?>" class="btn btn-success">Exporter en CSV</a>
        <a href="rapports.php?export=pdf&date_debut=<?= urlencode($date_debut) ?>&date_fin=<?= urlencode($date_fin) ?>" class="btn btn-danger">Exporter en PDF</a>
    </div>

    <!-- Tableaux -->
    <h3>Commandes</h3>
    <table class="table table-bordered">
        <thead class="table-dark"><tr><th>ID</th><th>Date</th><th>Fournisseur</th></tr></thead>
        <tbody>
            <?php foreach ($commandes as $c): ?>
            <tr><td><?= $c['id_commande'] ?></td><td><?= $c['date_commande'] ?></td><td><?= htmlspecialchars($c['fournisseur']) ?></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>

       <h3>Produits</h3>
    <table class="table table-bordered">
        <thead class="table-dark"><tr><th>ID</th><th>Nom</th><th>Stock actuel</th><th>Seuil minimum</th></tr></thead>
        <tbody>
            <?php foreach ($produits as $p): ?>
            <tr>
                <td><?= $p['id_produit'] ?></td>
                <td><?= htmlspecialchars($p['nom']) ?></td>
                <td><?= $p['stock_actuel'] ?></td>
                <td>
                    <?= $p['seuil_minimum'] ?>
                    <?php if ($p['stock_actuel'] <= $p['seuil_minimum']): ?>
                        <div class="alert alert-danger">⚠️ Stock faible pour ce produit !</div>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h3>Mouvements</h3>
    <table class="table table-bordered">
        <thead class="table-dark"><tr><th>ID</th><th>Produit</th><th>Type</th><th>Quantité</th><th>Date</th></tr></thead>
        <tbody>
            <?php foreach ($mouvements as $m): ?>
            <tr>
                <td><?= $m['id_mouvement'] ?></td>
                <td><?= htmlspecialchars($m['produit']) ?></td>
                <td><?= $m['type'] ?></td>
                <td><?= $m['quantite'] ?></td>
                <td><?= $m['date_mouvement'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Graphiques -->
    <h3>Statistiques</h3>
    <div class="row">
        <div class="col-md-4"><canvas id="chartCommandes"></canvas></div>
        <div class="col-md-4"><canvas id="chartProduits"></canvas></div>
        <div class="col-md-4"><canvas id="chartMouvements"></canvas></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Commandes par fournisseur
    const commandesLabels = <?= json_encode(array_column($commandes, 'fournisseur')) ?>;
    const commandesData = <?= json_encode(array_column($commandes, 'id_commande')) ?>;

    new Chart(document.getElementById('chartCommandes'), {
        type: 'bar',
        data: {
            labels: commandesLabels,
            datasets: [{
                label: 'Nombre de commandes',
                data: commandesData,
                backgroundColor: 'rgba(54, 162, 235, 0.7)'
            }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });

    // Produits stock
    const produitsLabels = <?= json_encode(array_column($produits, 'nom')) ?>;
    const produitsStock = <?= json_encode(array_column($produits, 'stock_actuel')) ?>;

    new Chart(document.getElementById('chartProduits'), {
        type: 'pie',
        data: {
            labels: produitsLabels,
            datasets: [{
                label: 'Stock actuel',
                data: produitsStock,
                backgroundColor: ['#ff6384','#36a2eb','#cc65fe','#ffce56','#4bc0c0','#9966ff']
            }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });

    // Mouvements par type
    const mouvementsLabels = <?= json_encode(array_column($mouvements, 'type')) ?>;
    const mouvementsQuantite = <?= json_encode(array_column($mouvements, 'quantite')) ?>;

    new Chart(document.getElementById('chartMouvements'), {
        type: 'line',
        data: {
            labels: mouvementsLabels,
            datasets: [{
                label: 'Quantité',
                data: mouvementsQuantite,
                borderColor: 'rgba(255, 99, 132, 1)',
                fill: false,
                tension: 0.1
            }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });
</script>

<?php require_once("include/footer.php"); ?>

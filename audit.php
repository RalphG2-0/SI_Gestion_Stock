<?php
$pageTitle = "Historique des actions";
require_once("init.php");
require_once("include/header.php");
require_once("database.php");

checkGestionnaire();

$pdo = getPDO();

// --- SUPPRESSION HISTORIQUE AUDIT ---
if (isset($_GET['clear_audit'])) {
    if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
        // Redirection pour confirmation
        ?>
        <h1>Historique des actions</h1>
        <div class="alert alert-warning">
            <strong>⚠️ Attention!</strong> Êtes-vous sûr de vouloir supprimer TOUT l'historique d'audit? Cette action est irréversible.
            <br><br>
            <a href="audit.php?clear_audit=1&confirm=yes" class="btn btn-danger btn-sm">Oui, supprimer tout</a>
            <a href="audit.php" class="btn btn-secondary btn-sm">Annuler</a>
        </div>
        <?php
        require_once("include/footer.php");
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("TRUNCATE TABLE audit_log");
        $stmt->execute();
        
        Message::success("Historique d'audit vidé!");
        header("Location: audit.php");
        exit();
    } catch (Exception $e) {
        Message::error("Erreur: " . $e->getMessage());
    }
}

$audit = $pdo->query("SELECT a.*, u.nom 
                      FROM audit_log a 
                      JOIN utilisateurs u ON a.utilisateur_id=u.id_utilisateur 
                      ORDER BY a.date_action DESC")->fetchAll();
?>

<h1>Historique des actions</h1>

<?php echo Message::display(); ?>

<div class="mb-3">
    <a href="audit.php?clear_audit=1" class="btn btn-warning">🗑️ Vider l'historique</a>
</div>

<table class="table table-bordered table-sm">
    <thead class="table-dark">
        <tr><th>ID</th><th>Utilisateur</th><th>Action</th><th>Date</th></tr>
    </thead>
    <tbody>
        <?php if (empty($audit)) { ?>
            <tr><td colspan="4" class="text-center text-muted">Aucune action enregistrée</td></tr>
        <?php } else { ?>
            <?php foreach ($audit as $log) { ?>
            <tr>
                <td><?= $log['id'] ?></td>
                <td><?= htmlspecialchars($log['nom']) ?></td>
                <td><?= htmlspecialchars($log['action']) ?></td>
                <td><?= $log['date_action'] ?></td>
            </tr>
            <?php } ?>
        <?php } ?>
    </tbody>
</table>

<?php require_once("include/footer.php"); ?>

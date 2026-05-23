<?php
$pageTitle = "Création d'Utilisateur";
require_once("init.php");
require_once("include/header.php");
require_once("database.php");

checkGestionnaire(); // Seulement les gestionnaires peuvent créer

$pdo = getPDO();

if (isset($_POST['ajouter'])) {
    if (!isset($_POST[CSRF_TOKEN_NAME]) || !CSRFToken::validate($_POST[CSRF_TOKEN_NAME])) {
        Message::error("Token invalide");
    } else {
        $nom = Validator::validateString($_POST['nom'] ?? '', 1, 100);
        $login = Validator::validateString($_POST['login'] ?? '', 3, 50);
        $password = $_POST['mot_de_passe'] ?? '';
        $role = $_POST['role'] ?? '';
        
        $errors = [];
        if ($nom === false) $errors[] = "Nom invalide (1-100 caractères)";
        if ($login === false) $errors[] = "Login invalide (3-50 caractères)";
        if ($role !== 'gestionnaire' && $role !== 'employe') $errors[] = "Rôle invalide";
        
        // Validation password force
        if (strlen($password) < 8) {
            $errors[] = "Mot de passe trop court (min 8 caractères)";
        } elseif (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Mot de passe doit avoir des minuscules";
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Mot de passe doit avoir des majuscules";
        } elseif (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Mot de passe doit avoir des chiffres";
        }
        
        // Vérifier login unique
        if ($login !== false) {
            $stmt = $pdo->prepare("SELECT id_utilisateur FROM utilisateurs WHERE login = ?");
            $stmt->execute([$login]);
            if ($stmt->fetch()) {
                $errors[] = "Ce login existe déjà";
            }
        }
        
        if (count($errors) > 0) {
            Message::error("Erreurs: " . implode(", ", $errors));
        } else {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO utilisateurs (nom, login, mot_de_passe, role, date_creation) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$nom, $login, $hash, $role]);
                
                $audit = $pdo->prepare("INSERT INTO audit_log (utilisateur_id, action) VALUES (?, ?)");
                $audit->execute([$_SESSION['id'], "Création utilisateur: " . $login . " (" . $role . ")"]);
                
                Message::success("Utilisateur créé avec succès!");
            } catch (Exception $e) {
                Message::error("Erreur: " . $e->getMessage());
            }
        }
    }
}
?>

<h1>Créer un utilisateur</h1>

<?php echo Message::display(); ?>

<div class="card">
    <div class="card-body">
        <form method="post">
            <?php echo CSRFToken::field(); ?>
            
            <div class="mb-3">
                <label class="form-label">Nom</label>
                <input type="text" name="nom" class="form-control" required>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Login</label>
                <input type="text" name="login" class="form-control" placeholder="Min 3 caractères" required>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Mot de passe</label>
                <input type="password" name="mot_de_passe" class="form-control" placeholder="Min 8 chars, majuscules, minuscules, chiffres" required>
                <small class="text-muted">Requis: 8+ caractères, majuscules, minuscules, chiffres</small>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Rôle</label>
                <select name="role" class="form-control">
                    <option value="employe">Employé</option>
                    <option value="gestionnaire">Gestionnaire</option>
                </select>
            </div>
            
            <button type="submit" name="ajouter" class="btn btn-success">Créer utilisateur</button>
        </form>
    </div>
</div>
<?php require_once("include/footer.php"); ?>

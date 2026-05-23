<?php
session_start();
require_once("database.php");

$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE login=? LIMIT 1");
    $stmt->execute([$_POST['login']]);
    $user = $stmt->fetch();

    if ($user && password_verify($_POST['mot_de_passe'], $user['mot_de_passe'])) {
        $_SESSION['id'] = $user['id_utilisateur'];
        $_SESSION['nom'] = $user['nom'];
        $_SESSION['role'] = $user['role'];
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Login ou mot de passe incorrect.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion</title>
    <link href="include/bootstrap-5.0.2-dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="include/style.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="height:100vh;">
    <div class="card shadow-lg" style="width: 400px;">
        <div class="card-body">
            <h3 class="card-title text-center mb-4">🔐 Connexion</h3>
            
            <?php if ($error) { ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php } ?>

            <form method="post">
                <div class="mb-3">
                    <label for="login" class="form-label">Login</label>
                    <input type="text" name="login" id="login" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="mot_de_passe" class="form-label">Mot de passe</label>
                    <input type="password" name="mot_de_passe" id="mot_de_passe" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Se connecter</button>
            </form>
        </div>
        <div class="card-footer text-center text-muted">
            © 2026 SI Stock – Uriel
        </div>
         <label class="theme-switch">
    <input type="checkbox" id="toggleDarkMode">
    <span class="slider"></span>
</label>
<span class="ms-2">🌙 Mode sombre</span>

    </div>
    <script src="include/script.js"></script>
</body>
</html>

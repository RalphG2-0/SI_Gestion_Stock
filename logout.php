<?php
session_start();
session_unset();   // supprime toutes les variables de session
session_destroy(); // détruit la session

header("Location: login.php"); // redirection vers la page de connexion
exit();
?>

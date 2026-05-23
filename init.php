<?php
session_start();
if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

require_once("config.php");
require_once("include/helpers.php");

function checkGestionnaire() {
    if ($_SESSION['role'] !== 'gestionnaire') {
        echo "Accès refusé. Réservé au gestionnaire.";
        exit();
    }
}
?>

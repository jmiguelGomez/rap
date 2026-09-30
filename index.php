<?php
// La raíz es pública; quienes ya tienen sesión continúan en su panel.
if (isset($_COOKIE[session_name()])) {
    session_start(['read_and_close' => true]);
    if (!empty($_SESSION['user']['autenticado'])) {
        header('Location: /rap/dashboard.php');
        exit;
    }
}
require __DIR__ . '/portada.php';

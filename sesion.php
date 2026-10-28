<?php
// sesion.php - Devuelve en JSON si hay sesión activa.
// Lo usa script.js para que las páginas .html cambien "Login" por "Mi panel / Cerrar sesión".
session_start();
header('Content-Type: application/json; charset=utf-8');

$activa = isset($_SESSION['id_usuario']);
$panel  = '#';
if ($activa) {
    $panel = ($_SESSION['rol'] === 'ADMIN') ? 'panel_admin.php'
           : (($_SESSION['rol'] === 'USU_GRAL') ? 'panel_cliente.php' : 'index.html');
}
echo json_encode(['activa' => $activa, 'panel' => $panel]);

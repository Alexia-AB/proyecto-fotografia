<?php
// sesion.php - Devuelve en JSON si hay sesión activa.
// Lo usa script.js para que las páginas .html cambien "Login" por "Mi panel / Cerrar sesión".
// Sprint 3: si el usuario fue desactivado por el administrador, se cierra su sesión.
session_start();
require_once 'includes/conexion.php';
header('Content-Type: application/json; charset=utf-8');

$activa = isset($_SESSION['id_usuario']);

if ($activa) {
    $stmt = $pdo->prepare('SELECT activo FROM tb_usuarios WHERE id_usuario = :id');
    $stmt->execute([':id' => $_SESSION['id_usuario']]);
    $activo = $stmt->fetchColumn();
    if ($activo === false || (int)$activo !== 1) {   // desactivado o eliminado
        $_SESSION = [];
        session_destroy();
        $activa = false;
    }
}

$panel = '#';
if ($activa) {
    $panel = ($_SESSION['rol'] === 'ADMIN') ? 'panel_admin.php'
           : (($_SESSION['rol'] === 'USU_GRAL') ? 'panel_cliente.php' : 'index.html');
}
echo json_encode(['activa' => $activa, 'panel' => $panel]);

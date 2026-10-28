<?php
// ==========================================================================
// conexion.php - Conexión a MySQL con PDO (XAMPP local)
// Se incluye en las demás páginas con: require_once 'includes/conexion.php';
// ==========================================================================

$host    = 'localhost';
$bd      = 'dain_studio';
$usuario = 'root';   // usuario por defecto de XAMPP
$clave   = '';       // contraseña por defecto de XAMPP (vacía)

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$bd;charset=utf8mb4",
        $usuario,
        $clave,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // errores como excepciones
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // arreglos asociativos
            PDO::ATTR_EMULATE_PREPARES   => false,                    // prepared statements reales
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    die('No se pudo conectar a la base de datos. Revisa que MySQL esté encendido en XAMPP y que dain_studio exista.');
}

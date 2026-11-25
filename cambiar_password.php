<?php
// ==========================================================================
// cambiar_password.php - Cambio de contraseña del cliente (Sprint 4, DP01)
//   Pide la contraseña actual, la nueva y su confirmación.
//   password_verify (actual) + password_hash (nueva) + nuevo id de sesión.
// ==========================================================================
session_start();
require_once 'includes/conexion.php';

// Solo clientes (USU_GRAL) con sesión y cuenta activa
if (!isset($_SESSION['id_usuario'])) {
    header('Location: login.php');
    exit;
}
if ($_SESSION['rol'] !== 'USU_GRAL') {
    header('Location: index.html');
    exit;
}

$idUsuario = (int)$_SESSION['id_usuario'];

$chk = $pdo->prepare('SELECT activo FROM tb_usuarios WHERE id_usuario = :id');
$chk->execute([':id' => $idUsuario]);
if ((int)$chk->fetchColumn() !== 1) {
    header('Location: logout.php');
    exit;
}

// Token CSRF
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $errores[] = 'Sesión de formulario inválida. Recarga la página e intenta de nuevo.';
    }

    $actual    = (string)($_POST['password_actual'] ?? '');
    $nueva     = (string)($_POST['password_nueva'] ?? '');
    $confirmar = (string)($_POST['password_confirmar'] ?? '');

    if ($actual === '') {
        $errores[] = 'Escribe tu contraseña actual.';
    }
    // 72 bytes es el máximo que usa bcrypt
    if (strlen($nueva) < 8 || strlen($nueva) > 72) {
        $errores[] = 'La nueva contraseña debe tener entre 8 y 72 caracteres.';
    }
    if ($nueva !== $confirmar) {
        $errores[] = 'La confirmación no coincide con la nueva contraseña.';
    }
    if ($actual !== '' && $nueva === $actual) {
        $errores[] = 'La nueva contraseña debe ser distinta de la actual.';
    }

    if (!$errores) {
        // password_verify contra el hash guardado
        $stmt = $pdo->prepare('SELECT password FROM tb_usuarios WHERE id_usuario = :id LIMIT 1');
        $stmt->execute([':id' => $idUsuario]);
        $hashGuardado = (string)$stmt->fetchColumn();

        if (!password_verify($actual, $hashGuardado)) {
            $errores[] = 'La contraseña actual es incorrecta.';
        }
    }

    if (!$errores) {
        try {
            $up = $pdo->prepare('UPDATE tb_usuarios SET password = :p WHERE id_usuario = :id');
            $up->execute([':p' => password_hash($nueva, PASSWORD_DEFAULT), ':id' => $idUsuario]);

            // Nuevo id de sesión (y nuevo token) después de un cambio de contraseña
            session_regenerate_id(true);
            $_SESSION['csrf']  = bin2hex(random_bytes(32));
            $_SESSION['flash'] = '✓ Tu contraseña se cambió correctamente.';
            header('Location: panel_cliente.php');
            exit;
        } catch (PDOException $ex) {
            $errores[] = 'No se pudo cambiar la contraseña. Intenta de nuevo.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambiar contraseña | DAIN Studio</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/gestion.css">
</head>
<body>

    <header class="navbar">
        <a href="index.html" class="logo-link">
            <div class="logo-container">
                <span class="logo-main">DAIN</span>
                <span class="logo-sub">STUDIO</span>
            </div>
        </a>
        <nav>
            <button id="btn-hamburguesa">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <ul class="nav-links">
                <li><a href="index.html">Inicio</a></li>
                <li><a href="comercial.html">Comercial</a></li>
                <li><a href="retrato.html">Retrato</a></li>
                <li><a href="proyectos.html">Proyectos</a></li>
                <li><a href="nosotros.html">Nosotros</a></li>
                <li><a href="contacto.html">Contacto</a></li>
                <li><a href="panel_cliente.php" class="active">Mi panel</a></li>
                <li><a href="logout.php">Cerrar Sesión</a></li>
            </ul>
        </nav>
    </header>

    <main class="panel-container">
        <section class="panel-header">
            <h1>Cambiar contraseña</h1>
            <p>Escribe tu contraseña actual y elige una nueva</p>
        </section>

        <p class="volver"><a href="panel_cliente.php">&larr; Volver a mi panel</a></p>

        <section class="ficha">
            <?php foreach ($errores as $err): ?>
                <p class="alerta alerta-error"><?= e($err) ?></p>
            <?php endforeach; ?>

            <form method="POST" action="cambiar_password.php" class="ficha-form">
                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">

                <div class="form-group-login">
                    <label for="password_actual">Contraseña actual</label>
                    <input type="password" id="password_actual" name="password_actual"
                           autocomplete="current-password" required>
                </div>
                <div class="form-group-login">
                    <label for="password_nueva">Nueva contraseña (mínimo 8 caracteres)</label>
                    <input type="password" id="password_nueva" name="password_nueva"
                           minlength="8" maxlength="72" autocomplete="new-password" required>
                </div>
                <div class="form-group-login">
                    <label for="password_confirmar">Confirmar nueva contraseña</label>
                    <input type="password" id="password_confirmar" name="password_confirmar"
                           minlength="8" maxlength="72" autocomplete="new-password" required>
                </div>

                <div class="ficha-acciones">
                    <button type="submit" class="btn btn-primary">Cambiar contraseña</button>
                    <a href="panel_cliente.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </section>
    </main>

    <footer>
        <p>&copy; 2026 DAIN Studio. | Materia: Ingeniería de Software II</p>
    </footer>

    <script src="js/script.js"></script>
    <script>
        // Validación del lado del cliente: la confirmación debe coincidir
        (function () {
            var nueva = document.getElementById('password_nueva');
            var conf  = document.getElementById('password_confirmar');
            function revisar() {
                conf.setCustomValidity(conf.value !== nueva.value ? 'Las contraseñas no coinciden.' : '');
            }
            nueva.addEventListener('input', revisar);
            conf.addEventListener('input', revisar);
        })();
    </script>
</body>
</html>

<?php
// ==========================================================================
// registro.php - Registro de nuevos clientes (RG-01 a RG-08)
// Pide: nombre, email, teléfono, usuario y password.
// El usuario nuevo queda con rol USU_GRAL (rol 4 = cliente).
// ==========================================================================
session_start();
require_once 'includes/conexion.php';

// Si ya hay sesión, no tiene caso registrarse
if (isset($_SESSION['id_usuario'])) {
    header('Location: ' . ($_SESSION['rol'] === 'ADMIN' ? 'panel_admin.php'
                        : ($_SESSION['rol'] === 'USU_GRAL' ? 'panel_cliente.php' : 'index.html')));
    exit;
}

// Token CSRF
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$errores = [];
$form = ['nombre_completo' => '', 'email' => '', 'telefono' => '', 'usuario' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $errores[] = 'Sesión de formulario inválida. Recarga la página e intenta de nuevo.';
    }

    $form['nombre_completo'] = trim($_POST['nombre_completo'] ?? '');
    $form['email']           = trim($_POST['email'] ?? '');
    $form['telefono']        = trim($_POST['telefono'] ?? '');
    $form['usuario']         = trim($_POST['usuario'] ?? '');
    $password                = $_POST['password'] ?? '';

    // RG-02: validaciones en PHP (además de las de HTML5)
    if ($form['nombre_completo'] === '' || mb_strlen($form['nombre_completo']) > 100) {
        $errores[] = 'El nombre es obligatorio (máximo 100 caracteres).';
    }
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($form['email']) > 100) {
        $errores[] = 'Ingresa un correo electrónico válido.';
    }
    if (!preg_match('/^[0-9+\s-]{8,20}$/', $form['telefono'])) {
        $errores[] = 'Ingresa un teléfono válido (solo números, 8 a 20 dígitos).';
    }
    if (!preg_match('/^[A-Za-z0-9_.]{4,50}$/', $form['usuario'])) {
        $errores[] = 'El usuario debe tener de 4 a 50 caracteres (letras, números, punto o guion bajo).';
    }
    if (strlen($password) < 8 || strlen($password) > 72) {
        $errores[] = 'La contraseña debe tener entre 8 y 72 caracteres.';
    }

    // Usuario y correo no deben existir ya
    if (!$errores) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM tb_usuarios WHERE usuario = :u');
        $stmt->execute([':u' => $form['usuario']]);
        if ($stmt->fetchColumn() > 0) {
            $errores[] = 'Ese nombre de usuario ya está en uso.';
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM tb_detalle_usuarios WHERE email = :e');
        $stmt->execute([':e' => $form['email']]);
        if ($stmt->fetchColumn() > 0) {
            $errores[] = 'Ese correo ya está registrado.';
        }
    }

    if (!$errores) {
        try {
            // Transacción: se insertan las 3 tablas o ninguna
            $pdo->beginTransaction();

            // RG-03 y RG-04: password con hash + rol USU_GRAL (rol 4)
            $ins1 = $pdo->prepare('INSERT INTO tb_usuarios (usuario, password, rol) VALUES (:u, :p, :r)');
            $ins1->execute([
                ':u' => $form['usuario'],
                ':p' => password_hash($password, PASSWORD_DEFAULT),
                ':r' => 'USU_GRAL',
            ]);
            $idUsuario = (int)$pdo->lastInsertId();

            // RG-05: datos generales
            $ins2 = $pdo->prepare('INSERT INTO tb_detalle_usuarios (id_usuario, nombre_completo, email, telefono)
                                   VALUES (:id, :n, :e, :t)');
            $ins2->execute([
                ':id' => $idUsuario,
                ':n'  => $form['nombre_completo'],
                ':e'  => $form['email'],
                ':t'  => $form['telefono'],
            ]);

            // RG-06: datos del cliente
            $ins3 = $pdo->prepare('INSERT INTO tb_clientes (id_usuario, nombre_completo, email, telefono)
                                   VALUES (:id, :n, :e, :t)');
            $ins3->execute([
                ':id' => $idUsuario,
                ':n'  => $form['nombre_completo'],
                ':e'  => $form['email'],
                ':t'  => $form['telefono'],
            ]);

            $pdo->commit();

            // RG-07: mensaje de éxito (login.php lo muestra con ?registro=ok)
            header('Location: login.php?registro=ok');
            exit;
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // 23000 = violación de UNIQUE (por si dos personas se registran al mismo tiempo)
            if ($ex->getCode() === '23000') {
                $errores[] = 'El usuario o el correo ya están registrados.';
            } else {
                $errores[] = 'No se pudo completar el registro. Intenta de nuevo.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro | DAIN Studio</title>
    <link rel="stylesheet" href="css/styles.css">
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
                <li><a href="comercial.html">Comercial</a></li>
                <li><a href="retrato.html">Retrato</a></li>
                <li><a href="proyectos.html">Proyectos</a></li>
                <li><a href="nosotros.html">Nosotros</a></li>
                <li><a href="contacto.html">Contacto</a></li>
                <li id="li-login"><a href="login.php" class="active">Login</a></li>
            </ul>
        </nav>
    </header>

    <main class="auth-container">
        <div class="auth-card">
            <h1 class="auth-title">Crear Cuenta</h1>
            <p class="auth-subtitle">Regístrate como cliente de DAIN Studio</p>

            <?php foreach ($errores as $err): ?>
                <p class="alerta alerta-error"><?= e($err) ?></p>
            <?php endforeach; ?>

            <form method="POST" action="registro.php" class="auth-form">
                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">

                <div class="form-group-login">
                    <label for="nombre_completo">Nombre completo</label>
                    <input type="text" id="nombre_completo" name="nombre_completo" maxlength="100"
                           placeholder="Ingrese su nombre" value="<?= e($form['nombre_completo']) ?>" required autofocus>
                </div>
                <div class="form-group-login">
                    <label for="email">Correo electrónico</label>
                    <input type="email" id="email" name="email" maxlength="100"
                           placeholder="correo@ejemplo.com" value="<?= e($form['email']) ?>" required>
                </div>
                <div class="form-group-login">
                    <label for="telefono">Teléfono</label>
                    <input type="tel" id="telefono" name="telefono" maxlength="20"
                           pattern="[0-9+\s\-]{8,20}" title="Solo números, de 8 a 20 dígitos"
                           placeholder="2221234567" value="<?= e($form['telefono']) ?>" required>
                </div>
                <div class="form-group-login">
                    <label for="usuario">Usuario</label>
                    <input type="text" id="usuario" name="usuario" minlength="4" maxlength="50"
                           pattern="[A-Za-z0-9_.]{4,50}" title="Letras, números, punto o guion bajo (mínimo 4)"
                           placeholder="Elige un usuario" value="<?= e($form['usuario']) ?>" required>
                </div>
                <div class="form-group-login">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" minlength="8" maxlength="72"
                           placeholder="Mínimo 8 caracteres" required>
                </div>
                <button type="submit" class="btn btn-primary btn-submit-login">Registrarme</button>
            </form>

            <p class="auth-extra">¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a></p>
        </div>
    </main>

    <footer>
        <p>&copy; 2026 DAIN Studio. | Materia: Ingeniería de Software II</p>
    </footer>

    <script src="js/script.js"></script>
</body>
</html>

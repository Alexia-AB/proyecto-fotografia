<?php
// ==========================================================================
// login.php - Login contra MySQL (LK01-LK05)
// ==========================================================================
session_start();
require_once 'includes/conexion.php';

// Según el rol, a dónde se manda al usuario
function destinoPorRol($rol) {
    switch ($rol) {
        case 'ADMIN':    return 'panel_admin.php';
        case 'USU_GRAL': return 'panel_cliente.php';
        default:         return 'index.html'; // PROGRAMADOR / USUARIA_TEST (sin panel por ahora)
    }
}

// Si ya hay sesión, no tiene caso mostrar el login
if (isset($_SESSION['id_usuario'])) {
    header('Location: ' . destinoPorRol($_SESSION['rol']));
    exit;
}

$error = '';
$usuarioIngresado = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuarioIngresado = trim($_POST['usuario'] ?? '');
    $password         = $_POST['password'] ?? '';

    if ($usuarioIngresado === '' || $password === '') {
        $error = 'Ingresa tu usuario y contraseña.';
    } else {
        // Prepared statement: evita SQL Injection
        $stmt = $pdo->prepare('SELECT id_usuario, usuario, password, rol FROM tb_usuarios WHERE usuario = :u LIMIT 1');
        $stmt->execute([':u' => $usuarioIngresado]);
        $fila = $stmt->fetch();

        if ($fila && password_verify($password, $fila['password'])) {
            session_regenerate_id(true);               // seguridad: nuevo id de sesión
            $_SESSION['id_usuario'] = (int)$fila['id_usuario'];
            $_SESSION['usuario']    = $fila['usuario'];
            $_SESSION['rol']        = $fila['rol'];

            header('Location: ' . destinoPorRol($fila['rol']));
            exit;
        }
        $error = 'Usuario o contraseña incorrectos.';
    }
}

$registroOk = isset($_GET['registro']) && $_GET['registro'] === 'ok';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión | DAIN Studio</title>
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
            <h1 class="auth-title">Iniciar Sesión</h1>
            <p class="auth-subtitle">Accede a tu panel de DAIN Studio</p>

            <?php if ($registroOk): ?>
                <p class="alerta alerta-exito">✓ Registro exitoso. Ya puedes iniciar sesión.</p>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <p class="alerta alerta-error"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>

            <form method="POST" action="login.php" class="auth-form">
                <div class="form-group-login">
                    <label for="usuario">Usuario</label>
                    <input type="text" id="usuario" name="usuario" placeholder="Ingrese su usuario"
                           value="<?= htmlspecialchars($usuarioIngresado) ?>" required autofocus>
                </div>
                <div class="form-group-login">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" placeholder="Ingrese su contraseña" required>
                </div>
                <button type="submit" class="btn btn-primary btn-submit-login">Ingresar</button>
            </form>

            <p class="auth-extra">¿No tienes cuenta? <a href="registro.php">Regístrate aquí</a></p>
        </div>
    </main>

    <footer>
        <p>&copy; 2026 DAIN Studio. | Materia: Ingeniería de Software II</p>
    </footer>

    <script src="js/script.js"></script>
</body>
</html>

<?php
// panel_admin.php - Panel del administrador: ficha de datos + edición (PA-01 a PA-05)
session_start();
require_once 'includes/conexion.php';

// PA-01: solo usuarios con sesión y rol ADMIN
if (!isset($_SESSION['id_usuario'])) {
    header('Location: login.php');
    exit;
}
if ($_SESSION['rol'] !== 'ADMIN') {
    header('Location: index.html');
    exit;
}

$idUsuario = $_SESSION['id_usuario'];

// Token CSRF para el formulario de edición
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$errores  = [];
$editando = isset($_GET['editar']);

// PA-04: guardar cambios (UPDATE en TB_DETALLE_USUARIOS)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $editando = true;

    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $errores[] = 'Sesión de formulario inválida. Intenta de nuevo.';
    }

    $nombre   = trim($_POST['nombre_completo'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');

    // PA-05: validaciones
    if ($nombre === '' || mb_strlen($nombre) > 100) {
        $errores[] = 'El nombre es obligatorio (máximo 100 caracteres).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
        $errores[] = 'Ingresa un correo electrónico válido.';
    }
    if (!preg_match('/^[0-9+\s-]{8,20}$/', $telefono)) {
        $errores[] = 'Ingresa un teléfono válido (solo números, 8 a 20 dígitos).';
    }

    // El correo no debe pertenecer a otro usuario
    if (!$errores) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM tb_detalle_usuarios WHERE email = :e AND id_usuario <> :id');
        $stmt->execute([':e' => $email, ':id' => $idUsuario]);
        if ($stmt->fetchColumn() > 0) {
            $errores[] = 'Ese correo ya está registrado por otro usuario.';
        }
    }

    if (!$errores) {
        try {
            $up = $pdo->prepare('UPDATE tb_detalle_usuarios
                                 SET nombre_completo = :n, email = :e, telefono = :t
                                 WHERE id_usuario = :id');
            $up->execute([':n' => $nombre, ':e' => $email, ':t' => $telefono, ':id' => $idUsuario]);

            $_SESSION['flash'] = '✓ Tus datos se actualizaron correctamente.';
            header('Location: panel_admin.php');
            exit;
        } catch (PDOException $e) {
            $errores[] = 'No se pudieron guardar los cambios. Intenta de nuevo.';
        }
    }
}

// PA-03: consultar datos del admin logueado (TB_USUARIOS + TB_DETALLE_USUARIOS)
$stmt = $pdo->prepare('SELECT u.usuario, u.rol,
                              d.nombre_completo, d.email, d.telefono, d.fecha_registro
                       FROM tb_usuarios u
                       INNER JOIN tb_detalle_usuarios d ON d.id_usuario = u.id_usuario
                       WHERE u.id_usuario = :id LIMIT 1');
$stmt->execute([':id' => $idUsuario]);
$admin = $stmt->fetch();

if (!$admin) {
    // Sesión de un usuario que ya no existe
    header('Location: logout.php');
    exit;
}

// Si hubo error al guardar, se conservan los valores escritos
if ($errores && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = ['nombre_completo' => $nombre, 'email' => $email, 'telefono' => $telefono];
} else {
    $form = $admin;
}

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$inicial = mb_strtoupper(mb_substr($admin['nombre_completo'], 0, 1));
$fecha   = date('d/m/Y', strtotime($admin['fecha_registro']));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración | DAIN Studio</title>
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
                <li><a href="index.html">Inicio</a></li>
                <li><a href="comercial.html">Comercial</a></li>
                <li><a href="retrato.html">Retrato</a></li>
                <li><a href="proyectos.html">Proyectos</a></li>
                <li><a href="contacto.html">Contacto</a></li>
                <li><a href="panel_admin.php" class="active">Mi panel</a></li>
                <li><a href="logout.php">Cerrar Sesión</a></li>
            </ul>
        </nav>
    </header>

    <main class="panel-container">
        <section class="panel-header">
            <h1>Panel de Administración</h1>
            <p>Bienvenido, <?= e($admin['nombre_completo']) ?></p>
        </section>

        <?php if ($flash): ?>
            <p class="alerta alerta-exito"><?= e($flash) ?></p>
        <?php endif; ?>

        <section class="ficha">
            <div class="ficha-encabezado">
                <div class="ficha-avatar"><?= e($inicial) ?></div>
                <div>
                    <h2><?= e($admin['nombre_completo']) ?></h2>
                    <span class="ficha-rol">Administrador</span>
                </div>
            </div>

            <?php if (!$editando): ?>
                <!-- PA-02: ficha de datos (solo lectura) -->
                <dl class="ficha-datos">
                    <div><dt>Usuario</dt><dd><?= e($admin['usuario']) ?></dd></div>
                    <div><dt>Rol</dt><dd><?= e($admin['rol']) ?></dd></div>
                    <div><dt>Correo electrónico</dt><dd><?= e($admin['email']) ?></dd></div>
                    <div><dt>Teléfono</dt><dd><?= e($admin['telefono']) ?></dd></div>
                    <div><dt>Registrado desde</dt><dd><?= e($fecha) ?></dd></div>
                </dl>
                <div class="ficha-acciones">
                    <a href="panel_admin.php?editar=1" class="btn btn-primary">Editar mis datos</a>
                </div>

            <?php else: ?>
                <!-- PA-04/PA-05: formulario de edición -->
                <?php foreach ($errores as $err): ?>
                    <p class="alerta alerta-error"><?= e($err) ?></p>
                <?php endforeach; ?>

                <form method="POST" action="panel_admin.php?editar=1" class="ficha-form">
                    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">

                    <div class="form-group-login">
                        <label>Usuario</label>
                        <input type="text" value="<?= e($admin['usuario']) ?>" disabled>
                    </div>
                    <div class="form-group-login">
                        <label>Rol</label>
                        <input type="text" value="<?= e($admin['rol']) ?>" disabled>
                    </div>
                    <div class="form-group-login">
                        <label for="nombre_completo">Nombre completo</label>
                        <input type="text" id="nombre_completo" name="nombre_completo" maxlength="100"
                               value="<?= e($form['nombre_completo']) ?>" required>
                    </div>
                    <div class="form-group-login">
                        <label for="email">Correo electrónico</label>
                        <input type="email" id="email" name="email" maxlength="100"
                               value="<?= e($form['email']) ?>" required>
                    </div>
                    <div class="form-group-login">
                        <label for="telefono">Teléfono</label>
                        <input type="tel" id="telefono" name="telefono" maxlength="20"
                               value="<?= e($form['telefono']) ?>" required>
                    </div>

                    <div class="ficha-acciones">
                        <button type="submit" class="btn btn-primary">Guardar cambios</button>
                        <a href="panel_admin.php" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    </main>

    <footer>
        <p>&copy; 2026 DAIN Studio. | Materia: Ingeniería de Software II</p>
    </footer>

    <script src="js/script.js"></script>
</body>
</html>

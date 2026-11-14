<?php
// ==========================================================================
// gestion_clientes.php - Administración de clientes (Sprint 3, CL01-CL08)
//   - Lista de clientes con su estado (activo / desactivado)
//   - Activar / desactivar (sin eliminar al cliente)
//   - Editar datos del cliente (usuario, rol y fecha NO son modificables)
// ==========================================================================
session_start();
require_once 'includes/conexion.php';

// CL01: solo el ADMIN con sesión activa
if (!isset($_SESSION['id_usuario'])) {
    header('Location: login.php');
    exit;
}
if ($_SESSION['rol'] !== 'ADMIN') {
    header('Location: index.html');
    exit;
}

// Token CSRF para todos los formularios de esta página
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$errores = [];
$idEditar = isset($_GET['editar']) ? (int)$_GET['editar'] : 0;

// Consulta de un cliente (rol USU_GRAL) por id, con sus datos de contacto
function obtenerCliente(PDO $pdo, int $id) {
    $stmt = $pdo->prepare("SELECT u.id_usuario, u.usuario, u.rol, u.activo,
                                  d.nombre_completo, d.email, d.telefono, d.fecha_registro,
                                  c.direccion
                           FROM tb_usuarios u
                           INNER JOIN tb_detalle_usuarios d ON d.id_usuario = u.id_usuario
                           LEFT JOIN tb_clientes c ON c.id_usuario = u.id_usuario
                           WHERE u.id_usuario = :id AND u.rol = 'USU_GRAL'
                           LIMIT 1");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch();
}

// --------------------------------------------------------------------------
// POST: activar / desactivar (CL03)  y  guardar edición (CL06, CL07)
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $_SESSION['flash_error'] = 'Sesión de formulario inválida. Intenta de nuevo.';
        header('Location: gestion_clientes.php');
        exit;
    }

    // ---- Activar / desactivar ----
    if ($accion === 'estado') {
        $id     = (int)($_POST['id_usuario'] ?? 0);
        $nuevo  = (($_POST['nuevo_estado'] ?? '') === '1') ? 1 : 0;
        $cli    = obtenerCliente($pdo, $id);   // solo clientes: nunca se toca a un admin

        if (!$cli) {
            $_SESSION['flash_error'] = 'El cliente no existe.';
        } else {
            $up = $pdo->prepare("UPDATE tb_usuarios SET activo = :a WHERE id_usuario = :id AND rol = 'USU_GRAL'");
            $up->execute([':a' => $nuevo, ':id' => $id]);
            $_SESSION['flash'] = $nuevo === 1
                ? '✓ Se activó a ' . $cli['nombre_completo'] . '. Ya puede iniciar sesión.'
                : '✓ Se desactivó a ' . $cli['nombre_completo'] . '. Ya no podrá iniciar sesión.';
        }
        header('Location: gestion_clientes.php');
        exit;
    }

    // ---- Guardar edición ----
    if ($accion === 'editar') {
        $idEditar  = (int)($_POST['id_usuario'] ?? 0);
        $nombre    = trim($_POST['nombre_completo'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $telefono  = trim($_POST['telefono'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');

        if (!obtenerCliente($pdo, $idEditar)) {
            $_SESSION['flash_error'] = 'El cliente no existe.';
            header('Location: gestion_clientes.php');
            exit;
        }

        // CL07: validaciones
        if ($nombre === '' || mb_strlen($nombre) > 100) {
            $errores[] = 'El nombre es obligatorio (máximo 100 caracteres).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
            $errores[] = 'Ingresa un correo electrónico válido.';
        }
        if (!preg_match('/^[0-9+\s-]{8,20}$/', $telefono)) {
            $errores[] = 'Ingresa un teléfono válido (solo números, 8 a 20 dígitos).';
        }
        if (mb_strlen($direccion) > 255) {
            $errores[] = 'La dirección es demasiado larga (máximo 255 caracteres).';
        }

        // El correo no debe pertenecer a otro usuario
        if (!$errores) {
            $dup = $pdo->prepare('SELECT COUNT(*) FROM tb_detalle_usuarios WHERE email = :e AND id_usuario <> :id');
            $dup->execute([':e' => $email, ':id' => $idEditar]);
            if ($dup->fetchColumn() > 0) {
                $errores[] = 'Ese correo ya está registrado por otro usuario.';
            }
        }

        if (!$errores) {
            try {
                // Transacción: se actualizan las dos tablas o ninguna
                $pdo->beginTransaction();

                $u1 = $pdo->prepare('UPDATE tb_detalle_usuarios
                                     SET nombre_completo = :n, email = :e, telefono = :t
                                     WHERE id_usuario = :id');
                $u1->execute([':n' => $nombre, ':e' => $email, ':t' => $telefono, ':id' => $idEditar]);

                $existe = $pdo->prepare('SELECT COUNT(*) FROM tb_clientes WHERE id_usuario = :id');
                $existe->execute([':id' => $idEditar]);

                if ($existe->fetchColumn() > 0) {
                    $u2 = $pdo->prepare('UPDATE tb_clientes
                                         SET nombre_completo = :n, email = :e, telefono = :t, direccion = :d
                                         WHERE id_usuario = :id');
                    $u2->execute([':n' => $nombre, ':e' => $email, ':t' => $telefono, ':d' => $direccion, ':id' => $idEditar]);
                } else {
                    $i2 = $pdo->prepare('INSERT INTO tb_clientes (id_usuario, nombre_completo, email, telefono, direccion)
                                         VALUES (:id, :n, :e, :t, :d)');
                    $i2->execute([':id' => $idEditar, ':n' => $nombre, ':e' => $email, ':t' => $telefono, ':d' => $direccion]);
                }

                $pdo->commit();
                $_SESSION['flash'] = '✓ Los datos de ' . $nombre . ' se actualizaron correctamente.';
                header('Location: gestion_clientes.php');
                exit;
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                $errores[] = 'No se pudieron guardar los cambios. Intenta de nuevo.';
            }
        }
    }
}

// --------------------------------------------------------------------------
// Datos para mostrar
// --------------------------------------------------------------------------
$flash      = $_SESSION['flash'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash'], $_SESSION['flash_error']);

$cliente = null;
if ($idEditar > 0) {
    $cliente = obtenerCliente($pdo, $idEditar);
    if (!$cliente) {
        $_SESSION['flash_error'] = 'El cliente no existe.';
        header('Location: gestion_clientes.php');
        exit;
    }
    // Si hubo errores al guardar, se conservan los valores escritos
    if ($errores && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $form = ['nombre_completo' => $nombre, 'email' => $email, 'telefono' => $telefono, 'direccion' => $direccion];
    } else {
        $form = $cliente;
    }
} else {
    // CL02: lista de clientes
    $lista = $pdo->query("SELECT u.id_usuario, u.usuario, u.activo,
                                 d.nombre_completo, d.email, d.telefono
                          FROM tb_usuarios u
                          INNER JOIN tb_detalle_usuarios d ON d.id_usuario = u.id_usuario
                          WHERE u.rol = 'USU_GRAL'
                          ORDER BY d.nombre_completo ASC")->fetchAll();
    $total     = count($lista);
    $activos   = count(array_filter($lista, function ($c) { return (int)$c['activo'] === 1; }));
    $inactivos = $total - $activos;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Clientes | DAIN Studio</title>
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
                <li><a href="panel_admin.php" class="active">Mi panel</a></li>
                <li><a href="logout.php">Cerrar Sesión</a></li>
            </ul>
        </nav>
    </header>

    <main class="panel-container panel-ancho">
        <section class="panel-header">
            <h1>Gestión de Clientes</h1>
            <p>Activa o desactiva clientes y actualiza sus datos</p>
        </section>

        <p class="volver"><a href="panel_admin.php">&larr; Volver a mi panel</a></p>

        <?php if ($flash): ?>
            <p class="alerta alerta-exito"><?= e($flash) ?></p>
        <?php endif; ?>
        <?php if ($flashError): ?>
            <p class="alerta alerta-error"><?= e($flashError) ?></p>
        <?php endif; ?>

        <?php if ($cliente): ?>
            <!-- ================= CL06: EDITAR CLIENTE ================= -->
            <section class="ficha">
                <div class="ficha-encabezado">
                    <div class="ficha-avatar"><?= e(mb_strtoupper(mb_substr($cliente['nombre_completo'], 0, 1))) ?></div>
                    <div>
                        <h2>Editar cliente</h2>
                        <span class="estado <?= (int)$cliente['activo'] === 1 ? 'estado-activo' : 'estado-inactivo' ?>">
                            <?= (int)$cliente['activo'] === 1 ? 'Activo' : 'Desactivado' ?>
                        </span>
                    </div>
                </div>

                <?php foreach ($errores as $err): ?>
                    <p class="alerta alerta-error"><?= e($err) ?></p>
                <?php endforeach; ?>

                <form method="POST" action="gestion_clientes.php?editar=<?= (int)$cliente['id_usuario'] ?>" class="ficha-form">
                    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" name="id_usuario" value="<?= (int)$cliente['id_usuario'] ?>">

                    <p class="nota-campos">Los campos en gris no se pueden modificar.</p>

                    <div class="form-group-login">
                        <label>Usuario</label>
                        <input type="text" value="<?= e($cliente['usuario']) ?>" disabled>
                    </div>
                    <div class="form-group-login">
                        <label>Rol</label>
                        <input type="text" value="Cliente" disabled>
                    </div>
                    <div class="form-group-login">
                        <label>Fecha de registro</label>
                        <input type="text" value="<?= e(date('d/m/Y', strtotime($cliente['fecha_registro']))) ?>" disabled>
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
                    <div class="form-group-login">
                        <label for="direccion">Dirección</label>
                        <input type="text" id="direccion" name="direccion" maxlength="255"
                               value="<?= e($form['direccion']) ?>">
                    </div>

                    <div class="ficha-acciones">
                        <button type="submit" class="btn btn-primary">Guardar cambios</button>
                        <a href="gestion_clientes.php" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </section>

        <?php else: ?>
            <!-- ================= CL02: LISTA DE CLIENTES ================= -->
            <section class="resumen-clientes">
                <div><strong><?= $total ?></strong><span>Clientes</span></div>
                <div><strong><?= $activos ?></strong><span>Activos</span></div>
                <div><strong><?= $inactivos ?></strong><span>Desactivados</span></div>
            </section>

            <section class="tabla-wrapper">
                <?php if ($total === 0): ?>
                    <p class="tabla-vacia">Todavía no hay clientes registrados.</p>
                <?php else: ?>
                    <table class="tabla-datos">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Teléfono</th>
                                <th>Usuario</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lista as $c): ?>
                                <?php $esActivo = ((int)$c['activo'] === 1); ?>
                                <tr class="<?= $esActivo ? '' : 'fila-inactiva' ?>">
                                    <td data-label="Nombre">
                                        <?= e($c['nombre_completo']) ?>
                                        <small><?= e($c['email']) ?></small>
                                    </td>
                                    <td data-label="Teléfono"><?= e($c['telefono']) ?></td>
                                    <td data-label="Usuario"><?= e($c['usuario']) ?></td>
                                    <td data-label="Estado">
                                        <span class="estado <?= $esActivo ? 'estado-activo' : 'estado-inactivo' ?>">
                                            <?= $esActivo ? 'Activo' : 'Desactivado' ?>
                                        </span>
                                    </td>
                                    <td data-label="Acciones" class="celda-acciones">
                                        <a href="gestion_clientes.php?editar=<?= (int)$c['id_usuario'] ?>" class="btn btn-sm btn-secondary">Editar</a>

                                        <form method="POST" action="gestion_clientes.php" class="form-inline"
                                              <?php if ($esActivo): ?>
                                              onsubmit="return confirm('¿Desactivar a este cliente? No podrá iniciar sesión hasta que lo actives de nuevo.');"
                                              <?php endif; ?>>
                                            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                                            <input type="hidden" name="accion" value="estado">
                                            <input type="hidden" name="id_usuario" value="<?= (int)$c['id_usuario'] ?>">
                                            <input type="hidden" name="nuevo_estado" value="<?= $esActivo ? '0' : '1' ?>">
                                            <button type="submit" class="btn btn-sm <?= $esActivo ? 'btn-peligro' : 'btn-primary' ?>">
                                                <?= $esActivo ? 'Desactivar' : 'Activar' ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>

    <footer>
        <p>&copy; 2026 DAIN Studio. Todos los derechos reservados. | Materia: Ingeniería de Software II</p>
    </footer>

    <script src="js/script.js"></script>
</body>
</html>

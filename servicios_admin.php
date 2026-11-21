<?php
session_start();
require_once 'includes/conexion.php';

// SV01: solo el ADMIN con sesión activa
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

function obtenerServicio(PDO $pdo, int $id) {
    $stmt = $pdo->prepare('SELECT id_servicio, nombre, descripcion, cantidad, precio, visible
                           FROM tb_servicios WHERE id_servicio = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    return $stmt->fetch();
}

// Valida los campos del formulario. Devuelve [datos limpios, errores]
function validarServicio(array $post): array {
    $errores = [];

    $nombre      = trim($post['nombre'] ?? '');
    $descripcion = trim($post['descripcion'] ?? '');
    $cantidadTxt = trim($post['cantidad'] ?? '');
    $precioTxt   = str_replace(',', '.', trim($post['precio'] ?? ''));
    $visible     = isset($post['visible']) ? 1 : 0;

    if ($nombre === '' || mb_strlen($nombre) > 100) {
        $errores[] = 'El nombre es obligatorio (máximo 100 caracteres).';
    }
    if (mb_strlen($descripcion) > 255) {
        $errores[] = 'La descripción es demasiado larga (máximo 255 caracteres).';
    }

    $cantidad = filter_var($cantidadTxt, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 999]]);
    if ($cantidad === false) {
        $errores[] = 'La cantidad debe ser un número entero entre 1 y 999.';
    }

    // Precio: mínimo $1.00 y máximo $999,999.99 (máximo 2 decimales)
    if (!preg_match('/^\d{1,6}(\.\d{1,2})?$/', $precioTxt)
        || (float)$precioTxt < 1 || (float)$precioTxt > 999999.99) {
        $errores[] = 'El precio debe ser un número entre $1.00 y $999,999.99 (máximo 2 decimales).';
    }

    $datos = [
        'nombre'      => $nombre,
        'descripcion' => $descripcion,
        'cantidad'    => $cantidad === false ? $cantidadTxt : $cantidad,
        'precio'      => $precioTxt,
        'visible'     => $visible,
    ];
    return [$datos, $errores];
}

$errores  = [];
$modo     = 'lista';                                   // lista | nuevo | editar
$idEditar = isset($_GET['editar']) ? (int)$_GET['editar'] : 0;
$form     = ['nombre' => '', 'descripcion' => '', 'cantidad' => 1, 'precio' => '', 'visible' => 1];

if (isset($_GET['nuevo'])) {
    $modo = 'nuevo';
} elseif ($idEditar > 0) {
    $modo = 'editar';
}

// --------------------------------------------------------------------------
// POST: crear, editar, eliminar, publicar/ocultar
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $_SESSION['flash_error'] = 'Sesión de formulario inválida. Intenta de nuevo.';
        header('Location: servicios_admin.php');
        exit;
    }

    // ---- SV03: guardar servicio nuevo ----
    if ($accion === 'crear') {
        $modo = 'nuevo';
        [$form, $errores] = validarServicio($_POST);

        if (!$errores) {
            try {
                $ins = $pdo->prepare('INSERT INTO tb_servicios (nombre, descripcion, cantidad, precio, visible)
                                      VALUES (:n, :d, :c, :p, :v)');
                $ins->execute([
                    ':n' => $form['nombre'],
                    ':d' => $form['descripcion'] === '' ? null : $form['descripcion'],
                    ':c' => $form['cantidad'],
                    ':p' => $form['precio'],
                    ':v' => $form['visible'],
                ]);
                $_SESSION['flash'] = '✓ El servicio "' . $form['nombre'] . '" se agregó correctamente.';
                header('Location: servicios_admin.php');
                exit;
            } catch (PDOException $ex) {
                $errores[] = 'No se pudo guardar el servicio. Intenta de nuevo.';
            }
        }
    }

    // ---- SV04: guardar cambios de un servicio ----
    if ($accion === 'editar') {
        $idEditar = (int)($_POST['id_servicio'] ?? 0);
        $modo = 'editar';

        if (!obtenerServicio($pdo, $idEditar)) {
            $_SESSION['flash_error'] = 'El servicio no existe.';
            header('Location: servicios_admin.php');
            exit;
        }

        [$form, $errores] = validarServicio($_POST);

        if (!$errores) {
            try {
                $up = $pdo->prepare('UPDATE tb_servicios
                                     SET nombre = :n, descripcion = :d, cantidad = :c, precio = :p, visible = :v
                                     WHERE id_servicio = :id');
                $up->execute([
                    ':n'  => $form['nombre'],
                    ':d'  => $form['descripcion'] === '' ? null : $form['descripcion'],
                    ':c'  => $form['cantidad'],
                    ':p'  => $form['precio'],
                    ':v'  => $form['visible'],
                    ':id' => $idEditar,
                ]);
                $_SESSION['flash'] = '✓ El servicio "' . $form['nombre'] . '" se actualizó correctamente.';
                header('Location: servicios_admin.php');
                exit;
            } catch (PDOException $ex) {
                $errores[] = 'No se pudieron guardar los cambios. Intenta de nuevo.';
            }
        }
    }

    // ---- CT02: publicar / ocultar ----
    if ($accion === 'visibilidad') {
        $id    = (int)($_POST['id_servicio'] ?? 0);
        $nuevo = (($_POST['nuevo_visible'] ?? '') === '1') ? 1 : 0;
        $srv   = obtenerServicio($pdo, $id);

        if (!$srv) {
            $_SESSION['flash_error'] = 'El servicio no existe.';
        } else {
            $up = $pdo->prepare('UPDATE tb_servicios SET visible = :v WHERE id_servicio = :id');
            $up->execute([':v' => $nuevo, ':id' => $id]);
            $_SESSION['flash'] = $nuevo === 1
                ? '✓ "' . $srv['nombre'] . '" ahora está publicado en el catálogo.'
                : '✓ "' . $srv['nombre'] . '" quedó oculto del catálogo.';
        }
        header('Location: servicios_admin.php');
        exit;
    }

    // ---- SV05: eliminar (la llave foránea RESTRICT protege los servicios ya solicitados) ----
    if ($accion === 'eliminar') {
        $id  = (int)($_POST['id_servicio'] ?? 0);
        $srv = obtenerServicio($pdo, $id);

        if (!$srv) {
            $_SESSION['flash_error'] = 'El servicio no existe.';
        } else {
            try {
                $del = $pdo->prepare('DELETE FROM tb_servicios WHERE id_servicio = :id');
                $del->execute([':id' => $id]);
                $_SESSION['flash'] = '✓ El servicio "' . $srv['nombre'] . '" se eliminó.';
            } catch (PDOException $ex) {
                if ($ex->getCode() === '23000') {
                    // La base no lo deja borrar: ya existen solicitudes de este servicio
                    $_SESSION['flash_error'] = 'No se puede eliminar "' . $srv['nombre'] .
                        '" porque ya tiene solicitudes. Mejor ocúltalo del catálogo para no perder el historial.';
                } else {
                    $_SESSION['flash_error'] = 'No se pudo eliminar el servicio. Intenta de nuevo.';
                }
            }
        }
        header('Location: servicios_admin.php');
        exit;
    }
}

// --------------------------------------------------------------------------
// Datos para mostrar
// --------------------------------------------------------------------------
$flash      = $_SESSION['flash'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash'], $_SESSION['flash_error']);

$servicio = null;
if ($modo === 'editar') {
    $servicio = obtenerServicio($pdo, $idEditar);
    if (!$servicio) {
        $_SESSION['flash_error'] = 'El servicio no existe.';
        header('Location: servicios_admin.php');
        exit;
    }
    // Sin errores de un POST se prellena con lo guardado en la base
    if (!($errores && $_SERVER['REQUEST_METHOD'] === 'POST')) {
        $form = $servicio;
    }
}

if ($modo === 'lista') {
    // SV01: lista de servicios (con cuántas solicitudes tiene cada uno)
    $lista = $pdo->query('SELECT s.id_servicio, s.nombre, s.descripcion, s.cantidad, s.precio, s.visible,
                                 (SELECT COUNT(*) FROM tb_solicitudes r WHERE r.id_servicio = s.id_servicio) AS solicitudes
                          FROM tb_servicios s
                          ORDER BY s.id_servicio ASC')->fetchAll();
    $total      = count($lista);
    $publicados = count(array_filter($lista, function ($s) { return (int)$s['visible'] === 1; }));
    $ocultos    = $total - $publicados;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Servicios | DAIN Studio</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/gestion.css">
    <link rel="stylesheet" href="css/servicios.css">
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
            <h1>Servicios</h1>
            <p>Crea, edita y decide qué servicios se publican en el catálogo</p>
        </section>

        <p class="volver"><a href="<?= $modo === 'lista' ? 'panel_admin.php' : 'servicios_admin.php' ?>">&larr; <?= $modo === 'lista' ? 'Volver a mi panel' : 'Volver a servicios' ?></a></p>

        <?php if ($flash): ?>
            <p class="alerta alerta-exito"><?= e($flash) ?></p>
        <?php endif; ?>
        <?php if ($flashError): ?>
            <p class="alerta alerta-error"><?= e($flashError) ?></p>
        <?php endif; ?>

        <?php if ($modo === 'nuevo' || $modo === 'editar'): ?>
            <!-- ================= SV02 / SV04: FORMULARIO ================= -->
            <section class="ficha">
                <div class="ficha-encabezado">
                    <div class="ficha-avatar"><?= $modo === 'nuevo' ? '+' : e(mb_strtoupper(mb_substr($servicio['nombre'], 0, 1))) ?></div>
                    <div>
                        <h2><?= $modo === 'nuevo' ? 'Nuevo servicio' : 'Editar servicio' ?></h2>
                    </div>
                </div>

                <?php foreach ($errores as $err): ?>
                    <p class="alerta alerta-error"><?= e($err) ?></p>
                <?php endforeach; ?>

                <form method="POST" action="servicios_admin.php<?= $modo === 'editar' ? '?editar=' . (int)$servicio['id_servicio'] : '?nuevo=1' ?>" class="ficha-form">
                    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                    <input type="hidden" name="accion" value="<?= $modo === 'editar' ? 'editar' : 'crear' ?>">
                    <?php if ($modo === 'editar'): ?>
                        <input type="hidden" name="id_servicio" value="<?= (int)$servicio['id_servicio'] ?>">
                    <?php endif; ?>

                    <div class="form-group-login">
                        <label for="nombre">Nombre del servicio</label>
                        <input type="text" id="nombre" name="nombre" maxlength="100"
                               placeholder="Ej. Fotografía de producto" value="<?= e($form['nombre']) ?>" required>
                    </div>
                    <div class="form-group-login">
                        <label for="descripcion">Descripción</label>
                        <textarea id="descripcion" name="descripcion" rows="3" maxlength="255"
                                  placeholder="Qué incluye el servicio"><?= e($form['descripcion']) ?></textarea>
                    </div>
                    <div class="form-group-login">
                        <label for="cantidad">Cantidad (por ejemplo, fotografías incluidas)</label>
                        <input type="number" id="cantidad" name="cantidad" min="1" max="999" step="1"
                               value="<?= e($form['cantidad']) ?>" required>
                    </div>
                    <div class="form-group-login">
                        <label for="precio">Precio (MXN)</label>
                        <input type="number" id="precio" name="precio" min="1" max="999999.99" step="0.01"
                               placeholder="0.00" value="<?= e($form['precio']) ?>" required>
                    </div>
                    <div class="form-group-login check-visible">
                        <label for="visible">
                            <input type="checkbox" id="visible" name="visible" value="1" <?= (int)$form['visible'] === 1 ? 'checked' : '' ?>>
                            Publicar en el catálogo (visible para los clientes)
                        </label>
                    </div>

                    <div class="ficha-acciones">
                        <button type="submit" class="btn btn-primary"><?= $modo === 'nuevo' ? 'Guardar servicio' : 'Guardar cambios' ?></button>
                        <a href="servicios_admin.php" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </section>

        <?php else: ?>
            <!-- ================= SV01 / SV06: LISTA EN TARJETAS ================= -->
            <section class="resumen-clientes">
                <div><strong><?= $total ?></strong><span>Servicios</span></div>
                <div><strong><?= $publicados ?></strong><span>Publicados</span></div>
                <div><strong><?= $ocultos ?></strong><span>Ocultos</span></div>
            </section>

            <p class="barra-acciones">
                <a href="servicios_admin.php?nuevo=1" class="btn btn-primary">+ Nuevo servicio</a>
            </p>

            <?php if ($total === 0): ?>
                <p class="tabla-vacia">Todavía no hay servicios. Agrega el primero con "Nuevo servicio".</p>
            <?php else: ?>
                <section class="servicios-grid">
                    <?php foreach ($lista as $s): ?>
                        <?php $pub = ((int)$s['visible'] === 1); ?>
                        <article class="servicio-card <?= $pub ? '' : 'servicio-oculto' ?>">
                            <div class="servicio-top">
                                <span class="estado <?= $pub ? 'estado-activo' : 'estado-inactivo' ?>"><?= $pub ? 'Publicado' : 'Oculto' ?></span>
                                <?php if ((int)$s['solicitudes'] > 0): ?>
                                    <span class="servicio-solicitudes"><?= (int)$s['solicitudes'] ?> solicitud(es)</span>
                                <?php endif; ?>
                            </div>

                            <h3><?= e($s['nombre']) ?></h3>
                            <p class="servicio-desc"><?= $s['descripcion'] !== null && $s['descripcion'] !== '' ? e($s['descripcion']) : 'Sin descripción' ?></p>

                            <div class="servicio-datos">
                                <div class="servicio-cantidad">
                                    <strong><?= (int)$s['cantidad'] ?></strong>
                                    <span>Cantidad</span>
                                </div>
                                <div class="servicio-precio">
                                    <strong>$<?= number_format((float)$s['precio'], 2) ?></strong>
                                    <span>MXN</span>
                                </div>
                            </div>

                            <div class="servicio-acciones">
                                <a href="servicios_admin.php?editar=<?= (int)$s['id_servicio'] ?>" class="btn btn-sm btn-secondary">Editar</a>

                                <form method="POST" action="servicios_admin.php" class="form-inline">
                                    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                                    <input type="hidden" name="accion" value="visibilidad">
                                    <input type="hidden" name="id_servicio" value="<?= (int)$s['id_servicio'] ?>">
                                    <input type="hidden" name="nuevo_visible" value="<?= $pub ? '0' : '1' ?>">
                                    <button type="submit" class="btn btn-sm <?= $pub ? 'btn-secondary' : 'btn-primary' ?>"><?= $pub ? 'Ocultar' : 'Publicar' ?></button>
                                </form>

                                <form method="POST" action="servicios_admin.php" class="form-inline form-eliminar"
                                      data-nombre="<?= e($s['nombre']) ?>">
                                    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="id_servicio" value="<?= (int)$s['id_servicio'] ?>">
                                    <button type="submit" class="btn btn-sm btn-peligro">Eliminar</button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <footer>
        <p>&copy; 2026 DAIN Studio. Todos los derechos reservados. | Materia: Ingeniería de Software II</p>
    </footer>

    <script src="js/script.js"></script>
    <script>
        // SV05: confirmación antes de eliminar un servicio
        document.querySelectorAll('.form-eliminar').forEach(function (f) {
            f.addEventListener('submit', function (ev) {
                var nombre = f.getAttribute('data-nombre');
                if (!confirm('¿Eliminar el servicio "' + nombre + '"? Esta acción no se puede deshacer.')) {
                    ev.preventDefault();
                }
            });
        });
    </script>
</body>
</html>
<?php
// ==========================================================================
// mis_solicitudes.php - Solicitudes del cliente (Sprint 4, MS01-MS07)
//   MS01 sesión + rol USU_GRAL + cuenta activa   MS02 tabla de solicitudes
//   MS03 insignias de estatus                     MS04 modificar cantidad
//   MS05 cancelar (con confirmación)              MS06 acciones protegidas en el servidor
//   MS07 estilos (tabla adaptable a celular)
// ==========================================================================
session_start();
require_once 'includes/conexion.php';

// MS01: solo clientes (USU_GRAL) con sesión y cuenta activa
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

// Token CSRF para todos los formularios de esta página
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// MS03: clase CSS y texto de cada estatus
function insigniaEstatus(string $estatus): array {
    switch ($estatus) {
        case 'SOLICITADO': return ['estatus-solicitado', 'Solicitado'];
        case 'EN_PROCESO': return ['estatus-proceso',    'En proceso'];
        case 'FINALIZADO': return ['estatus-finalizado', 'Finalizado'];
        default:           return ['estatus-solicitado', $estatus];
    }
}

// Solicitud del cliente en sesión que todavía se puede modificar o cancelar
function obtenerSolicitudEditable(PDO $pdo, int $idSolicitud, int $idUsuario) {
    $stmt = $pdo->prepare("SELECT s.id_solicitud, s.cantidad, s.estatus, sv.nombre
                           FROM tb_solicitudes s
                           INNER JOIN tb_servicios sv ON sv.id_servicio = s.id_servicio
                           WHERE s.id_solicitud = :id AND s.id_usuario = :u AND s.estatus = 'SOLICITADO'
                           LIMIT 1");
    $stmt->execute([':id' => $idSolicitud, ':u' => $idUsuario]);
    return $stmt->fetch();
}

$errores  = [];
$idEditar = isset($_GET['editar']) ? (int)$_GET['editar'] : 0;
$modo     = $idEditar > 0 ? 'editar' : 'lista';
$cantidadForm = '';

// --------------------------------------------------------------------------
// POST: modificar (MS04) y cancelar (MS05)
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $_SESSION['flash_error'] = 'Sesión de formulario inválida. Intenta de nuevo.';
        header('Location: mis_solicitudes.php');
        exit;
    }

    // ---- MS04: modificar la cantidad ----
    if ($accion === 'modificar') {
        $idEditar     = (int)($_POST['id_solicitud'] ?? 0);
        $modo         = 'editar';
        $cantidadForm = trim((string)($_POST['cantidad'] ?? ''));

        $cantidad = filter_var($cantidadForm, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]);
        if ($cantidad === false) {
            $errores[] = 'La cantidad debe ser un número entero entre 1 y 99.';
        } else {
            try {
                // MS06: las condiciones de dueño y estatus van dentro del propio UPDATE
                $up = $pdo->prepare("UPDATE tb_solicitudes SET cantidad = :c
                                     WHERE id_solicitud = :id AND id_usuario = :u AND estatus = 'SOLICITADO'");
                $up->execute([':c' => $cantidad, ':id' => $idEditar, ':u' => $idUsuario]);

                if ($up->rowCount() > 0) {
                    $_SESSION['flash'] = '✓ La cantidad de tu solicitud se actualizó a ' . $cantidad . '.';
                } else {
                    // 0 filas: o escribió la misma cantidad, o la solicitud no es suya / ya no está en SOLICITADO
                    $sol = obtenerSolicitudEditable($pdo, $idEditar, $idUsuario);
                    if ($sol && (int)$sol['cantidad'] === $cantidad) {
                        $_SESSION['flash'] = '✓ Tu solicitud ya tenía esa cantidad, no hubo cambios.';
                    } else {
                        $_SESSION['flash_error'] = 'No se pudo modificar la solicitud: ya no está en estatus SOLICITADO o no existe.';
                    }
                }
                header('Location: mis_solicitudes.php');
                exit;
            } catch (PDOException $ex) {
                $errores[] = 'No se pudieron guardar los cambios. Intenta de nuevo.';
            }
        }
    }

    // ---- MS05: cancelar (eliminar) la solicitud ----
    if ($accion === 'cancelar') {
        $id = (int)($_POST['id_solicitud'] ?? 0);
        try {
            // MS06: mismas condiciones dentro del DELETE
            $del = $pdo->prepare("DELETE FROM tb_solicitudes
                                  WHERE id_solicitud = :id AND id_usuario = :u AND estatus = 'SOLICITADO'");
            $del->execute([':id' => $id, ':u' => $idUsuario]);

            if ($del->rowCount() > 0) {
                $_SESSION['flash'] = '✓ Tu solicitud se canceló correctamente.';
            } else {
                $_SESSION['flash_error'] = 'No se pudo cancelar la solicitud: ya no está en estatus SOLICITADO o no existe.';
            }
        } catch (PDOException $ex) {
            $_SESSION['flash_error'] = 'No se pudo cancelar la solicitud. Intenta de nuevo.';
        }
        header('Location: mis_solicitudes.php');
        exit;
    }
}

// --------------------------------------------------------------------------
// Datos para mostrar
// --------------------------------------------------------------------------
$flash      = $_SESSION['flash'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash'], $_SESSION['flash_error']);

$solicitud = null;
if ($modo === 'editar') {
    $solicitud = obtenerSolicitudEditable($pdo, $idEditar, $idUsuario);
    if (!$solicitud) {
        $_SESSION['flash_error'] = 'Esa solicitud no existe o ya no se puede modificar.';
        header('Location: mis_solicitudes.php');
        exit;
    }
    if ($cantidadForm === '') {
        $cantidadForm = (string)$solicitud['cantidad'];
    }
} else {
    // MS02: solo las solicitudes del cliente en sesión
    $stmt = $pdo->prepare('SELECT s.id_solicitud, sv.nombre, s.cantidad, s.fecha_solicitud, s.estatus
                           FROM tb_solicitudes s
                           INNER JOIN tb_servicios sv ON sv.id_servicio = s.id_servicio
                           WHERE s.id_usuario = :id
                           ORDER BY s.fecha_solicitud DESC, s.id_solicitud DESC');
    $stmt->execute([':id' => $idUsuario]);
    $lista = $stmt->fetchAll();

    $total       = count($lista);
    $nSolicitado = count(array_filter($lista, function ($r) { return $r['estatus'] === 'SOLICITADO'; }));
    $nProceso    = count(array_filter($lista, function ($r) { return $r['estatus'] === 'EN_PROCESO'; }));
    $nFinalizado = count(array_filter($lista, function ($r) { return $r['estatus'] === 'FINALIZADO'; }));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis solicitudes | DAIN Studio</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/gestion.css">
    <link rel="stylesheet" href="css/servicios.css">
    <link rel="stylesheet" href="css/solicitudes.css">
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

    <main class="panel-container panel-ancho">
        <section class="panel-header">
            <h1>Mis solicitudes</h1>
            <p>Consulta el estatus de lo que has pedido y modifica o cancela las que siguen en Solicitado</p>
        </section>

        <p class="volver"><a href="<?= $modo === 'lista' ? 'panel_cliente.php' : 'mis_solicitudes.php' ?>">&larr; <?= $modo === 'lista' ? 'Volver a mi panel' : 'Volver a mis solicitudes' ?></a></p>

        <?php if ($flash): ?>
            <p class="alerta alerta-exito"><?= e($flash) ?></p>
        <?php endif; ?>
        <?php if ($flashError): ?>
            <p class="alerta alerta-error"><?= e($flashError) ?></p>
        <?php endif; ?>

        <?php if ($modo === 'editar'): ?>
            <!-- ================= MS04: MODIFICAR CANTIDAD ================= -->
            <section class="ficha">
                <div class="ficha-encabezado">
                    <div class="ficha-avatar"><?= e(mb_strtoupper(mb_substr($solicitud['nombre'], 0, 1))) ?></div>
                    <div>
                        <h2>Modificar solicitud</h2>
                        <span class="estado estatus-solicitado">Solicitado</span>
                    </div>
                </div>

                <?php foreach ($errores as $err): ?>
                    <p class="alerta alerta-error"><?= e($err) ?></p>
                <?php endforeach; ?>

                <form method="POST" action="mis_solicitudes.php?editar=<?= (int)$solicitud['id_solicitud'] ?>" class="ficha-form">
                    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                    <input type="hidden" name="accion" value="modificar">
                    <input type="hidden" name="id_solicitud" value="<?= (int)$solicitud['id_solicitud'] ?>">

                    <p class="nota-campos">Solo puedes cambiar la cantidad mientras la solicitud siga en Solicitado.</p>

                    <div class="form-group-login">
                        <label>Servicio</label>
                        <input type="text" value="<?= e($solicitud['nombre']) ?>" disabled>
                    </div>
                    <div class="form-group-login">
                        <label for="cantidad">Cantidad que necesitas (1 a 99)</label>
                        <input type="number" id="cantidad" name="cantidad" min="1" max="99" step="1"
                               value="<?= e($cantidadForm) ?>" required>
                    </div>

                    <div class="ficha-acciones">
                        <button type="submit" class="btn btn-primary">Guardar cambios</button>
                        <a href="mis_solicitudes.php" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </section>

        <?php else: ?>
            <!-- ================= MS02: TABLA DE SOLICITUDES ================= -->
            <section class="resumen-clientes">
                <div><strong><?= $nSolicitado ?></strong><span>Solicitadas</span></div>
                <div><strong><?= $nProceso ?></strong><span>En proceso</span></div>
                <div><strong><?= $nFinalizado ?></strong><span>Finalizadas</span></div>
            </section>

            <p class="barra-acciones">
                <a href="galeria_servicios.php" class="btn btn-primary">+ Solicitar servicios</a>
            </p>

            <section class="tabla-wrapper">
                <?php if ($total === 0): ?>
                    <p class="tabla-vacia">Todavía no has solicitado ningún servicio. Entra a la galería y elige el que necesitas.</p>
                <?php else: ?>
                    <table class="tabla-datos">
                        <thead>
                            <tr>
                                <th>Servicio</th>
                                <th>Cantidad</th>
                                <th>Fecha</th>
                                <th>Estatus</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lista as $r): ?>
                                <?php
                                    [$claseEstatus, $textoEstatus] = insigniaEstatus($r['estatus']);
                                    $editable = ($r['estatus'] === 'SOLICITADO');
                                ?>
                                <tr>
                                    <td data-label="Servicio"><?= e($r['nombre']) ?></td>
                                    <td data-label="Cantidad"><?= (int)$r['cantidad'] ?></td>
                                    <td data-label="Fecha"><?= e(date('d/m/Y H:i', strtotime($r['fecha_solicitud']))) ?></td>
                                    <td data-label="Estatus">
                                        <span class="estado <?= e($claseEstatus) ?>"><?= e($textoEstatus) ?></span>
                                    </td>
                                    <td data-label="Acciones" class="celda-acciones">
                                        <?php if ($editable): ?>
                                            <a href="mis_solicitudes.php?editar=<?= (int)$r['id_solicitud'] ?>" class="btn btn-sm btn-secondary">Modificar</a>

                                            <form method="POST" action="mis_solicitudes.php" class="form-inline form-cancelar"
                                                  data-nombre="<?= e($r['nombre']) ?>">
                                                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                                                <input type="hidden" name="accion" value="cancelar">
                                                <input type="hidden" name="id_solicitud" value="<?= (int)$r['id_solicitud'] ?>">
                                                <button type="submit" class="btn btn-sm btn-peligro">Cancelar</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="sin-acciones">Sin cambios</span>
                                        <?php endif; ?>
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
    <script>
        // MS05: confirmación antes de cancelar una solicitud
        document.querySelectorAll('.form-cancelar').forEach(function (f) {
            f.addEventListener('submit', function (ev) {
                var nombre = f.getAttribute('data-nombre');
                if (!confirm('¿Cancelar tu solicitud de "' + nombre + '"? Esta acción no se puede deshacer.')) {
                    ev.preventDefault();
                }
            });
        });
    </script>
</body>
</html>

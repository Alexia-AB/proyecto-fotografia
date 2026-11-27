<?php
// ==========================================================================
// gestion_solicitudes.php - Gestión de solicitudes del administrador
// Sprint 5 (GS01-GS09): ver todas las solicitudes, cambiar su estatus,
// filtrar por estatus y ver contadores.
// ==========================================================================
session_start();
require_once 'includes/conexion.php';

// GS01: solo el ADMIN con sesión activa
if (!isset($_SESSION['id_usuario'])) {
    header('Location: login.php');
    exit;
}
if ($_SESSION['rol'] !== 'ADMIN') {
    header('Location: index.html');
    exit;
}

// Token CSRF
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Texto y clase de color de cada estatus (mismos colores que Mis solicitudes del cliente)
function insigniaEstatus(string $estatus): array {
    switch ($estatus) {
        case 'SOLICITADO': return ['estatus-solicitado', 'Solicitado'];
        case 'EN_PROCESO': return ['estatus-proceso',    'En proceso'];
        case 'FINALIZADO': return ['estatus-finalizado', 'Finalizado'];
        default:           return ['estatus-solicitado', $estatus];
    }
}

$ESTATUS = ['SOLICITADO', 'EN_PROCESO', 'FINALIZADO'];

// GS06: filtro por estatus (solo se aceptan valores de la lista)
$filtro = $_GET['estatus'] ?? '';
if (!in_array($filtro, $ESTATUS, true)) {
    $filtro = '';
}

// --------------------------------------------------------------------------
// GS04 / GS05: cambiar el estatus de una solicitud
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $filtroPost = $_POST['filtro'] ?? '';
    $volver = 'gestion_solicitudes.php' . (in_array($filtroPost, $ESTATUS, true) ? '?estatus=' . $filtroPost : '');

    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $_SESSION['flash_error'] = 'Sesión de formulario inválida. Intenta de nuevo.';
    } else {
        $id    = (int)($_POST['id_solicitud'] ?? 0);
        $nuevo = $_POST['nuevo_estatus'] ?? '';

        // Desde qué estatus se puede llegar a cada uno: solo se avanza, nunca se retrocede.
        // SOLICITADO no aparece aquí porque lo genera el cliente, no el administrador.
        $permitidos = [
            'EN_PROCESO' => ['SOLICITADO'],
            'FINALIZADO' => ['SOLICITADO', 'EN_PROCESO'],
        ];

        if ($id <= 0 || !is_string($nuevo) || !isset($permitidos[$nuevo])) {
            $_SESSION['flash_error'] = 'Estatus no válido.';
        } else {
            $previos = $permitidos[$nuevo];
            $marcas  = implode(',', array_fill(0, count($previos), '?'));
            $stmt = $pdo->prepare("UPDATE tb_solicitudes SET estatus = ?
                                   WHERE id_solicitud = ? AND estatus IN ($marcas)");
            $stmt->execute(array_merge([$nuevo, $id], $previos));

            if ($stmt->rowCount() > 0) {
                $_SESSION['flash'] = '✓ La solicitud #' . $id . ' ahora está en estatus '
                                   . insigniaEstatus($nuevo)[1] . '.';
            } else {
                $_SESSION['flash_error'] = 'No se pudo cambiar la solicitud: no existe o su estatus ya cambió.';
            }
        }
    }
    header('Location: ' . $volver);
    exit;
}

// --------------------------------------------------------------------------
// GS07: contadores por estatus
// --------------------------------------------------------------------------
$cont = ['SOLICITADO' => 0, 'EN_PROCESO' => 0, 'FINALIZADO' => 0];
foreach ($pdo->query('SELECT estatus, COUNT(*) AS n FROM tb_solicitudes GROUP BY estatus') as $fila) {
    if (isset($cont[$fila['estatus']])) {
        $cont[$fila['estatus']] = (int)$fila['n'];
    }
}
$total = array_sum($cont);

// --------------------------------------------------------------------------
// GS02: tabla de solicitudes (las pendientes primero)
// --------------------------------------------------------------------------
$sql = "SELECT s.id_solicitud, s.cantidad, s.fecha_solicitud, s.estatus,
               sv.nombre AS servicio, sv.precio,
               u.usuario, d.nombre_completo
        FROM tb_solicitudes s
        INNER JOIN tb_servicios sv ON sv.id_servicio = s.id_servicio
        INNER JOIN tb_usuarios u   ON u.id_usuario   = s.id_usuario
        LEFT JOIN tb_detalle_usuarios d ON d.id_usuario = s.id_usuario";
$params = [];
if ($filtro !== '') {
    $sql .= ' WHERE s.estatus = :e';
    $params[':e'] = $filtro;
}
$sql .= " ORDER BY FIELD(s.estatus, 'SOLICITADO', 'EN_PROCESO', 'FINALIZADO'), s.fecha_solicitud DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$solicitudes = $stmt->fetchAll();

$flash      = $_SESSION['flash'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash'], $_SESSION['flash_error']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Solicitudes | DAIN Studio</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/gestion.css">
    <link rel="stylesheet" href="css/servicios.css">
    <link rel="stylesheet" href="css/solicitudes.css">
    <link rel="stylesheet" href="css/gestion_solicitudes.css">
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
            <h1>Gestión de Solicitudes</h1>
            <p>Atiende las solicitudes de los clientes y actualiza su estatus</p>
        </section>

        <p class="volver"><a href="panel_admin.php">&larr; Volver a mi panel</a></p>

        <?php if ($flash): ?>
            <p class="alerta alerta-exito"><?= e($flash) ?></p>
        <?php endif; ?>
        <?php if ($flashError): ?>
            <p class="alerta alerta-error"><?= e($flashError) ?></p>
        <?php endif; ?>

        <!-- GS07: contadores -->
        <section class="resumen-solicitudes">
            <div><strong><?= $total ?></strong><span>Total</span></div>
            <div><strong><?= $cont['SOLICITADO'] ?></strong><span>Solicitadas</span></div>
            <div><strong><?= $cont['EN_PROCESO'] ?></strong><span>En proceso</span></div>
            <div><strong><?= $cont['FINALIZADO'] ?></strong><span>Finalizadas</span></div>
        </section>

        <!-- GS06: filtro por estatus -->
        <nav class="filtros-estatus">
            <a href="gestion_solicitudes.php" class="<?= $filtro === '' ? 'activo' : '' ?>">Todas (<?= $total ?>)</a>
            <a href="gestion_solicitudes.php?estatus=SOLICITADO" class="<?= $filtro === 'SOLICITADO' ? 'activo' : '' ?>">Solicitadas (<?= $cont['SOLICITADO'] ?>)</a>
            <a href="gestion_solicitudes.php?estatus=EN_PROCESO" class="<?= $filtro === 'EN_PROCESO' ? 'activo' : '' ?>">En proceso (<?= $cont['EN_PROCESO'] ?>)</a>
            <a href="gestion_solicitudes.php?estatus=FINALIZADO" class="<?= $filtro === 'FINALIZADO' ? 'activo' : '' ?>">Finalizadas (<?= $cont['FINALIZADO'] ?>)</a>
        </nav>

        <section class="tabla-wrapper">
            <?php if (count($solicitudes) === 0): ?>
                <p class="tabla-vacia">
                    <?= $filtro === '' ? 'Todavía no hay solicitudes.' : 'No hay solicitudes con este estatus.' ?>
                </p>
            <?php else: ?>
                <table class="tabla-datos">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Servicio</th>
                            <th>Cantidad</th>
                            <th>Total</th>
                            <th>Fecha</th>
                            <th>Estatus</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($solicitudes as $r): ?>
                            <?php [$claseEstatus, $textoEstatus] = insigniaEstatus($r['estatus']); ?>
                            <tr>
                                <td data-label="Cliente">
                                    <?= e($r['nombre_completo'] !== null ? $r['nombre_completo'] : $r['usuario']) ?>
                                    <small>@<?= e($r['usuario']) ?></small>
                                </td>
                                <td data-label="Servicio"><?= e($r['servicio']) ?></td>
                                <td data-label="Cantidad"><?= (int)$r['cantidad'] ?></td>
                                <td data-label="Total">$<?= number_format((float)$r['precio'] * (int)$r['cantidad'], 2) ?></td>
                                <td data-label="Fecha"><?= e(date('d/m/Y H:i', strtotime($r['fecha_solicitud']))) ?></td>
                                <td data-label="Estatus">
                                    <span class="estado <?= e($claseEstatus) ?>"><?= e($textoEstatus) ?></span>
                                </td>
                                <td data-label="Acciones" class="celda-acciones">
                                    <?php if ($r['estatus'] === 'SOLICITADO'): ?>
                                        <form method="POST" action="gestion_solicitudes.php" class="form-inline">
                                            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                                            <input type="hidden" name="id_solicitud" value="<?= (int)$r['id_solicitud'] ?>">
                                            <input type="hidden" name="nuevo_estatus" value="EN_PROCESO">
                                            <input type="hidden" name="filtro" value="<?= e($filtro) ?>">
                                            <button type="submit" class="btn btn-sm btn-secondary">En proceso</button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($r['estatus'] === 'SOLICITADO' || $r['estatus'] === 'EN_PROCESO'): ?>
                                        <form method="POST" action="gestion_solicitudes.php" class="form-inline"
                                              onsubmit="return confirm('¿Marcar esta solicitud como finalizada?');">
                                            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                                            <input type="hidden" name="id_solicitud" value="<?= (int)$r['id_solicitud'] ?>">
                                            <input type="hidden" name="nuevo_estatus" value="FINALIZADO">
                                            <input type="hidden" name="filtro" value="<?= e($filtro) ?>">
                                            <button type="submit" class="btn btn-sm btn-primary">Finalizar</button>
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
    </main>

    <footer>
        <p>&copy; 2026 DAIN Studio. Todos los derechos reservados. | Materia: Ingeniería de Software II</p>
    </footer>

    <script src="js/script.js"></script>
</body>
</html>
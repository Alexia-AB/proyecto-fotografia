<?php
session_start();
require_once 'includes/conexion.php';

// GL01: solo clientes (USU_GRAL) con sesión y cuenta activa
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

$errores   = [];
$seleccion = [];   // id_servicio => cantidad escrita (para no perder lo elegido si hay error)

// --------------------------------------------------------------------------
// SO01-SO04: el cliente presiona "Solicitar"
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $errores[] = 'Sesión de formulario inválida. Intenta de nuevo.';
    } else {
        $ids   = $_POST['seleccionados'] ?? [];
        $cants = $_POST['cantidad'] ?? [];
        if (!is_array($ids))   { $ids = []; }
        if (!is_array($cants)) { $cants = []; }

        // Ids enteros, positivos y sin repetir
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), function ($v) {
            return $v > 0;
        })));

        // Se conserva lo elegido por si hay que mostrar un error
        foreach ($ids as $id) {
            $v = $cants[$id] ?? '1';
            $seleccion[$id] = is_scalar($v) ? (string)$v : '1';
        }

        // SO02: validaciones en el servidor
        $pedido = [];   // id_servicio => cantidad validada
        if (count($ids) === 0) {
            $errores[] = 'Selecciona al menos un servicio para solicitar.';
        } elseif (count($ids) > 50) {
            $errores[] = 'Solicitaste demasiados servicios a la vez. Intenta con menos.';
        } else {
            foreach ($ids as $id) {
                $c = filter_var($cants[$id] ?? '', FILTER_VALIDATE_INT,
                                ['options' => ['min_range' => 1, 'max_range' => 99]]);
                if ($c === false) {
                    $errores[] = 'La cantidad de cada servicio debe ser un número entero entre 1 y 99.';
                    break;
                }
                $pedido[$id] = $c;
            }
        }

        // Cada servicio debe existir y estar publicado (visible = 1)
        if (!$errores) {
            $marcas = implode(',', array_fill(0, count($pedido), '?'));
            $stmt = $pdo->prepare("SELECT id_servicio FROM tb_servicios WHERE visible = 1 AND id_servicio IN ($marcas)");
            $stmt->execute(array_keys($pedido));
            if (count($stmt->fetchAll(PDO::FETCH_COLUMN)) !== count($pedido)) {
                $errores[] = 'Alguno de los servicios ya no está disponible. Revisa la galería e inténtalo de nuevo.';
            }
        }

        // SO03: una fila por servicio, todas dentro de una transacción
        if (!$errores) {
            try {
                $pdo->beginTransaction();
                $ins = $pdo->prepare("INSERT INTO tb_solicitudes (id_usuario, id_servicio, cantidad, estatus)
                                      VALUES (:u, :s, :c, 'SOLICITADO')");
                foreach ($pedido as $idServicio => $cantidad) {
                    $ins->execute([':u' => $idUsuario, ':s' => $idServicio, ':c' => $cantidad]);
                }
                $pdo->commit();

                // SO04: mensaje de éxito y redirección a Mis solicitudes
                $n = count($pedido);
                $_SESSION['flash'] = '✓ ¡Listo! Se envió tu solicitud de ' . $n . ($n === 1 ? ' servicio' : ' servicios')
                                   . '. Aquí puedes seguir su estatus.';
                $destino = file_exists(__DIR__ . '/mis_solicitudes.php') ? 'mis_solicitudes.php' : 'galeria_servicios.php';
                header('Location: ' . $destino);
                exit;
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                $errores[] = 'No se pudo guardar tu solicitud. Intenta de nuevo.';
            }
        }
    }
}

// --------------------------------------------------------------------------
// GL02: servicios publicados
// --------------------------------------------------------------------------
$servicios = $pdo->query('SELECT id_servicio, nombre, descripcion, cantidad, precio
                          FROM tb_servicios
                          WHERE visible = 1
                          ORDER BY nombre ASC')->fetchAll();

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);
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
    <link rel="stylesheet" href="css/galeria.css">
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

    <main class="panel-container panel-ancho galeria-ancha">
        <section class="panel-header">
            <h1>Nuestros servicios</h1>
            <p>Elige los que necesitas, indica cuántos y presiona Solicitar</p>
        </section>

        <p class="volver"><a href="panel_cliente.php">&larr; Volver a mi panel</a></p>

        <?php if ($flash): ?>
            <p class="alerta alerta-exito"><?= e($flash) ?></p>
        <?php endif; ?>
        <?php foreach ($errores as $err): ?>
            <p class="alerta alerta-error"><?= e($err) ?></p>
        <?php endforeach; ?>

        <?php if (count($servicios) === 0): ?>
            <p class="tabla-vacia">Por ahora no hay servicios disponibles. Vuelve pronto.</p>
        <?php else: ?>
            <form method="POST" action="galeria_servicios.php" id="form-solicitud" class="galeria-layout">
                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">

                <!-- GL03: tarjetas de servicio -->
                <section class="servicios-grid">
                    <?php foreach ($servicios as $s): ?>
                        <?php
                            $id      = (int)$s['id_servicio'];
                            $marcado = isset($seleccion[$id]);
                            $valCant = $marcado ? $seleccion[$id] : '1';
                        ?>
                        <article class="servicio-card galeria-card">
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

                            <!-- GL04: selección y cantidad -->
                            <div class="seleccion-servicio">
                                <label class="check-seleccionar">
                                    <input type="checkbox" class="chk-servicio" name="seleccionados[]"
                                           value="<?= $id ?>"
                                           data-nombre="<?= e($s['nombre']) ?>"
                                           data-precio="<?= number_format((float)$s['precio'], 2, '.', '') ?>"
                                           <?= $marcado ? 'checked' : '' ?>>
                                    <span>Seleccionar</span>
                                </label>
                                <div class="campo-cantidad">
                                    <label for="cant-<?= $id ?>">¿Cuántos?</label>
                                    <input type="number" id="cant-<?= $id ?>" name="cantidad[<?= $id ?>]"
                                           class="inp-cantidad" min="1" max="99" step="1"
                                           value="<?= e($valCant) ?>">
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </section>

                <!-- GL05 / GL06: resumen de la selección -->
                <aside class="resumen-solicitud">
                    <h2>Tu selección</h2>
                    <p class="resumen-vacio" id="resumen-vacio">Aún no has seleccionado ningún servicio.</p>
                    <ul class="resumen-lista" id="resumen-lista"></ul>

                    <div class="resumen-total">
                        <span>Total estimado</span>
                        <strong id="resumen-total">$0.00</strong>
                    </div>
                    <p class="resumen-nota">Es solo una referencia. El estudio confirmará los detalles contigo.</p>

                    <!-- SO01: botón Solicitar -->
                    <button type="submit" class="btn btn-primary" id="btn-solicitar">Solicitar</button>
                </aside>
            </form>
        <?php endif; ?>
    </main>

    <footer>
        <p>&copy; 2026 DAIN Studio. Todos los derechos reservados. | Materia: Ingeniería de Software II</p>
    </footer>

    <script src="js/script.js"></script>
    <script src="js/galeria.js"></script>
</body>
</html>
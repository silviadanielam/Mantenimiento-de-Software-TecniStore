<?php
// HU-05: Permite consultar el perfil y cambiar la contraseña del administrador.
require_once __DIR__ . '/funciones.php';
requiere_login();
require_once __DIR__ . '/conexion.php';

// Consultamos el correo directamente desde la base de datos, no desde un formulario.
$idAdministrador = (int)$_SESSION['administrador_id'];
$stmt = $pdo->prepare('SELECT id, correo, contrasena_hash FROM administradores WHERE id = ? LIMIT 1');
$stmt->execute([$idAdministrador]);
$administrador = $stmt->fetch();

if (!$administrador) {
    // La cuenta fue eliminada: no mantenemos una sesión sin administrador válido.
    unset($_SESSION['administrador_id'], $_SESSION['administrador_correo'], $_SESSION['ultima_actividad']);
    redirigir('login.php');
}

$error = '';
$ok = (string)($_SESSION['ok_perfil'] ?? '');
unset($_SESSION['ok_perfil']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();

    // Las contraseñas se leen tal como se escribieron, sin quitar espacios.
    $actual = (string)($_POST['contrasena_actual'] ?? '');
    $nueva = (string)($_POST['contrasena_nueva'] ?? '');
    $confirmar = (string)($_POST['confirmar_contrasena'] ?? '');

    // Si mbstring está disponible contamos caracteres Unicode; si no, bytes.
    $longitud = function_exists('mb_strlen') ? mb_strlen($nueva, 'UTF-8') : strlen($nueva);

    if ($actual === '' || $nueva === '' || $confirmar === '') {
        $error = 'Completa los tres campos de contraseña.';
    } elseif (!password_verify($actual, (string)$administrador['contrasena_hash'])) {
        $error = 'La contraseña actual no es correcta.';
    } elseif ($longitud < 10) {
        $error = 'La nueva contraseña debe tener al menos 10 caracteres.';
    } elseif (strlen($nueva) > 72) {
        // PASSWORD_DEFAULT puede usar bcrypt, que admite como máximo 72 bytes.
        $error = 'La nueva contraseña es demasiado larga. Utiliza una de hasta 72 bytes.';
    } elseif ($nueva !== $confirmar) {
        $error = 'La nueva contraseña y su confirmación no coinciden.';
    } elseif (password_verify($nueva, (string)$administrador['contrasena_hash'])) {
        $error = 'La nueva contraseña debe ser diferente de la actual.';
    } else {
        try {
            // Generamos un hash: nunca guardamos ni mostramos la contraseña en texto plano.
            $hashNuevo = password_hash($nueva, PASSWORD_DEFAULT);

            // Comparamos con el hash leído para evitar sobrescribir cambios simultáneos.
            $actualizar = $pdo->prepare(
                'UPDATE administradores SET contrasena_hash = ? WHERE id = ? AND contrasena_hash = ?'
            );
            $actualizar->execute([$hashNuevo, $idAdministrador, $administrador['contrasena_hash']]);

            if ($actualizar->rowCount() !== 1) {
                $error = 'La cuenta cambió durante la operación. Actualiza la página e inténtalo nuevamente.';
            } else {
                // Renovamos el identificador de sesión y evitamos reenviar el formulario.
                session_regenerate_id(true);
                $_SESSION['ultima_actividad'] = time();
                $_SESSION['ok_perfil'] = 'Contraseña actualizada correctamente. Al cerrar sesión podrás ingresar con la nueva contraseña.';
                redirigir('perfil.php');
            }
        } catch (Throwable $e) {
            error_log('TecniStore: error al actualizar la contraseña: ' . $e->getMessage());
            $error = 'No se pudo actualizar la contraseña. Inténtalo nuevamente.';
        }
    }
}

$token = token_csrf();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mi perfil | TecniStore</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
          crossorigin="anonymous">
    <link rel="stylesheet" href="assets/css/estilos.css">
</head>
<body>
<main class="container py-3 py-md-4" style="max-width: 870px;">
    <header class="encabezado p-3 p-md-4 mb-4 shadow-sm d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h1 class="h3 fw-bold mb-1">TecniStore - Mi perfil</h1>
            <div class="small text-white-50">Administración de la cuenta</div>
        </div>
        <a href="index.php" class="btn btn-light btn-sm">Volver al inventario</a>
    </header>

    <?php if ($ok !== ''): ?>
        <div class="alert alert-success" role="status"><?= escapar($ok) ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= escapar($error) ?></div>
    <?php endif; ?>

    <section class="card tarjeta shadow-sm mb-4" aria-labelledby="titulo-cuenta">
        <div class="card-body p-3 p-md-4">
            <h2 class="h5 fw-bold" id="titulo-cuenta">Información de mi cuenta</h2>
            <p class="text-secondary mb-1">Correo electrónico del administrador</p>
            <p class="mb-0 fw-semibold text-break"><?= escapar($administrador['correo']) ?></p>
        </div>
    </section>

    <section class="card tarjeta shadow-sm" aria-labelledby="titulo-contrasena">
        <div class="card-body p-3 p-md-4">
            <h2 class="h5 fw-bold mb-2" id="titulo-contrasena">Cambiar contraseña</h2>
            <p class="text-secondary small mb-4">Ingresa tu contraseña actual y elige una nueva de al menos 10 caracteres.</p>

            <form method="POST" action="perfil.php" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= escapar($token) ?>">
                <div class="mb-3">
                    <label for="contrasena_actual" class="form-label fw-semibold">Contraseña actual</label>
                    <input type="password" id="contrasena_actual" name="contrasena_actual" class="form-control"
                           autocomplete="current-password" required>
                </div>
                <div class="mb-3">
                    <label for="contrasena_nueva" class="form-label fw-semibold">Nueva contraseña</label>
                    <input type="password" id="contrasena_nueva" name="contrasena_nueva" class="form-control"
                           autocomplete="new-password" minlength="10" required>
                    <div class="form-text">Mínimo 10 caracteres. No reutilices la contraseña actual.</div>
                </div>
                <div class="mb-4">
                    <label for="confirmar_contrasena" class="form-label fw-semibold">Confirmar nueva contraseña</label>
                    <input type="password" id="confirmar_contrasena" name="confirmar_contrasena" class="form-control"
                           autocomplete="new-password" minlength="10" required>
                </div>
                <div class="d-flex justify-content-end gap-2 flex-wrap">
                    <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Actualizar contraseña</button>
                </div>
            </form>
        </div>
    </section>
</main>
</body>
</html>

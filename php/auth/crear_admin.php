<?php
/* =========================================================
   CHISPA ECOLÓGICA - Crear la cuenta de administrador
   ========================================================= */
require __DIR__ . '/../config/conexion.php';

$esLocal  = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
$hayAdmin = (int)$conexion->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'admin'")->fetchColumn() > 0;
$error    = '';
$creado   = false;

/* ---------- GUARDAR EL ADMINISTRADOR ---------- */
if ($esLocal && !$hayAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $correo = strtolower(trim($_POST['correo'] ?? ''));
    $clave  = $_POST['clave'] ?? '';

    if ($nombre === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = 'Escribe un nombre y un correo válido.';
    } elseif (strlen($clave) < 8) {
        $error = 'La contraseña debe tener mínimo 8 caracteres.';
    } else {
        $existe = $conexion->prepare("SELECT id FROM usuarios WHERE correo = ?");
        $existe->execute([$correo]);
        if ($existe->fetch()) {
            $error = 'Ese correo ya está registrado. Usa otro o convierte esa cuenta con el SQL del LEEME.';
        } else {
            $stmt = $conexion->prepare("INSERT INTO usuarios (nombre_completo, correo, contrasena, rol, grado) VALUES (?, ?, ?, 'admin', NULL)");
            $stmt->execute([$nombre, $correo, password_hash($clave, PASSWORD_DEFAULT)]);
            $creado = true;
            $hayAdmin = true;
        }
    }
}

function h(string $t): string { return htmlspecialchars($t, ENT_QUOTES, 'UTF-8'); }
$base = rutaBase();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear administrador - CHISPA ECOLÓGICA</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; font-family: "Poppins", Arial, sans-serif; background: linear-gradient(135deg, #123d22, #2cb673); }
        .caja { width: min(440px, 92vw); padding: 34px 32px; border-radius: 28px; background: #fff; box-shadow: 0 24px 60px rgba(0, 0, 0, .3); }
        h1 { margin: 0 0 6px; font-size: 1.5rem; color: #1a381e; }
        p { color: #4a5a43; line-height: 1.5; }
        label { display: block; margin: 14px 0 6px; font-weight: 700; font-size: .9rem; color: #1a381e; }
        input { width: 100%; box-sizing: border-box; padding: 12px 14px; border: 2px solid #dbe8d3; border-radius: 14px; font-size: 1rem; }
        input:focus { outline: none; border-color: #2cb673; }
        button, .enlace { display: inline-block; margin-top: 22px; padding: 13px 28px; border: none; border-radius: 999px; background: linear-gradient(135deg, #3d8b3d, #2cb673); color: #fff; font-weight: 700; font-size: 1rem; text-decoration: none; cursor: pointer; }
        .error { margin-top: 14px; padding: 10px 14px; border-radius: 12px; background: #ffe3e6; color: #a31224; }
        .ok { padding: 12px 14px; border-radius: 12px; background: #e3f6ea; color: #1b6b3a; }
    </style>
</head>
<body>
    <div class="caja">
        <h1>Crear administrador</h1>
        <?php if (!$esLocal): ?>
            <p>Por seguridad, esta página solo funciona desde el computador donde está instalado el sitio (localhost).</p>
        <?php elseif ($creado): ?>
            <p class="ok">¡Listo! La cuenta de administrador fue creada.</p>
            <p>Ya puedes iniciar sesión con ese correo y tu contraseña; entrarás directo al panel.</p>
            <a class="enlace" href="<?= h($base) ?>/Index.html?tab=login">Ir a iniciar sesión</a>
        <?php elseif ($hayAdmin): ?>
            <p>Ya existe un administrador, así que esta página quedó desactivada.</p>
            <a class="enlace" href="<?= h($base) ?>/Index.html?tab=login">Iniciar sesión</a>
        <?php else: ?>
            <p>Aún no hay administrador. Crea la cuenta que usarás para entrar al panel.</p>
            <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
            <form method="POST">
                <label for="nombre">Nombre</label>
                <input id="nombre" name="nombre" maxlength="100" value="<?= h($_POST['nombre'] ?? '') ?>" required>
                <label for="correo">Correo</label>
                <input id="correo" name="correo" type="email" value="<?= h($_POST['correo'] ?? '') ?>" required>
                <label for="clave">Contraseña (mínimo 8 caracteres)</label>
                <input id="clave" name="clave" type="password" minlength="8" required>
                <button type="submit">Crear administrador</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>

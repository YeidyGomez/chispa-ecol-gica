<?php
/* =========================================================
   CHISPA ECOLÓGICA - Registro, inicio y cierre de sesión
   Recibe los formularios de Index.html
   ========================================================= */
require __DIR__ . '/../config/conexion.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

/* ---------- CERRAR SESIÓN ---------- */
if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    redirigir('Index.html?msg=sesion_cerrada');
}

/* ---------- REGISTRO ---------- */
if ($action === 'registrar') {
    $nombre   = trim($_POST['regNombre'] ?? '');
    $correo   = strtolower(trim($_POST['regEmail'] ?? ''));
    $clave    = $_POST['regPassword'] ?? '';
    $perfil   = $_POST['regPerfil'] ?? '';                 // estudiante o externo
    $grado    = (int)($_POST['regGrado'] ?? 0);            // 6 a 11 (solo estudiantes)
    $acepto   = ($_POST['regAcepto'] ?? '') === '1';       // aceptó la política de privacidad
    $versionPolitica = '2026-11-11';                       // versión de la política que se acepta

    if ($nombre === '' || $correo === '' || $clave === '') {
        redirigir('Index.html?error=campos_vacios&tab=registro');
    }
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        redirigir('Index.html?error=correo_invalido&tab=registro');
    }
    if (strlen($clave) < 6) {
        redirigir('Index.html?error=clave_corta&tab=registro');
    }

    // La política de privacidad debe aceptarse (los menores, con autorización de su acudiente)
    if (!$acepto) {
        redirigir('Index.html?error=acepto_requerido&tab=registro');
    }

    // Validar perfil y grado (el rol administrador no se puede elegir al registrarse)
    if (!in_array($perfil, ['estudiante', 'externo'], true)) {
        redirigir('Index.html?error=perfil_invalido&tab=registro');
    }
    if ($perfil === 'estudiante' && ($grado < 6 || $grado > 11)) {
        redirigir('Index.html?error=grado_invalido&tab=registro');
    }
    if ($perfil !== 'estudiante') {
        $grado = null;
    }

    // Verificar correo repetido
    $existe = $conexion->prepare("SELECT id FROM usuarios WHERE correo = ?");
    $existe->execute([$correo]);
    if ($existe->fetch()) {
        redirigir('Index.html?error=correo_duplicado&tab=registro');
    }

    // Guardar usuario nuevo
    $stmt = $conexion->prepare("INSERT INTO usuarios (nombre_completo, correo, contrasena, rol, grado, politica_aceptada, politica_version) VALUES (?, ?, ?, ?, ?, NOW(), ?)");
    $stmt->execute([$nombre, $correo, password_hash($clave, PASSWORD_DEFAULT), $perfil, $grado, $versionPolitica]);

    // Inicia sesión automáticamente después de registrarse
    session_regenerate_id(true);
    $_SESSION['usuario_id'] = (int)$conexion->lastInsertId();
    $_SESSION['nombre']     = $nombre;
    $_SESSION['rol']        = $perfil;

    redirigir('paginas/retos/RetosEcologicos.html?msg=registro_ok');
}

/* ---------- INICIAR SESIÓN ---------- */
if ($action === 'login') {
    $correo = strtolower(trim($_POST['loginEmail'] ?? ''));
    $clave  = $_POST['loginPassword'] ?? '';

    // Buscar usuario por correo
    $stmt = $conexion->prepare("SELECT * FROM usuarios WHERE correo = ?");
    $stmt->execute([$correo]);
    $usuario = $stmt->fetch();

    if (!$usuario || !password_verify($clave, $usuario['contrasena'])) {
        redirigir('Index.html?error=credenciales_incorrectas&tab=login');
    }

    session_regenerate_id(true);
    $_SESSION['usuario_id'] = (int)$usuario['id'];
    $_SESSION['nombre']     = $usuario['nombre_completo'];
    $_SESSION['rol']        = $usuario['rol'] ?? 'externo';

    // "Recordarme": mantiene la sesión abierta 30 días
    if (!empty($_POST['recordar'])) {
        $p = session_get_cookie_params();
        setcookie(session_name(), session_id(), [
            'expires'  => time() + 60 * 60 * 24 * 30,
            'path'     => $p['path'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    // El administrador entra directo a su panel
    if (($usuario['rol'] ?? '') === 'admin') {
        redirigir('paginas/admin/PanelAdmin.html');
    }
    redirigir('paginas/retos/RetosEcologicos.html?msg=bienvenido');
}

/* Si alguien abre auth.php directamente */
redirigir('Index.html');

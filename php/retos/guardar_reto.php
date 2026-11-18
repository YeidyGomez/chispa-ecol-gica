<?php
/* =========================================================
   CHISPA ECOLÓGICA - Guarda la evidencia de un reto y suma EcoPuntos
   Lo usan los formularios de cada reto 
   ========================================================= */
require __DIR__ . '/../config/conexion.php';

$clave  = $_POST['reto'] ?? '';
// La clave solo puede ser letras minúsculas y debe existir su página en paginas/retos/ (así agregar un reto no exige tocar este archivo)
$pagina = (preg_match('/^[a-z]+$/', $clave) && is_file(RAIZ_DIR . "/paginas/retos/$clave.html"))
    ? "paginas/retos/$clave.html"
    : 'paginas/retos/RetosEcologicos.html';

$u = usuarioActual();
if (!$u) {
    redirigir('Index.html?error=necesitas_sesion&tab=login');
}

// El administrador gestiona la plataforma, no acumula EcoPuntos
if (($u['rol'] ?? '') === 'admin') {
    redirigir('paginas/retos/RetosEcologicos.html?error=admin_no_participa');
}

// Solo se acepta el envío del formulario
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir($pagina);
}

// Si la imagen supera post_max_size de PHP, $_POST llega vacío
if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    redirigir('paginas/retos/RetosEcologicos.html?error=imagen_grande');
}

// Buscar el reto
$stmt = $conexion->prepare("SELECT id, puntos FROM retos WHERE clave = ?");
$stmt->execute([$clave]);
$reto = $stmt->fetch();
if (!$reto) {
    redirigir('paginas/retos/RetosEcologicos.html?error=reto_invalido');
}

$descripcion = trim($_POST['descripcion'] ?? '');
if (mb_strlen($descripcion) < 10) {
    redirigir("$pagina?error=descripcion_corta");
}

// Filtro de seguridad: lenguaje ofensivo, enlaces y datos de contacto
$problema = revisarContenido($descripcion);
if ($problema) {
    redirigir("$pagina?error=$problema");
}

// Reto semanal: solo una vez cada 7 días (una evidencia rechazada no cuenta, así puede volver a intentarlo)
$stmt = $conexion->prepare(
    "SELECT COUNT(*) FROM evidencias WHERE usuario_id = ? AND reto_id = ? AND estado IN ('pendiente','aprobado') AND fecha >= (NOW() - INTERVAL 7 DAY)"
);
$stmt->execute([$u['id'], $reto['id']]);
if ($stmt->fetchColumn() > 0) {
    redirigir('paginas/retos/RetosEcologicos.html?error=reto_repetido');
}

$imagen = guardarImagen('imagen', 'retos');
if (!$imagen) {
    redirigir("$pagina?error=imagen_invalida");
}

// La evidencia queda 'pendiente': los EcoPuntos se suman cuando el administrador la aprueba (ver php/admin/moderacion.php)
try {
    $conexion->prepare("INSERT INTO evidencias (usuario_id, reto_id, descripcion, imagen, puntos, estado) VALUES (?, ?, ?, ?, ?, 'pendiente')")
             ->execute([$u['id'], $reto['id'], $descripcion, $imagen, $reto['puntos']]);
} catch (PDOException $e) {
    @unlink(RAIZ_DIR . '/' . $imagen);
    redirigir("$pagina?error=error_servidor");
}

redirigir('paginas/retos/RetosEcologicos.html?msg=reto_pendiente&pts=' . $reto['puntos']);

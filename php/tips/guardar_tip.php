<?php
/* =========================================================
   CHISPA ECOLÓGICA - Guarda un tip enviado desde compartirtips.html
   ========================================================= */
require __DIR__ . '/../config/conexion.php';

// Solo se acepta el envío del formulario
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('paginas/tips/compartirtips.html');
}

$titulo      = trim($_POST['titulo'] ?? '');
$categoria   = $_POST['categoria'] ?? '';
$explicacion = trim($_POST['explicacion'] ?? '');

$categorias = ['hogar', 'energia', 'movilidad', 'agua'];

if ($titulo === '' || $explicacion === '' || !in_array($categoria, $categorias, true)) {
    redirigir('paginas/tips/compartirtips.html?error=campos_vacios');
}
if (mb_strlen($titulo) > 150) {
    $titulo = mb_substr($titulo, 0, 150);
}

// Filtro de seguridad: lenguaje ofensivo, enlaces y datos de contacto
$problema = revisarContenido($titulo, $explicacion);
if ($problema) {
    redirigir('paginas/tips/compartirtips.html?error=' . $problema);
}

$u = usuarioActual();

// Moderación: el tip queda 'pendiente' hasta que el administrador lo apruebe (si lo envía el administrador, se publica directo)
$estado = esAdmin($conexion) ? 'aprobado' : 'pendiente';

// Guardar el tip
$stmt = $conexion->prepare(
    "INSERT INTO tips (usuario_id, autor, titulo, categoria, explicacion, origen, estado) VALUES (?, ?, ?, ?, ?, 'comunidad', ?)"
);
$stmt->execute([$u['id'] ?? null, $u['nombre'] ?? 'Anónimo', $titulo, $categoria, $explicacion, $estado]);

redirigir('paginas/tips/TipsEcologicos.html?msg=' . ($estado === 'aprobado' ? 'tip_ok' : 'tip_pendiente'));

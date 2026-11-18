<?php
/* =========================================================
   CHISPA ECOLÓGICA - Devuelve el usuario con sesión iniciada
   Lo usan todas las páginas
   ========================================================= */
require __DIR__ . '/../config/conexion.php';

$u = usuarioActual();
if (!$u) {
    responderJSON(['logueado' => false]);
}

// Consultar datos del usuario
$stmt = $conexion->prepare("SELECT id, nombre_completo, correo, puntos, rol, grado FROM usuarios WHERE id = ?");
$stmt->execute([$u['id']]);
$usuario = $stmt->fetch();

if (!$usuario) {                         // el usuario fue borrado de la BD
    session_destroy();
    responderJSON(['logueado' => false]);
}

$_SESSION['rol'] = $usuario['rol'];       // mantiene el rol de la sesión al día

// Cantidad de retos completados (solo los aprobados por el administrador)
$stmt = $conexion->prepare("SELECT COUNT(*) FROM evidencias WHERE usuario_id = ? AND estado = 'aprobado'");
$stmt->execute([$u['id']]);
$totalRetos = (int)$stmt->fetchColumn();

// Retos enviados en los últimos 7 días (son retos semanales). Los rechazados no cuentan: puede volver a intentarlo.
$stmt = $conexion->prepare(
    "SELECT r.clave, e.estado FROM evidencias e JOIN retos r ON r.id = e.reto_id
     WHERE e.usuario_id = ? AND e.estado IN ('pendiente','aprobado') AND e.fecha >= (NOW() - INTERVAL 7 DAY)"
);
$stmt->execute([$u['id']]);
$retosSemana = [];     // aprobados + en revisión
$retosRevision = [];   // solo los que esperan aprobación
foreach ($stmt->fetchAll() as $f) {
    $retosSemana[] = $f['clave'];
    if ($f['estado'] === 'pendiente') $retosRevision[] = $f['clave'];
}
$retosSemana   = array_values(array_unique($retosSemana));
$retosRevision = array_values(array_unique($retosRevision));

responderJSON([
    'logueado'     => true,
    'id'           => (int)$usuario['id'],
    'nombre'       => $usuario['nombre_completo'],
    'correo'       => $usuario['correo'],
    'rol'          => $usuario['rol'],
    'grado'        => $usuario['grado'] !== null ? (int)$usuario['grado'] : null,
    'puntos'       => (int)$usuario['puntos'],
    'retos'        => $totalRetos,
    'retos_semana' => $retosSemana,
    'retos_revision' => $retosRevision,
    'nivel'        => calcularNivel((int)$usuario['puntos']),
]);

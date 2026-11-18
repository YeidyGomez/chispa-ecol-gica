<?php
/* =========================================================
   CHISPA ECOLÓGICA - Lista / busca tips (JSON)
   ?origen=comunidad  -> solo los enviados por usuarios (TipsEcologicos.html)
   ?q=texto           -> búsqueda en todos los tips (buscartip.html)
   ========================================================= */
require __DIR__ . '/../config/conexion.php';

$q      = trim($_GET['q'] ?? '');
$origen = $_GET['origen'] ?? '';

// Solo se muestran los tips aprobados. Quien envió un tip también ve el suyo mientras está "en revisión".
$yo     = usuarioActual();
$sql    = "SELECT id, autor, titulo, categoria, explicacion, origen, fecha, estado FROM tips
           WHERE (estado = 'aprobado' OR (estado = 'pendiente' AND usuario_id IS NOT NULL AND usuario_id = ?))";
$params = [$yo['id'] ?? 0];

if ($origen === 'comunidad' || $origen === 'base') {
    $sql .= " AND origen = ?";
    $params[] = $origen;
}
if ($q !== '') {
    $sql .= " AND (titulo LIKE ? OR explicacion LIKE ? OR categoria LIKE ?)";
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
$sql .= " ORDER BY fecha DESC LIMIT 50";

// Consultar tips
$stmt = $conexion->prepare($sql);
$stmt->execute($params);

responderJSON($stmt->fetchAll());

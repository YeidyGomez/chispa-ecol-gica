<?php
/* =========================================================
   CHISPA ECOLÓGICA - Tabla de posiciones
   ========================================================= */
require __DIR__ . '/../config/conexion.php';

$filas = $conexion->query(
    "SELECT u.id, u.nombre_completo, u.puntos, COUNT(e.id) AS retos
     FROM usuarios u
     LEFT JOIN evidencias e ON e.usuario_id = u.id AND e.estado = 'aprobado'
     WHERE u.rol <> 'admin'
     GROUP BY u.id, u.nombre_completo, u.puntos
     ORDER BY u.puntos DESC, retos DESC, u.id ASC
     LIMIT 10"
)->fetchAll();

$yo = usuarioActual();
$ranking = [];
foreach ($filas as $f) {
    // Muestra "Nombre A." en vez del nombre completo (privacidad)
    $partes = preg_split('/\s+/', trim($f['nombre_completo']));
    $corto  = $partes[0] . (isset($partes[1]) ? ' ' . mb_substr($partes[1], 0, 1) . '.' : '');

    $ranking[] = [
        'nombre' => $corto,
        'puntos' => (int)$f['puntos'],
        'retos'  => (int)$f['retos'],
        'nivel'  => calcularNivel((int)$f['puntos'])['nombre'],
        'soy_yo' => $yo && $yo['id'] === (int)$f['id'],
    ];
}

responderJSON($ranking);

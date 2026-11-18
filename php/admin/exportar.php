<?php
/* =========================================================
   CHISPA ECOLÓGICA - Reportes en CSV (se abre con Excel)
   tipo=detalle -> una fila por persona (respeta los filtros)
   tipo=grados  -> resumen de sexto a undécimo
   ========================================================= */
require __DIR__ . '/../config/conexion.php';
require __DIR__ . '/funciones_admin.php';

requerirAdmin($conexion, false);

/* Evita que Excel interprete un texto como fórmula */
function celdaSegura($valor) {
    if (is_string($valor) && $valor !== '' && strpos('=+-@', $valor[0]) !== false) {
        return "'" . $valor;
    }
    return $valor;
}

$tipo    = ($_GET['tipo'] ?? 'detalle') === 'grados' ? 'grados' : 'detalle';
$filtros = leerFiltros();
$fecha   = date('Y-m-d');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="reporte_chispa_ecologica_' . $tipo . '_' . $fecha . '.csv"');
echo "\xEF\xBB\xBF";                                   // para que Excel lea bien las tildes
$salida = fopen('php://output', 'w');

/* ---------- REPORTE POR GRADO ---------- */
if ($tipo === 'grados') {
    fputcsv($salida, ['Reporte por grado - Chispa Ecológica', $fecha], ';', '"', '\\');
    fputcsv($salida, ['Grado', 'Estudiantes', 'Con interacciones', 'Participación (%)', 'EcoPuntos', 'Retos completados', 'Tips compartidos', 'Publicaciones', 'Comentarios', 'Me gusta dados'], ';', '"', '\\');
    $suma = ['estudiantes' => 0, 'activos' => 0, 'puntos' => 0, 'retos' => 0, 'tips' => 0, 'publicaciones' => 0, 'comentarios' => 0, 'likes' => 0];
    foreach (resumenPorGrado($conexion) as $g) {
        fputcsv($salida, [$g['grado'] . '° ' . $g['nombre'], $g['estudiantes'], $g['activos'], $g['participacion'], $g['puntos'], $g['retos'], $g['tips'], $g['publicaciones'], $g['comentarios'], $g['likes']], ';', '"', '\\');
        foreach ($suma as $k => $v) { $suma[$k] += $g[$k]; }
    }
    $part = $suma['estudiantes'] > 0 ? (int)round($suma['activos'] * 100 / $suma['estudiantes']) : 0;
    fputcsv($salida, ['TOTAL', $suma['estudiantes'], $suma['activos'], $part, $suma['puntos'], $suma['retos'], $suma['tips'], $suma['publicaciones'], $suma['comentarios'], $suma['likes']], ';', '"', '\\');
    fclose($salida);
    exit;
}

/* ---------- REPORTE DETALLADO ---------- */
$textoFiltros = 'Perfil: ' . ($filtros['perfil'] === 'todos' ? 'todos' : etiquetaPerfil($filtros['perfil']))
              . ' | Grado: ' . ($filtros['grado'] > 0 ? $filtros['grado'] . '°' : 'todos')
              . ($filtros['q'] !== '' ? ' | Búsqueda: ' . $filtros['q'] : '');
fputcsv($salida, ['Reporte detallado - Chispa Ecológica', $fecha, celdaSegura($textoFiltros)], ';', '"', '\\');
fputcsv($salida, ['Nombre', 'Correo', 'Perfil', 'Grado', 'EcoPuntos', 'Nivel', 'Retos completados', 'Tips compartidos', 'Publicaciones', 'Comentarios', 'Me gusta dados', 'Total de interacciones', 'Fecha de registro', 'Política aceptada', 'Última actividad'], ';', '"', '\\');
foreach (consultarUsuarios($conexion, $filtros) as $u) {
    fputcsv($salida, [
        celdaSegura($u['nombre']), celdaSegura($u['correo']), etiquetaPerfil($u['rol']),
        $u['grado'] !== null ? $u['grado'] . '°' : '',
        $u['puntos'], $u['nivel'], $u['retos'], $u['tips'], $u['publicaciones'], $u['comentarios'], $u['likes'], $u['interacciones'],
        $u['registro'], $u['politica'] ?? 'Sin registro', $u['ultima_actividad'] ?? '',
    ], ';', '"', '\\');
}
fclose($salida);

<?php
/* =========================================================
   CHISPA ECOLÓGICA - Funciones compartidas del panel de administración
   Las usan api_admin.php (JSON) y exportar.php (CSV)
   ========================================================= */

/* ---------- NOMBRES DE LOS GRADOS ---------- */
function nombresGrados(): array {
    return [6 => 'Sexto', 7 => 'Séptimo', 8 => 'Octavo', 9 => 'Noveno', 10 => 'Décimo', 11 => 'Undécimo'];
}

/* ---------- FILTROS RECIBIDOS POR URL ---------- */
function leerFiltros(): array {
    $perfil = $_GET['perfil'] ?? 'todos';
    if (!in_array($perfil, ['todos', 'estudiante', 'externo'], true)) {
        $perfil = 'todos';
    }
    $grado = (int)($_GET['grado'] ?? 0);
    if ($grado < 6 || $grado > 11 || $perfil === 'externo') {
        $grado = 0;                                   // 0 = todos los grados
    }
    $q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100);
    return ['perfil' => $perfil, 'grado' => $grado, 'q' => $q];
}

/* ---------- PERSONAS E INTERACCIONES (CON FILTROS) ---------- */
function consultarUsuarios(PDO $db, array $f): array {
    $donde  = ["u.rol <> 'admin'"];
    $params = [];

    if ($f['perfil'] === 'estudiante') {
        $donde[] = "u.rol = 'estudiante'";
    } elseif ($f['perfil'] === 'externo') {
        $donde[] = "u.rol = 'externo'";
    }
    if ($f['grado'] > 0) {
        $donde[]  = "u.grado = ?";
        $params[] = $f['grado'];
    }
    if ($f['q'] !== '') {
        $patron   = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $f['q']) . '%';
        $donde[]  = "(u.nombre_completo LIKE ? OR u.correo LIKE ?)";
        $params[] = $patron;
        $params[] = $patron;
    }

    $sql = "SELECT u.id, u.nombre_completo, u.correo, u.rol, u.grado, u.puntos, u.fecha_registro, u.politica_aceptada,
                   (SELECT COUNT(*) FROM evidencias e WHERE e.usuario_id = u.id AND e.estado = 'aprobado')                       AS retos,
                   (SELECT COUNT(*) FROM tips t WHERE t.usuario_id = u.id AND t.estado = 'aprobado')                             AS tips,
                   (SELECT COUNT(*) FROM publicaciones p WHERE p.usuario_id = u.id AND p.estado = 'aprobado')                    AS publicaciones,
                   (SELECT COUNT(*) FROM comentarios c WHERE c.visitante = CONCAT('u', u.id) AND c.estado = 'aprobado')          AS comentarios,
                   (SELECT COUNT(*) FROM publicacion_likes l WHERE l.visitante = CONCAT('u', u.id))    AS likes_pub,
                   (SELECT COUNT(*) FROM comentario_likes cl WHERE cl.visitante = CONCAT('u', u.id))   AS likes_com,
                   GREATEST(
                       COALESCE((SELECT MAX(fecha) FROM evidencias    WHERE usuario_id = u.id), '1970-01-01 00:00:00'),
                       COALESCE((SELECT MAX(fecha) FROM tips          WHERE usuario_id = u.id), '1970-01-01 00:00:00'),
                       COALESCE((SELECT MAX(fecha) FROM publicaciones WHERE usuario_id = u.id), '1970-01-01 00:00:00'),
                       COALESCE((SELECT MAX(fecha) FROM comentarios   WHERE visitante = CONCAT('u', u.id)), '1970-01-01 00:00:00')
                   ) AS ultima
            FROM usuarios u
            WHERE " . implode(' AND ', $donde) . "
            ORDER BY u.nombre_completo ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $filas = [];
    foreach ($stmt->fetchAll() as $r) {
        $likes = (int)$r['likes_pub'] + (int)$r['likes_com'];
        $filas[] = [
            'id'                => (int)$r['id'],
            'nombre'            => $r['nombre_completo'],
            'correo'            => $r['correo'],
            'rol'               => $r['rol'],
            'grado'             => $r['grado'] !== null ? (int)$r['grado'] : null,
            'puntos'            => (int)$r['puntos'],
            'nivel'             => calcularNivel((int)$r['puntos'])['nombre'],
            'retos'             => (int)$r['retos'],
            'tips'              => (int)$r['tips'],
            'publicaciones'     => (int)$r['publicaciones'],
            'comentarios'       => (int)$r['comentarios'],
            'likes'             => $likes,
            'interacciones'     => (int)$r['retos'] + (int)$r['tips'] + (int)$r['publicaciones'] + (int)$r['comentarios'] + $likes,
            'registro'          => $r['fecha_registro'],
            'politica'          => $r['politica_aceptada'],
            'ultima_actividad'  => strpos((string)$r['ultima'], '1970') === 0 ? null : $r['ultima'],
        ];
    }
    return $filas;
}

/* ---------- TOTALES DE LO QUE SE ESTÁ VIENDO ---------- */
function calcularTotales(array $usuarios): array {
    $t = ['personas' => count($usuarios), 'estudiantes' => 0, 'externos' => 0, 'puntos' => 0,
          'retos' => 0, 'tips' => 0, 'publicaciones' => 0, 'comentarios' => 0, 'likes' => 0];
    foreach ($usuarios as $u) {
        if ($u['rol'] === 'estudiante') { $t['estudiantes']++; } else { $t['externos']++; }
        foreach (['puntos', 'retos', 'tips', 'publicaciones', 'comentarios', 'likes'] as $k) {
            $t[$k] += $u[$k];
        }
    }
    return $t;
}

/* ---------- RESUMEN POR GRADO (SEXTO A UNDÉCIMO) ---------- */
function resumenPorGrado(PDO $db): array {
    $todos = consultarUsuarios($db, ['perfil' => 'estudiante', 'grado' => 0, 'q' => '']);
    $grados = [];
    foreach (nombresGrados() as $g => $nombre) {
        $grados[$g] = ['grado' => $g, 'nombre' => $nombre, 'estudiantes' => 0, 'activos' => 0, 'puntos' => 0,
                       'retos' => 0, 'tips' => 0, 'publicaciones' => 0, 'comentarios' => 0, 'likes' => 0];
    }
    foreach ($todos as $u) {
        if (!isset($grados[$u['grado']])) continue;     // estudiante sin grado válido
        $g = &$grados[$u['grado']];
        $g['estudiantes']++;
        if ($u['interacciones'] > 0) $g['activos']++;
        foreach (['puntos', 'retos', 'tips', 'publicaciones', 'comentarios', 'likes'] as $k) {
            $g[$k] += $u[$k];
        }
        unset($g);
    }
    foreach ($grados as &$g) {
        $g['participacion'] = $g['estudiantes'] > 0 ? (int)round($g['activos'] * 100 / $g['estudiantes']) : 0;
    }
    unset($g);
    return array_values($grados);
}

/* ---------- TEXTO DEL PERFIL ---------- */
function etiquetaPerfil(string $rol): string {
    return $rol === 'estudiante' ? 'Estudiante' : 'Usuario externo';
}

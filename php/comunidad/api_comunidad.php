<?php
/* =========================================================
   CHISPA ECOLÓGICA - API de Comunidad 
 ========================================================= */
require __DIR__ . '/../config/conexion.php';

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';
$yo     = usuarioActual();
$v      = visitanteId();
$esPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$admin  = esAdmin($conexion);
$estadoNuevo = $admin ? 'aprobado' : 'pendiente';   // lo del administrador se publica directo

/* ---------- LISTAR PUBLICACIONES ---------- */
if ($accion === 'listar') {
    $stmt = $conexion->prepare(
        "SELECT p.id, p.autor, p.titulo, p.descripcion, p.imagen, p.fecha, p.visitante,
                (SELECT COUNT(*) FROM publicacion_likes l WHERE l.publicacion_id = p.id) AS likes,
                (SELECT COUNT(*) FROM publicacion_likes l WHERE l.publicacion_id = p.id AND l.visitante = ?) AS me_gusta,
                (SELECT COUNT(*) FROM comentarios c WHERE c.publicacion_id = p.id AND c.estado = 'aprobado') AS comentarios,
                p.estado
         FROM publicaciones p
         WHERE p.estado = 'aprobado' OR (p.estado = 'pendiente' AND p.visitante <> '' AND p.visitante = ?)
         ORDER BY p.fecha DESC, p.id DESC
         LIMIT 100"
    );
    $stmt->execute([$v, $v]);
    $datos = array_map(function ($p) use ($v, $admin) {
        return [
            'id'          => (int)$p['id'],
            'autor'       => $p['autor'],
            'titulo'      => $p['titulo'],
            'descripcion' => $p['descripcion'],
            'imagen'      => $p['imagen'],
            'fecha'       => $p['fecha'],
            'likes'       => (int)$p['likes'],
            'me_gusta'    => (bool)$p['me_gusta'],
            'comentarios' => (int)$p['comentarios'],
            'es_mio'      => $p['visitante'] !== '' && $p['visitante'] === $v,
            'puede_eliminar' => $admin || ($p['visitante'] !== '' && $p['visitante'] === $v),
            'estado'      => $p['estado'],
        ];
    }, $stmt->fetchAll());
    responderJSON($datos);
}

/* ---------- PUBLICAR ---------- */
if ($accion === 'publicar' && $esPost) {
    $autor       = trim($_POST['autor'] ?? '');
    $titulo      = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');

    if ($autor === '') $autor = $yo['nombre'] ?? 'Anónimo';

    if ($titulo === '' || $descripcion === '') {
        responderJSON(['ok' => false, 'error' => 'Completa el título y la descripción.'], 400);
    }

    // Filtro de seguridad: lenguaje ofensivo, enlaces y datos de contacto
    $problema = revisarContenido($autor, $titulo, $descripcion);
    if ($problema) {
        responderJSON(['ok' => false, 'error' => mensajeFiltro($problema)], 400);
    }

    $imagen = guardarImagen('imagen', 'comunidad');
    if (!$imagen) {
        responderJSON(['ok' => false, 'error' => 'Sube una imagen válida (JPG, PNG, GIF o WEBP de máximo 5 MB).'], 400);
    }

    $stmt = $conexion->prepare(
        "INSERT INTO publicaciones (usuario_id, autor, titulo, descripcion, imagen, visitante, estado) VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$yo['id'] ?? null, mb_substr($autor, 0, 30), mb_substr($titulo, 0, 150), mb_substr($descripcion, 0, 2000), $imagen, $v, $estadoNuevo]);

    responderJSON(['ok' => true, 'id' => (int)$conexion->lastInsertId(), 'estado' => $estadoNuevo]);
}

/* ---------- ELIMINAR PUBLICACIÓN ---------- */
if ($accion === 'eliminar' && $esPost) {
    $id = (int)($_POST['id'] ?? 0);
    // El administrador puede eliminar cualquier publicación; los demás, solo las suyas
    $stmt = $conexion->prepare("SELECT imagen FROM publicaciones WHERE id = ? AND (? = 1 OR (visitante = ? AND visitante <> ''))");
    $stmt->execute([$id, $admin ? 1 : 0, $v]);
    $pub = $stmt->fetch();
    if (!$pub) {
        responderJSON(['ok' => false, 'error' => 'Solo puedes eliminar tus propias publicaciones.'], 403);
    }
    $conexion->prepare("DELETE FROM publicaciones WHERE id = ?")->execute([$id]);
    @unlink(RAIZ_DIR . '/' . $pub['imagen']);
    responderJSON(['ok' => true]);
}

/* ---------- ME GUSTA EN PUBLICACIÓN (activar / quitar) ---------- */
if ($accion === 'like' && $esPost) {
    $id = (int)($_POST['id'] ?? 0);
    $ok = $conexion->prepare("SELECT 1 FROM publicaciones WHERE id = ? AND estado = 'aprobado'");
    $ok->execute([$id]);
    if (!$ok->fetch()) responderJSON(['ok' => false, 'error' => 'Esta publicación aún no está disponible.'], 404);
    $meGusta = alternarLike($conexion, 'publicacion_likes', 'publicacion_id', $id, $v);

    $stmt = $conexion->prepare("SELECT COUNT(*) FROM publicacion_likes WHERE publicacion_id = ?");
    $stmt->execute([$id]);
    responderJSON(['ok' => true, 'me_gusta' => $meGusta, 'likes' => (int)$stmt->fetchColumn()]);
}

/* ---------- LISTAR COMENTARIOS (con respuestas) ---------- */
if ($accion === 'comentarios') {
    $pubId = (int)($_GET['id'] ?? 0);

    // Una publicación en revisión solo la ve su autor
    $vis = $conexion->prepare("SELECT 1 FROM publicaciones WHERE id = ? AND (estado = 'aprobado' OR visitante = ?)");
    $vis->execute([$pubId, $v]);
    if (!$vis->fetch()) responderJSON([]);

    // Solo comentarios aprobados; el autor también ve los suyos "en revisión"
    $stmt = $conexion->prepare(
        "SELECT c.id, c.parent_id, c.autor, c.texto, c.fecha, c.visitante, c.estado,
                (SELECT COUNT(*) FROM comentario_likes l WHERE l.comentario_id = c.id) AS likes,
                (SELECT COUNT(*) FROM comentario_likes l WHERE l.comentario_id = c.id AND l.visitante = ?) AS me_gusta
         FROM comentarios c
         WHERE c.publicacion_id = ?
           AND (c.estado = 'aprobado' OR (c.estado = 'pendiente' AND c.visitante <> '' AND c.visitante = ?))
         ORDER BY c.fecha ASC, c.id ASC"
    );
    $stmt->execute([$v, $pubId, $v]);

    $principales = [];
    $respuestas  = [];
    foreach ($stmt->fetchAll() as $c) {
        $item = [
            'id'        => (int)$c['id'],
            'autor'     => $c['autor'],
            'texto'     => $c['texto'],
            'fecha'     => $c['fecha'],
            'likes'     => (int)$c['likes'],
            'me_gusta'  => (bool)$c['me_gusta'],
            'es_mio'    => $c['visitante'] !== '' && $c['visitante'] === $v,
            'puede_eliminar' => $admin || ($c['visitante'] !== '' && $c['visitante'] === $v),
            'estado'    => $c['estado'],
            'respuestas'=> [],
        ];
        if ($c['parent_id']) $respuestas[(int)$c['parent_id']][] = $item;
        else                 $principales[] = $item;
    }
    foreach ($principales as &$p) {
        $p['respuestas'] = $respuestas[$p['id']] ?? [];
    }
    unset($p);
    responderJSON($principales);
}

/* ---------- COMENTAR / RESPONDER ---------- */
if ($accion === 'comentar' && $esPost) {
    $id       = (int)($_POST['id'] ?? 0);
    $parentId = (int)($_POST['parent_id'] ?? 0) ?: null;
    $autor    = trim($_POST['autor'] ?? '');
    $texto    = trim($_POST['texto'] ?? '');

    if ($autor === '') $autor = $yo['nombre'] ?? 'Anónimo';
    if ($texto === '') {
        responderJSON(['ok' => false, 'error' => 'Escribe un comentario.'], 400);
    }

    // Filtro de seguridad: lenguaje ofensivo, enlaces y datos de contacto
    $problema = revisarContenido($autor, $texto);
    if ($problema) {
        responderJSON(['ok' => false, 'error' => mensajeFiltro($problema)], 400);
    }

    // Solo se comenta en publicaciones ya aprobadas
    $pub = $conexion->prepare("SELECT 1 FROM publicaciones WHERE id = ? AND estado = 'aprobado'");
    $pub->execute([$id]);
    if (!$pub->fetch()) responderJSON(['ok' => false, 'error' => 'Publicación no encontrada.'], 404);

    // Una respuesta debe pertenecer a un comentario principal (ya aprobado) de la misma publicación
    if ($parentId) {
        $stmt = $conexion->prepare("SELECT parent_id FROM comentarios WHERE id = ? AND publicacion_id = ? AND estado = 'aprobado'");
        $stmt->execute([$parentId, $id]);
        $padre = $stmt->fetch();
        if (!$padre) responderJSON(['ok' => false, 'error' => 'El comentario ya no existe.'], 404);
        if ($padre['parent_id']) $parentId = (int)$padre['parent_id'];   // respuestas de un solo nivel
    }

    try {
        $conexion->prepare("INSERT INTO comentarios (publicacion_id, parent_id, autor, texto, visitante, estado) VALUES (?, ?, ?, ?, ?, ?)")
                 ->execute([$id, $parentId, mb_substr($autor, 0, 30), mb_substr($texto, 0, 500), $v, $estadoNuevo]);
    } catch (PDOException $e) {
        responderJSON(['ok' => false, 'error' => 'Publicación no encontrada.'], 404);
    }
    responderJSON(['ok' => true, 'estado' => $estadoNuevo]);
}

/* ---------- ME GUSTA EN COMENTARIO ---------- */
if ($accion === 'like_comentario' && $esPost) {
    $id = (int)($_POST['id'] ?? 0);
    $ok = $conexion->prepare("SELECT 1 FROM comentarios WHERE id = ? AND estado = 'aprobado'");
    $ok->execute([$id]);
    if (!$ok->fetch()) responderJSON(['ok' => false, 'error' => 'Este comentario aún no está disponible.'], 404);
    $meGusta = alternarLike($conexion, 'comentario_likes', 'comentario_id', $id, $v);

    $stmt = $conexion->prepare("SELECT COUNT(*) FROM comentario_likes WHERE comentario_id = ?");
    $stmt->execute([$id]);
    responderJSON(['ok' => true, 'me_gusta' => $meGusta, 'likes' => (int)$stmt->fetchColumn()]);
}

/* ---------- ELIMINAR COMENTARIO (y sus respuestas) ---------- */
if ($accion === 'eliminar_comentario' && $esPost) {
    $id = (int)($_POST['id'] ?? 0);
    // El administrador puede eliminar cualquier comentario; los demás, solo los suyos
    $stmt = $conexion->prepare("SELECT id FROM comentarios WHERE id = ? AND (? = 1 OR (visitante = ? AND visitante <> ''))");
    $stmt->execute([$id, $admin ? 1 : 0, $v]);
    if (!$stmt->fetch()) {
        responderJSON(['ok' => false, 'error' => 'Solo puedes eliminar tus propios comentarios.'], 403);
    }
    $conexion->prepare("DELETE FROM comentarios WHERE parent_id = ?")->execute([$id]);
    $conexion->prepare("DELETE FROM comentarios WHERE id = ?")->execute([$id]);
    responderJSON(['ok' => true]);
}

responderJSON(['ok' => false, 'error' => 'Acción no válida.'], 400);

/* ---------- Función auxiliar: pone o quita un "me gusta" ---------- */
function alternarLike(PDO $db, string $tabla, string $columna, int $id, string $v): bool {
    $stmt = $db->prepare("SELECT 1 FROM $tabla WHERE $columna = ? AND visitante = ?");
    $stmt->execute([$id, $v]);
    if ($stmt->fetch()) {
        $db->prepare("DELETE FROM $tabla WHERE $columna = ? AND visitante = ?")->execute([$id, $v]);
        return false;
    }
    try {
        $db->prepare("INSERT INTO $tabla ($columna, visitante) VALUES (?, ?)")->execute([$id, $v]);
    } catch (PDOException $e) {
        responderJSON(['ok' => false, 'error' => 'Elemento no encontrado.'], 404);
    }
    return true;
}

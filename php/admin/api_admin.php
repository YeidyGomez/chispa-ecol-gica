<?php
/* =========================================================
   CHISPA ECOLÓGICA - API del panel de administración (JSON)
   Acciones: resumen, detalle, actualizar
   Moderación: moderacion_resumen, moderacion_listar, moderar, eliminar
   Solo responde a usuarios con perfil administrador
   ========================================================= */
require __DIR__ . '/../config/conexion.php';
require __DIR__ . '/funciones_admin.php';
require __DIR__ . '/moderacion.php';

requerirAdmin($conexion);

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

/* ---------- RESUMEN: TOTALES, GRADOS Y PERSONAS ---------- */
if ($accion === 'resumen') {
    $filtros  = leerFiltros();
    $usuarios = consultarUsuarios($conexion, $filtros);

    responderJSON([
        'ok'       => true,
        'filtros'  => $filtros,
        'totales'  => calcularTotales($usuarios),
        'grados'   => resumenPorGrado($conexion),
        'usuarios' => $usuarios,
    ]);
}

/* ---------- DETALLE DE UNA PERSONA ---------- */
if ($accion === 'detalle') {
    $id = (int)($_GET['id'] ?? 0);

    // Datos de la persona
    $stmt = $conexion->prepare("SELECT id, nombre_completo, correo, rol, grado, puntos, fecha_registro, politica_aceptada, politica_version FROM usuarios WHERE id = ? AND rol <> 'admin'");
    $stmt->execute([$id]);
    $u = $stmt->fetch();
    if (!$u) {
        responderJSON(['ok' => false, 'error' => 'Persona no encontrada.'], 404);
    }

    // Retos completados
    $stmt = $conexion->prepare("SELECT r.nombre, e.puntos, e.fecha, e.descripcion FROM evidencias e JOIN retos r ON r.id = e.reto_id WHERE e.usuario_id = ? AND e.estado = 'aprobado' ORDER BY e.fecha DESC LIMIT 50");
    $stmt->execute([$id]);
    $retos = $stmt->fetchAll();

    // Tips compartidos
    $stmt = $conexion->prepare("SELECT titulo, categoria, fecha FROM tips WHERE usuario_id = ? AND estado = 'aprobado' ORDER BY fecha DESC LIMIT 50");
    $stmt->execute([$id]);
    $tips = $stmt->fetchAll();

    // Publicaciones en Comunidad Eco
    $stmt = $conexion->prepare(
        "SELECT p.titulo, p.fecha,
                (SELECT COUNT(*) FROM publicacion_likes l WHERE l.publicacion_id = p.id) AS likes,
                (SELECT COUNT(*) FROM comentarios c WHERE c.publicacion_id = p.id AND c.estado = 'aprobado') AS comentarios
         FROM publicaciones p WHERE p.usuario_id = ? AND p.estado = 'aprobado' ORDER BY p.fecha DESC LIMIT 50"
    );
    $stmt->execute([$id]);
    $publicaciones = $stmt->fetchAll();

    // Comentarios escritos
    $stmt = $conexion->prepare(
        "SELECT c.texto, c.fecha, p.titulo AS publicacion
         FROM comentarios c JOIN publicaciones p ON p.id = c.publicacion_id
         WHERE c.visitante = ? AND c.estado = 'aprobado' ORDER BY c.fecha DESC LIMIT 50"
    );
    $stmt->execute(['u' . $id]);
    $comentarios = $stmt->fetchAll();

    responderJSON([
        'ok'            => true,
        'persona'       => [
            'id'       => (int)$u['id'],
            'nombre'   => $u['nombre_completo'],
            'correo'   => $u['correo'],
            'rol'      => $u['rol'],
            'grado'    => $u['grado'] !== null ? (int)$u['grado'] : null,
            'puntos'   => (int)$u['puntos'],
            'nivel'    => calcularNivel((int)$u['puntos'])['nombre'],
            'registro' => $u['fecha_registro'],
            'politica' => $u['politica_aceptada'],
            'politica_version' => $u['politica_version'],
        ],
        'retos'         => $retos,
        'tips'          => $tips,
        'publicaciones' => $publicaciones,
        'comentarios'   => $comentarios,
    ]);
}

/* ---------- CAMBIAR PERFIL Y GRADO DE UNA PERSONA ---------- */
if ($accion === 'actualizar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id  = (int)($_POST['id'] ?? 0);
    $rol = $_POST['rol'] ?? '';
    $grado = (int)($_POST['grado'] ?? 0);

    if (!in_array($rol, ['estudiante', 'externo'], true)) {
        responderJSON(['ok' => false, 'error' => 'Perfil no válido.'], 400);
    }
    if ($rol === 'estudiante' && ($grado < 6 || $grado > 11)) {
        responderJSON(['ok' => false, 'error' => 'Elige un grado entre sexto y undécimo.'], 400);
    }
    $gradoFinal = $rol === 'estudiante' ? $grado : null;

    $stmt = $conexion->prepare("UPDATE usuarios SET rol = ?, grado = ? WHERE id = ? AND rol <> 'admin'");
    $stmt->execute([$rol, $gradoFinal, $id]);

    responderJSON(['ok' => true]);
}

/* ---------- MODERACIÓN: CONTADORES ---------- */
if ($accion === 'moderacion_resumen') {
    responderJSON(['ok' => true, 'resumen' => resumenModeracion($conexion)]);
}

/* ---------- MODERACIÓN: LISTA DE CONTENIDO (por estado y tipo) ---------- */
if ($accion === 'moderacion_listar') {
    $estado = $_GET['estado'] ?? 'pendiente';
    $tipo   = $_GET['tipo'] ?? 'todos';
    if (!in_array($estado, MOD_ESTADOS, true))            $estado = 'pendiente';
    if ($tipo !== 'todos' && !in_array($tipo, MOD_TIPOS, true)) $tipo = 'todos';

    responderJSON([
        'ok'      => true,
        'estado'  => $estado,
        'tipo'    => $tipo,
        'items'   => listarModeracion($conexion, $estado, $tipo),
        'resumen' => resumenModeracion($conexion),
    ]);
}

/* ---------- MODERACIÓN: APROBAR O RECHAZAR ---------- */
if ($accion === 'moderar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo      = $_POST['tipo'] ?? '';
    $id        = (int)($_POST['id'] ?? 0);
    $decision  = $_POST['decision'] ?? '';
    $nuevo     = ['aprobar' => 'aprobado', 'rechazar' => 'rechazado'][$decision] ?? null;

    if (!in_array($tipo, MOD_TIPOS, true) || !$nuevo || $id <= 0) {
        responderJSON(['ok' => false, 'error' => 'Datos no válidos.'], 400);
    }
    if (!moderarContenido($conexion, $tipo, $id, $nuevo)) {
        responderJSON(['ok' => false, 'error' => 'Ese contenido ya no existe.'], 404);
    }
    responderJSON(['ok' => true, 'estado' => $nuevo, 'resumen' => resumenModeracion($conexion)]);
}

/* ---------- MODERACIÓN: ELIMINAR DEFINITIVAMENTE ---------- */
if ($accion === 'eliminar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = $_POST['tipo'] ?? '';
    $id   = (int)($_POST['id'] ?? 0);

    if (!in_array($tipo, MOD_TIPOS, true) || $id <= 0) {
        responderJSON(['ok' => false, 'error' => 'Datos no válidos.'], 400);
    }
    if (!eliminarContenido($conexion, $tipo, $id)) {
        responderJSON(['ok' => false, 'error' => 'Ese contenido ya no existe.'], 404);
    }
    responderJSON(['ok' => true, 'resumen' => resumenModeracion($conexion)]);
}

responderJSON(['ok' => false, 'error' => 'Acción no válida.'], 400);

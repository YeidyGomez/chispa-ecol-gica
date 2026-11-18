<?php
/* =========================================================
   CHISPA ECOLÓGICA - Moderación de contenido (solo administrador)
   ========================================================= */

const MOD_TIPOS   = ['tips', 'retos', 'publicaciones', 'comentarios'];
const MOD_ESTADOS = ['pendiente', 'aprobado', 'rechazado'];

/* Tabla de cada tipo */
function modTabla(string $tipo): string {
    return ['tips' => 'tips', 'retos' => 'evidencias', 'publicaciones' => 'publicaciones', 'comentarios' => 'comentarios'][$tipo];
}

/* ---------- Cuántos hay en cada estado (para los contadores del panel) ---------- */
function resumenModeracion(PDO $db): array {
    $r = ['pendiente' => array_fill_keys(MOD_TIPOS, 0), 'aprobado' => array_fill_keys(MOD_TIPOS, 0), 'rechazado' => array_fill_keys(MOD_TIPOS, 0)];
    foreach (MOD_TIPOS as $tipo) {
        $extra = $tipo === 'tips' ? " WHERE origen = 'comunidad'" : '';
        $filas = $db->query("SELECT estado, COUNT(*) AS n FROM " . modTabla($tipo) . $extra . " GROUP BY estado")->fetchAll();
        foreach ($filas as $f) $r[$f['estado']][$tipo] = (int)$f['n'];
    }
    foreach (MOD_ESTADOS as $e) $r[$e]['todos'] = array_sum($r[$e]);
    return $r;
}

/* ---------- Lista de contenido a revisar ---------- */
function listarModeracion(PDO $db, string $estado, string $tipo, int $limite = 60): array {
    $items = [];
    $consultas = [];

    if ($tipo === 'todos' || $tipo === 'tips') {
        $consultas['tips'] = "SELECT t.id, t.estado, t.fecha, t.titulo, t.explicacion AS texto, t.categoria AS detalle,
                                     NULL AS imagen, NULL AS contexto, t.autor AS autor_publico,
                                     u.nombre_completo AS usuario, u.correo, u.rol, u.grado
                              FROM tips t LEFT JOIN usuarios u ON u.id = t.usuario_id
                              WHERE t.origen = 'comunidad' AND t.estado = ? ORDER BY t.fecha DESC, t.id DESC LIMIT $limite";
    }
    if ($tipo === 'todos' || $tipo === 'retos') {
        $consultas['retos'] = "SELECT e.id, e.estado, e.fecha, r.nombre AS titulo, e.descripcion AS texto, CONCAT(e.puntos, ' EcoPuntos') AS detalle,
                                      e.imagen, NULL AS contexto, u.nombre_completo AS autor_publico,
                                      u.nombre_completo AS usuario, u.correo, u.rol, u.grado
                               FROM evidencias e JOIN retos r ON r.id = e.reto_id LEFT JOIN usuarios u ON u.id = e.usuario_id
                               WHERE e.estado = ? ORDER BY e.fecha DESC, e.id DESC LIMIT $limite";
    }
    if ($tipo === 'todos' || $tipo === 'publicaciones') {
        $consultas['publicaciones'] = "SELECT p.id, p.estado, p.fecha, p.titulo, p.descripcion AS texto, NULL AS detalle,
                                              p.imagen, NULL AS contexto, p.autor AS autor_publico,
                                              u.nombre_completo AS usuario, u.correo, u.rol, u.grado
                                       FROM publicaciones p LEFT JOIN usuarios u ON u.id = p.usuario_id
                                       WHERE p.estado = ? ORDER BY p.fecha DESC, p.id DESC LIMIT $limite";
    }
    if ($tipo === 'todos' || $tipo === 'comentarios') {
        $consultas['comentarios'] = "SELECT c.id, c.estado, c.fecha, NULL AS titulo, c.texto, IF(c.parent_id IS NULL, 'Comentario', 'Respuesta') AS detalle,
                                            NULL AS imagen, pu.titulo AS contexto, c.autor AS autor_publico,
                                            u.nombre_completo AS usuario, u.correo, u.rol, u.grado
                                     FROM comentarios c
                                     JOIN publicaciones pu ON pu.id = c.publicacion_id
                                     LEFT JOIN usuarios u ON c.visitante = CONCAT('u', u.id)
                                     WHERE c.estado = ? ORDER BY c.fecha DESC, c.id DESC LIMIT $limite";
    }

    foreach ($consultas as $t => $sql) {
        $stmt = $db->prepare($sql);
        $stmt->execute([$estado]);
        foreach ($stmt->fetchAll() as $f) {
            $items[] = [
                'tipo'     => $t,
                'id'       => (int)$f['id'],
                'estado'   => $f['estado'],
                'fecha'    => $f['fecha'],
                'titulo'   => $f['titulo'],
                'texto'    => $f['texto'],
                'detalle'  => $f['detalle'],
                'imagen'   => $f['imagen'],
                'contexto' => $f['contexto'],
                'autor'    => $f['autor_publico'] ?: ($f['usuario'] ?: 'Anónimo'),
                'usuario'  => $f['usuario'],          // cuenta real (puede diferir del nombre público)
                'correo'   => $f['correo'],
                'perfil'   => $f['rol'],
                'grado'    => $f['grado'] !== null ? (int)$f['grado'] : null,
            ];
        }
    }

    usort($items, fn($a, $b) => strcmp($b['fecha'], $a['fecha']) ?: ($b['id'] <=> $a['id']));
    return array_slice($items, 0, $limite);
}

/* ---------- Aprobar / rechazar ----------
   Los EcoPuntos de un reto se suman al aprobarlo y se restan si después se rechaza. */
function moderarContenido(PDO $db, string $tipo, int $id, string $nuevoEstado): bool {
    $tabla = modTabla($tipo);
    $db->beginTransaction();
    try {
        $extra = $tipo === 'retos' ? ', usuario_id, puntos' : '';
        $solo = $tipo === 'tips' ? " AND origen = 'comunidad'" : '';   // los tips base del sitio no se moderan
        $stmt = $db->prepare("SELECT estado$extra FROM $tabla WHERE id = ?$solo FOR UPDATE");
        $stmt->execute([$id]);
        $fila = $stmt->fetch();
        if (!$fila) { $db->rollBack(); return false; }

        if ($fila['estado'] !== $nuevoEstado) {
            $db->prepare("UPDATE $tabla SET estado = ? WHERE id = ?")->execute([$nuevoEstado, $id]);

            if ($tipo === 'retos' && $fila['usuario_id']) {
                if ($nuevoEstado === 'aprobado') {
                    $db->prepare("UPDATE usuarios SET puntos = puntos + ? WHERE id = ?")->execute([$fila['puntos'], $fila['usuario_id']]);
                } elseif ($fila['estado'] === 'aprobado') {
                    $db->prepare("UPDATE usuarios SET puntos = GREATEST(0, puntos - ?) WHERE id = ?")->execute([$fila['puntos'], $fila['usuario_id']]);
                }
            }
        }
        $db->commit();
        return true;
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

/* ---------- Eliminar definitivamente (también borra la imagen del servidor) ---------- */
function eliminarContenido(PDO $db, string $tipo, int $id): bool {
    $tabla = modTabla($tipo);
    $imagen = null;

    $db->beginTransaction();
    try {
        $cols = ['tips' => 'estado', 'retos' => 'estado, imagen, usuario_id, puntos', 'publicaciones' => 'estado, imagen', 'comentarios' => 'estado'][$tipo];
        $extra = $tipo === 'tips' ? " AND origen = 'comunidad'" : '';   // los tips base del sitio no se borran desde aquí
        $stmt = $db->prepare("SELECT $cols FROM $tabla WHERE id = ?$extra FOR UPDATE");
        $stmt->execute([$id]);
        $fila = $stmt->fetch();
        if (!$fila) { $db->rollBack(); return false; }

        if ($tipo === 'retos') {
            // Si ya había dado EcoPuntos, se devuelven
            if ($fila['estado'] === 'aprobado' && $fila['usuario_id']) {
                $db->prepare("UPDATE usuarios SET puntos = GREATEST(0, puntos - ?) WHERE id = ?")->execute([$fila['puntos'], $fila['usuario_id']]);
            }
            $imagen = $fila['imagen'];
        } elseif ($tipo === 'publicaciones') {
            $imagen = $fila['imagen'];
        } elseif ($tipo === 'comentarios') {
            $db->prepare("DELETE FROM comentarios WHERE parent_id = ?")->execute([$id]);   // sus respuestas
        }

        $db->prepare("DELETE FROM $tabla WHERE id = ?")->execute([$id]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }

    // La imagen se borra solo si la ruta es una carpeta de uploads (evita borrar archivos ajenos)
    if ($imagen && preg_match('#^uploads/(retos|comunidad)/[A-Za-z0-9]+\.(jpg|png|gif|webp)$#', $imagen)) {
        @unlink(RAIZ_DIR . '/' . $imagen);
    }
    return true;
}

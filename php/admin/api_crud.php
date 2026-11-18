<?php
/* =========================================================
   CHISPA ECOLÓGICA - API del CRUD de la base de datos (solo administrador)

   GET  accion=tablas                                   -> esquema de todas las tablas + cantidad de registros
   GET  accion=listar&tabla=&q=&pagina=&orden=&dir=&estado=   -> registros paginados
   GET  accion=opciones&tabla=&q=                       -> opciones para los campos que apuntan a otra tabla
   POST accion=crear       tabla, datos[campo], imagen  -> crea un registro
   POST accion=actualizar  tabla, pk[campo], datos[campo], imagen
   POST accion=eliminar    tabla, pk[campo]

   Seguridad: solo administradores (se verifica en la base de datos), tablas y
   columnas permitidas solo las del esquema (esquema_crud.php), consultas
   preparadas y cabecera X-Requested-With en los POST (evita solicitudes falsas
   desde otros sitios).
   ========================================================= */
require __DIR__ . '/../config/conexion.php';
require __DIR__ . '/moderacion.php';
require __DIR__ . '/esquema_crud.php';

const CRUD_POR_PAGINA = 15;

$admin  = requerirAdmin($conexion);
$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
$esPost = $_SERVER['REQUEST_METHOD'] === 'POST';

if ($esPost && ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
    responderJSON(['ok' => false, 'error' => 'Solicitud no válida.'], 400);
}

function fallo(string $mensaje, int $codigo = 400): void {
    responderJSON(['ok' => false, 'error' => $mensaje], $codigo);
}

/* ---------- Esquema ---------- */
function configTabla(string $tabla): array {
    $esquema = esquemaCrud();
    if (!isset($esquema[$tabla])) fallo('Tabla no válida.');
    return $esquema[$tabla];
}

/* Campos que se pueden leer (la contraseña nunca sale del servidor) */
function camposLeibles(array $cfg): array {
    return array_keys(array_filter($cfg['campos'], fn($c) => $c['tipo'] !== 'clave'));
}

/* ---------- Llave primaria recibida en la solicitud ---------- */
function llaveDesdePost(array $cfg): array {
    $pk = $_POST['pk'] ?? [];
    if (!is_array($pk)) fallo('Registro no válido.');
    $llave = [];
    foreach ($cfg['pk'] as $col) {
        $v = $pk[$col] ?? '';
        if (!is_string($v) && !is_int($v)) fallo('Registro no válido.');
        $v = trim((string)$v);
        if ($v === '') fallo('Registro no válido.');
        if ($cfg['campos'][$col]['tipo'] === 'texto') {
            $llave[$col] = $v;
        } else {
            if (!ctype_digit($v)) fallo('Registro no válido.');
            $llave[$col] = (int)$v;
        }
    }
    return $llave;
}

function dondeDeLlave(array $llave): array {
    $partes = [];
    foreach ($llave as $col => $_) $partes[] = "$col = ?";
    return [implode(' AND ', $partes), array_values($llave)];
}

/* ---------- Borrado seguro de imágenes subidas ---------- */
function borrarImagenSubida(?string $ruta): void {
    if ($ruta && preg_match('#^uploads/(retos|comunidad)/[A-Za-z0-9]+\.(jpg|png|gif|webp)$#', $ruta)) {
        @unlink(RAIZ_DIR . '/' . $ruta);
    }
}

/* ---------- Mensajes legibles para los errores de la base de datos ---------- */
function mensajeBD(PDOException $e): string {
    $m = $e->getMessage();
    if (stripos($m, 'duplicate') !== false || stripos($m, 'unique') !== false) {
        return 'Ya existe un registro con ese valor (por ejemplo, un correo, una clave o un "me gusta" repetido).';
    }
    if ($e->getCode() === '23000') {
        return 'No se pudo completar: el registro está relacionado con otros datos.';
    }
    return 'Error de la base de datos. Revisa que MySQL esté encendido.';
}

/* ---------- Validación de un campo ---------- */
const CRUD_OMITIR = '__omitir__';

function validarCampo(PDO $db, string $nombre, array $def, $crudo, string $modo) {
    $etq = $def['etq'];
    if (is_array($crudo)) fallo("$etq no es válido.");
    $crudo = is_string($crudo) ? trim($crudo) : $crudo;
    $vacio = ($crudo === null || $crudo === '');

    if ($vacio) {
        if ($def['tipo'] === 'clave') {
            if ($modo === 'crear') fallo("$etq es obligatoria (mínimo {$def['min']} caracteres).");
            return CRUD_OMITIR;                         // al editar, vacía = no cambiar
        }
        if (!empty($def['req'])) fallo("$etq es obligatorio.");
        if (!empty($def['nulo'])) return null;
        if (array_key_exists('defecto', $def)) return $def['defecto'];
        fallo("$etq es obligatorio.");
    }

    switch ($def['tipo']) {
        case 'int':
            if (filter_var($crudo, FILTER_VALIDATE_INT) === false) fallo("$etq debe ser un número entero.");
            $n = (int)$crudo;
            if (isset($def['min']) && $n < $def['min']) fallo("$etq debe ser mínimo {$def['min']}.");
            if (isset($def['max']) && $n > $def['max']) fallo("$etq debe ser máximo {$def['max']}.");
            return $n;

        case 'texto':
        case 'textarea':
            $t = (string)$crudo;
            if (isset($def['max']) && mb_strlen($t) > $def['max']) fallo("$etq admite máximo {$def['max']} caracteres.");
            if (isset($def['patron']) && !preg_match($def['patron'], $t)) fallo("$etq no tiene un formato válido.");
            return $t;

        case 'email':
            $t = strtolower((string)$crudo);
            if (!filter_var($t, FILTER_VALIDATE_EMAIL)) fallo("$etq no es un correo válido.");
            if (isset($def['max']) && mb_strlen($t) > $def['max']) fallo("$etq admite máximo {$def['max']} caracteres.");
            return $t;

        case 'clave':
            if (strlen((string)$crudo) < $def['min']) fallo("$etq debe tener mínimo {$def['min']} caracteres.");
            return password_hash((string)$crudo, PASSWORD_DEFAULT);

        case 'enum':
            if (!in_array($crudo, $def['opciones'], true)) fallo("$etq no es válido.");
            return $crudo;

        case 'fk':
            if (filter_var($crudo, FILTER_VALIDATE_INT) === false) fallo("$etq no es válido.");
            $n = (int)$crudo;
            $tabla = configTabla($def['fk']['tabla']) ? $def['fk']['tabla'] : '';   // solo tablas del esquema
            $existe = $db->prepare("SELECT 1 FROM $tabla WHERE id = ?");
            $existe->execute([$n]);
            if (!$existe->fetch()) fallo("$etq: el registro elegido no existe.");
            return $n;
    }
    fallo("$etq no es válido.");
}

/* Recorre los campos editables del esquema y devuelve [columna => valor] ya validado */
function prepararDatos(PDO $db, array $cfg, string $modo): array {
    $enviados = $_POST['datos'] ?? [];
    if (!is_array($enviados)) $enviados = [];
    $datos = [];
    foreach ($cfg['campos'] as $nombre => $def) {
        if (!empty($def['solo_lectura']) || $def['tipo'] === 'imagen') continue;
        if ($modo === 'editar' && !empty($def['solo_crear'])) continue;
        // Al editar, un campo que no se envió se deja como está
        if ($modo === 'editar' && !array_key_exists($nombre, $enviados)) continue;
        $v = validarCampo($db, $nombre, $def, $enviados[$nombre] ?? null, $modo);
        if ($v !== CRUD_OMITIR) $datos[$nombre] = $v;
    }
    return $datos;
}

/* Mensaje de error de subida de imagen */
const CRUD_MSG_IMAGEN = 'Sube una imagen válida (JPG, PNG, GIF o WEBP, máximo 5 MB).';

/* =========================================================
   TABLAS: esquema + cantidad de registros
   ========================================================= */
if ($accion === 'tablas') {
    $salida = [];
    foreach (esquemaCrud() as $nombre => $cfg) {
        $campos = [];
        foreach ($cfg['campos'] as $c => $def) {
            if ($def['tipo'] === 'clave') $def['lista'] = false;
            $campos[] = ['nombre' => $c] + $def;
        }
        $n = (int)$conexion->query("SELECT COUNT(*) FROM $nombre")->fetchColumn();
        $salida[] = [
            'nombre' => $nombre, 'titulo' => $cfg['titulo'], 'singular' => $cfg['singular'], 'icono' => $cfg['icono'],
            'pk' => $cfg['pk'], 'crear' => $cfg['crear'], 'editar' => $cfg['editar'], 'eliminar' => $cfg['eliminar'],
            'nota' => $cfg['nota'] ?? null, 'aviso_eliminar' => $cfg['aviso_eliminar'] ?? null,
            'conteo' => $n, 'campos' => $campos,
        ];
    }
    responderJSON(['ok' => true, 'tablas' => $salida, 'por_pagina' => CRUD_POR_PAGINA, 'yo' => (int)$admin['id']]);
}

/* =========================================================
   LISTAR (con búsqueda, filtro de estado, orden y paginación)
   ========================================================= */
if ($accion === 'listar') {
    $tabla = $_GET['tabla'] ?? '';
    $cfg   = configTabla($tabla);

    // Columnas a leer + etiqueta legible de cada campo que apunta a otra tabla
    $columnas = [];
    foreach (camposLeibles($cfg) as $c) {
        $columnas[] = "t.$c";
        $def = $cfg['campos'][$c];
        if ($def['tipo'] === 'fk') {
            $destino = configTabla($def['fk']['tabla']);
            $columnas[] = "(SELECT {$destino['etiqueta']} FROM {$def['fk']['tabla']} WHERE id = t.$c) AS _$c";
        }
    }

    $donde = [];
    $params = [];
    $q = trim((string)($_GET['q'] ?? ''));
    if ($q !== '') {
        $like = '%' . addcslashes(mb_substr($q, 0, 100), '%_\\') . '%';
        $partes = [];
        foreach ($cfg['buscar'] as $col) { $partes[] = "t.$col LIKE ?"; $params[] = $like; }
        if (ctype_digit($q) && isset($cfg['campos']['id'])) { $partes[] = 't.id = ?'; $params[] = (int)$q; }
        $donde[] = '(' . implode(' OR ', $partes) . ')';
    }
    $estado = $_GET['estado'] ?? '';
    if (isset($cfg['campos']['estado']) && in_array($estado, CRUD_ESTADOS, true)) {
        $donde[] = 't.estado = ?';
        $params[] = $estado;
    }
    $sqlDonde = $donde ? ' WHERE ' . implode(' AND ', $donde) : '';

    // Orden (solo columnas del esquema)
    $campoOrden = $_GET['orden'] ?? '';
    if (isset($cfg['campos'][$campoOrden]) && $cfg['campos'][$campoOrden]['tipo'] !== 'clave') {
        $orden = "t.$campoOrden " . (($_GET['dir'] ?? '') === 'asc' ? 'ASC' : 'DESC');
    } else {
        $orden = "t.{$cfg['orden'][0]} {$cfg['orden'][1]}";
    }

    $total = $conexion->prepare("SELECT COUNT(*) FROM $tabla t$sqlDonde");
    $total->execute($params);
    $total = (int)$total->fetchColumn();

    $paginas = max(1, (int)ceil($total / CRUD_POR_PAGINA));
    $pagina  = min(max(1, (int)($_GET['pagina'] ?? 1)), $paginas);
    $desde   = ($pagina - 1) * CRUD_POR_PAGINA;

    $sql = "SELECT " . implode(', ', $columnas) . " FROM $tabla t$sqlDonde ORDER BY $orden LIMIT " . CRUD_POR_PAGINA . " OFFSET $desde";
    $stmt = $conexion->prepare($sql);
    $stmt->execute($params);

    responderJSON(['ok' => true, 'filas' => $stmt->fetchAll(), 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas]);
}

/* =========================================================
   OPCIONES para los campos que apuntan a otra tabla
   ========================================================= */
if ($accion === 'opciones') {
    $tabla = $_GET['tabla'] ?? '';
    $cfg = configTabla($tabla);
    if (!isset($cfg['etiqueta'])) fallo('Tabla no válida.');

    $params = [];
    $donde = '';
    $q = trim((string)($_GET['q'] ?? ''));
    if ($q !== '') {
        $like = '%' . addcslashes(mb_substr($q, 0, 100), '%_\\') . '%';
        $partes = [];
        foreach ($cfg['buscar'] as $col) { $partes[] = "$col LIKE ?"; $params[] = $like; }
        if (ctype_digit($q)) { $partes[] = 'id = ?'; $params[] = (int)$q; }
        $donde = ' WHERE ' . implode(' OR ', $partes);
    }
    $stmt = $conexion->prepare("SELECT id, {$cfg['etiqueta']} AS etiqueta FROM $tabla$donde ORDER BY id DESC LIMIT 200");
    $stmt->execute($params);
    responderJSON(['ok' => true, 'opciones' => $stmt->fetchAll()]);
}

/* =========================================================
   Desde aquí solo POST
   ========================================================= */
if (!$esPost) fallo('Acción no válida.');

$tabla = $_POST['tabla'] ?? '';
$cfg   = configTabla($tabla);

/* ---------- CREAR ---------- */
if ($accion === 'crear') {
    if (empty($cfg['crear'])) fallo('Esta tabla no permite crear registros desde aquí.', 403);

    $datos = prepararDatos($conexion, $cfg, 'crear');

    // Reglas propias de cada tabla
    if ($tabla === 'usuarios') {
        if ($datos['rol'] === 'estudiante') {
            if (empty($datos['grado'])) fallo('Los estudiantes necesitan un grado (de 6 a 11).');
        } else {
            $datos['grado'] = null;
        }
    }
    if ($tabla === 'comentarios') {
        if (!empty($datos['parent_id'])) {
            $padre = $conexion->prepare("SELECT publicacion_id, parent_id FROM comentarios WHERE id = ?");
            $padre->execute([$datos['parent_id']]);
            $p = $padre->fetch();
            if (!$p || (int)$p['publicacion_id'] !== $datos['publicacion_id'] || $p['parent_id'] !== null) {
                fallo('Solo se puede responder a un comentario principal de la misma publicación.');
            }
        }
        $datos['visitante'] = visitanteId();
    }
    $imagenNueva = null;
    if ($tabla === 'publicaciones') {
        $imagenNueva = guardarImagen('imagen', 'comunidad');
        if (!$imagenNueva) fallo(CRUD_MSG_IMAGEN);
        $datos['imagen'] = $imagenNueva;
        $datos['visitante'] = visitanteId();
    }

    $cols = array_keys($datos);
    try {
        $stmt = $conexion->prepare("INSERT INTO $tabla (" . implode(', ', $cols) . ") VALUES (" . implode(', ', array_fill(0, count($cols), '?')) . ")");
        $stmt->execute(array_values($datos));
    } catch (PDOException $e) {
        borrarImagenSubida($imagenNueva);
        fallo(mensajeBD($e), 409);
    }
    responderJSON(['ok' => true]);
}

/* ---------- ACTUALIZAR ---------- */
if ($accion === 'actualizar') {
    if (empty($cfg['editar'])) fallo('Esta tabla no permite editar registros.', 403);

    $llave = llaveDesdePost($cfg);
    [$sqlDonde, $paramsDonde] = dondeDeLlave($llave);

    $actual = $conexion->prepare("SELECT * FROM $tabla WHERE $sqlDonde");
    $actual->execute($paramsDonde);
    $fila = $actual->fetch();
    if (!$fila) fallo('Ese registro ya no existe.', 404);

    $datos = prepararDatos($conexion, $cfg, 'editar');

    if ($tabla === 'usuarios') {
        $rol = $datos['rol'] ?? $fila['rol'];
        if ((int)$fila['id'] === (int)$admin['id'] && $rol !== 'admin') {
            fallo('No puedes quitarte a ti mismo el perfil de administrador.');
        }
        if ($rol === 'estudiante') {
            $grado = array_key_exists('grado', $datos) ? $datos['grado'] : $fila['grado'];
            if (empty($grado)) fallo('Los estudiantes necesitan un grado (de 6 a 11).');
        } else {
            $datos['grado'] = null;
        }
    }

    // La evidencia cambia de estado por la moderación: así se suman o restan los EcoPuntos
    $estadoModerado = null;
    if (($cfg['moderar'] ?? '') === 'retos' && isset($datos['estado'])) {
        $estadoModerado = $datos['estado'];
        unset($datos['estado']);
    }

    $imagenAnterior = null;
    $imagenNueva = null;
    if ($tabla === 'publicaciones' && !empty($_FILES['imagen']) && $_FILES['imagen']['error'] !== UPLOAD_ERR_NO_FILE) {
        $imagenNueva = guardarImagen('imagen', 'comunidad');
        if (!$imagenNueva) fallo(CRUD_MSG_IMAGEN);
        $datos['imagen'] = $imagenNueva;
        $imagenAnterior = $fila['imagen'];
    }

    try {
        if ($datos) {
            $asignaciones = implode(', ', array_map(fn($c) => "$c = ?", array_keys($datos)));
            $stmt = $conexion->prepare("UPDATE $tabla SET $asignaciones WHERE $sqlDonde");
            $stmt->execute(array_merge(array_values($datos), $paramsDonde));
        }
        if ($estadoModerado !== null) {
            moderarContenido($conexion, 'retos', (int)$llave['id'], $estadoModerado);
        }
    } catch (PDOException $e) {
        borrarImagenSubida($imagenNueva);
        fallo(mensajeBD($e), 409);
    }
    borrarImagenSubida($imagenAnterior);
    responderJSON(['ok' => true]);
}

/* ---------- ELIMINAR ---------- */
if ($accion === 'eliminar') {
    if (empty($cfg['eliminar'])) fallo('Esta tabla no permite eliminar registros.', 403);

    $llave = llaveDesdePost($cfg);

    try {
        // Evidencias, publicaciones y comentarios: mismo borrado que la moderación (devuelve EcoPuntos, borra imagen y respuestas)
        if (isset($cfg['moderar'])) {
            $tipoMod = ['retos' => 'retos', 'publicaciones' => 'publicaciones', 'comentarios' => 'comentarios'][$cfg['moderar']];
            if (!eliminarContenido($conexion, $tipoMod, (int)$llave['id'])) fallo('Ese registro ya no existe.', 404);
            responderJSON(['ok' => true]);
        }

        $imagenes = [];
        if ($tabla === 'usuarios') {
            if ((int)$llave['id'] === (int)$admin['id']) fallo('No puedes eliminar tu propia cuenta.');
            $s = $conexion->prepare("SELECT imagen FROM evidencias WHERE usuario_id = ?");
            $s->execute([$llave['id']]);
            $imagenes = $s->fetchAll(PDO::FETCH_COLUMN);
        } elseif ($tabla === 'retos') {
            $s = $conexion->prepare("SELECT imagen FROM evidencias WHERE reto_id = ?");
            $s->execute([$llave['id']]);
            $imagenes = $s->fetchAll(PDO::FETCH_COLUMN);
        }

        [$sqlDonde, $paramsDonde] = dondeDeLlave($llave);
        $stmt = $conexion->prepare("DELETE FROM $tabla WHERE $sqlDonde");
        $stmt->execute($paramsDonde);
        if ($stmt->rowCount() === 0) fallo('Ese registro ya no existe.', 404);

        foreach ($imagenes as $ruta) borrarImagenSubida($ruta);
    } catch (PDOException $e) {
        fallo(mensajeBD($e), 409);
    }
    responderJSON(['ok' => true]);
}

fallo('Acción no válida.');

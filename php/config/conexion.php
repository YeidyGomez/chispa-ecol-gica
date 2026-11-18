<?php
/* =========================================================
   CHISPA ECOLÓGICA - Conexión a la base de datos + funciones comunes
   Todos los PHP del proyecto incluyen este archivo.
   ========================================================= */

/* Carpeta raíz del proyecto en disco (chispa-ecologica/) */
define('RAIZ_DIR', dirname(__DIR__, 2));

$host     = "localhost";
$dbname   = "chispa_ecologica_db";
$username = "root";
$password = "";          // En XAMPP el usuario root no tiene contraseña por defecto

try {
    $conexion = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conexion->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    die("Error de conexión a la base de datos: " . $e->getMessage()
        . "<br>Verifica que MySQL esté encendido en XAMPP y que importaste database.sql");
}

/* ---------- Sesión ---------- */
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

// Usuario de la sesión
function usuarioActual(): ?array {
    if (empty($_SESSION['usuario_id'])) return null;
    return ['id' => (int)$_SESSION['usuario_id'], 'nombre' => $_SESSION['nombre'] ?? '', 'rol' => $_SESSION['rol'] ?? 'externo'];
}

/* Solo para el panel: devuelve al administrador o corta con error 403 (se consulta la base de datos, no solo la sesión) */
function requerirAdmin(PDO $db, bool $json = true): array {
    $u = usuarioActual();
    if ($u) {
        $stmt = $db->prepare("SELECT id, nombre_completo, rol FROM usuarios WHERE id = ?");
        $stmt->execute([$u['id']]);
        $fila = $stmt->fetch();
        if ($fila && $fila['rol'] === 'admin') return $fila;
    }
    if ($json) {
        responderJSON(['ok' => false, 'error' => 'Acceso solo para administradores.'], 403);
    }
    http_response_code(403);
    exit('Acceso solo para administradores.');
}

/* ¿La sesión actual es de un administrador? (consulta la base de datos; no corta la ejecución) */
function esAdmin(PDO $db): bool {
    static $cache = null;
    if ($cache !== null) return $cache;
    $u = usuarioActual();
    if (!$u) return $cache = false;
    $stmt = $db->prepare("SELECT rol FROM usuarios WHERE id = ?");
    $stmt->execute([$u['id']]);
    return $cache = ($stmt->fetchColumn() === 'admin');
}

/* Identificador para likes: el id del usuario o, si no ha iniciado sesión, la sesión del navegador */
function visitanteId(): string {
    $u = usuarioActual();
    return $u ? 'u' . $u['id'] : 's' . session_id();
}

/* ---------- Filtro automático de contenido inapropiado ---------- */
require_once __DIR__ . '/filtro.php';

/* ---------- Respuestas ---------- */
function responderJSON($datos, int $codigo = 200): void {
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

/* Ruta web de la raíz del sitio (ej. /chispa-ecologica). Los PHP viven en php/<grupo>/archivo.php */
function rutaBase(): string {
    return rtrim(dirname($_SERVER['SCRIPT_NAME'], 3), '/\\');
}

/* Redirige. $url es relativa a la raíz del proyecto, ej. 'paginas/retos/RetosEcologicos.html?msg=ok' */
function redirigir(string $url): void {
    if (!preg_match('#^(https?:)?//#', $url) && $url[0] !== '/') {
        $url = rutaBase() . '/' . $url;
    }
    header("Location: $url");
    exit;
}

/* ---------- Niveles según EcoPuntos ---------- */
function calcularNivel(int $puntos): array {
    $niveles = [
        [0,    'Principiante Verde'],
        [500,  'Explorador Verde'],
        [1500, 'Guardián Eco'],
        [3000, 'Héroe del Planeta'],
    ];
    $actual = $niveles[0];
    $siguiente = null;
    foreach ($niveles as $i => $n) {
        if ($puntos >= $n[0]) {
            $actual = $n;
            $siguiente = $niveles[$i + 1] ?? null;
        }
    }
    $progreso = $siguiente
        ? (int)round(($puntos - $actual[0]) * 100 / ($siguiente[0] - $actual[0]))
        : 100;
    return ['nombre' => $actual[1], 'progreso' => $progreso, 'siguiente' => $siguiente[1] ?? null];
}

/* ---------- Subida segura de imágenes ---------- */
function guardarImagen(string $campo, string $carpeta): ?string {
    if (empty($_FILES[$campo]) || $_FILES[$campo]['error'] !== UPLOAD_ERR_OK) return null;

    $archivo = $_FILES[$campo];
    if ($archivo['size'] > 5 * 1024 * 1024) return null;           // máx. 5 MB

    $permitidos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    $info = @getimagesize($archivo['tmp_name']);                    // verifica que sea una imagen real
    if (!$info || !isset($permitidos[$info['mime']])) return null;

    $dir = RAIZ_DIR . "/uploads/$carpeta";
    if (!is_dir($dir)) mkdir($dir, 0775, true);

    $nombre = bin2hex(random_bytes(8)) . '.' . $permitidos[$info['mime']];
    if (!move_uploaded_file($archivo['tmp_name'], "$dir/$nombre")) return null;

    return "uploads/$carpeta/$nombre";
}

<?php
/* Palabras completas que se bloquean */
function palabrasProhibidas(): array {
    return [
        // Groserías e insultos
        'puta', 'putas', 'puto', 'putos', 'putita', 'putito', 'putada', 'putear',
        'mierda', 'mierdas', 'gonorrea', 'gonorreas', 'malparido', 'malparida', 'malparidos',
        'hijueputa', 'hijoeputa', 'hijoputa', 'hijueputas', 'hdp', 'hptas',
        'pendejo', 'pendeja', 'pendejos', 'pendejas', 'pendejada',
        'cabron', 'cabrona', 'cabrones', 'marica', 'maricas', 'maricon', 'maricones',
        'verga', 'vergas', 'chingar', 'chingada', 'chingado', 'chinga', 'joder', 'jodete', 'jodido',
        'carajo', 'culiado', 'culiao', 'culia', 'gilipollas', 'capullo', 'boludo', 'boluda', 'pelotudo', 'pelotuda',
        'imbecil', 'imbeciles', 'idiota', 'idiotas', 'estupido', 'estupida', 'estupidos', 'estupidas',
        'subnormal', 'zorra', 'zorras', 'guevon', 'huevon', 'bastardo', 'bastarda',
        'cagada', 'coito', 'prostituta', 'prostituto', 'violador',
        // Discriminación
        'sudaca', 'nigga', 'nigger', 'negrata', 'nazi', 'nazis',
        // Contenido sexual
        'porno', 'pornografia', 'tetas',
        // Amenazas y autolesión
        'suicidate', 'matate', 'matarte', 'matarlos', 'matarla',
        // Inglés
        'fuck', 'fucking', 'fucker', 'shit', 'bitch', 'asshole', 'cunt', 'slut', 'whore', 'bastard', 'nigga',
    ];
}

/* Raíces: bloquean cualquier palabra que EMPIECE así */
function raicesProhibidas(): array {
    return ['mierd', 'hijueput', 'hijoput', 'hijodeput', 'malparid', 'pendej', 'gonorre', 'maricon', 'cabron', 'puteri', 'putead'];
}

/* Frases completas (se buscan en el texto ya normalizado) */
function frasesProhibidas(): array {
    return ['te voy a matar', 'voy a matarte', 'ojala te mueras', 'ojala te mueran', 'hijo de puta', 'hijos de puta', 'vete a la mierda', 'andate a la mierda'];
}

/* ---------- Normalización: minúsculas, sin tildes, números "disfrazados" -> letras ---------- */
function normalizarParaFiltro(string $t): string {
    $t = mb_strtolower($t, 'UTF-8');
    $t = strtr($t, [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i', 'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u', 'ñ' => 'n',
        '0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '@' => 'a', '$' => 's',
    ]);
    return $t;
}

/* "puuuuta" -> "puta" (también se aplica a las listas, así que "perra" se compara como "pera" en ambos lados) */
function colapsarLetras(string $t): string {
    return preg_replace('/(.)\1+/u', '$1', $t);
}

/* ¿Esta palabra (ya normalizada) está prohibida? */
function palabraProhibida(string $palabra, array $conjunto, array $raices): bool {
    if ($palabra === '') return false;
    $c = colapsarLetras($palabra);
    if (isset($conjunto[$c])) return true;
    foreach ($raices as $r) {
        if (strncmp($c, $r, strlen($r)) === 0) return true;
    }
    return false;
}

/* ---------- ¿El texto tiene lenguaje ofensivo? ---------- */
function tieneLenguajeOfensivo(string $texto): bool {
    static $conjunto = null, $raices = null;
    if ($conjunto === null) {
        $conjunto = [];
        foreach (palabrasProhibidas() as $p) $conjunto[colapsarLetras($p)] = true;
        $raices = array_map('colapsarLetras', raicesProhibidas());
    }

    $norm   = normalizarParaFiltro($texto);
    $tokens = preg_split('/[^a-z]+/', $norm, -1, PREG_SPLIT_NO_EMPTY) ?: [];

    // 1) Palabra por palabra
    foreach ($tokens as $t) {
        if (palabraProhibida($t, $conjunto, $raices)) return true;
    }

    // 1b) Palabras partidas con guion, punto o asterisco: "mal-parido", "mal_parido"
    $pegado = preg_replace('/(?<=[a-z])[-_.*]+(?=[a-z])/', '', $norm);
    if ($pegado !== $norm) {
        foreach (preg_split('/[^a-z]+/', $pegado, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $t) {
            if (palabraProhibida($t, $conjunto, $raices)) return true;
        }
    }

    // 2) Letras separadas: "p u t a", "m.i.e.r.d.a" (3 o más letras sueltas seguidas)
    $racha = '';
    foreach (array_merge($tokens, ['']) as $t) {
        if (strlen($t) === 1) {
            $racha .= $t;
            continue;
        }
        if (strlen($racha) >= 3 && palabraProhibida($racha, $conjunto, $raices)) return true;
        $racha = '';
    }

    // 3) Frases
    $plano = ' ' . implode(' ', $tokens) . ' ';
    foreach (frasesProhibidas() as $f) {
        if (strpos($plano, ' ' . $f . ' ') !== false) return true;
    }
    return false;
}

/* ---------- ¿Comparte enlaces o datos de contacto? ---------- */
function tieneDatosDeContacto(string $texto): bool {
    if (preg_match('~(https?://|www\.|\b[a-z0-9-]+\.(com|net|org|co|io|me|xyz|info|edu|gov|ly|tk|link|site)\b)~i', $texto)) return true;
    if (preg_match('/[\w.+-]+@[\w-]+\.[\w.-]+/u', $texto)) return true;                 // correo
    if (preg_match('/\b3\d{2}[\s.\-]?\d{3}[\s.\-]?\d{4}\b/', $texto)) return true;     // celular colombiano
    if (preg_match('/\d{10,}/', $texto)) return true;                                   // cualquier número largo
    return false;
}

/* ---------- Revisa varios textos a la vez ----------
   Devuelve null si todo está bien, o el código del problema:
   'contenido_ofensivo' | 'contenido_contacto'  */
function revisarContenido(string ...$textos): ?string {
    foreach ($textos as $t) {
        if (tieneLenguajeOfensivo($t)) return 'contenido_ofensivo';
    }
    foreach ($textos as $t) {
        if (tieneDatosDeContacto($t)) return 'contenido_contacto';
    }
    return null;
}

/* Mensaje para mostrar al usuario según el código */
function mensajeFiltro(string $codigo): string {
    return $codigo === 'contenido_contacto'
        ? 'Por seguridad no se permiten enlaces, correos ni números de teléfono. Quítalos e intenta de nuevo.'
        : 'Tu mensaje contiene lenguaje inapropiado u ofensivo. Por favor corrígelo e intenta de nuevo.';
}

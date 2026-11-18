<?php
/* =========================================================
   CHISPA ECOLÓGICA - Esquema del CRUD (gestión de datos)
   Describe cada tabla de la base de datos: sus campos, qué se puede
   crear / editar / eliminar y qué reglas tiene cada campo.
   api_crud.php lo usa para validar, y la página "Gestión de datos"
   dibuja las tablas y los formularios a partir de él.

   Tipos de campo:  int | texto | textarea | email | clave | enum | fk | fecha | imagen
   Opciones de campo:
     etq          nombre que se muestra
     req          obligatorio
     nulo         puede quedar vacío (NULL)
     max / min    largo máximo (texto) o rango (número)
     opciones     valores permitidos (enum)
     fk           ['tabla' => ...] referencia a otra tabla
     lista        se muestra como columna en la tabla
     solo_lectura se muestra pero no se puede escribir
     solo_crear   se escribe al crear y luego queda fijo
     patron       expresión regular que debe cumplir
     defecto      valor si se deja vacío
     ayuda        texto de ayuda bajo el campo
   ========================================================= */

const CRUD_ESTADOS = ['pendiente', 'aprobado', 'rechazado'];

function esquemaCrud(): array {
    $estado = ['tipo' => 'enum', 'etq' => 'Estado', 'opciones' => CRUD_ESTADOS, 'req' => true, 'lista' => true,
               'ayuda' => 'Solo lo aprobado se ve en el sitio.'];
    $fecha  = ['tipo' => 'fecha', 'etq' => 'Fecha', 'solo_lectura' => true, 'lista' => true];
    $id     = ['tipo' => 'int', 'etq' => 'ID', 'solo_lectura' => true, 'lista' => true];

    return [

        'usuarios' => [
            'titulo' => 'Usuarios', 'singular' => 'usuario', 'icono' => 'fa-users',
            'pk' => ['id'], 'orden' => ['id', 'DESC'],
            'buscar' => ['nombre_completo', 'correo'],
            'etiqueta' => "CONCAT(nombre_completo, ' · ', correo)",
            'crear' => true, 'editar' => true, 'eliminar' => true,
            'aviso_eliminar' => 'Se borran también sus evidencias de retos. Sus tips y publicaciones quedan en el sitio sin autor.',
            'campos' => [
                'id' => $id,
                'nombre_completo' => ['tipo' => 'texto', 'etq' => 'Nombre completo', 'req' => true, 'max' => 100, 'lista' => true],
                'correo' => ['tipo' => 'email', 'etq' => 'Correo', 'req' => true, 'max' => 150, 'lista' => true],
                'contrasena' => ['tipo' => 'clave', 'etq' => 'Contraseña', 'min' => 8,
                                 'ayuda' => 'Mínimo 8 caracteres. Al editar, déjala vacía para no cambiarla.'],
                'rol' => ['tipo' => 'enum', 'etq' => 'Perfil', 'opciones' => ['admin', 'estudiante', 'externo'], 'req' => true, 'lista' => true],
                'grado' => ['tipo' => 'int', 'etq' => 'Grado', 'nulo' => true, 'min' => 6, 'max' => 11, 'lista' => true,
                            'ayuda' => 'De 6 a 11. Solo para estudiantes.'],
                'puntos' => ['tipo' => 'int', 'etq' => 'EcoPuntos', 'min' => 0, 'defecto' => 0, 'lista' => true],
                'politica_aceptada' => ['tipo' => 'fecha', 'etq' => 'Política aceptada', 'solo_lectura' => true],
                'politica_version' => ['tipo' => 'texto', 'etq' => 'Versión de la política', 'solo_lectura' => true],
                'fecha_registro' => ['tipo' => 'fecha', 'etq' => 'Registro', 'solo_lectura' => true, 'lista' => true],
            ],
        ],

        'retos' => [
            'titulo' => 'Retos', 'singular' => 'reto', 'icono' => 'fa-trophy',
            'pk' => ['id'], 'orden' => ['id', 'ASC'],
            'buscar' => ['clave', 'nombre'],
            'etiqueta' => 'nombre',
            'crear' => true, 'editar' => true, 'eliminar' => true,
            'aviso_eliminar' => 'Se borran también todas las evidencias de este reto. Las páginas de los retos usan su clave: no elimines los retos que ya existen en el sitio.',
            'campos' => [
                'id' => $id,
                'clave' => ['tipo' => 'texto', 'etq' => 'Clave', 'req' => true, 'max' => 30, 'solo_crear' => true, 'lista' => true,
                            'patron' => '/^[a-z0-9_]{2,30}$/', 'ayuda' => 'Minúsculas, números y guion bajo. No se puede cambiar después (la usan las páginas del sitio).'],
                'nombre' => ['tipo' => 'texto', 'etq' => 'Nombre', 'req' => true, 'max' => 100, 'lista' => true],
                'puntos' => ['tipo' => 'int', 'etq' => 'EcoPuntos', 'req' => true, 'min' => 0, 'lista' => true],
            ],
        ],

        'evidencias' => [
            'titulo' => 'Evidencias de retos', 'singular' => 'evidencia', 'icono' => 'fa-camera',
            'pk' => ['id'], 'orden' => ['id', 'DESC'],
            'buscar' => ['descripcion'],
            'etiqueta' => "CONCAT('#', id, ' ', LEFT(descripcion, 40))",
            'crear' => false, 'editar' => true, 'eliminar' => true,
            'moderar' => 'retos',   // el cambio de estado pasa por la moderación (suma o resta EcoPuntos)
            'nota' => 'Las evidencias las envían los usuarios desde cada reto (llevan una foto). Aquí puedes corregir el texto, cambiar su estado o eliminarlas. Al aprobar se suman los EcoPuntos; al rechazar o eliminar una aprobada, se restan.',
            'aviso_eliminar' => 'Si la evidencia estaba aprobada, se restan los EcoPuntos al usuario. También se borra la imagen.',
            'campos' => [
                'id' => $id,
                'usuario_id' => ['tipo' => 'fk', 'etq' => 'Usuario', 'fk' => ['tabla' => 'usuarios'], 'solo_lectura' => true, 'lista' => true],
                'reto_id' => ['tipo' => 'fk', 'etq' => 'Reto', 'fk' => ['tabla' => 'retos'], 'solo_lectura' => true, 'lista' => true],
                'descripcion' => ['tipo' => 'textarea', 'etq' => 'Descripción', 'req' => true, 'max' => 2000, 'lista' => true],
                'imagen' => ['tipo' => 'imagen', 'etq' => 'Imagen', 'solo_lectura' => true, 'lista' => true],
                'puntos' => ['tipo' => 'int', 'etq' => 'EcoPuntos', 'solo_lectura' => true, 'lista' => true],
                'estado' => $estado,
                'fecha' => $fecha,
            ],
        ],

        'tips' => [
            'titulo' => 'Tips', 'singular' => 'tip', 'icono' => 'fa-lightbulb',
            'pk' => ['id'], 'orden' => ['id', 'DESC'],
            'buscar' => ['titulo', 'explicacion', 'autor'],
            'etiqueta' => "CONCAT('#', id, ' ', titulo)",
            'crear' => true, 'editar' => true, 'eliminar' => true,
            'aviso_eliminar' => 'Si es un tip base del sitio, dejará de mostrarse.',
            'campos' => [
                'id' => $id,
                'titulo' => ['tipo' => 'texto', 'etq' => 'Título', 'req' => true, 'max' => 150, 'lista' => true],
                'categoria' => ['tipo' => 'enum', 'etq' => 'Categoría', 'opciones' => ['hogar', 'energia', 'movilidad', 'agua'], 'req' => true, 'lista' => true],
                'explicacion' => ['tipo' => 'textarea', 'etq' => 'Explicación', 'req' => true, 'max' => 3000],
                'autor' => ['tipo' => 'texto', 'etq' => 'Autor (nombre público)', 'nulo' => true, 'max' => 100, 'lista' => true],
                'usuario_id' => ['tipo' => 'fk', 'etq' => 'Cuenta del autor', 'fk' => ['tabla' => 'usuarios'], 'nulo' => true, 'ayuda' => 'Opcional.'],
                'origen' => ['tipo' => 'enum', 'etq' => 'Origen', 'opciones' => ['base', 'comunidad'], 'req' => true, 'lista' => true],
                'estado' => $estado,
                'fecha' => $fecha,
            ],
        ],

        'publicaciones' => [
            'titulo' => 'Publicaciones', 'singular' => 'publicación', 'icono' => 'fa-images',
            'pk' => ['id'], 'orden' => ['id', 'DESC'],
            'buscar' => ['titulo', 'descripcion', 'autor'],
            'etiqueta' => "CONCAT('#', id, ' ', titulo)",
            'crear' => true, 'editar' => true, 'eliminar' => true,
            'moderar' => 'publicaciones',
            'aviso_eliminar' => 'Se borran también su imagen, sus comentarios y sus "me gusta".',
            'campos' => [
                'id' => $id,
                'imagen' => ['tipo' => 'imagen', 'etq' => 'Imagen', 'req' => true, 'lista' => true,
                             'ayuda' => 'JPG, PNG, GIF o WEBP, máximo 5 MB. Al editar, súbela solo si quieres cambiarla.'],
                'titulo' => ['tipo' => 'texto', 'etq' => 'Título', 'req' => true, 'max' => 150, 'lista' => true],
                'descripcion' => ['tipo' => 'textarea', 'etq' => 'Descripción', 'req' => true, 'max' => 2000],
                'autor' => ['tipo' => 'texto', 'etq' => 'Autor (nombre público)', 'req' => true, 'max' => 30, 'lista' => true],
                'usuario_id' => ['tipo' => 'fk', 'etq' => 'Cuenta del autor', 'fk' => ['tabla' => 'usuarios'], 'nulo' => true, 'ayuda' => 'Opcional.'],
                'visitante' => ['tipo' => 'texto', 'etq' => 'Identificador del autor', 'solo_lectura' => true],
                'estado' => $estado,
                'fecha' => $fecha,
            ],
        ],

        'comentarios' => [
            'titulo' => 'Comentarios', 'singular' => 'comentario', 'icono' => 'fa-comments',
            'pk' => ['id'], 'orden' => ['id', 'DESC'],
            'buscar' => ['texto', 'autor'],
            'etiqueta' => "CONCAT('#', id, ' ', LEFT(texto, 40))",
            'crear' => true, 'editar' => true, 'eliminar' => true,
            'moderar' => 'comentarios',
            'aviso_eliminar' => 'Se borran también las respuestas a este comentario.',
            'campos' => [
                'id' => $id,
                'publicacion_id' => ['tipo' => 'fk', 'etq' => 'Publicación', 'fk' => ['tabla' => 'publicaciones'], 'req' => true, 'solo_crear' => true, 'lista' => true],
                'parent_id' => ['tipo' => 'fk', 'etq' => 'Responde al comentario', 'fk' => ['tabla' => 'comentarios'], 'nulo' => true, 'solo_crear' => true,
                                'ayuda' => 'Déjalo vacío si es un comentario nuevo. Debe ser un comentario principal de la misma publicación.'],
                'autor' => ['tipo' => 'texto', 'etq' => 'Autor (nombre público)', 'req' => true, 'max' => 30, 'lista' => true],
                'texto' => ['tipo' => 'textarea', 'etq' => 'Comentario', 'req' => true, 'max' => 500, 'lista' => true],
                'visitante' => ['tipo' => 'texto', 'etq' => 'Identificador del autor', 'solo_lectura' => true],
                'estado' => $estado,
                'fecha' => $fecha,
            ],
        ],

        'publicacion_likes' => [
            'titulo' => 'Me gusta (publicaciones)', 'singular' => 'me gusta', 'icono' => 'fa-heart',
            'pk' => ['publicacion_id', 'visitante'], 'orden' => ['publicacion_id', 'DESC'],
            'buscar' => ['visitante'],
            'crear' => true, 'editar' => false, 'eliminar' => true,
            'nota' => 'Cada "me gusta" es único por publicación y visitante. No se editan: se crean o se eliminan.',
            'campos' => [
                'publicacion_id' => ['tipo' => 'fk', 'etq' => 'Publicación', 'fk' => ['tabla' => 'publicaciones'], 'req' => true, 'lista' => true],
                'visitante' => ['tipo' => 'texto', 'etq' => 'Visitante', 'req' => true, 'max' => 64, 'lista' => true,
                                'ayuda' => 'Ej.: u12 (usuario con id 12) o el código de sesión de un visitante.'],
            ],
        ],

        'comentario_likes' => [
            'titulo' => 'Me gusta (comentarios)', 'singular' => 'me gusta', 'icono' => 'fa-thumbs-up',
            'pk' => ['comentario_id', 'visitante'], 'orden' => ['comentario_id', 'DESC'],
            'buscar' => ['visitante'],
            'crear' => true, 'editar' => false, 'eliminar' => true,
            'nota' => 'Cada "me gusta" es único por comentario y visitante. No se editan: se crean o se eliminan.',
            'campos' => [
                'comentario_id' => ['tipo' => 'fk', 'etq' => 'Comentario', 'fk' => ['tabla' => 'comentarios'], 'req' => true, 'lista' => true],
                'visitante' => ['tipo' => 'texto', 'etq' => 'Visitante', 'req' => true, 'max' => 64, 'lista' => true,
                                'ayuda' => 'Ej.: u12 (usuario con id 12) o el código de sesión de un visitante.'],
            ],
        ],
    ];
}

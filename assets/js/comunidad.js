/* =========================================================
   CHISPA ECOLÓGICA - ComunidadEco.html
   Publicaciones, "me gusta", comentarios, respuestas y eliminar,
   todo guardado en la base de datos
   ========================================================= */
(function () {
    'use strict';
    const { sesion, toast, escapeHTML, raiz } = window.ChispaEcologica;
    const API = raiz + 'php/comunidad/api_comunidad.php';

    const track       = document.getElementById('cardsTrack');
    const modalPub    = document.getElementById('modalPublicar');
    const formAccion  = document.getElementById('formAccion');
    const modalComent = document.getElementById('modalComentarios');
    const comentLista = document.getElementById('comentLista');
    const comentLike  = document.getElementById('comentLike');

    let publicaciones = [];
    let actual        = null;      // publicación abierta en la ventana de comentarios
    let miNombre      = '';

    sesion.then(s => {
        if (s.logueado) {
            miNombre = s.nombre.slice(0, 30);
            document.getElementById('nombreAutor').value = miNombre;
            document.getElementById('comentNombre').value = miNombre;
        }
    });

    /* ---------- Utilidades ---------- */
    const COLORES = ['#ffe58a', '#a0d6ef', '#ffcdb5', '#b5ffc2', '#e3d1ff'];
    /* Color del avatar */
    function colorAvatar(nombre) {
        let h = 0;
        for (const c of nombre) h = (h + c.charCodeAt(0)) % COLORES.length;
        return COLORES[h];
    }

    /* Tiempo transcurrido */
    function hace(fechaSQL) {
        const f = new Date(fechaSQL.replace(' ', 'T'));
        const seg = Math.max(0, (Date.now() - f.getTime()) / 1000);
        if (seg < 60) return 'hace un momento';
        if (seg < 3600) return 'hace ' + Math.floor(seg / 60) + ' min';
        if (seg < 86400) return 'hace ' + Math.floor(seg / 3600) + ' h';
        if (seg < 604800) return 'hace ' + Math.floor(seg / 86400) + ' d';
        return f.toLocaleDateString('es-CO');
    }

    /* Petición al servidor */
    async function pedir(url, opciones) {
        const r = await fetch(url, Object.assign({ credentials: 'same-origin', cache: 'no-store' }, opciones));
        const datos = await r.json();
        if (!r.ok || datos.ok === false) throw new Error(datos.error || 'Error del servidor');
        return datos;
    }
    const enviar = (datos) => pedir(API, { method: 'POST', body: new URLSearchParams(datos) });

    /* ---------- Abrir / cerrar ventanas (Style.css las oculta con display:none) ---------- */
    function abrir(modal)  { modal.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
    /* Cerrar ventana */
    function cerrar(modal) { modal.style.display = 'none'; document.body.style.overflow = ''; }

    [modalPub, modalComent].forEach(m => m.addEventListener('click', e => { if (e.target === m) cerrar(m); }));
    /* Cerrar con la tecla Escape */
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') { cerrar(modalPub); cerrar(modalComent); }
    });
    /* Abrir y cerrar la ventana de publicar */
    document.getElementById('btnAbrirModal').addEventListener('click', () => abrir(modalPub));
    document.getElementById('btnCerrarModal').addEventListener('click', () => cerrar(modalPub));
    document.getElementById('btnCancelarForm').addEventListener('click', () => {
        formAccion.reset();
        if (miNombre) document.getElementById('nombreAutor').value = miNombre;
        cerrar(modalPub);
    });
    /* Cerrar ventana de comentarios */
    document.getElementById('btnCerrarComent').addEventListener('click', () => cerrar(modalComent));

    /* =====================================================
       TARJETAS DE PUBLICACIONES
       ===================================================== */
    /* Tarjeta de publicación */
    function htmlTarjeta(p) {
        const enRevision = p.estado === 'pendiente';
        return `
        <article class="card${enRevision ? ' en-revision' : ''}" data-id="${p.id}">
            ${enRevision ? '<span class="badge-revision badge-revision-card" title="Solo tú la ves hasta que el administrador la apruebe"><i class="fa-regular fa-clock"></i> En revisión</span>' : ''}
            <img src="${escapeHTML(raiz + p.imagen)}" alt="${escapeHTML(p.titulo)}" loading="lazy">
            <h3>${escapeHTML(p.titulo)}</h3>
            <p>${escapeHTML(p.descripcion)}</p>
            <span class="card-autor">Por <strong>${escapeHTML(p.autor)}</strong> · ${hace(p.fecha)}</span>
            <div class="card-acciones">
                <button type="button" class="btn-like${p.me_gusta ? ' activo' : ''}" data-like="${p.id}" aria-pressed="${p.me_gusta}" aria-label="Me gusta">
                    <span class="ico"><i class="fa-solid fa-heart" aria-hidden="true"></i></span> <span class="etq">Me gusta</span> <span class="num">${p.likes}</span>
                </button>
                <button type="button" class="btn-coment" data-coment="${p.id}" aria-label="Ver comentarios">
                    <span class="ico"><i class="fa-solid fa-comment-dots" aria-hidden="true"></i></span> <span class="etq">Comentar</span> <span class="num">${p.comentarios}</span>
                </button>
                ${p.puede_eliminar ? `<button type="button" class="btn-delete-card" data-eliminar="${p.id}" title="Eliminar publicación" aria-label="Eliminar publicación"><i class="fa-solid fa-trash"></i></button>` : ''}
            </div>
        </article>`;
    }

    /* Dibujar publicaciones */
    function pintar() {
        track.innerHTML = publicaciones.length
            ? publicaciones.map(htmlTarjeta).join('')
            : '<p class="coment-vacio">Todavía no hay publicaciones. ¡Sé el primero en compartir tu acción! 🌱</p>';
    }

    /* Cargar publicaciones */
    async function cargar() {
        try {
            publicaciones = await pedir(API + '?accion=listar');
            pintar();
        } catch (e) {
            track.innerHTML = '<p class="coment-vacio">No se pudieron cargar las publicaciones. Verifica que XAMPP esté encendido.</p>';
        }
    }

    /* Me gusta en publicación */
    async function darLike(id) {
        try {
            const r = await enviar({ accion: 'like', id });
            const p = publicaciones.find(x => x.id === id);
            if (p) { p.likes = r.likes; p.me_gusta = r.me_gusta; }
            pintar();
            if (actual && actual.id === id) pintarCabecera();
        } catch (e) { toast(e.message, 'error'); }
    }

    /* Eliminar publicación */
    async function eliminarPublicacion(id) {
        if (!confirm('¿Eliminar esta publicación? No se puede deshacer.')) return;
        try {
            await enviar({ accion: 'eliminar', id });
            publicaciones = publicaciones.filter(p => p.id !== id);
            pintar();
            toast('Publicación eliminada.');
        } catch (e) { toast(e.message, 'error'); }
    }

    /* Clics en las tarjetas */
    track.addEventListener('click', e => {
        const like = e.target.closest('[data-like]');
        if (like) return darLike(Number(like.dataset.like));
        const del = e.target.closest('[data-eliminar]');
        if (del) return eliminarPublicacion(Number(del.dataset.eliminar));
        const card = e.target.closest('.card');
        if (card) abrirComentarios(Number(card.dataset.id));
    });

    /* Flechas: desplazan el carrusel */
    function mover(dir) {
        const card = track.querySelector('.card');
        const paso = card ? card.offsetWidth + 24 : 340;
        track.scrollBy({ left: dir * paso, behavior: 'smooth' });
    }
    const flechaIzq = document.getElementById('flechaIzq');
    const flechaDer = document.getElementById('flechaDer');
    flechaIzq.addEventListener('click', () => mover(-1));
    flechaDer.addEventListener('click', () => mover(1));

    /* Estado de las flechas: se apagan al llegar a un extremo */
    function actualizarFlechas() {
        const maximo = track.scrollWidth - track.clientWidth - 2;
        const hayExceso = maximo > 0;
        flechaIzq.classList.toggle('inactiva', hayExceso && track.scrollLeft <= 2);
        flechaDer.classList.toggle('inactiva', hayExceso && track.scrollLeft >= maximo);
    }
    /* Eventos que actualizan las flechas */
    track.addEventListener('scroll', actualizarFlechas, { passive: true });
    window.addEventListener('resize', actualizarFlechas);
    new MutationObserver(actualizarFlechas).observe(track, { childList: true });
    actualizarFlechas();

    /* ---------- Publicar ---------- */
    formAccion.addEventListener('submit', async e => {
        e.preventDefault();
        const archivo = document.getElementById('imgAccion').files[0];
        if (!archivo) return toast('Selecciona una imagen.', 'error');
        if (archivo.size > 5 * 1024 * 1024) return toast('La imagen pesa más de 5 MB.', 'error');

        const datos = new FormData();
        datos.append('accion', 'publicar');
        datos.append('autor', document.getElementById('nombreAutor').value.trim());
        datos.append('titulo', document.getElementById('tituloAccion').value.trim());
        datos.append('descripcion', document.getElementById('descAccion').value.trim());
        datos.append('imagen', archivo);

        const btn = formAccion.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.textContent = 'Publicando...';
        try {
            const r = await pedir(API, { method: 'POST', body: datos });
            formAccion.reset();
            if (miNombre) document.getElementById('nombreAutor').value = miNombre;
            cerrar(modalPub);
            toast(r.estado === 'pendiente'
                ? '¡Gracias! Tu publicación se verá para todos cuando el administrador la apruebe 🌿'
                : '¡Tu acción fue publicada! 🌿');
            await cargar();
            track.scrollTo({ left: 0, behavior: 'smooth' });
        } catch (err) {
            toast(err.message, 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Publicar ahora';
        }
    });

    /* =====================================================
       VENTANA DE COMENTARIOS
       ===================================================== */
    /* Cabecera de comentarios */
    function pintarCabecera() {
        document.getElementById('comentTitulo').textContent = actual.titulo;
        document.getElementById('comentDesc').textContent = actual.descripcion;
        comentLike.classList.toggle('activo', actual.me_gusta);
        comentLike.setAttribute('aria-pressed', actual.me_gusta);
        comentLike.querySelector('.num').textContent = actual.likes;
        document.getElementById('comentTotal').textContent =
            actual.comentarios + (actual.comentarios === 1 ? ' comentario' : ' comentarios');
    }

    /* Comentario individual */
    function htmlComentario(c, esRespuesta) {
        const enRevision = c.estado === 'pendiente';
        return `
        <div class="coment-item${esRespuesta ? ' es-respuesta' : ''}${enRevision ? ' en-revision' : ''}" data-cid="${c.id}">
            <span class="coment-avatar" style="background:${colorAvatar(c.autor)}">${escapeHTML(c.autor.charAt(0).toUpperCase())}</span>
            <div class="coment-cuerpo">
                <div class="coment-burbuja">
                    <strong>${escapeHTML(c.autor)}</strong>
                    <span>${escapeHTML(c.texto)}</span>
                </div>
                <div class="coment-meta">
                    <time datetime="${escapeHTML(c.fecha)}">${hace(c.fecha)}</time>
                    ${enRevision ? '<span class="badge-revision"><i class="fa-regular fa-clock"></i> En revisión</span>' : `<button type="button" class="coment-btn${c.me_gusta ? ' activo' : ''}" data-clike="${c.id}">
                        <i class="fa-solid fa-heart" aria-hidden="true"></i> Me gusta${c.likes ? ' · ' + c.likes : ''}
                    </button>`}
                    ${esRespuesta || enRevision ? '' : `<button type="button" class="coment-btn" data-responder="${c.id}">Responder</button>`}
                    ${c.puede_eliminar ? `<button type="button" class="coment-btn delete-c" data-cdel="${c.id}">Eliminar</button>` : ''}
                </div>
                ${esRespuesta ? '' : `<div class="coment-respuestas">${c.respuestas.map(r => htmlComentario(r, true)).join('')}</div>`}
            </div>
        </div>`;
    }

    /* Cargar comentarios */
    async function cargarComentarios(mantenerScroll) {
        const scroll = comentLista.scrollTop;
        try {
            const lista = await pedir(API + '?accion=comentarios&id=' + actual.id);
            // El contador cuenta solo los comentarios ya aprobados (los "en revisión" solo los ve su autor)
            const aprobado = c => c.estado !== 'pendiente';
            actual.comentarios = lista.reduce((n, c) => n + (aprobado(c) ? 1 : 0) + c.respuestas.filter(aprobado).length, 0);
            pintarCabecera();
            comentLista.innerHTML = lista.length
                ? lista.map(c => htmlComentario(c, false)).join('')
                : '<p class="coment-vacio">Sé el primero en comentar 💬</p>';
            comentLista.scrollTop = mantenerScroll ? scroll : comentLista.scrollHeight;
            pintar();   // actualiza el contador de la tarjeta
        } catch (e) {
            comentLista.innerHTML = '<p class="coment-vacio">No se pudieron cargar los comentarios.</p>';
        }
    }

    /* Abrir comentarios */
    function abrirComentarios(id) {
        actual = publicaciones.find(p => p.id === id);
        if (!actual) return;
        pintarCabecera();
        comentLista.innerHTML = '<p class="coment-vacio">Cargando...</p>';
        abrir(modalComent);
        cargarComentarios(false);
    }

    comentLike.addEventListener('click', () => actual && darLike(actual.id));

    /* Nombre del comentarista */
    function nombreComentario() {
        return document.getElementById('comentNombre').value.trim() || miNombre;
    }

    /* Nuevo comentario */
    const comentTexto = document.getElementById('comentTexto');
    /* Enviar comentario */
    async function comentar() {
        const texto = comentTexto.value.trim();
        if (!texto) return toast('Escribe un comentario.', 'error');
        try {
            const r = await enviar({ accion: 'comentar', id: actual.id, autor: nombreComentario(), texto });
            comentTexto.value = '';
            if (r.estado === 'pendiente') toast('Tu comentario se mostrará a todos cuando el administrador lo apruebe 💬');
            await cargarComentarios(false);
        } catch (e) { toast(e.message, 'error'); }
    }
    /* Botón de comentar */
    document.getElementById('comentEnviar').addEventListener('click', comentar);
    comentTexto.addEventListener('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); comentar(); }
    });

    /* Me gusta / responder / eliminar dentro de la lista */
    comentLista.addEventListener('click', async e => {
        const like = e.target.closest('[data-clike]');
        if (like) {
            try {
                await enviar({ accion: 'like_comentario', id: like.dataset.clike });
                await cargarComentarios(true);
            } catch (err) { toast(err.message, 'error'); }
            return;
        }

        const del = e.target.closest('[data-cdel]');
        if (del) {
            if (!confirm('¿Eliminar este comentario?')) return;
            try {
                await enviar({ accion: 'eliminar_comentario', id: del.dataset.cdel });
                await cargarComentarios(true);
            } catch (err) { toast(err.message, 'error'); }
            return;
        }

        const resp = e.target.closest('[data-responder]');
        if (resp) {
            comentLista.querySelectorAll('.reply-form').forEach(f => f.remove());
            const cont = resp.closest('.coment-cuerpo').querySelector('.coment-respuestas');
            cont.insertAdjacentHTML('beforeend', `
                <form class="reply-form" data-parent="${resp.dataset.responder}">
                    <input type="text" maxlength="500" placeholder="Escribe una respuesta..." aria-label="Escribe una respuesta" required>
                    <button type="submit" class="btn-guardar">Responder</button>
                    <button type="button" class="btn-cancelar">Cancelar</button>
                </form>`);
            cont.querySelector('.reply-form input').focus();
            return;
        }

        if (e.target.closest('.reply-form .btn-cancelar')) {
            e.target.closest('.reply-form').remove();
        }
    });

    comentLista.addEventListener('submit', async e => {
        const form = e.target.closest('.reply-form');
        if (!form) return;
        e.preventDefault();
        const texto = form.querySelector('input').value.trim();
        if (!texto) return;
        try {
            const r = await enviar({ accion: 'comentar', id: actual.id, parent_id: form.dataset.parent, autor: nombreComentario(), texto });
            if (r.estado === 'pendiente') toast('Tu respuesta se mostrará a todos cuando el administrador la apruebe 💬');
            await cargarComentarios(true);
        } catch (err) { toast(err.message, 'error'); }
    });

    cargar();
})();

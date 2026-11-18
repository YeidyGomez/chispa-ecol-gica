/* =========================================================
   CHISPA ECOLÓGICA - Panel de administración: MODERACIÓN
   Revisa tips, evidencias de retos, publicaciones de Comunidad y
   comentarios. El administrador ve las imágenes y el texto y puede
   aprobar, rechazar o eliminar cada elemento
   ========================================================= */
(function () {
    'use strict';

    const { sesion, escapeHTML: esc, toast, raiz } = window.ChispaEcologica;
    const API = raiz + 'php/admin/api_admin.php';

    const lista      = document.getElementById('modLista');
    const tabsEstado = document.getElementById('modEstados');
    const chipsTipo  = document.getElementById('modTipos');
    const alerta     = document.getElementById('modAlerta');
    const visor      = document.getElementById('modVisor');
    const visorImg   = document.getElementById('modVisorImg');
    if (!lista) return;

    /* ---------- Estado ---------- */
    let estado = 'pendiente';
    let tipo = 'todos';
    let resumen = null;
    let items = [];

    const TIPOS = {
        tips:          { nombre: 'Tip',           icono: 'fa-lightbulb', tema: 'tema-azul' },
        retos:         { nombre: 'Reto',          icono: 'fa-trophy',    tema: 'tema-violeta' },
        publicaciones: { nombre: 'Comunidad',     icono: 'fa-images',    tema: 'tema-verde' },
        comentarios:   { nombre: 'Comentario',    icono: 'fa-comments',  tema: 'tema-turquesa' },
    };
    const CATEGORIAS = { hogar: 'Hogar', energia: 'Energía', movilidad: 'Movilidad', agua: 'Agua' };
    const VACIO = {
        pendiente: '¡Todo al día! No hay contenido pendiente de revisar 🌿',
        aprobado:  'Aún no hay contenido aprobado en esta categoría.',
        rechazado: 'No hay contenido rechazado en esta categoría.',
    };

    /* "2026-11-03 10:15:00" -> "03/11/2026 10:15" */
    function fechaHora(f) {
        if (!f) return '';
        const [dia, hora] = String(f).split(' ');
        const [y, m, d] = dia.split('-');
        return `${d}/${m}/${y}${hora ? ' ' + hora.slice(0, 5) : ''}`;
    }

    /* ---------- Servidor ---------- */
    async function pedir(parametros, cuerpo) {
        const opciones = { credentials: 'same-origin', cache: 'no-store' };
        if (cuerpo) {
            opciones.method = 'POST';
            opciones.headers = { 'Content-Type': 'application/x-www-form-urlencoded' };
            opciones.body = new URLSearchParams(cuerpo).toString();
        }
        const r = await fetch(API + '?' + new URLSearchParams(parametros).toString(), opciones);
        if (r.status === 403) throw new Error('Tu sesión ya no tiene permisos de administrador. Vuelve a iniciar sesión.');
        const d = await r.json();
        if (!d.ok) throw new Error(d.error || 'Error del servidor');
        return d;
    }

    /* ---------- Contadores ---------- */
    function pintarContadores() {
        if (!resumen) return;
        tabsEstado.querySelectorAll('[data-cuenta]').forEach(b => {
            b.textContent = resumen[b.dataset.cuenta][tipo];
        });
        chipsTipo.querySelectorAll('[data-cuenta-tipo]').forEach(b => {
            b.textContent = resumen[estado][b.dataset.cuentaTipo];
        });
        const pend = resumen.pendiente.todos;
        alerta.hidden = pend === 0;
        alerta.textContent = pend + (pend === 1 ? ' pendiente' : ' pendientes');
    }

    function sincronizar() {
        tabsEstado.querySelectorAll('.mod-tab').forEach(b => {
            const on = b.dataset.estado === estado;
            b.classList.toggle('activo', on);
            b.setAttribute('aria-pressed', String(on));
        });
        chipsTipo.querySelectorAll('.admin-chip').forEach(b => {
            const on = b.dataset.tipo === tipo;
            b.classList.toggle('activo', on);
            b.setAttribute('aria-pressed', String(on));
        });
    }

    /* ---------- Tarjetas ---------- */
    function botones(it) {
        const aprobar  = `<button type="button" class="mod-btn mod-aprobar"  data-accion="aprobar"><i class="fa-solid fa-check"></i> Aprobar</button>`;
        const rechazar = `<button type="button" class="mod-btn mod-rechazar" data-accion="rechazar"><i class="fa-solid fa-ban"></i> ${it.estado === 'aprobado' ? 'Ocultar (rechazar)' : 'Rechazar'}</button>`;
        const eliminar = `<button type="button" class="mod-btn mod-eliminar" data-accion="eliminar"><i class="fa-solid fa-trash"></i> Eliminar</button>`;
        if (it.estado === 'pendiente') return aprobar + rechazar + eliminar;
        if (it.estado === 'aprobado')  return rechazar + eliminar;
        return aprobar + eliminar;
    }

    function tarjeta(it) {
        const t = TIPOS[it.tipo];
        let detalle = '';
        if (it.tipo === 'tips' && it.detalle) detalle = esc(CATEGORIAS[it.detalle] || it.detalle);
        else if (it.tipo === 'retos') detalle = esc(it.detalle) + (it.estado === 'aprobado' ? ' otorgados' : ' al aprobar');
        else if (it.tipo === 'comentarios') detalle = esc(it.detalle) + (it.contexto ? ' en «' + esc(it.contexto) + '»' : '');

        // Cuenta real de quien publicó (el nombre público puede ser distinto)
        const cuenta = it.usuario
            ? `${esc(it.usuario)} · ${esc(it.correo)} · ${it.perfil === 'estudiante' ? 'Estudiante' + (it.grado ? ' ' + it.grado + '°' : '') : 'Usuario externo'}`
            : 'Visitante sin cuenta';
        const imagen = it.imagen
            ? `<button type="button" class="mod-img" data-ver="${esc(raiz + it.imagen)}" aria-label="Ver la imagen en grande"><img src="${esc(raiz + it.imagen)}" alt="Imagen enviada por ${esc(it.autor)}" loading="lazy"><span><i class="fa-solid fa-magnifying-glass-plus"></i> Ampliar</span></button>`
            : '';

        return `
        <article class="mod-item ${t.tema}" data-tipo="${it.tipo}" data-id="${it.id}">
            ${imagen}
            <div class="mod-cuerpo">
                <div class="mod-fila-sup">
                    <span class="mod-tipo"><i class="fa-solid ${t.icono}" aria-hidden="true"></i> ${t.nombre}</span>
                    <span class="mod-estado mod-estado-${it.estado}">${it.estado}</span>
                </div>
                ${it.titulo ? `<h4>${esc(it.titulo)}</h4>` : ''}
                <p class="mod-texto">${esc(it.texto)}</p>
                ${detalle ? `<p class="mod-detalle">${detalle}</p>` : ''}
                <p class="mod-meta"><strong>${esc(it.autor)}</strong> · ${fechaHora(it.fecha)}<br><small>${cuenta}</small></p>
                <div class="mod-botones">${botones(it)}</div>
            </div>
        </article>`;
    }

    function pintar() {
        lista.innerHTML = items.length
            ? items.map(tarjeta).join('')
            : `<p class="mod-vacio">${VACIO[estado]}</p>`;
    }

    /* ---------- Cargar ---------- */
    async function cargar() {
        lista.setAttribute('aria-busy', 'true');
        try {
            const d = await pedir({ accion: 'moderacion_listar', estado, tipo });
            items = d.items;
            resumen = d.resumen;
            pintarContadores();
            pintar();
        } catch (e) {
            lista.innerHTML = `<p class="mod-vacio">${esc(e.message === 'Failed to fetch' ? 'No se pudo conectar. Verifica que XAMPP esté encendido.' : e.message)}</p>`;
        } finally {
            lista.removeAttribute('aria-busy');
        }
    }

    /* ---------- Acciones: aprobar / rechazar / eliminar ---------- */
    lista.addEventListener('click', async e => {
        const ver = e.target.closest('[data-ver]');
        if (ver) {
            visorImg.src = ver.dataset.ver;
            visor.hidden = false;
            return;
        }

        const boton = e.target.closest('[data-accion]');
        if (!boton) return;
        const card = boton.closest('.mod-item');
        const accion = boton.dataset.accion;
        const datos = { tipo: card.dataset.tipo, id: card.dataset.id };

        if (accion === 'eliminar' &&
            !confirm('¿Eliminar definitivamente este contenido? No se puede deshacer' +
                     (datos.tipo === 'retos' ? ' (si ya sumó EcoPuntos, se restan).' : '.'))) return;

        card.querySelectorAll('button').forEach(b => { b.disabled = true; });
        try {
            const d = accion === 'eliminar'
                ? await pedir({ accion: 'eliminar' }, datos)
                : await pedir({ accion: 'moderar' }, Object.assign({ decision: accion }, datos));
            resumen = d.resumen;
            items = items.filter(i => !(i.tipo === datos.tipo && String(i.id) === datos.id));
            card.remove();
            pintarContadores();
            if (!items.length) cargar();          // por si había más elementos que el límite de la lista
            toast(accion === 'aprobar' ? 'Aprobado ✔ Ya es visible en el sitio.'
                : accion === 'rechazar' ? 'Rechazado. Ya no se muestra en el sitio.'
                : 'Eliminado.');
        } catch (err) {
            card.querySelectorAll('button').forEach(b => { b.disabled = false; });
            toast(err.message, 'error');
        }
    });

    /* ---------- Filtros ---------- */
    tabsEstado.addEventListener('click', e => {
        const b = e.target.closest('.mod-tab');
        if (!b) return;
        estado = b.dataset.estado;
        sincronizar();
        cargar();
    });
    chipsTipo.addEventListener('click', e => {
        const b = e.target.closest('.admin-chip');
        if (!b) return;
        tipo = b.dataset.tipo;
        sincronizar();
        cargar();
    });
    document.getElementById('modActualizar').addEventListener('click', cargar);

    /* ---------- Visor de imágenes ---------- */
    function cerrarVisor() { visor.hidden = true; visorImg.removeAttribute('src'); }
    document.getElementById('modVisorCerrar').addEventListener('click', cerrarVisor);
    visor.addEventListener('click', e => { if (e.target === visor) cerrarVisor(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !visor.hidden) cerrarVisor(); });

    /* ---------- Arranque: solo el administrador ---------- */
    sesion.then(s => {
        if (!s.logueado || s.rol !== 'admin') return;
        sincronizar();
        cargar();
    });
})();

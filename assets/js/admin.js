/* =========================================================
   CHISPA ECOLÓGICA - Panel de administración
   Filtros por perfil y grado, tabla de interacciones,
   resumen por grado, detalle de cada persona y reportes
   ========================================================= */
(function () {
    'use strict';

    const { sesion, escapeHTML: esc, toast, raiz } = window.ChispaEcologica;
    const API      = raiz + 'php/admin/api_admin.php';
    const EXPORTAR = raiz + 'php/admin/exportar.php';

    /* ---------- Elementos de la página ---------- */
    const panel      = document.getElementById('adminPanel');
    const denegado   = document.getElementById('adminDenegado');
    const statsBox   = document.getElementById('adminStats');
    const gradosBox  = document.getElementById('adminGrados');
    const cuerpo     = document.getElementById('adminCuerpo');
    const conteo     = document.getElementById('adminConteo');
    const buscar     = document.getElementById('adminBuscar');
    const chipsPerfil = document.getElementById('chipsPerfil');
    const chipsGrado  = document.getElementById('chipsGrado');
    const modal      = document.getElementById('adminModal');
    const modalBox   = document.getElementById('modalContenido');

    /* ---------- Estado ---------- */
    const filtros = { perfil: 'todos', grado: 0, q: '' };
    let usuarios = [];
    let orden = { campo: 'nombre', dir: 1 };
    let temporizador = null;

    const NOMBRES_GRADO = { 6: 'Sexto', 7: 'Séptimo', 8: 'Octavo', 9: 'Noveno', 10: 'Décimo', 11: 'Undécimo' };
    const fmt = n => Number(n).toLocaleString('es-CO');

    /* Fecha "2026-11-03 10:15:00" -> "03/11/2026" */
    function fecha(f) {
        if (!f) return '—';
        const [y, m, d] = String(f).split(' ')[0].split('-');
        return `${d}/${m}/${y}`;
    }

    /* Texto del perfil */
    const etiquetaPerfil = rol => (rol === 'estudiante' ? 'Estudiante' : 'Usuario externo');

    /* Iniciales para el círculo de cada persona */
    function iniciales(nombre) {
        const p = String(nombre).trim().split(/\s+/);
        return ((p[0] || '')[0] || '?').toUpperCase() + ((p[1] || '')[0] || '').toUpperCase();
    }

    /* ---------- Petición al servidor ---------- */
    async function pedir(parametros, opciones) {
        const r = await fetch(API + '?' + parametros.toString(), Object.assign({ credentials: 'same-origin' }, opciones));
        if (r.status === 403) throw new Error('403');
        const d = await r.json();
        if (!d.ok) throw new Error(d.error || 'Error');
        return d;
    }

    /* Filtros actuales como parámetros de la URL */
    function parametrosFiltros() {
        const p = new URLSearchParams();
        p.set('perfil', filtros.perfil);
        p.set('grado', filtros.grado);
        if (filtros.q) p.set('q', filtros.q);
        return p;
    }

    /* ---------- Acceso ---------- */
    function mostrarDenegado(texto) {
        panel.hidden = true;
        denegado.hidden = false;
        document.getElementById('adminDenegadoTexto').textContent = texto;
    }

    /* ---------- Cargar datos ---------- */
    async function cargar() {
        try {
            const p = parametrosFiltros();
            p.set('accion', 'resumen');
            const d = await pedir(p);
            usuarios = d.usuarios;
            pintarTotales(d.totales);
            pintarGrados(d.grados);
            pintarTabla();
            actualizarEnlaces();
            actualizarImpresion(d.totales);
        } catch (e) {
            if (e.message === '403') mostrarDenegado('Tu sesión ya no tiene permisos de administrador. Vuelve a iniciar sesión.');
            else toast('No se pudieron cargar los datos. Verifica que XAMPP esté encendido.', 'error');
        }
    }

    /* ---------- Tarjetas de totales ---------- */
    function pintarTotales(t) {
        const items = [
            ['fa-user-graduate',  'tema-verde',    'Estudiantes',       t.estudiantes],
            ['fa-earth-americas', 'tema-turquesa', 'Usuarios externos', t.externos],
            ['fa-star',           'tema-naranja',  'EcoPuntos',         t.puntos],
            ['fa-trophy',         'tema-violeta',  'Retos completados', t.retos],
            ['fa-lightbulb',      'tema-azul',     'Tips compartidos',  t.tips],
            ['fa-images',         'tema-verde',    'Publicaciones',     t.publicaciones],
            ['fa-comments',       'tema-turquesa', 'Comentarios',       t.comentarios],
            ['fa-heart',          'tema-rojo',     'Me gusta dados',    t.likes],
        ];
        statsBox.innerHTML = items.map(([icono, tema, etiqueta, valor]) => `
            <article class="admin-stat ${tema}">
                <div class="admin-stat-icono"><i class="fa-solid ${icono}" aria-hidden="true"></i></div>
                <div><strong>${fmt(valor)}</strong><span>${etiqueta}</span></div>
            </article>`).join('');
    }

    /* ---------- Resumen por grado ---------- */
    function pintarGrados(grados) {
        const maximo = Math.max(1, ...grados.map(g => g.puntos));
        gradosBox.innerHTML = grados.map(g => {
            const ancho = g.puntos > 0 ? Math.max(3, Math.round(g.puntos * 100 / maximo)) : 0;
            const activo = filtros.grado === g.grado ? ' activo' : '';
            return `
            <button type="button" class="admin-grado${activo}" data-grado="${g.grado}" aria-pressed="${activo ? 'true' : 'false'}">
                <div class="g-info"><strong>${g.grado}°</strong><span>${esc(g.nombre)}</span></div>
                <div class="g-barras">
                    <div class="g-fila"><span>EcoPuntos</span><div class="g-barra"><div class="g-relleno puntos" style="width:${ancho}%"></div></div><b>${fmt(g.puntos)}</b></div>
                    <div class="g-fila"><span>Participación</span><div class="g-barra"><div class="g-relleno part" style="width:${g.participacion}%"></div></div><b>${g.participacion}%</b></div>
                </div>
                <div class="g-datos">
                    <span><b>${fmt(g.estudiantes)}</b> estudiantes</span>
                    <span><b>${fmt(g.retos)}</b> retos</span>
                    <span><b>${fmt(g.tips)}</b> tips</span>
                    <span><b>${fmt(g.publicaciones)}</b> publ.</span>
                    <span><b>${fmt(g.comentarios)}</b> coment.</span>
                    <span><b>${fmt(g.likes)}</b> me gusta</span>
                </div>
            </button>`;
        }).join('');
    }

    /* ---------- Tabla de personas ---------- */
    function ordenar(lista) {
        const { campo, dir } = orden;
        return lista.slice().sort((a, b) => {
            let x = a[campo], y = b[campo];
            if (x === null || x === undefined) return 1;     // los vacíos siempre al final
            if (y === null || y === undefined) return -1;
            if (typeof x === 'string') return x.localeCompare(y, 'es') * dir;
            return (x - y) * dir;
        });
    }

    const celda = n => `<td class="num${n === 0 ? ' cero' : ''}">${fmt(n)}</td>`;

    function pintarTabla() {
        conteo.textContent = `(${fmt(usuarios.length)})`;

        document.querySelectorAll('#adminTabla th[data-orden]').forEach(th => {
            th.classList.remove('orden-asc', 'orden-desc');
            if (th.dataset.orden === orden.campo) th.classList.add(orden.dir === 1 ? 'orden-asc' : 'orden-desc');
        });

        if (!usuarios.length) {
            cuerpo.innerHTML = '<tr><td colspan="11" class="admin-vacio">No hay personas con estos filtros.</td></tr>';
            return;
        }
        cuerpo.innerHTML = ordenar(usuarios).map(u => `
            <tr>
                <td>
                    <div class="p-nombre">
                        <span class="p-avatar">${esc(iniciales(u.nombre))}</span>
                        <div><strong>${esc(u.nombre)}</strong><small>${esc(u.correo)}</small></div>
                    </div>
                </td>
                <td><span class="badge-perfil ${u.rol}">${etiquetaPerfil(u.rol)}</span></td>
                <td>${u.grado ? u.grado + '°' : '—'}</td>
                ${celda(u.puntos)}${celda(u.retos)}${celda(u.tips)}${celda(u.publicaciones)}${celda(u.comentarios)}${celda(u.likes)}
                <td>${fecha(u.ultima_actividad)}</td>
                <td class="no-imprimir"><button type="button" class="admin-ver" data-id="${u.id}"><i class="fa-solid fa-eye"></i> Ver</button></td>
            </tr>`).join('');
    }

    /* ---------- Enlaces de los reportes ---------- */
    function actualizarEnlaces() {
        const det = parametrosFiltros();
        det.set('tipo', 'detalle');
        document.getElementById('btnCsvDetalle').href = EXPORTAR + '?' + det.toString();
        document.getElementById('btnCsvGrados').href = EXPORTAR + '?tipo=grados';
    }

    /* Texto que aparece al imprimir */
    function actualizarImpresion(t) {
        const perfil = filtros.perfil === 'todos' ? 'todos' : etiquetaPerfil(filtros.perfil).toLowerCase();
        const grado = filtros.grado ? `${filtros.grado}° ${NOMBRES_GRADO[filtros.grado]}` : 'todos';
        const hoy = new Date().toLocaleDateString('es-CO');
        document.getElementById('infoImpresion').textContent =
            `Fecha: ${hoy} · Perfil: ${perfil} · Grado: ${grado}` +
            (filtros.q ? ` · Búsqueda: "${filtros.q}"` : '') + ` · Personas: ${fmt(t.personas)}`;
    }

    /* ---------- Filtros ---------- */
    function sincronizarChips() {
        chipsPerfil.querySelectorAll('.admin-chip').forEach(b => {
            const on = b.dataset.perfil === filtros.perfil;
            b.classList.toggle('activo', on);
            b.setAttribute('aria-pressed', String(on));
        });
        chipsGrado.querySelectorAll('.admin-chip').forEach(b => {
            const on = Number(b.dataset.grado) === filtros.grado;
            b.classList.toggle('activo', on);
            b.setAttribute('aria-pressed', String(on));
        });
        chipsGrado.classList.toggle('deshabilitado', filtros.perfil === 'externo');
    }

    chipsPerfil.addEventListener('click', e => {
        const b = e.target.closest('.admin-chip');
        if (!b) return;
        filtros.perfil = b.dataset.perfil;
        if (filtros.perfil === 'externo') filtros.grado = 0;      // los externos no tienen grado
        sincronizarChips();
        cargar();
    });

    chipsGrado.addEventListener('click', e => {
        const b = e.target.closest('.admin-chip');
        if (!b || filtros.perfil === 'externo') return;
        filtros.grado = Number(b.dataset.grado);
        if (filtros.grado > 0) filtros.perfil = 'estudiante';      // elegir un grado implica estudiantes
        sincronizarChips();
        cargar();
    });

    gradosBox.addEventListener('click', e => {
        const b = e.target.closest('.admin-grado');
        if (!b) return;
        const g = Number(b.dataset.grado);
        filtros.grado = filtros.grado === g ? 0 : g;               // segundo clic quita el filtro
        filtros.perfil = 'estudiante';
        sincronizarChips();
        cargar();
        document.getElementById('tituloTabla').scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    buscar.addEventListener('input', () => {
        clearTimeout(temporizador);
        temporizador = setTimeout(() => { filtros.q = buscar.value.trim(); cargar(); }, 350);
    });

    document.getElementById('adminLimpiar').addEventListener('click', () => {
        filtros.perfil = 'todos'; filtros.grado = 0; filtros.q = '';
        buscar.value = '';
        sincronizarChips();
        cargar();
    });

    /* ---------- Orden de la tabla ---------- */
    document.querySelector('#adminTabla thead').addEventListener('click', e => {
        const th = e.target.closest('th[data-orden]');
        if (!th) return;
        const campo = th.dataset.orden;
        orden = { campo, dir: orden.campo === campo ? -orden.dir : (campo === 'nombre' || campo === 'rol' ? 1 : -1) };
        pintarTabla();
    });

    /* ---------- Imprimir / guardar en PDF ---------- */
    document.getElementById('btnImprimir').addEventListener('click', () => window.print());

    /* ---------- Detalle de una persona ---------- */
    const lista = (items, vacio, fila) => items.length
        ? `<ul class="detalle-lista">${items.map(fila).join('')}</ul>`
        : `<p class="detalle-vacio">${vacio}</p>`;

    async function abrirDetalle(id) {
        modal.hidden = false;
        modalBox.innerHTML = '<p class="detalle-vacio">Cargando...</p>';
        try {
            const p = new URLSearchParams({ accion: 'detalle', id });
            const d = await pedir(p);
            pintarDetalle(d);
        } catch (e) {
            modalBox.innerHTML = '<p class="detalle-vacio">No se pudo cargar el detalle.</p>';
        }
    }

    function pintarDetalle(d) {
        const u = d.persona;
        const opcionesGrado = Object.entries(NOMBRES_GRADO)
            .map(([g, n]) => `<option value="${g}"${u.grado === Number(g) ? ' selected' : ''}>${g}° ${n}</option>`).join('');

        modalBox.innerHTML = `
            <div class="detalle-cabecera">
                <span class="p-avatar grande">${esc(iniciales(u.nombre))}</span>
                <div>
                    <h3 id="modalNombre">${esc(u.nombre)}</h3>
                    <p>${esc(u.correo)} · <span class="badge-perfil ${u.rol}">${etiquetaPerfil(u.rol)}</span>${u.grado ? ' · ' + u.grado + '° ' + NOMBRES_GRADO[u.grado] : ''}</p>
                    <p class="detalle-meta">Registro: ${fecha(u.registro)} · ${fmt(u.puntos)} EcoPuntos · ${esc(u.nivel)}</p>
                    <p class="detalle-meta">Política de privacidad: ${u.politica ? 'aceptada el ' + fecha(u.politica) : 'sin registro (cuenta anterior a la versión 2)'}</p>
                </div>
            </div>

            <div class="detalle-editar">
                <strong><i class="fa-solid fa-pen"></i> Perfil</strong>
                <select id="editRol" aria-label="Perfil">
                    <option value="estudiante"${u.rol === 'estudiante' ? ' selected' : ''}>Estudiante</option>
                    <option value="externo"${u.rol === 'externo' ? ' selected' : ''}>Usuario externo</option>
                </select>
                <select id="editGrado" aria-label="Grado"${u.rol === 'externo' ? ' hidden' : ''}>${opcionesGrado}</select>
                <button type="button" class="admin-btn" id="editGuardar" data-id="${u.id}"><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
            </div>

            <div class="detalle-columnas">
                <section>
                    <h4><i class="fa-solid fa-trophy"></i> Retos completados (${d.retos.length})</h4>
                    ${lista(d.retos, 'Aún no completa retos.', r => `<li><b>${esc(r.nombre)}</b> <em>+${fmt(r.puntos)} pts · ${fecha(r.fecha)}</em><small>${esc(String(r.descripcion).slice(0, 140))}</small></li>`)}
                </section>
                <section>
                    <h4><i class="fa-solid fa-lightbulb"></i> Tips compartidos (${d.tips.length})</h4>
                    ${lista(d.tips, 'No ha compartido tips.', t => `<li><b>${esc(t.titulo)}</b> <em>${esc(t.categoria)} · ${fecha(t.fecha)}</em></li>`)}
                </section>
                <section>
                    <h4><i class="fa-solid fa-images"></i> Publicaciones (${d.publicaciones.length})</h4>
                    ${lista(d.publicaciones, 'No ha publicado en Comunidad Eco.', p => `<li><b>${esc(p.titulo)}</b> <em>${fmt(p.likes)} me gusta · ${fmt(p.comentarios)} comentarios · ${fecha(p.fecha)}</em></li>`)}
                </section>
                <section>
                    <h4><i class="fa-solid fa-comments"></i> Comentarios (${d.comentarios.length})</h4>
                    ${lista(d.comentarios, 'No ha comentado.', c => `<li><b>"${esc(String(c.texto).slice(0, 120))}"</b> <em>en ${esc(c.publicacion)} · ${fecha(c.fecha)}</em></li>`)}
                </section>
            </div>`;

        const selRol = document.getElementById('editRol');
        const selGrado = document.getElementById('editGrado');
        selRol.addEventListener('change', () => { selGrado.hidden = selRol.value !== 'estudiante'; });
        document.getElementById('editGuardar').addEventListener('click', () => guardarPerfil(u.id, selRol.value, selGrado.value));
    }

    async function guardarPerfil(id, rol, grado) {
        try {
            const cuerpoForm = new URLSearchParams({ accion: 'actualizar', id, rol, grado });
            await pedir(new URLSearchParams(), {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: cuerpoForm.toString(),
            });
            toast('Perfil actualizado ✔');
            cerrarModal();
            cargar();
        } catch (e) {
            toast(e.message && e.message !== '403' ? e.message : 'No se pudo guardar el cambio.', 'error');
        }
    }

    function cerrarModal() { modal.hidden = true; modalBox.innerHTML = ''; }

    cuerpo.addEventListener('click', e => {
        const b = e.target.closest('.admin-ver');
        if (b) abrirDetalle(b.dataset.id);
    });
    document.getElementById('modalCerrar').addEventListener('click', cerrarModal);
    modal.addEventListener('click', e => { if (e.target === modal) cerrarModal(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !modal.hidden) cerrarModal(); });

    /* ---------- Arranque: solo el administrador ve el panel ---------- */
    sesion.then(s => {
        if (s.sinServidor) return mostrarDenegado('Abre el sitio desde http://localhost/chispa-ecologica/ con XAMPP encendido.');
        if (!s.logueado) return mostrarDenegado('Inicia sesión con la cuenta de administrador para ver el panel.');
        if (s.rol !== 'admin') return mostrarDenegado('Tu cuenta no tiene permisos de administrador.');
        panel.hidden = false;
        sincronizarChips();
        cargar();
    });
})();

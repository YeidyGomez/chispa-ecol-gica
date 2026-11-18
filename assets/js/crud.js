/* =========================================================
   CHISPA ECOLÓGICA - Gestión de datos (CRUD de toda la base de datos)
   ========================================================= */
(function () {
    'use strict';

    const { sesion, escapeHTML: esc, toast, raiz } = window.ChispaEcologica;
    const API = raiz + 'php/admin/api_crud.php';

    /* ---------- Elementos ---------- */
    const $ = id => document.getElementById(id);
    const app = $('crudApp'), denegado = $('adminDenegado');
    const tabsBox = $('crudTablas'), titulo = $('crudTitulo'), nota = $('crudNota');
    const btnNuevo = $('crudNuevo'), buscar = $('crudBuscar');
    const filtroEstado = $('crudFiltroEstado'), selEstado = $('crudEstado');
    const tabla = $('crudTabla'), thead = tabla.querySelector('thead'), tbody = tabla.querySelector('tbody');
    const vacio = $('crudVacio'), pagTxt = $('crudPagina');
    const btnAnt = $('crudAnterior'), btnSig = $('crudSiguiente');
    const modal = $('crudModal'), form = $('crudForm'), camposBox = $('crudCampos');
    const info = $('crudInfo'), errorBox = $('crudError'), btnGuardar = $('crudGuardar');
    const visor = $('crudVisor'), visorImg = $('crudVisorImg');

    /* ---------- Estado ---------- */
    let tablas = [];          // esquema + conteos
    let yo = 0;               // id del administrador en sesión
    let actual = null;        // tabla seleccionada
    let filas = [];           // filas de la página actual
    const vista = { q: '', estado: '', pagina: 1, paginas: 1, total: 0, orden: '', dir: 'desc' };
    let pedidoN = 0;          // para ignorar respuestas viejas
    let editando = null;      // fila que se edita (null = nuevo)
    let disparador = null;    // botón que abrió el formulario (para devolver el foco)
    const cacheOpciones = {}; // opciones de los campos que apuntan a otra tabla

    /* ---------- Textos legibles ---------- */
    const ETIQUETAS = {
        rol:       { admin: 'Administrador', estudiante: 'Estudiante', externo: 'Usuario externo' },
        categoria: { hogar: 'Hogar', energia: 'Energía', movilidad: 'Movilidad', agua: 'Agua' },
        origen:    { base: 'Base del sitio', comunidad: 'Comunidad' },
        estado:    { pendiente: 'Pendiente', aprobado: 'Aprobado', rechazado: 'Rechazado' },
    };
    const etiquetaOpcion = (campo, v) => (ETIQUETAS[campo] && ETIQUETAS[campo][v]) || v;

    function fechaHora(f) {
        if (!f) return '—';
        const [dia, hora] = String(f).split(' ');
        const [y, m, d] = dia.split('-');
        return `${d}/${m}/${y}${hora ? ' ' + hora.slice(0, 5) : ''}`;
    }

    /* ---------- Servidor ---------- */
    async function pedir(parametros, cuerpo) {
        const opciones = { credentials: 'same-origin', cache: 'no-store' };
        let url = API;
        if (cuerpo) {
            opciones.method = 'POST';
            opciones.body = cuerpo;
            opciones.headers = { 'X-Requested-With': 'XMLHttpRequest' };
        } else {
            url += '?' + new URLSearchParams(parametros).toString();
        }
        let r;
        try { r = await fetch(url, opciones); }
        catch (e) { throw new Error('No se pudo conectar. Verifica que XAMPP esté encendido.'); }
        if (r.status === 403) { mostrarDenegado('Tu sesión ya no tiene permisos de administrador. Vuelve a iniciar sesión.'); throw new Error('Sin permisos.'); }
        let d;
        try { d = await r.json(); }
        catch (e) { throw new Error('El servidor respondió con un error. Revisa la base de datos.'); }
        if (!d.ok) throw new Error(d.error || 'Error del servidor');
        return d;
    }

    function mostrarDenegado(texto) {
        app.hidden = true;
        denegado.hidden = false;
        $('adminDenegadoTexto').textContent = texto;
    }

    /* =========================================================
       PESTAÑAS DE TABLAS
       ========================================================= */
    function pintarTabs() {
        tabsBox.innerHTML = tablas.map(t => `
            <button type="button" role="tab" class="admin-chip${actual && actual.nombre === t.nombre ? ' activo' : ''}"
                    data-tabla="${esc(t.nombre)}" aria-selected="${actual && actual.nombre === t.nombre}">
                <i class="fa-solid ${esc(t.icono)}" aria-hidden="true"></i> ${esc(t.titulo)} <b>${t.conteo}</b>
            </button>`).join('');
    }

    async function refrescarConteos() {
        try {
            const d = await pedir({ accion: 'tablas' });
            tablas.forEach(t => { const n = d.tablas.find(x => x.nombre === t.nombre); if (n) t.conteo = n.conteo; });
            pintarTabs();
        } catch (e) { /* los conteos no son críticos */ }
    }

    function seleccionar(nombre) {
        actual = tablas.find(t => t.nombre === nombre);
        Object.assign(vista, { q: '', estado: '', pagina: 1, orden: '', dir: 'desc' });
        buscar.value = '';
        selEstado.value = '';
        pintarTabs();

        titulo.innerHTML = `<i class="fa-solid ${esc(actual.icono)}" aria-hidden="true"></i> ${esc(actual.titulo)}`;
        nota.textContent = actual.nota || 'Busca, ordena haciendo clic en los títulos de las columnas, y usa los botones de cada fila para editar o eliminar.';
        btnNuevo.hidden = !actual.crear;
        btnNuevo.querySelector('span').textContent = 'Agregar ' + actual.singular;
        filtroEstado.hidden = !actual.campos.some(c => c.nombre === 'estado');

        // Mientras llegan los datos nuevos no se deja a la vista la tabla anterior
        thead.innerHTML = '';
        tbody.innerHTML = '';
        tabla.hidden = true;
        vacio.textContent = 'Cargando…';
        vacio.hidden = false;
        cargar();
    }

    /* =========================================================
       LISTA
       ========================================================= */
    async function cargar() {
        const n = ++pedidoN;
        tabla.setAttribute('aria-busy', 'true');
        try {
            const p = { accion: 'listar', tabla: actual.nombre, pagina: vista.pagina };
            if (vista.q) p.q = vista.q;
            if (vista.estado) p.estado = vista.estado;
            if (vista.orden) { p.orden = vista.orden; p.dir = vista.dir; }
            const d = await pedir(p);
            if (n !== pedidoN) return;                 // llegó una respuesta más nueva
            filas = d.filas;
            Object.assign(vista, { pagina: d.pagina, paginas: d.paginas, total: d.total });
            pintarTabla();
        } catch (e) {
            if (n !== pedidoN) return;
            filas = [];
            tbody.innerHTML = '';
            tabla.hidden = true;
            vacio.textContent = e.message;
            vacio.hidden = false;
        } finally {
            if (n === pedidoN) tabla.removeAttribute('aria-busy');
        }
    }

    const columnas = () => actual.campos.filter(c => c.lista);

    function celda(def, fila) {
        const v = fila[def.nombre];
        if (v === null || v === undefined || v === '') return '<span class="cero">—</span>';
        switch (def.tipo) {
            case 'imagen':
                return `<button type="button" class="crud-mini" data-ver="${esc(raiz + v)}" aria-label="Ver imagen en grande"><img src="${esc(raiz + v)}" alt="" loading="lazy"></button>`;
            case 'fk':
                return `<span class="crud-trunc" title="${esc(fila['_' + def.nombre] || v)}">${esc(fila['_' + def.nombre] || '#' + v)}</span>`;
            case 'fecha':
                return esc(fechaHora(v));
            case 'enum':
                return def.nombre === 'estado'
                    ? `<span class="mod-estado mod-estado-${esc(v)}">${esc(v)}</span>`
                    : esc(etiquetaOpcion(def.nombre, v));
            case 'int':
                return esc(v);
            default:
                return `<span class="crud-trunc" title="${esc(v)}">${esc(v)}</span>`;
        }
    }

    function pintarTabla() {
        const cols = columnas();
        thead.innerHTML = '<tr>' + cols.map(c => {
            const activo = vista.orden === c.nombre;
            return `<th scope="col" data-orden="${esc(c.nombre)}" class="${c.tipo === 'int' ? 'num ' : ''}${activo ? (vista.dir === 'asc' ? 'orden-asc' : 'orden-desc') : ''}">${esc(c.etq)}</th>`;
        }).join('') + '<th scope="col" class="crud-th-acc">Acciones</th></tr>';

        tbody.innerHTML = filas.map((f, i) => {
            const esYo = actual.nombre === 'usuarios' && Number(f.id) === yo;
            const acc = [];
            if (actual.editar)   acc.push(`<button type="button" class="crud-icono" data-editar="${i}" title="Editar" aria-label="Editar"><i class="fa-solid fa-pen"></i></button>`);
            if (actual.eliminar) acc.push(esYo
                ? `<button type="button" class="crud-icono crud-icono-borrar" disabled title="No puedes eliminar tu propia cuenta" aria-label="No puedes eliminar tu propia cuenta"><i class="fa-solid fa-trash"></i></button>`
                : `<button type="button" class="crud-icono crud-icono-borrar" data-eliminar="${i}" title="Eliminar" aria-label="Eliminar"><i class="fa-solid fa-trash"></i></button>`);
            return '<tr>' + cols.map(c => `<td class="${c.tipo === 'int' ? 'num' : ''}">${celda(c, f)}</td>`).join('')
                 + `<td class="crud-acciones-fila">${acc.join('') || '<span class="cero">—</span>'}</td></tr>`;
        }).join('');

        vacio.textContent = vista.q || vista.estado ? 'No hay registros que coincidan con la búsqueda.' : 'Todavía no hay registros en esta tabla.';
        vacio.hidden = filas.length > 0;
        tabla.hidden = filas.length === 0;
        pagTxt.textContent = `Página ${vista.pagina} de ${vista.paginas} · ${vista.total} registro${vista.total === 1 ? '' : 's'}`;
        btnAnt.disabled = vista.pagina <= 1;
        btnSig.disabled = vista.pagina >= vista.paginas;
    }

    /* =========================================================
       FORMULARIO: CREAR / EDITAR
       ========================================================= */
    function infoFija(fila) {
        const filasInfo = actual.campos.filter(c => c.nombre in fila && (c.solo_lectura || c.solo_crear) && c.tipo !== 'clave');
        if (!filasInfo.length) { info.hidden = true; return; }
        info.innerHTML = filasInfo.map(c => {
            const v = fila[c.nombre];
            let txt;
            if (v === null || v === undefined || v === '') txt = '—';
            else if (c.tipo === 'imagen') txt = `<button type="button" class="crud-mini" data-ver="${esc(raiz + v)}" aria-label="Ver imagen en grande"><img src="${esc(raiz + v)}" alt=""></button>`;
            else if (c.tipo === 'fk') txt = esc(fila['_' + c.nombre] || '#' + v);
            else if (c.tipo === 'fecha') txt = esc(fechaHora(v));
            else txt = esc(etiquetaOpcion(c.nombre, v));
            return `<div><dt>${esc(c.etq)}</dt><dd>${txt}</dd></div>`;
        }).join('');
        info.hidden = false;
    }

    async function opcionesDe(tablaFk) {
        if (!cacheOpciones[tablaFk]) {
            const d = await pedir({ accion: 'opciones', tabla: tablaFk });
            cacheOpciones[tablaFk] = d.opciones;
        }
        return cacheOpciones[tablaFk];
    }

    /* Al editar, la contraseña y la imagen solo se envían si se quieren cambiar */
    const esObligatorio = (def, modo) => !!def.req && !(modo === 'editar' && (def.tipo === 'clave' || def.tipo === 'imagen'));

    function controlHTML(def, valor, modo) {
        const id = 'f_' + def.nombre;
        const req = esObligatorio(def, modo);
        const base = `id="${id}" name="${esc(def.nombre)}"${req ? ' required' : ''}`;
        const v = valor === null || valor === undefined ? '' : valor;
        switch (def.tipo) {
            case 'textarea':
                return `<textarea ${base} rows="4"${def.max ? ` maxlength="${def.max}"` : ''}>${esc(v)}</textarea>`;
            case 'enum':
                return `<select ${base}>${def.opciones.map(o => `<option value="${esc(o)}"${String(v) === o ? ' selected' : ''}>${esc(etiquetaOpcion(def.nombre, o))}</option>`).join('')}</select>`;
            case 'fk':
                return `<select ${base} data-fk="${esc(def.fk.tabla)}" data-valor="${esc(v)}"><option value="">Cargando…</option></select>`;
            case 'int':
                return `<input ${base} type="number" step="1"${def.min !== undefined ? ` min="${def.min}"` : ''}${def.max !== undefined ? ` max="${def.max}"` : ''} value="${esc(v)}">`;
            case 'email':
                return `<input ${base} type="email" maxlength="${def.max || 150}" value="${esc(v)}" autocomplete="off">`;
            case 'clave':
                return `<input ${base} type="password" minlength="${def.min || 8}" autocomplete="new-password" placeholder="${modo === 'editar' ? 'Dejar vacía para no cambiarla' : ''}">`;
            case 'imagen':
                return `<input ${base} type="file" accept="image/jpeg,image/png,image/gif,image/webp">`
                     + (modo === 'editar' && valor ? `<img class="crud-previa" src="${esc(raiz + valor)}" alt="Imagen actual">` : '');
            default:
                return `<input ${base} type="text"${def.max ? ` maxlength="${def.max}"` : ''} value="${esc(v)}" autocomplete="off">`;
        }
    }

    async function llenarSelectsFk(fila) {
        const selects = camposBox.querySelectorAll('select[data-fk]');
        for (const sel of selects) {
            const def = actual.campos.find(c => c.nombre === sel.name);
            try {
                const ops = await opcionesDe(sel.dataset.fk);
                const actualV = sel.dataset.valor;
                let html = def.req ? '<option value="">— Elige —</option>' : '<option value="">— Ninguno —</option>';
                const hay = ops.some(o => String(o.id) === actualV);
                if (actualV && !hay) html += `<option value="${esc(actualV)}" selected>${esc((fila && fila['_' + def.nombre]) || '#' + actualV)}</option>`;
                html += ops.map(o => `<option value="${esc(o.id)}"${String(o.id) === actualV ? ' selected' : ''}>${esc(o.etiqueta)}</option>`).join('');
                sel.innerHTML = html;
            } catch (e) {
                sel.innerHTML = '<option value="">No se pudieron cargar las opciones</option>';
            }
        }
    }

    function abrirForm(fila, origen) {
        editando = fila;
        disparador = origen || null;
        const modo = fila ? 'editar' : 'crear';
        $('crudModalTitulo').textContent = fila
            ? `Editar ${actual.singular}${fila.id ? ' #' + fila.id : ''}`
            : `Agregar ${actual.singular}`;

        if (fila) infoFija(fila); else info.hidden = true;

        const editables = actual.campos.filter(c => !c.solo_lectura && c.tipo !== 'fecha' && !(modo === 'editar' && c.solo_crear));
        camposBox.innerHTML = editables.map(c => `
            <div class="crud-campo${c.tipo === 'textarea' ? ' ancho' : ''}">
                <label for="f_${esc(c.nombre)}">${esc(c.etq)}${esObligatorio(c, modo) ? ' <span class="crud-req" aria-hidden="true">*</span>' : ''}</label>
                ${controlHTML(c, fila ? fila[c.nombre] : (c.defecto !== undefined ? c.defecto : ''), modo)}
                ${c.ayuda ? `<small>${esc(c.ayuda)}</small>` : ''}
            </div>`).join('');

        // Usuarios: el grado solo aplica a estudiantes
        if (actual.nombre === 'usuarios') {
            const rol = $('f_rol'), grado = $('f_grado');
            const ajustar = () => {
                const est = rol.value === 'estudiante';
                grado.disabled = !est;
                grado.required = est;
                if (!est) grado.value = '';
            };
            rol.addEventListener('change', ajustar);
            ajustar();
        }

        errorBox.hidden = true;
        btnGuardar.disabled = false;
        modal.hidden = false;
        llenarSelectsFk(fila);
        const primero = camposBox.querySelector('input:not([disabled]), select, textarea');
        if (primero) primero.focus();
    }

    function cerrarForm() {
        modal.hidden = true;
        editando = null;
        if (disparador && document.contains(disparador)) disparador.focus();
        disparador = null;
    }

    form.addEventListener('submit', async e => {
        e.preventDefault();
        if (!form.checkValidity()) { form.reportValidity(); return; }

        const modo = editando ? 'editar' : 'crear';
        const fd = new FormData();
        fd.append('accion', modo === 'crear' ? 'crear' : 'actualizar');
        fd.append('tabla', actual.nombre);
        if (editando) actual.pk.forEach(c => fd.append(`pk[${c}]`, editando[c]));

        actual.campos.forEach(c => {
            if (c.solo_lectura || c.tipo === 'fecha' || (modo === 'editar' && c.solo_crear)) return;
            const el = form.elements[c.nombre];
            if (!el) return;
            if (c.tipo === 'imagen') { if (el.files[0]) fd.append('imagen', el.files[0]); }
            else fd.append(`datos[${c.nombre}]`, el.value);
        });

        btnGuardar.disabled = true;
        errorBox.hidden = true;
        try {
            await pedir(null, fd);
            Object.keys(cacheOpciones).forEach(k => delete cacheOpciones[k]);
            cerrarForm();
            toast(modo === 'crear' ? 'Registro creado ✔' : 'Cambios guardados ✔');
            cargar();
            refrescarConteos();
        } catch (err) {
            errorBox.textContent = err.message;
            errorBox.hidden = false;
            btnGuardar.disabled = false;
        }
    });

    /* =========================================================
       ELIMINAR
       ========================================================= */
    async function eliminar(fila) {
        const que = fila.id ? `${actual.singular} #${fila.id}` : `este ${actual.singular}`;
        const aviso = actual.aviso_eliminar ? '\n\n' + actual.aviso_eliminar : '';
        if (!confirm(`¿Eliminar ${que}? No se puede deshacer.${aviso}`)) return;

        const fd = new FormData();
        fd.append('accion', 'eliminar');
        fd.append('tabla', actual.nombre);
        actual.pk.forEach(c => fd.append(`pk[${c}]`, fila[c]));
        try {
            await pedir(null, fd);
            Object.keys(cacheOpciones).forEach(k => delete cacheOpciones[k]);
            toast('Registro eliminado.');
            cargar();
            refrescarConteos();
        } catch (err) {
            toast(err.message, 'error');
        }
    }

    /* =========================================================
       EVENTOS
       ========================================================= */
    tabsBox.addEventListener('click', e => {
        const b = e.target.closest('[data-tabla]');
        if (b) seleccionar(b.dataset.tabla);
    });

    btnNuevo.addEventListener('click', () => abrirForm(null, btnNuevo));

    document.addEventListener('click', e => {
        const ver = e.target.closest('[data-ver]');
        if (ver) { visorImg.src = ver.dataset.ver; visor.hidden = false; return; }
    });

    tbody.addEventListener('click', e => {
        const ed = e.target.closest('[data-editar]');
        if (ed) return abrirForm(filas[Number(ed.dataset.editar)], ed);
        const el = e.target.closest('[data-eliminar]');
        if (el) eliminar(filas[Number(el.dataset.eliminar)]);
    });

    thead.addEventListener('click', e => {
        const th = e.target.closest('[data-orden]');
        if (!th) return;
        const campo = th.dataset.orden;
        vista.dir = vista.orden === campo && vista.dir === 'asc' ? 'desc' : 'asc';
        vista.orden = campo;
        vista.pagina = 1;
        cargar();
    });

    let temporizador = null;
    buscar.addEventListener('input', () => {
        clearTimeout(temporizador);
        temporizador = setTimeout(() => { vista.q = buscar.value.trim(); vista.pagina = 1; cargar(); }, 300);
    });
    selEstado.addEventListener('change', () => { vista.estado = selEstado.value; vista.pagina = 1; cargar(); });
    $('crudRecargar').addEventListener('click', () => { cargar(); refrescarConteos(); });
    btnAnt.addEventListener('click', () => { if (vista.pagina > 1) { vista.pagina--; cargar(); } });
    btnSig.addEventListener('click', () => { if (vista.pagina < vista.paginas) { vista.pagina++; cargar(); } });

    $('crudModalCerrar').addEventListener('click', cerrarForm);
    $('crudCancelar').addEventListener('click', cerrarForm);
    modal.addEventListener('mousedown', e => { if (e.target === modal) cerrarForm(); });

    function cerrarVisor() { visor.hidden = true; visorImg.removeAttribute('src'); }
    $('crudVisorCerrar').addEventListener('click', cerrarVisor);
    visor.addEventListener('click', e => { if (e.target === visor) cerrarVisor(); });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            if (!visor.hidden) cerrarVisor();
            else if (!modal.hidden) cerrarForm();
        }
        // El foco se queda dentro del formulario mientras está abierto
        if (e.key === 'Tab' && !modal.hidden && visor.hidden) {
            const f = [...modal.querySelectorAll('button, input, select, textarea, a[href]')].filter(x => !x.disabled && x.offsetParent !== null);
            if (!f.length) return;
            const primero = f[0], ultimo = f[f.length - 1];
            if (e.shiftKey && document.activeElement === primero) { e.preventDefault(); ultimo.focus(); }
            else if (!e.shiftKey && document.activeElement === ultimo) { e.preventDefault(); primero.focus(); }
        }
    });

    /* =========================================================
       ARRANQUE: solo el administrador
       ========================================================= */
    sesion.then(async s => {
        if (s.sinServidor) return mostrarDenegado('Abre el sitio desde http://localhost/chispa-ecologica/ con XAMPP encendido.');
        if (!s.logueado) return mostrarDenegado('Inicia sesión con la cuenta de administrador para gestionar los datos.');
        if (s.rol !== 'admin') return mostrarDenegado('Tu cuenta no tiene permisos de administrador.');
        try {
            const d = await pedir({ accion: 'tablas' });
            tablas = d.tablas;
            yo = d.yo;
            app.hidden = false;
            seleccionar(tablas[0].nombre);
        } catch (e) {
            if (denegado.hidden) mostrarDenegado(e.message);
        }
    });
})();

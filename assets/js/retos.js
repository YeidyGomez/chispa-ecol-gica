/* =========================================================
   CHISPA ECOLÓGICA - RetosEcologicos.html
   Carga los datos reales del usuario y la tabla de posiciones 
   ========================================================= */
(function () {
    'use strict';
    const { sesion, escapeHTML, raiz } = window.ChispaEcologica;
    const fmt = n => Number(n).toLocaleString('es-CO');

    /* ---------- Resumen y filtros de los retos ---------- */
    const tarjetas = Array.from(document.querySelectorAll('.challenge-item-card'));
    const resumen  = document.getElementById('retos-resumen');
    const vacio    = document.getElementById('retos-vacio');
    const filtros  = document.querySelectorAll('.reto-filtro');
    const totalPts = tarjetas.reduce((n, c) => n + Number(c.dataset.pts || 0), 0);
    let estado = 'todos';

    /* Resumen de retos */
    function pintarResumen() {
        const hechas  = tarjetas.filter(c => c.dataset.hecho === 'true');
        const ganados = hechas.reduce((n, c) => n + Number(c.dataset.pts || 0), 0);
        if (!hechas.length) {
            resumen.textContent = `${tarjetas.length} retos disponibles · hasta ${fmt(totalPts)} EcoPuntos por semana`;
        } else if (hechas.length === tarjetas.length) {
            resumen.textContent = '¡Completaste todos los retos de la semana! 🌎';
        } else {
            resumen.textContent = `${hechas.length} de ${tarjetas.length} retos completados · te faltan ${fmt(totalPts - ganados)} EcoPuntos por ganar esta semana`;
        }
    }

    /* Filtrar retos */
    function aplicarFiltro() {
        let visibles = 0;
        tarjetas.forEach(c => {
            const hecho = c.dataset.hecho === 'true';
            const ok = estado === 'todos' || (estado === 'pendientes' && !hecho) || (estado === 'completados' && hecho);
            c.hidden = !ok;
            if (ok) visibles++;
        });
        vacio.hidden = visibles > 0;
        vacio.textContent = estado === 'completados'
            ? 'Todavía no completas ningún reto esta semana. ¡Empieza por uno fácil! 💪'
            : '¡Completaste todos los retos de la semana! 🌎';
    }

    filtros.forEach(btn => btn.addEventListener('click', () => {
        filtros.forEach(b => { b.classList.remove('activo'); b.setAttribute('aria-pressed', 'false'); });
        btn.classList.add('activo');
        btn.setAttribute('aria-pressed', 'true');
        estado = btn.dataset.estado;
        aplicarFiltro();
    }));

    pintarResumen();

    /* ---------- Perfil del usuario ---------- */
    sesion.then(s => {
        const saludo   = document.querySelector('.ecohero-profile-box h2');
        const nivel    = document.querySelector('.current-level-tag strong');
        const barra    = document.querySelector('.canva-progress-bar');
        const textoBar = document.querySelector('.canva-progress-text');
        const puntos   = document.querySelector('.points-digits h3');

        if (!s.logueado) {
            saludo.innerHTML = '¡Hola, EcoHéroe! <span class="wave-hand">👋</span>';
            nivel.textContent = 'Inicia sesión para empezar';
            barra.style.width = '0%';
            textoBar.innerHTML = `<a href="${raiz}Index.html?tab=login">Inicia sesión</a> o <a href="${raiz}Index.html?tab=registro">regístrate</a> para acumular EcoPuntos`;
            puntos.textContent = '0';
            // Los botones "Comenzar" llevan a iniciar sesión
            document.querySelectorAll('.action-complete-link-btn').forEach(a => {
                a.href = raiz + 'Index.html?tab=login';
            });
            return;
        }

        const primerNombre = escapeHTML(s.nombre.split(' ')[0]);
        saludo.innerHTML = `¡Hola, ${primerNombre}! <span class="wave-hand">👋</span>`;
        nivel.textContent = s.nivel.nombre;
        barra.style.width = s.nivel.progreso + '%';
        textoBar.textContent = s.nivel.siguiente
            ? `${s.nivel.progreso}% completado para llegar a ${s.nivel.siguiente}`
            : '¡Alcanzaste el nivel máximo! 🌎';
        puntos.textContent = fmt(s.puntos);

        // Marca los retos que ya completó esta semana
        tarjetas.forEach(card => {
            const enlace = card.querySelector('.action-complete-link-btn');
            if (!enlace) return;
            const clave = card.dataset.clave;
            if (s.retos_semana.includes(clave)) {
                // Si el administrador aún no revisa la evidencia, se muestra "En revisión"
                const enRevision = (s.retos_revision || []).includes(clave);
                card.dataset.hecho = 'true';
                card.classList.add('reto-hecho');
                const badge = card.querySelector('.active-status-badge');
                if (badge) badge.textContent = enRevision ? 'En revisión ⏳' : 'Completado ✔';
                enlace.textContent = enRevision ? 'Enviado, en revisión' : 'Hecho esta semana';
                enlace.removeAttribute('href');
                enlace.setAttribute('aria-disabled', 'true');
            }
        });
        pintarResumen();
        aplicarFiltro();
    });

    /* ---------- Tabla de posiciones ---------- */
    const cuerpo = document.getElementById('ranking-body');
    const medallas = ['🥇', '🥈', '🥉'];

    fetch(raiz + 'php/retos/ranking.php', { credentials: 'same-origin', cache: 'no-store' })
        .then(r => r.json())
        .then(lista => {
            if (!lista.length) {
                cuerpo.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:18px">Aún no hay EcoHéroes. ¡Sé el primero en completar un reto! 🌱</td></tr>';
                return;
            }
            cuerpo.innerHTML = lista.map((u, i) => {
                const avatar = u.soy_yo ? 'av-me' : 'av-' + ((i % 5) + 1);
                const inicial = u.soy_yo ? 'TÚ' : escapeHTML(u.nombre.charAt(0).toUpperCase());
                const nombre = escapeHTML(u.nombre) + (u.soy_yo ? ' (Tú)' : '');
                const clases = [i < 3 ? 'top-' + (i + 1) : '', u.soy_yo ? 'rank-me' : ''].join(' ').trim();
                const posicion = i < 3 ? `<span class="medal">${medallas[i]}</span>` : `<span class="pos-num">${i + 1}</span>`;
                return `
                <tr data-puntos="${u.puntos}" data-retos="${u.retos}"${clases ? ` class="${clases}"` : ''}>
                    <td class="rank-pos">${posicion}</td>
                    <td><div class="rank-user"><span class="rank-avatar ${avatar}">${inicial}</span><div><strong>${nombre}</strong><small>${escapeHTML(u.nivel)}</small></div></div></td>
                    <td class="col-retos">${u.retos}</td>
                    <td class="rank-points">${fmt(u.puntos)} pts</td>
                </tr>`;
            }).join('');
        })
        .catch(() => {
            cuerpo.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:18px">No se pudo cargar la tabla. Verifica que XAMPP esté encendido.</td></tr>';
        });
})();

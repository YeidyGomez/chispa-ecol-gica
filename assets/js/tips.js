/* =========================================================
   CHISPA ECOLÓGICA - TipsEcologicos.html
   - Carga los tips que envía la comunidad
   - Filtros por categoría y buscador
   ========================================================= */
(function () {
    'use strict';
    const { escapeHTML, raiz } = window.ChispaEcologica;

    const grid       = document.getElementById('cards-grid');
    const botones    = document.querySelectorAll('.filter-btn');
    const buscador   = document.getElementById('search-input');
    const formBuscar = document.querySelector('.search-wrapper');
    let filtroActual = 'all';

    const ICONOS = {
        hogar:     ['green-icon',  'fa-solid fa-house'],
        energia:   ['yellow-icon', 'fa-regular fa-lightbulb'],
        movilidad: ['red-icon',    'fa-solid fa-bicycle'],
        agua:      ['blue-icon',   'fa-solid fa-droplet'],
    };
    const TEMAS = { hogar: 'tip-tema-arboles', energia: 'tip-tema-energia', movilidad: 'tip-tema-movilidad', agua: 'tip-tema-agua' };
    const NOMBRES = { hogar: 'Hogar', energia: 'Energía', movilidad: 'Movilidad', agua: 'Agua' };

    /* ---------- Filtrar (categoría + texto) ---------- */
    function sinTildes(t) {
        return t.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    }

    /* Filtrar tips por categoría y texto */
    function aplicarFiltros() {
        const texto = sinTildes(buscador.value.trim());
        let visibles = 0;

        grid.querySelectorAll('.tip-card').forEach(card => {
            const categorias = (card.dataset.category || '').split(' ');
            const okCategoria = filtroActual === 'all' || categorias.includes(filtroActual);
            const okTexto = !texto || sinTildes(card.textContent).includes(texto);
            const visible = okCategoria && okTexto;
            card.classList.toggle('hidden', !visible);
            if (visible) visibles++;
        });

        let vacio = document.getElementById('tips-vacio');
        if (!visibles) {
            if (!vacio) {
                vacio = document.createElement('p');
                vacio.id = 'tips-vacio';
                vacio.style.cssText = 'grid-column:1/-1;text-align:center;padding:20px;color:#555';
                grid.appendChild(vacio);
            }
            vacio.innerHTML = 'No encontramos tips con ese criterio. <a href="compartirtips.html">¿Compartes uno?</a>';
        } else if (vacio) {
            vacio.remove();
        }
    }

    botones.forEach(btn => {
        btn.addEventListener('click', () => {
            botones.forEach(b => b.classList.remove('active-btn'));
            btn.classList.add('active-btn');
            filtroActual = btn.dataset.filter;
            aplicarFiltros();
        });
    });

    buscador.addEventListener('input', aplicarFiltros);

    // Enter o la lupa: búsqueda completa en la base de datos
    function buscarEnBD() {
        const q = buscador.value.trim();
        if (q) location.href = 'buscartip.html?q=' + encodeURIComponent(q);
    }
    /* Envío del buscador */
    formBuscar.addEventListener('submit', e => { e.preventDefault(); buscarEnBD(); });

    /* ---------- Tips enviados por la comunidad ---------- */
    fetch(raiz + 'php/tips/api_tips.php?origen=comunidad', { cache: 'no-store' })
        .then(r => r.json())
        .then(tips => {
            tips.forEach(t => {
                const [color, icono] = ICONOS[t.categoria] || ICONOS.hogar;
                const art = document.createElement('article');
                art.className = 'tip-card ' + (TEMAS[t.categoria] || 'tip-tema-arboles');
                art.dataset.category = t.categoria;
                art.innerHTML = `
                    <span class="tip-category-badge">${NOMBRES[t.categoria] || ''} · Comunidad</span>
                    <span class="icon-holder ${color}"><i class="${icono}"></i></span>
                    <h3>${escapeHTML(t.titulo)}</h3>
                    <p>${escapeHTML(t.explicacion)}</p>
                    <small style="color:#6b7a63">Compartido por ${escapeHTML(t.autor || 'Anónimo')}</small>
                    ${t.estado === 'pendiente' ? '<span class="badge-revision" title="Solo tú lo ves hasta que el administrador lo apruebe"><i class="fa-regular fa-clock"></i> En revisión</span>' : ''}`;
                grid.appendChild(art);
            });
            aplicarFiltros();
        })
        .catch(() => { /* sin servidor: se ven solo los tips fijos */ });
})();

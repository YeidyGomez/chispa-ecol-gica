/* =========================================================
   CHISPA ECOLÓGICA -
   Busca en la BD el texto recibido 
   ========================================================= */
(function () {
    'use strict';
    const { escapeHTML, raiz } = window.ChispaEcologica;

    const q = new URLSearchParams(location.search).get('q') || '';
    const contenedor = document.getElementById('resultados-busqueda');
    const descripcion = document.querySelector('.parrafo-descripcion-tip');
    const input = document.getElementById('buscar-otra');

    const ICONOS = {
        hogar: 'fa-solid fa-house', energia: 'fa-solid fa-lightbulb',
        movilidad: 'fa-solid fa-bicycle', agua: 'fa-solid fa-droplet',
    };

    if (input) input.value = q;

    if (!q) {
        descripcion.textContent = 'Escribe una palabra para buscar entre todos los tips.';
        return;
    }

    descripcion.innerHTML = `Resultados para: <strong>"${escapeHTML(q)}"</strong>`;

    fetch(raiz + 'php/tips/api_tips.php?q=' + encodeURIComponent(q), { cache: 'no-store' })
        .then(r => r.json())
        .then(tips => {
            if (!tips.length) {
                contenedor.innerHTML = '<p class="texto-resultado-item">No encontramos tips con esa palabra. Prueba con "agua", "energía" o "bicicleta".</p>';
                return;
            }
            contenedor.innerHTML = tips.map(t => `
                <div class="tarjeta-resultado-busqueda">
                    <h4 class="titulo-resultado-item"><i class="${ICONOS[t.categoria] || 'fa-solid fa-leaf'} icono-azul-resultado"></i> ${escapeHTML(t.titulo)}${t.estado === 'pendiente' ? ' <span class="badge-revision"><i class="fa-regular fa-clock"></i> En revisión</span>' : ''}</h4>
                    <p class="texto-resultado-item">${escapeHTML(t.explicacion)}</p>
                </div>`).join('');
        })
        .catch(() => {
            contenedor.innerHTML = '<p class="texto-resultado-item">No se pudo conectar con el servidor.</p>';
        });
})();

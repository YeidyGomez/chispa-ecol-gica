/* =========================================================
   CHISPA ECOLÓGICA - Script común para TODAS las páginas
   ========================================================= */
(function () {
    'use strict';

    /* Raíz del sitio así los enlaces y llamadas a PHP funcionan desde páginas en cualquier carpeta. */
    const scriptActual = document.currentScript || document.querySelector('script[src$="assets/js/main.js"]');
    const RAIZ = new URL('../../', scriptActual.src).href;

    /* ---------- Utilidades compartidas ---------- */
    function escapeHTML(texto) {
        return String(texto ?? '').replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }

    /* Mensajes emergentes */
    function toast(mensaje, tipo = 'ok') {
        let caja = document.getElementById('es-toast');
        if (!caja) {
            caja = document.createElement('div');
            caja.id = 'es-toast';
            document.body.appendChild(caja);
        }
        caja.textContent = mensaje;
        caja.className = 'es-toast-' + tipo + ' es-toast-visible';
        clearTimeout(caja._t);
        caja._t = setTimeout(() => caja.classList.remove('es-toast-visible'), 4500);
    }

    // Estilos del toast y del usuario en el menú (así no dependen de Style.css)
    const css = document.createElement('style');
    css.textContent = `
        #es-toast{position:fixed;left:50%;bottom:28px;transform:translate(-50%,30px);opacity:0;
            padding:14px 22px;border-radius:14px;color:#fff;font:600 14px Poppins,sans-serif;
            box-shadow:0 8px 24px rgba(0,0,0,.18);z-index:9999;transition:.35s;pointer-events:none;
            max-width:90vw;text-align:center}
        #es-toast.es-toast-visible{opacity:1;transform:translate(-50%,0)}
        .es-toast-ok{background:#4d7736}.es-toast-error{background:#b3412f}
        .nav-links .nav-user-pts{font-size:.8em;opacity:.85;margin-left:4px}
    `;
    document.head.appendChild(css);

    /* ---------- Mensajes que llegan por la URL (?msg= / ?error=) ---------- */
    const MENSAJES = {
        registro_ok: '¡Cuenta creada! Bienvenido a Chispa Ecológica 🌿',
        bienvenido: '¡Hola de nuevo! 🍃',
        sesion_cerrada: 'Sesión cerrada correctamente.',
        reto_ok: '¡Reto completado! Sumaste EcoPuntos 🏆',
        tip_ok: '¡Gracias! Tu tip ya está publicado 🌱',
        tip_pendiente: '¡Gracias! Tu tip se publicará cuando el administrador lo revise y apruebe 🌱',
        reto_pendiente: '¡Evidencia enviada! Sumarás tus EcoPuntos cuando el administrador la apruebe 🏆',
    };
    const ERRORES = {
        campos_vacios: 'Completa todos los campos.',
        correo_invalido: 'El correo no es válido.',
        clave_corta: 'La contraseña debe tener mínimo 6 caracteres.',
        correo_duplicado: 'Ese correo ya está registrado. Inicia sesión.',
        credenciales_incorrectas: 'Correo o contraseña incorrectos.',
        necesitas_sesion: 'Inicia sesión para participar en los retos.',
        reto_repetido: 'Ya completaste este reto esta semana. ¡Vuelve en unos días!',
        reto_invalido: 'Ese reto no existe.',
        descripcion_corta: 'Cuéntanos un poco más (mínimo 10 caracteres).',
        imagen_invalida: 'Sube una imagen válida (JPG, PNG, GIF o WEBP de máximo 5 MB).',
        imagen_grande: 'La imagen es demasiado grande (máximo 5 MB).',
        error_servidor: 'Ocurrió un error en el servidor. Intenta de nuevo.',
        perfil_invalido: 'Elige tu perfil: estudiante o usuario externo.',
        grado_invalido: 'Elige tu grado (de sexto a undécimo).',
        acepto_requerido: 'Debes aceptar la Política de Privacidad para crear tu cuenta.',
        contenido_ofensivo: 'Tu mensaje contiene lenguaje inapropiado u ofensivo. Por favor corrígelo e intenta de nuevo.',
        contenido_contacto: 'Por seguridad no se permiten enlaces, correos ni números de teléfono. Quítalos e intenta de nuevo.',
        admin_no_participa: 'El administrador gestiona la plataforma y no participa en los retos.',
    };

    const params = new URLSearchParams(location.search);
    const msg = params.get('msg'), error = params.get('error'), pts = params.get('pts');
    if (msg) {
        let texto = MENSAJES[msg] || 'Listo.';
        if (pts && msg === 'reto_ok') texto = texto.replace('EcoPuntos', '+' + pts + ' EcoPuntos');
        setTimeout(() => toast(texto, 'ok'), 300);
    }
    if (error) {
        setTimeout(() => toast(ERRORES[error] || 'Ocurrió un error.', 'error'), 300);
    }
    // Limpia la URL para que el mensaje no se repita al recargar
    if (params.has('msg') || params.has('error')) {
        ['msg', 'error', 'pts'].forEach(p => params.delete(p));
        const resto = params.toString();
        history.replaceState(null, '', location.pathname + (resto ? '?' + resto : ''));
    }

    /* ---------- Menú hamburguesa ---------- */
    document.querySelectorAll('.menu-toggle').forEach(btn => {
        btn.addEventListener('click', () => {
            const lista = btn.parentElement.querySelector('.nav-links');
            if (!lista) return;
            const abierto = lista.classList.toggle('active');
            lista.classList.toggle('show', abierto);
            btn.setAttribute('aria-expanded', abierto ? 'true' : 'false');
        });
    });

    /* ---------- Index.html: abrir el modal en login / registro ---------- */
    const modalToggle = document.getElementById('modalToggle');
    const tab = new URLSearchParams(location.search).get('tab');
    if (modalToggle && tab) {
        modalToggle.checked = true;
        const radio = document.getElementById(tab === 'registro' ? 'tabRegister' : 'tabLogin');
        if (radio) radio.checked = true;
        history.replaceState(null, '', location.pathname);
    }

    /* ---------- Sesión: pregunta al servidor quién está conectado ---------- */
    const sesion = fetch(RAIZ + 'php/auth/sesion.php', { credentials: 'same-origin', cache: 'no-store' })
        .then(r => r.ok ? r.json() : { logueado: false })
        .catch(() => {
            console.warn('Chispa Ecológica: no se pudo contactar sesion.php. ¿Abriste el sitio desde http://localhost/ ?');
            return { logueado: false, sinServidor: true };
        });

    sesion.then(s => {
        if (s.sinServidor && location.protocol === 'file:') {
            toast('Abre el sitio desde http://localhost/chispa-ecologica/ (con XAMPP), no con doble clic.', 'error');
        }

        document.querySelectorAll('.nav-links').forEach(lista => {
            lista.querySelectorAll('.nav-sesion').forEach(li => li.remove());
            if (s.logueado) {
                const primerNombre = escapeHTML(s.nombre.split(' ')[0]);
                // Administrador: enlace a su panel; demás perfiles: enlace a sus retos
                const enlacePerfil = s.rol === 'admin'
                    ? `<li class="nav-sesion"><a href="${RAIZ}paginas/admin/PanelAdmin.html" title="Panel de administración">🛡️ Panel</a></li>`
                    : `<li class="nav-sesion"><a href="${RAIZ}paginas/retos/RetosEcologicos.html" title="Mis retos">🌿 ${primerNombre}<span class="nav-user-pts">(${s.puntos} pts)</span></a></li>`;
                lista.insertAdjacentHTML('beforeend',
                    `${enlacePerfil}
                     <li class="nav-sesion"><a href="${RAIZ}php/auth/auth.php?action=logout">Cerrar sesión</a></li>`);
            } else {
                lista.insertAdjacentHTML('beforeend',
                    `<li class="nav-sesion"><a href="${RAIZ}Index.html?tab=login">Ingresar</a></li>`);
            }
        });

        // En Index: si ya inició sesión, "EMPEZAR AHORA" lleva a los retos (o al panel si es administrador)
        if (s.logueado && modalToggle) {
            const btn = document.querySelector('label.btn[for="modalToggle"]');
            if (btn) {
                const a = document.createElement('a');
                a.href = RAIZ + (s.rol === 'admin' ? 'paginas/admin/PanelAdmin.html' : 'paginas/retos/RetosEcologicos.html');
                a.className = btn.className;
                a.textContent = s.rol === 'admin' ? 'IR AL PANEL' : 'IR A MIS RETOS';
                btn.replaceWith(a);
            }
        }
    });

    /* ---------- Registro: el grado solo aparece para estudiantes ---------- */
    const perfiles = document.querySelectorAll('input[name="regPerfil"]');
    const grupoGrado = document.getElementById('grupoGrado');
    if (perfiles.length && grupoGrado) {
        const selectGrado = document.getElementById('regGrado');
        const actualizarGrado = () => {
            const esEstudiante = document.querySelector('input[name="regPerfil"]:checked')?.value === 'estudiante';
            grupoGrado.hidden = !esEstudiante;
            selectGrado.required = esEstudiante;
            if (!esEstudiante) selectGrado.value = '';
        };
        perfiles.forEach(p => p.addEventListener('change', actualizarGrado));
        actualizarGrado();
    }

    /* ---------- Carrusel de "Quiénes Somos" ---------- */
    const carrusel = document.querySelector('.contenedor-carrusel');
    if (carrusel) {
        const cards = Array.from(carrusel.querySelectorAll('.card'));
        const dots = document.querySelector('.slider-dots');
        let indice = 0;

        /* Ir a un integrante */
        function irA(i) {
            indice = (i + cards.length) % cards.length;
            const card = cards[indice];
            carrusel.scrollTo({ left: card.offsetLeft - carrusel.offsetLeft, behavior: 'smooth' });
            cards.forEach((c, n) => c.classList.toggle('active', n === indice));
            if (dots) dots.querySelectorAll('button').forEach((d, n) => d.classList.toggle('active', n === indice));
        }

        if (dots) {
            cards.forEach((_, n) => {
                const d = document.createElement('button');
                d.className = 'dot';
                d.setAttribute('aria-label', 'Ver integrante ' + (n + 1));
                d.addEventListener('click', () => irA(n));
                dots.appendChild(d);
            });
        }
        const izq = document.querySelector('.flecha.izquierda');
        const der = document.querySelector('.flecha.derecha');
        if (izq) izq.addEventListener('click', () => irA(indice - 1));
        if (der) der.addEventListener('click', () => irA(indice + 1));
        if (cards.length) irA(0);
    }

    /* ---------- Exporta utilidades para los demás scripts ---------- */
    window.ChispaEcologica = { sesion, toast, escapeHTML, raiz: RAIZ };
})();

/* =========================================================
   CHISPA ECOLÓGICA - recicla.html, agua.html y naturaleza.html
   - Exige sesión iniciada
   - Vista previa de la imagen de evidencia
   - Validación antes de enviar a guardar_reto.php
   ========================================================= */
(function () {
    'use strict';
    const { sesion, toast, raiz } = window.ChispaEcologica;

    const form = document.querySelector('.formulario-evidencia');
    if (!form) return;
    const clave = form.querySelector('input[name="reto"]').value;

    // Si no hay sesión, lo manda a iniciar sesión
    sesion.then(s => {
        if (s.sinServidor) return;
        if (!s.logueado) {
            location.href = raiz + 'Index.html?error=necesitas_sesion&tab=login';
        } else if (s.retos_semana.includes(clave)) {
            toast('Ya completaste este reto esta semana. ¡Vuelve en unos días!', 'error');
            const btn = form.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.style.opacity = '.6';
        }
    });

    // Aviso: la evidencia la revisa el administrador antes de dar los EcoPuntos
    const botonEnviar = form.querySelector('button[type="submit"]');
    if (botonEnviar) {
        botonEnviar.insertAdjacentHTML('beforebegin',
            '<p class="aviso-revision"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Tu evidencia será revisada por el administrador. Los EcoPuntos se suman cuando la apruebe. No incluyas datos personales ni lenguaje ofensivo.</p>');
    }

    // Vista previa de la imagen
    const inputImg = form.querySelector('input[type="file"]');
    const caja     = document.getElementById('preview-' + clave);
    const img      = document.getElementById('img-prev-' + clave);

    inputImg.addEventListener('change', () => {
        const archivo = inputImg.files[0];
        if (!archivo) { caja.style.display = 'none'; return; }

        if (!archivo.type.startsWith('image/')) {
            toast('El archivo debe ser una imagen.', 'error');
            inputImg.value = '';
            caja.style.display = 'none';
            return;
        }
        if (archivo.size > 5 * 1024 * 1024) {
            toast('La imagen pesa más de 5 MB. Elige una más liviana.', 'error');
            inputImg.value = '';
            caja.style.display = 'none';
            return;
        }
        img.src = URL.createObjectURL(archivo);
        caja.style.display = 'block';
    });

    // Validación final
    form.addEventListener('submit', e => {
        const texto = form.querySelector('textarea').value.trim();
        if (texto.length < 10) {
            e.preventDefault();
            toast('Cuéntanos un poco más (mínimo 10 caracteres).', 'error');
            return;
        }
        const btn = form.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.textContent = 'Enviando...';
    });
})();

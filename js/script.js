// ==========================================================================
// MENÚ HAMBURGUESA (RESPONSIVE)
// ==========================================================================

const btnHamburguesa = document.getElementById('btn-hamburguesa');
const navLinks = document.querySelector('.nav-links');

if (btnHamburguesa && navLinks) {
    btnHamburguesa.addEventListener('click', function() {
        navLinks.classList.toggle('menu-abierto');
    });
}

// ==========================================================================
// CARRUSEL DE SERVICIOS
// ==========================================================================

let slideActual = 0;
const slides = document.querySelectorAll('.slide');
const totalSliders = slides.length;
const indicadores = document.querySelectorAll('.indicador');
const btnAnterior = document.getElementById('btn-anterior');
const btnSiguiente = document.getElementById('btn-siguiente');

// Función para mostrar un slide específico
function mostrarSlide(indice) {
    if (!slides.length) return; // páginas sin carrusel
    // Calcular cuántas imágenes se ven según el ancho de pantalla
    let imagenesPorSlide = 3;
    if (window.innerWidth <= 768) {
        imagenesPorSlide = 1;
    } else if (window.innerWidth <= 1024) {
        imagenesPorSlide = 2;
    }

    // Ajustar el índice si se sale de rango
    const maxIndice = totalSliders - imagenesPorSlide;
    if (indice < 0) {
        slideActual = maxIndice;
    } else if (indice > maxIndice) {
        slideActual = 0;
    } else {
        slideActual = indice;
    }

    // Mover los slides
    const contenedor = document.getElementById('contenedor-slides');
    const anchoSlide = slides[0].offsetWidth;
    contenedor.style.transform = `translateX(-${slideActual * anchoSlide}px)`;

    // Actualizar indicadores
    actualizarIndicadores();
}

// Función para actualizar los indicadores (puntos)
function actualizarIndicadores() {
    indicadores.forEach((indicador, index) => {
        if (index === slideActual) {
            indicador.classList.add('activo');
        } else {
            indicador.classList.remove('activo');
        }
    });
}

// Botón siguiente
if (btnSiguiente) {
    btnSiguiente.addEventListener('click', function() {
        mostrarSlide(slideActual + 1);
    });
}

// Botón anterior
if (btnAnterior) {
    btnAnterior.addEventListener('click', function() {
        mostrarSlide(slideActual - 1);
    });
}

// Autoplay cada 4 segundos
let autoplay = setInterval(function() {
    mostrarSlide(slideActual + 1);
}, 4000);

// Pausar autoplay al pasar el cursor
const carrusel = document.getElementById('carrusel');
if (carrusel) {
    carrusel.addEventListener('mouseenter', function() {
        clearInterval(autoplay);
    });

    carrusel.addEventListener('mouseleave', function() {
        autoplay = setInterval(function() {
            mostrarSlide(slideActual + 1);
        }, 4000);
    });
}

// Recalcular al redimensionar la ventana
window.addEventListener('resize', function() {
    mostrarSlide(slideActual);
});

// ==========================================================================
// CONTROL DE SESIÓN (SESIÓN PHP)
// Pregunta a sesion.php si hay sesión y ajusta la navbar
// ==========================================================================

document.addEventListener('DOMContentLoaded', function() {
    const liLogin = document.getElementById('li-login');
    const navLinks = document.querySelector('.nav-links');
    if (!liLogin || !navLinks) return;

    fetch('sesion.php', { credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.activa) return;

            liLogin.style.display = 'none';

            const liPanel = document.createElement('li');
            liPanel.innerHTML = '<a href="' + data.panel + '">Mi panel</a>';
            navLinks.appendChild(liPanel);

            const liLogout = document.createElement('li');
            liLogout.id = 'li-logout';
            liLogout.innerHTML = '<a href="logout.php">Cerrar Sesión</a>';
            navLinks.appendChild(liLogout);
        })
        .catch(function() { /* sin servidor PHP: se deja el botón Login */ });
});

// ==========================================================================
// MANEJO DE FORMULARIOS (FORMSPREE SILENCIOSO)
// ==========================================================================

document.addEventListener('DOMContentLoaded', function() {
    // 1. Detectar si venimos de un envío exitoso (?enviado=true)
    const parametrosURL = new URLSearchParams(window.location.search);
    if (parametrosURL.get('enviado') === 'true') {
        mostrarMensajeExito();
        limpiarFormularios();
        // Quitar el ?enviado=true de la URL
        window.history.replaceState({}, document.title, window.location.pathname);
        return; // Salimos aquí para no agregar el listener de submit
    }

    // 2. Configurar envío AJAX para los formularios
    document.querySelectorAll('form[action*="formspree"]').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault(); // Evita que la página se recargue

            const url = form.getAttribute('action');
            const formData = new FormData(form);

            // Enviar datos
            fetch(url, {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json'
                }
            })
            .then(response => {
                if (response.ok) {
                    mostrarMensajeExito();
                    limpiarFormularios();
                } else {
                    response.json().then(data => {
                        if (Object.hasOwn(data, 'errors')) {
                            alert(data["errors"].map(error => error["message"]).join(", "));
                        } else {
                            alert("Hubo un error al enviar el formulario.");
                        }
                    });
                }
            })
            .catch(error => {
                alert("Hubo un error al enviar el formulario.");
            });
        });
    });

    // Funciones auxiliares
    function mostrarMensajeExito() {
        const mensajes = document.querySelectorAll('.mensaje-exito');
        mensajes.forEach(function(mensaje) {
            mensaje.classList.add('visible');
        });
    }

    function limpiarFormularios() {
        document.querySelectorAll('form').forEach(function(form) {
            form.reset();
        });
    }
});
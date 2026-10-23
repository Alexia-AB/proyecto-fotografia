// ==========================================================================
// SISTEMA DE LOGIN
// ==========================================================================

// Credenciales
const USUARIO_CORRECTO = 'admin';
const CONTRASENA_CORRECTA = 'dain2026';

// Elementos del modal
const modal = document.getElementById('login-modal');
const btnLogin = document.getElementById('btn-login');
const btnCerrar = document.getElementById('btn-cerrar-modal');
const overlay = document.getElementById('modal-overlay');
const formulario = document.getElementById('form-login');
const mensajeError = document.getElementById('mensaje-error');

// Abrir modal al hacer click en "Login"
if (btnLogin) {
    btnLogin.addEventListener('click', function(e) {
        e.preventDefault();
        if (modal) {
            modal.style.display = 'flex';
        }
    });
}

// Cerrar modal con botón X
if (btnCerrar) {
    btnCerrar.addEventListener('click', function() {
        modal.style.display = 'none';
        formulario.reset();
        mensajeError.style.display = 'none';
    });
}

// Cerrar modal al hacer click fuera
if (overlay) {
    overlay.addEventListener('click', function() {
        modal.style.display = 'none';
        formulario.reset();
        mensajeError.style.display = 'none';
    });
}

// Cerrar modal con tecla Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && modal && modal.style.display === 'flex') {
        modal.style.display = 'none';
        formulario.reset();
        mensajeError.style.display = 'none';
    }
});

// Validar credenciales al enviar el formulario
if (formulario) {
    formulario.addEventListener('submit', function(e) {
        e.preventDefault();

        const usuario = document.getElementById('input-usuario').value;
        const contrasena = document.getElementById('input-contrasena').value;

        if (usuario === USUARIO_CORRECTO && contrasena === CONTRASENA_CORRECTA) {
            // Login correcto: guardar sesión y redirigir
            localStorage.setItem('sesion', 'activa');

            // Ocultar login y mostrar cerrar sesión en la navbar
            const navLinks = document.querySelector('.nav-links');
            if (navLinks) {
                const btnLoginLi = document.getElementById('li-login');
                if (btnLoginLi) {
                    btnLoginLi.style.display = 'none';
                }
                // Agregar botón cerrar sesión si no existe
                if (!document.getElementById('li-logout')) {
                    const btnLogout = document.createElement('li');
                    btnLogout.id = 'li-logout';
                    btnLogout.innerHTML = '<a href="#" id="btn-logout">Cerrar Sesión</a>';
                    btnLogout.addEventListener('click', function(e) {
                        e.preventDefault();
                        localStorage.removeItem('sesion');
                        location.reload();
                    });
                    navLinks.appendChild(btnLogout);
                }
            }

            // Redirigir a bienvenida
            window.location.href = 'bienvenida.html';
        } else {
            // Login incorrecto: mostrar error
            mensajeError.style.display = 'block';
        }
    });
}

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
// CONTROL DE SESIÓN AL CARGAR LA PÁGINA
// ==========================================================================

document.addEventListener('DOMContentLoaded', function() {
    const sesion = localStorage.getItem('sesion');

    if (sesion === 'activa') {
        // Ocultar botón Login
        const btnLoginLi = document.getElementById('li-login');
        if (btnLoginLi) {
            btnLoginLi.style.display = 'none';
        }

        // Agregar botón Cerrar Sesión si no existe
        if (!document.getElementById('li-logout')) {
            const navLinks = document.querySelector('.nav-links');
            if (navLinks) {
                const btnLogout = document.createElement('li');
                btnLogout.id = 'li-logout';
                btnLogout.innerHTML = '<a href="#" id="btn-logout">Cerrar Sesión</a>';
                btnLogout.addEventListener('click', function(e) {
                    e.preventDefault();
                    localStorage.removeItem('sesion');
                    location.reload();
                });
                navLinks.appendChild(btnLogout);
            }
        }
    }
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
    document.querySelectorAll('form').forEach(function(form) {
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
# DAIN Studio — Sitio web de estudio fotográfico

**Ingeniería de Software II** · BUAP · Docente: Roberto Elvira Enriquez
**Integrantes:** Alexia Dayan Alvarez Badillo · Ingrid Andree González López

Sitio web para un estudio de fotografía y video profesional en Puebla. Presenta los servicios del estudio y,
con una cuenta, permite a los clientes **solicitar servicios** y al administrador **gestionarlos**.

## Qué puede hacer cada tipo de usuario

| Rol | Funciones |
|---|---|
| **Visitante** | Ver el sitio (inicio, comercial, retrato, proyectos, nosotros), enviar el formulario de contacto, registrarse e iniciar sesión. |
| **Cliente** (`USU_GRAL`, rol 4) | Ver su ficha y editar sus datos, cambiar su contraseña, ver la galería de servicios, seleccionar uno o varios y **solicitarlos**, y consultar, modificar o cancelar sus solicitudes mientras estén en estatus SOLICITADO. |
| **Administrador** (`ADMIN`) | Ver y editar su ficha, **gestionar clientes** (activar, desactivar y editar sus datos), **gestionar servicios** (alta, modificación, eliminación, publicar u ocultar) y **gestionar solicitudes** (cambiar el estatus a EN PROCESO o FINALIZADO). |

Un cliente **desactivado** no puede iniciar sesión (su información se conserva). Un servicio que ya fue solicitado
no se elimina: se oculta.

## Tecnologías

HTML5 · CSS3 · JavaScript · PHP 8 (PDO) · MySQL/MariaDB · XAMPP · Git y GitHub

## Instalación en XAMPP

1. Instala [XAMPP](https://www.apachefriends.org/) y abre su panel de control.
2. Copia esta carpeta dentro de `C:\xampp\htdocs\` (debe quedar `C:\xampp\htdocs\proyectoFotografia`).
3. Inicia **Apache** y **MySQL** desde el panel de XAMPP.
4. Entra a `http://localhost/phpmyadmin`, abre la pestaña **Importar**, elige `sql/dain_studio.sql` y presiona **Importar**.
   Esto crea la base de datos `dain_studio` con sus tablas y datos de ejemplo.
   > El script **borra y vuelve a crear** las tablas: no lo importes sobre datos que quieras conservar.
5. Abre `http://localhost/proyectoFotografia/index.html`.

Si tu MySQL tiene otro usuario o contraseña, ajústalos en `includes/conexion.php`
(por defecto XAMPP usa `root` sin contraseña).

## Usuarios de prueba

| Usuario | Contraseña | Rol |
|---|---|---|
| `admin` | `dain2026` | Administrador |
| `cliente` | `cliente2026` | Cliente |

También se puede crear un cliente nuevo desde **Login → Regístrate aquí** (`registro.php`).

## Estructura del proyecto

```
proyectoFotografia/
├── index.html, comercial.html, retrato.html,
│   proyectos.html, nosotros.html, contacto.html     Páginas públicas
├── login.php, logout.php, registro.php, sesion.php  Acceso y sesión
├── panel_cliente.php, cambiar_password.php          Cliente: ficha y contraseña
├── galeria_servicios.php, mis_solicitudes.php       Cliente: solicitar y dar seguimiento
├── panel_admin.php                                  Administrador: ficha
├── gestion_clientes.php                             Administrador: clientes
├── servicios_admin.php                              Administrador: servicios
├── gestion_solicitudes.php                          Administrador: solicitudes
├── css/       styles, gestion, servicios, galeria, solicitudes
├── js/        script.js (menú, carrusel, sesión), galeria.js (selección de servicios)
├── includes/  conexion.php (conexión a MySQL con PDO)
├── sql/       dain_studio.sql (base de datos completa)
├── videos/    video-comercial.mp4
└── Documentaciones/   Sprint Plannings, Daily Scrums y manual
```

## Base de datos (`dain_studio`)

| Tabla | Contenido |
|---|---|
| `tb_usuarios` | Usuario, contraseña (hash), rol (`ADMIN`, `PROGRAMADOR`, `USUARIA_TEST`, `USU_GRAL`) y estado `activo`. |
| `tb_detalle_usuarios` | Datos generales: nombre, correo, teléfono y fecha de registro. |
| `tb_clientes` | Datos específicos del cliente, incluida la dirección. |
| `tb_servicios` | Catálogo: nombre, descripción, cantidad, precio y si está publicado (`visible`). |
| `tb_solicitudes` | Solicitudes: cliente, servicio, cantidad, fecha y estatus (`SOLICITADO`, `EN_PROCESO`, `FINALIZADO`). |

## Seguridad

- Contraseñas guardadas con `password_hash()` y validadas con `password_verify()`.
- Consultas preparadas (PDO) en todo el acceso a la base de datos.
- Token CSRF en los formularios y `session_regenerate_id()` al iniciar sesión y al cambiar la contraseña.
- Cada página PHP valida la sesión y el rol; las cuentas desactivadas pierden el acceso.
- Las acciones del cliente sobre sus solicitudes se validan en el servidor (pertenencia y estatus).

## Sprints

| Sprint | Objetivo |
|---|---|
| 1 | Sitio web responsivo, carrusel de servicios y login estático. |
| 2 | Base de datos MySQL, login con sesiones PHP, registro y paneles de administrador y cliente. |
| 3 | Gestión de clientes (activar y desactivar) y de servicios (alta, edición, eliminación y catálogo). |
| 4 | Galería de servicios, solicitud de servicios, Mis solicitudes y cambio de contraseña. |
| 5 | Gestión de solicitudes del administrador, pruebas, seguridad y documentación final. |

La planeación de cada sprint está en la carpeta `Documentaciones/`.
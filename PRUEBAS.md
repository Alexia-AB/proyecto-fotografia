# Checklist de pruebas — DAIN Studio (Sprint 5)

Marca cada casilla al probarla y anota el problema si falla. Base limpia: importar `sql/dain_studio.sql`.
Usuarios: `admin / dain2026` y `cliente / cliente2026`.

## 1. Visitante (sin sesión)
- [ ] Las 6 páginas públicas cargan con estilos, sin errores en la consola (F12).
- [ ] El menú marca la página actual y el carrusel de inicio funciona.
- [ ] El formulario de contacto envía y muestra el mensaje de éxito.
- [ ] El video de `comercial.html` se reproduce.
- [ ] Registro: un usuario nuevo se crea y `login.php?registro=ok` muestra el aviso.
- [ ] Registro: usuario repetido, correo repetido y contraseña corta muestran error.
- [ ] Login con datos incorrectos muestra "Usuario o contraseña incorrectos".

## 2. Cliente
- [ ] Login → llega a `panel_cliente.php` y el menú cambia a "Mi panel / Cerrar Sesión".
- [ ] Editar datos: guardar con datos válidos; correo repetido y teléfono inválido muestran error.
- [ ] Galería: solo aparecen servicios publicados.
- [ ] Seleccionar dos servicios cambia el resumen y el total; la **×** quita un servicio.
- [ ] Solicitar guarda una fila por servicio con estatus SOLICITADO y lleva a Mis solicitudes con mensaje.
- [ ] Mis solicitudes: solo se ven las propias.
- [ ] Modificar la cantidad (1 a 99) y cancelar una solicitud SOLICITADA funcionan.
- [ ] Una solicitud EN PROCESO o FINALIZADA ya no se puede modificar ni cancelar.
- [ ] Cambiar contraseña: la actual incorrecta falla; con la correcta se guarda y se entra con la nueva.
- [ ] Cerrar sesión regresa al inicio.

## 3. Administrador
- [ ] Login → `panel_admin.php`, editar sus datos.
- [ ] Clientes: la lista muestra a todos; desactivar y activar funcionan; editar respeta los campos de solo lectura.
- [ ] Un cliente desactivado no puede iniciar sesión (mensaje claro); si ya estaba dentro, lo sacan.
- [ ] Servicios: alta, edición, ocultar y publicar. Precio fuera de $1 a $999,999.99 y cantidad fuera de 1 a 999 dan error.
- [ ] Eliminar un servicio sin solicitudes funciona; con solicitudes no se deja y se sugiere ocultarlo.
- [ ] Solicitudes: la tabla muestra todas, el filtro y los contadores funcionan.
- [ ] Cambiar una solicitud a EN PROCESO y FINALIZADO; no se puede retroceder.
- [ ] El cliente ve el nuevo estatus en Mis solicitudes.

## 4. Seguridad por URL (probar escribiendo la dirección)

| Página | Sin sesión | Como cliente | Como admin |
|---|---|---|---|
| `panel_admin.php` | → login | → inicio | entra |
| `gestion_clientes.php` | → login | → inicio | entra |
| `servicios_admin.php` | → login | → inicio | entra |
| `gestion_solicitudes.php` | → login | → inicio | entra |
| `panel_cliente.php` | → login | entra | → inicio |
| `galeria_servicios.php` | → login | entra | → inicio |
| `mis_solicitudes.php` | → login | entra | → inicio |
| `cambiar_password.php` | → login | entra | → inicio |

- [ ] Cada fila de la tabla se comporta como indica.
- [ ] `login.php` y `registro.php` con sesión abierta mandan al panel correspondiente.

## 5. Celular y navegadores
- [ ] El menú hamburguesa abre y cierra.
- [ ] Tablas (clientes, solicitudes) se ven bien en pantalla chica.
- [ ] La galería con el resumen se apila en celular y el botón Solicitar es alcanzable.
- [ ] Se ve bien en Chrome/Brave y en Firefox o Edge.

## 6. Base de datos
- [ ] Importar `sql/dain_studio.sql` en una base limpia termina sin errores.
- [ ] Tras registrar un cliente hay una fila en `tb_usuarios`, `tb_detalle_usuarios` y `tb_clientes`.
- [ ] Las contraseñas están como hash, no en texto plano.

## Errores encontrados

| # | Dónde | Qué pasó | Quién lo corrige | Corregido |
|---|---|---|---|---|
| 1 | | | | |
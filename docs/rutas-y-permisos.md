# Rutas, permisos y contratos internos

Fuentes: routes/web.php, bootstrap/app.php y `php artisan route:list --json --except-vendor`. Todas las mutaciones web usan sesión y CSRF. No existe API pública con tokens ni routes/api.php operativo. No presentar estos endpoints como un contrato estable para terceros.

## Inventario de rutas

| Método | Ruta | Nombre | Controlador/acción | Middleware |
| --- | --- | --- | --- | --- |
| GET / POST / HEAD | `/` | — | `LegacyController` | web, limits |
| GET / HEAD | `/activate/{ticket}` | — | `ActivationController@show` | web |
| POST | `/activate/{ticket}` | — | `ActivationController@activate` | web, throttle:10,10 |
| GET / HEAD | `/login` | login | `AuthController@form` | web |
| POST | `/login` | — | `AuthController@login` | web |
| POST | `/logout` | — | `AuthController@logout` | web |
| GET / HEAD | `/national` | — | `NationalController@index` | web, limits, superadmin |
| POST | `/scope` | — | `NationalController@scope` | web, limits, superadmin |
| GET / HEAD | `/security` | — | `Closure` | web, limits |
| POST | `/security/revoke` | — | `AuthController@revoke` | web, limits, throttle:5,5 |
| POST | `/session/activity` | — | `AuthController@activity` | web, limits |
| GET / HEAD | `/session/status` | — | `AuthController@status` | web, limits |
| GET / HEAD | `/users` | users.index | `UsersController@index` | web, limits, superadmin |
| POST | `/users` | — | `UsersController@store` | web, limits, superadmin |
| POST / PUT / PATCH | `/users/{id}` | users.update | `UsersController@update` | web, limits, superadmin |
| POST | `/users/{id}/invite` | — | `UsersController@invite` | web, limits, superadmin, throttle:10,1 |

`/up` es la ruta de salud del framework; no demuestra que SMTP, mapas, copias o todos los flujos de base estén funcionando. `web` incorpora sesión/CSRF y SecureResponse. `limits` exige login, credencial vigente e intervalos de sesión. `superadmin` exige el rol nacional.

## Subvistas del adaptador `/`

| vista / parámetro | Método | Operación |
| --- | --- | --- |
| sin vista | GET | Dashboard provincial; nacional sin ámbito se redirige. |
| atenciones | GET | Listado provincial; nacional redirige al listado nacional. |
| nueva | GET / POST | Crear ficha; accion=guardar/cerrar. |
| id=ID, sin vista especial | GET / POST | Abrir ficha y guardar/corregir; version controla concurrencia. |
| eliminar&id=ID | POST | Borrado lógico con CSRF, provincia y versión. GET conserva vista de confirmación de compatibilidad. |
| imprimir&id=ID | GET | Hoja HTML imprimible, no binario PDF. |
| recursos, id opcional | GET / POST | Listar, crear o editar recurso provincial. |
| perfil | GET / POST | Nombre, avatar y contraseña propia. |
| avatar | GET | Binario de avatar propio o 404. |
| operadores | GET | Técnico: 403; nacional: redirección a /users. |
| cie&q=texto&pagina=N | GET | JSON de catálogo: items y total, páginas de 50. |
| direccion | POST | lat, lon y CSRF; JSON direccion, puede ser cadena vacía. |
| exportar | GET | Descarga XLSX de cerradas. |
| geojson | GET | FeatureCollection de cerradas con coordenadas. |

## Matriz de autorización

| Acción | Técnico provincial | Superadministrador |
| --- | --- | --- |
| Dashboard | Su provincia | Todas las provincias y filtro |
| Leer/corregir fichas | Todas las de su provincia | Todas las provincias |
| Eliminar fichas | Cualquier ficha propia de su provincia, con auditoría | Nacional, con auditoría |
| Crear ficha/gestionar recursos | Su provincia | Provincia seleccionada |
| Crear/editar/suspender usuarios | No | Sí; no desactivar ni degradar su propia cuenta |
| Perfil/clave/avatar | Propios | Propios |
| Exportar/imprimir | Su provincia | Ámbito provincial/registro autorizado |
| Revocar sesiones propias | Sí | Sí |

El ID de una ficha no concede acceso por sí mismo. Las consultas combinan ID y provincia. `_province` del formulario nacional se valida contra catálogo; un técnico no puede ampliar su ámbito enviándolo. Ocultar un botón no sustituye las comprobaciones de servidor.

## Contratos internos útiles

- GET /session/status devuelve idle_until, absolute_until y server_time (Unix); no renueva actividad. POST /session/activity sí la renueva bajo sesión válida.
- POST /scope recibe province y next opcional (nueva, recursos, atenciones); guarda ámbito de operación nacional.
- PUT/PATCH /users/{id} recibe action=edit, edit_user_id, first_name, last_name, email, password opcional, role y province. POST también se admite para compatibilidad y cambios action=state, active=0/1.
- Activación recibe code de seis dígitos, password y password_confirmation. El editor administrativo no exige confirmed porque no tiene campo de confirmación.
- Formulario tradicional inválido: errores en sesión y redirección; una petición que solicita JSON puede recibir 422. Sesión ausente: redirección login o 401 JSON. Permiso denegado: 403. CSRF: 419. Límite: 429. No todos los errores de negocio son excepciones HTTP: el formulario legacy puede volver con mensajes en HTML.

Para una integración externa, diseñar una API separada, autenticación, permisos, paginación y minimización de datos; no automatizar estas rutas como si fueran una API pública.

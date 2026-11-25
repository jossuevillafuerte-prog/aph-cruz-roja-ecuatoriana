# Variables y servicios externos

Plantilla: `aph-laravel/.env.example`. Nunca publicar .env, APP_KEY, contraseñas MySQL/SMTP, tickets de activación o respaldos. Cambiar variables requiere reconstruir config:cache y recargar procesos persistentes. La raíz del repositorio contiene el proyecto; la raíz de Laravel es aph-laravel/.

| Grupo / variable | Uso | Desarrollo | Producción |
| --- | --- | --- | --- |
| APP_NAME | Nombre de aplicación | APH Cruz Roja | Nombre institucional |
| APP_ENV | Activa controles de entorno | local | production |
| APP_DEBUG | Detalle de errores | Solo local aislado | false |
| APP_KEY | Cifrado y HMAC | Generar una vez | Clave privada estable, respaldada |
| APP_URL | URLs absolutas/activación | http://127.0.0.1:8090 | URL pública HTTPS pendiente |
| APP_LOCALE / APP_FALLBACK_LOCALE | Idioma de los mensajes de validación | es (lang/es/validation.php) | es |
| DB_CONNECTION | Driver | mysql | mysql |
| DB_HOST / DB_PORT | Servidor MySQL | Piloto 127.0.0.1:3308 | Host privado y puerto de infraestructura |
| MYSQL_ATTR_SSL_CA | CA para MySQL remoto, soportada en config/database.php | Normalmente no requerida en localhost | Ruta privada a CA validada si DB es remota |
| DB_DATABASE / DB_USERNAME / DB_PASSWORD | Base y cuenta | aph; pruebas aph_test | Credencial de mínimos privilegios |
| BCRYPT_ROUNDS | Coste de hashes Laravel | 12 en plantilla | Medir coste en servidor; parte heredada usa password_hash |
| SESSION_DRIVER | Persistencia | database | database |
| SESSION_ENCRYPT | Cifrado del payload | true | true |
| SESSION_LIFETIME | Minutos de persistencia | 10 | 10; límites funcionales también fijados en middleware |
| SESSION_SECURE_COOKIE | Cookie solo HTTPS | false únicamente para HTTP local | true |
| SESSION_HTTP_ONLY / SESSION_SAME_SITE | Protección de cookie | true / strict | true / strict |
| SESSION_EXPIRE_ON_CLOSE | Sin persistencia explícita | true | true; cierre de navegador no reemplaza timeout |
| SESSION_COOKIE / PATH / DOMAIN | Identificación y alcance | Cookie distinta en pruebas | Dominio mínimo; normalmente DOMAIN=null y PATH=/ |
| CACHE_STORE | Límites y caché | database | database; debe compartirse si hay varias instancias |
| QUEUE_CONNECTION | Cola configurada | database | Sin Jobs de negocio actuales |
| FILESYSTEM_DISK | Disco de Laravel | local | No hacer públicos archivos privados |
| LOG_CHANNEL / LOG_STACK / LOG_LEVEL | Registro técnico | stack/single según plantilla | Configurar rotación y nivel; evitar debug con datos médicos |
| MAIL_MAILER | Transporte | log, solo con invitaciones deshabilitadas | SMTP institucional u otro transporte validado |
| MAIL_SCHEME / HOST / PORT | Conexión de correo | Valores ficticios | Según proveedor, TLS validado |
| MAIL_USERNAME / PASSWORD | Credenciales SMTP | Sin secretos en repo | Gestor privado de secretos |
| MAIL_FROM_ADDRESS / NAME | Remitente | Ficticio | Remitente autorizado pendiente |
| APH_INVITATIONS_ENABLED | Permite envío de activaciones | false | true solo después de prueba real |
| APH_MAP_TILES / ATTRIBUTION | Teselas y atribución | OSM de referencia | Proveedor aprobado y capacidad evaluada |
| APH_REVERSE_URL | Endpoint inverso opcional | Default Nominatim | Se lee vía getenv; configurar entorno FPM al sobrescribir |
| AWS_*, REDIS_*, MEMCACHED_HOST, BROADCAST_CONNECTION | Opciones de plantilla | No necesarias para piloto | No son servicios implementados por el solo hecho de figurar en .env |
| VITE_APP_NAME | Build Vite opcional | Nombre | Solo si se construye ese frontend |

Los 600 segundos de inactividad y 28800 de duración absoluta se repiten en SessionLimits, AuthController::status, public/session-security.js y la vista sessions; cambiarlos exige actualizar los cuatro. cambiar SESSION_LIFETIME por sí solo no cambia la política funcional. El aviso a los ocho minutos lo gestiona el cliente.

## Integraciones y fallos

- Pwned Passwords: HTTPS desde PHP, solo prefijo SHA-1 de cinco caracteres. Fallo de TLS/red rechaza establecer clave nueva con mensaje específico; no bloquea el login con hash existente. Mantener CA y reloj correctos.
- OpenStreetMap: teselas desde el navegador; requiere acceso al proveedor y atribución. Referrer-Policy strict-origin transmite origen sin rutas clínicas. Una CSP del alojamiento debe permitir teselas, scripts/estilos y los elementos inline reales; no copiar una CSP restrictiva sin pruebas.
- Nominatim: PHP envía coordenadas redondeadas, sin nombres/documentos. El navegador conserva el texto escrito: solo completa direcciones vacías o propone la sugerida, y explica si la búsqueda falla. Caché SQL por hash; límite temporal con Cache::add por dos segundos. Fallo devuelve dirección vacía, no inventa una ubicación. Aprobar uso del proveedor y de coordenadas con el responsable de datos.
- SMTP: deshabilitado por defecto. Nunca activar con MAIL_MAILER=log en producción, pues el mensaje contiene código y enlace. Un envío fallido invalida la invitación creada. No existe garantía de entrega solo por aceptar SMTP.

Los dominios permitidos se mantienen en config/institution.php, no en un comodín. Se admiten el dominio nacional y exactamente los 24 dominios provinciales. Aceptar un dominio no acredita que el buzón exista. Una cuenta de pruebas con correo registrado no equivale a un buzón creado.

## Desarrollo rápido

Con MySQL vacío exclusivo de desarrollo y PHP/Composer instalados, seguir clonación, instalación, .env, key:generate y migraciones de la guía VPS, usando APP_ENV=local, APP_DEBUG=false y SESSION_SECURE_COOKIE=false para HTTP local. Provisionar anclas y cuenta nacional como se documenta. Ejecutar `php artisan serve --host=127.0.0.1 --port=8090` desde aph-laravel/. No exponer este servidor a internet. El repositorio no distribuye credenciales ni bases pobladas.

## Tiempo y coherencia

config/app.php fija la zona de aplicación en America/Guayaquil. La fecha clínica B es una fecha de atención sin zona y I una hora local introducida; cambios.fecha se almacena como texto ISO 8601 y las marcas de cuentas son timestamps. Mantener relojes sincronizados y validar presentación/conversión antes de establecer una zona institucional distinta; no reinterpretar datos históricos al cambiarla.

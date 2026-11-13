# Roles nacionales y activación institucional

Actualización: 13 de noviembre de 2026. Sustituye la política de roles y segundo factor anterior.

## Decisiones confirmadas

Solo hay dos roles: superadministrador nacional y técnico provincial de atención prehospitalaria. Los técnicos consultan, registran y corrigen todas las fichas de su propia provincia y gestionan sus recursos. Los técnicos pueden eliminar lógicamente cualquier ficha de su provincia, aunque la haya creado otro técnico; el superadministrador puede hacerlo a nivel nacional. Cada eliminación conserva la ficha y registra fecha, autor y cambio de visibilidad en el historial. El panel nacional permite consultar todas las provincias y seleccionar una para sus operaciones. Los permisos se comprueban en el servidor.

El usuario nacional es manejoinformacion, con nombre Cuenta de Manejo de la Información y correo manejoinformacion@cruzroja.org.ec. Su contraseña inicial se generó aleatoriamente y se guardó únicamente en un archivo local privado. Las 24 cuentas provinciales se convirtieron a técnicos conservando usuario y hash de contraseña. También se conserva el operador personal existente. Los antiguos datos de usuarios y operadores se mantienen para los vínculos históricos, pero no conceden acceso administrativo: la fuente de autenticación es accounts.

El archivo original cuentas-provinciales.tsv, compatible con Excel, no se modificó. No se asignaron buzones jp ni presidencia a cuentas. Los correos de los técnicos migrados quedan vacíos hasta que se registren los reales.

## Acceso

Solo usuario y contraseña. Se retiraron las rutas y pantallas TOTP y se invalidaron sesiones previas. No se guardan contraseñas en cookies; el gestor del navegador puede recordarlas. Se conserva la política de 6 a 72 caracteres ASCII, con letra, número y símbolo permitido, sin tildes, ñ ni espacios, y comprobación de contraseñas filtradas al establecer una nueva.

Continúan el límite de intentos, cookies HttpOnly/SameSite=Strict/Secure bajo HTTPS, protección CSRF, aviso a los ocho minutos, vencimiento a los diez de inactividad y máximo absoluto de ocho horas. La contraseña de seis caracteres sin MFA es una excepción expresamente solicitada y no se presenta como conformidad NIST ni certificación ISO.

Las cuentas migradas y la cuenta nacional tienen una excepción temporal legacy_access: pueden entrar sin correo verificado. El sistema no inventa fechas de verificación. Las cuentas nuevas quedan pendientes de activación. La excepción no se ofrece como interruptor en la interfaz.

## Panel de usuarios

Acceso exclusivo del superadministrador. Muestra nombre, usuario, rol, provincia, correo, verificación, estado activo/inactivo y último ingreso. Permite buscar, filtrar, crear técnicos, editar nombre/correo, activar/desactivar y reenviar invitaciones. No permite desactivar la propia cuenta nacional. La desactivación revoca sesiones y códigos pendientes. Los cambios administrativos quedan auditados.

La provincia y el rol se pueden editar por el superadministrador. Una reasignación crea o reutiliza la identidad operativa de destino, conserva las fichas y responsables históricos y revoca las sesiones anteriores. El administrador no puede quitarse su propio rol. El modal permite editar nombre, apellido, correo y contraseña opcional; una contraseña vacía conserva la actual. Los nombres existentes se conservan completos, sin separar apellidos automáticamente. Los correos se validan contra la lista exacta confirmada. No se derivan permisos automáticamente del dominio.

## Dominios confirmados

El dominio nacional es cruzroja.org.ec. La lista exacta por provincia está en config/institution.php. Incluye las variantes confirmadas caniar, eloro, morona, staelena, stodomingo, losrios y zamora. Santo Domingo, Carchi y Los Ríos fueron confirmados explícitamente con terminación .org.ec. No se permiten dominios parecidos, sufijos agregados ni subdominios arbitrarios.

## Correo y URL pública: PENDIENTES

APH_INVITATIONS_ENABLED=false mantiene deshabilitado el envío. La pantalla informa esta condición; al pulsar reenviar no envía mensajes ni crea códigos. Falta configurar MAIL_* con el servicio institucional, remitente autorizado, APP_URL con URL HTTPS accesible y verificar entrega real antes de habilitar la bandera. No introducir secretos en documentación o Git.

El flujo preparado genera invitación y código numérico de seis dígitos, de un solo uso, con diez minutos de vigencia y máximo de cinco intentos. Hay límite de un reenvío por minuto y cinco por hora. Reenviar invalida el código previo. El código se guarda como HMAC, el ticket como hash. El usuario confirma código y establece su contraseña antes de activar la cuenta. Una suspensión invalida invitaciones y no se revierte por reenviarlas. El flujo de recuperación autónoma por correo queda pendiente junto con el servicio de envío; no se simula un envío exitoso.

## Operación

Aplicación local: http://127.0.0.1:8090/login. En producción se requiere HTTPS y cookie Secure. El acceso nacional inicial está en C:/Users/Chosto/AppData/Local/APH-Piloto/superadministrador-laravel.txt. El archivo de claves provinciales original conserva su ubicación y contenido.

Se creó una copia cifrada de MySQL antes de cambiar identidades. No se alteraron las fichas, recursos ni su historial. La migración agregó cuentas y vínculos de operadores para las cuentas provinciales. Para revertir identidades debe restaurarse una copia verificada; no se realiza un rollback destructivo automático.

Se mantienen las referencias ISO 27799:2025, ISO/IEC 27001 y 27701 y las obligaciones de privacidad documentadas anteriormente. Cambiar autenticación no resuelve las tareas pendientes de TLS institucional, cifrado del almacenamiento, respaldos, retención y gobierno de datos médicos.

## Mapas y navegación (13 de noviembre de 2026)

El acceso a Seguridad se muestra solamente en el menú de Mi perfil. Cerrar sesión permanece en todos los encabezados. Los botones provinciales conservan el orden alfabético y el foco al filtrar.

Los mapas utilizan Leaflet y OpenStreetMap, sin API key. Se detectó que no-referrer suprimía el Referer que exige la política del proveedor (https://operations.osmfoundation.org/policies/tiles/). Se sustituyó por strict-origin; en la verificación del navegador los tiles respondieron HTTP 200 y recibieron únicamente http://127.0.0.1:8090/. El bloqueo reportado no se reprodujo de forma constante: también se recibieron imágenes antes del cambio. No se identificó un rechazo por CORS, X-Frame-Options o CSP de la aplicación.

APH_MAP_TILES y APH_MAP_ATTRIBUTION se leen desde config/maps.php, compatible con config:cache. En producción se requiere HTTPS; cualquier CSP impuesta por el alojamiento debe permitir el origen del proveedor en img-src. La disponibilidad del servicio cartográfico externo no está garantizada por esta configuración. No se envían identificadores de fichas en el Referer.

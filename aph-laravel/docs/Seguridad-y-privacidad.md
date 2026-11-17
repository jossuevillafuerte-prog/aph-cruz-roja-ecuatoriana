> **ARCHIVO HISTÓRICO.** Describe la entrega anterior, incluidas funciones MFA ya retiradas. Consultar [documentación vigente](../../docs/README.md), [seguridad actual](../../docs/seguridad.md) y [evidencia de pruebas](../../docs/calidad-y-trazabilidad.md).

> Documento histórico de la entrega anterior. La política vigente de roles y acceso está en Roles-y-activacion.md. Se retiró MFA y las cuentas provinciales ahora son técnicos.

# Seguridad y privacidad del sistema APH

Fecha de revisión: 10 de noviembre de 2026.

## Alcance implementado

Laravel 13.40, PHP 8.4.27 y MySQL 8.4.13. El núcleo de autenticación utiliza el guard de sesión y un proveedor de usuarios de Laravel. Las pantallas y reglas del piloto se conservan mediante un adaptador interno, fuera del directorio público. No es una reescritura completa a controladores Eloquent: las fichas conservan su JSON y sus identificadores.

Se importaron 24 cuentas provinciales, 1 operador, 6 recursos, 101 fichas, 101 registros históricos y 4 direcciones cacheadas. La base SQLite original se conserva sin modificación. No se migraron sesiones ni intentos anteriores. Cada cuenta debe vincular su segundo factor en el primer acceso.

## Política acordada e implementada

- Contraseña nueva de 6 a 72 caracteres ASCII, al menos letra, número y símbolo de `. @ _ - ! # $ %`. No se admiten espacios, tildes, ñ ni emojis. El máximo evita truncamiento de bcrypt.
- Bloqueo de patrones frecuentes y consulta de contraseñas filtradas mediante el servicio Pwned Passwords. Solo se transmite el prefijo de cinco caracteres del SHA-1, nunca la contraseña completa. Si no puede verificarse el servicio, la nueva contraseña se rechaza y debe intentarse de nuevo. Las credenciales importadas conservan su hash y no se modifican automáticamente.
- Segundo factor TOTP obligatorio para todas las cuentas. Se configura manualmente en una aplicación autenticadora. Se entregan ocho códigos de recuperación de un solo uso; solo se conservan sus hashes. El secreto TOTP y el contenido de sesión se cifran con la clave de Laravel. Se rechaza la reutilización del mismo paso TOTP.
- Cinco intentos fallidos de contraseña por cuenta durante una ventana de cinco minutos provocan una espera hasta que termine esa ventana. Hay un límite adicional de 50 fallos por IP en cinco minutos. El segundo factor tiene cinco intentos por cuenta en cinco minutos. No hay bloqueo permanente automático.
- Aviso a los ocho minutos, vencimiento a los diez minutos de inactividad y límite absoluto de ocho horas. Las comprobaciones se hacen en el servidor. Las consultas periódicas de estado no renuevan la actividad. La interacción real con el formulario envía una renovación limitada, sin mantener viva una página abandonada.
- Al vencer la sesión se oculta la pantalla. No se ha implementado autoguardado de formularios: los cambios sin guardar pueden perderse; se muestra aviso previo.
- Sesiones en MySQL, sin Recordarme, cookie HttpOnly, SameSite=Strict, caducidad al cerrar el navegador y rotación del ID al autenticar y confirmar el segundo factor.
- El cierre se hace por POST con CSRF, invalida la sesión del servidor y renueva el token. Las otras pestañas reciben el aviso. Cambiar la contraseña revoca las demás sesiones; la revisión de credenciales rechaza también sesiones de cuentas desactivadas o restablecidas. Existe cierre de todas las sesiones con confirmación de contraseña.
- Respuestas privadas no-store, denegación de enmarcado, nosniff y Referrer-Policy strict-origin (actualización del 13 de noviembre de 2026: identifica únicamente el origen ante el proveedor cartográfico, sin rutas ni parámetros clínicos). Se registra autenticación, fallos, vencimiento, cierres, operaciones de escritura e impresión/exportación. Las contraseñas, secretos y contenido clínico no se copian al registro de seguridad.
- Todas las pantallas y exportaciones requieren autenticación y segundo factor. Los datos se filtran por provincia en el servidor.

## Excepción de contraseña corta

El mínimo de seis caracteres se implementa por decisión expresa del solicitante. No satisface el mínimo de NIST SP 800-63B-4, incluso con MFA (ocho caracteres en ese contexto). El segundo factor y los límites de intentos reducen exposición, pero no convierten esta excepción en conformidad NIST o ISO. Se recomienda que la institución registre y revise formalmente esta decisión.

## Entorno local y publicación

En localhost se mantiene HTTP para pruebas y SESSION_SECURE_COOKIE=false. Es una excepción local explícita. En producción se exige HTTPS, SESSION_SECURE_COOKIE=true y APP_ENV=production: el middleware rechaza acceso inseguro. La configuración del proxy debe confiar únicamente en los proxies controlados por la institución. No se ha publicado el sistema ni configurado un certificado público.

El servidor integrado de PHP es solo para pruebas. Para publicación usar un servidor web con PHP-FPM o un equivalente mantenido, raíz documental exclusivamente `public/`, sin APP_DEBUG, con MySQL privado y una cuenta de aplicación de permisos mínimos. Separar credenciales de migraciones. Conservar APP_KEY fuera del código y respaldarla de forma protegida; perderla impide descifrar MFA y sesiones.

La base de datos de fichas no tiene cifrado de contenido clínico en esta entrega. Debe habilitarse protección del volumen/almacenamiento y de las copias de seguridad en el despliegue institucional. El cifrado de las sesiones no equivale al cifrado de toda la base. Los PDF descargados y las impresiones no se eliminan al cerrar sesión.

## Normas relevantes

| Referencia | Aplicación al proyecto | Estado |
|---|---|---|
| ISO 27799:2025 | Controles de seguridad específicos para organizaciones y sistemas de salud, basados en ISO/IEC 27002 | Referencia principal; no se declara cumplimiento integral |
| ISO/IEC 27001:2022 | Sistema de gestión de seguridad: riesgos, responsabilidades, revisión y evidencias | Requiere gestión y evaluación institucional además del software |
| ISO/IEC 27002:2022 | Orientación sobre controles de acceso, identidades, autenticación, registros y protección de información | Base de diseño; no se ha realizado auditoría de todos los controles |
| ISO/IEC 27701:2025 | Sistema de gestión de privacidad para responsables y encargados de datos personales | Aplica a gobierno del tratamiento y responsabilidades |
| LOPDP y su Reglamento de Ecuador | Protección de datos personales y datos de salud | Debe evaluarse con la institución y su responsable/delegado de protección de datos |

No existe una certificación ISO de esta aplicación por haber añadido un login seguro. Una evaluación de conformidad requiere alcance, políticas, análisis de riesgos, evidencias operativas y auditoría. La investigación utilizó resúmenes oficiales públicos, no una auditoría del texto completo de normas de pago.

## Decisiones institucionales pendientes

Definir base jurídica y finalidad del tratamiento, perfiles de acceso reales, retención y eliminación, atención de derechos, responsable y encargados, recuperación de cuentas con verificación de identidad, copias y restauración, respuesta a incidentes y revisión de accesos. Evaluar los requisitos relativos a delegado y evaluación de impacto según las condiciones del tratamiento. No debe asumirse que un consentimiento genérico resuelve todo tratamiento de datos médicos de urgencia.

Los mapas y la geocodificación consultan proveedores externos. Aunque no se envían nombres ni cédulas a esos servicios, las coordenadas o teselas solicitadas pueden revelar ubicaciones. Antes de usar datos clínicos reales debe evaluarse esta comunicación, las condiciones del proveedor y la necesidad de un servicio institucional.

También se recomienda bloqueo del equipo por el sistema operativo a los cinco minutos, cuentas personales de Windows y un procedimiento para documentos descargados. La aplicación no puede garantizar el borrado de archivos locales ni bloquear físicamente una computadora.

## Fuentes oficiales

- ISO 27799:2025: https://www.iso.org/standard/84647.html
- ISO/IEC 27001: https://www.iso.org/standard/27001
- ISO/IEC 27002:2022: https://www.iso.org/standard/75652.html
- ISO/IEC 27701:2025: https://www.iso.org/standard/27701
- NIST SP 800-63B-4: https://pages.nist.gov/800-63-4/sp800-63b.html
- OWASP, gestión de sesiones: https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html
- Laravel, autenticación: https://laravel.com/framework/docs/13.x/authentication
- Marco normativo SPDP: https://www.spdp.gob.ec/marco-normativo/
- Política de datos personales del MSP, referencia sobre confidencialidad de datos de salud: https://www.salud.gob.ec/politica-datos-personales/
- Reglamento LOPDP: https://www.telecomunicaciones.gob.ec/ley-y-reglamento-de-la-ley-de-proteccion-de-datos-personales/

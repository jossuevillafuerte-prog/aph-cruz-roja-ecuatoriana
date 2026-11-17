# Revisión de documentación del sistema APH

Fecha: 17 de noviembre de 2026. Alcance: código versionado, migraciones, configuración de ejemplo y documentación disponible. Resultado: **documentación parcial; no lista todavía para una entrega operativa institucional completa**. Este informe no acredita cumplimiento normativo ni certificación.

## Inventario y cobertura

| Requisito | Estado | Evidencia y pendiente |
| --- | --- | --- |
| Propósito, origen Excel y funciones | Cubierto | README raíz describe problemática, alcance y límites; sin métricas de impacto inventadas. |
| Versiones y dependencias | Parcial | Laravel 13.40, PHP 8.4 y MySQL 8.4 en piloto; composer.lock fija paquetes. Falta matriz de compatibilidad y extensiones por entorno. No se ha validado MariaDB. |
| Entorno y secretos | Parcial | aph-laravel/.env.example sin secretos, variables MySQL, sesiones, correo y mapas. Faltan explicación exhaustiva y procedimiento de gestión/rotación de secretos. |
| Instalación y actualización | Parcial | README técnico contiene pasos iniciales; falta procedimiento reproducible desde clonación hasta validación y reversión. No regenerar APP_KEY en cada actualización. |
| Nginx/Apache, TLS y permisos | Parcial | Raíz public/ y HTTPS documentados; faltan VirtualHost, PHP-FPM, permisos concretos y proxies del servidor elegido. |
| Frontend y almacenamiento | Parcial | package.json contiene Vite; vistas actuales usan recursos estáticos public/. Falta explicar qué requiere build y si storage:link aplica; no ejecutarlo por costumbre. |
| Cron, Supervisor y colas | No requerido por los flujos actuales; falta documentar operación | routes/console.php no programa tareas de negocio; las invitaciones usan Mail::raw síncrono. Existen tablas de colas, pero no Jobs/Events/Listeners propios. No afirmar que existen trabajadores desplegados. |
| Copias, restauración, monitorización y retención | Pendiente | Se reconocen como pendientes. Faltan RPO/RTO, frecuencia, responsables, restauración probada, alertas y respuesta a incidentes. |
| ERD / diagrama físico | Pendiente | No existe diagrama MySQL versionado. Generarlo desde migraciones/esquema, incluyendo relaciones reales y enlaces lógicos diferenciados. |
| Diccionario MySQL tabla por tabla | Pendiente | Migraciones disponibles; falta documentar tipos, nulos, defaults, PK/FK, acciones referenciales e índices. El diccionario de Excel no sustituye este documento. |
| Correspondencia Eloquent–tablas | Pendiente | Autenticación mediante ProvincialProvider y accounts; Query Builder y PDO en adaptador legacy. App\Models\User no es la fuente de autenticación activa. No inventar hasMany/belongsTo que no existen. |
| Arquitectura y flujos | Parcial | README describe integración Laravel/legacy y JSON de fichas. Faltan diagramas de componentes, secuencias y detalle de métricas, exportaciones, concurrencia y eliminación lógica. |
| Rutas/controladores/middleware | Pendiente como documento | routes/web.php contiene rutas vigentes; controles limits y superadmin. Falta tabla de métodos, rutas, acciones, permisos y errores. No hay API pública versionada; documentar endpoints web internos como tales. |
| Seguridad | Parcial | Roles-y-activacion.md vigente; sesión, CSRF, hash y límites implementados. No usa Breeze/Jetstream/Sanctum. Hay documentación histórica de MFA claramente obsoleta. Faltan gestión integral de datos médicos y revisión independiente. |
| Manual de usuario y soporte | Pendiente | No hay manual completo por rol con tareas, errores, recuperación, impresión y accesibilidad. |
| README y enlaces | Parcial | Portada y enlaces disponibles; falta guía rápida validada de instalación limpia. |
| .gitignore y .env.example | Cubierto para el repositorio revisado | Excluidos secretos, vendor, node_modules, builds Vite, bases, logs y herramientas locales. Recursos estáticos propios de public/ se versionan porque son parte funcional del sistema. Plantilla dentro de aph-laravel/, raíz de la aplicación. |
| CHANGELOG | Incorporado en esta revisión | Registro de entregas conocidas sin inventar versiones ni etiquetas previas. |
| Calidad y gestión de versiones | Parcial | Pruebas funcionales y commits disponibles; faltan criterios medibles de calidad, pruebas de carga, matriz de trazabilidad, política de releases y CI. |

## Referencias normativas verificadas

- [ISO/IEC/IEEE 26514:2022](https://www.iso.org/obp/ui#iso:std:iso-iec-ieee:26514:ed-1:v1:en): diseño y desarrollo de información para usuarios. Orienta manuales; no sustituye la documentación de arquitectura y operaciones.
- [ISO/IEC/IEEE 12207:2026](https://standards.ieee.org/ieee/12207/11416/): procesos del ciclo de vida del software; reemplaza la edición de 2017. Falta definir procesos, responsables, entregables y evidencias del proyecto.
- [ISO/IEC 25010:2023](https://www.iso.org/standard/78176.html): modelo de calidad de producto. Faltan objetivos medibles y resultados para rendimiento, seguridad, mantenibilidad y demás atributos aplicables.

Se consultaron los alcances públicos oficiales; no se realizó una evaluación cláusula por cláusula del texto íntegro de las normas. Citarlas o generar documentos no equivale a conformidad.

## Organización propuesta para completar la entrega

1. docs/arquitectura.md: componentes, dependencias, rutas, permisos y flujos.
2. docs/base-de-datos/esquema.md: diccionario físico y diagrama Mermaid desde migraciones; diccionario de claves JSON de atenciones separado.
3. docs/instalacion-vps.md: instalación, configuración, despliegue, actualización y reversión para el alojamiento seleccionado.
4. docs/operaciones.md: copias/restauración, certificados, observabilidad, incidencias y retención.
5. docs/manual-usuario.md: tareas por rol y resolución de problemas.
6. docs/calidad-y-trazabilidad.md: requisitos, pruebas, criterios de aceptación y evidencias.

Estos documentos son pendientes, no archivos que se afirme haber completado. Mermaid funciona en GitHub sin incorporar dependencias a la aplicación. Un generador basado solo en Eloquent omitiría buena parte de este esquema; preferir migraciones/MySQL para el ERD. MkDocs o Docusaurus son opcionales, no necesarios para cerrar las brechas documentales.

## Decisiones institucionales aún necesarias

Alojamiento y sistema operativo, dominio HTTPS, SMTP/remitente, responsables de operación, política de conservación de datos médicos, acceso a respaldos, objetivos de recuperación y aprobación funcional de la entrega. Se pueden redactar arquitectura y diccionario sin esperar estas decisiones; la guía productiva definitiva depende de ellas.

## Evidencia y alcance de esta entrega

23 pruebas de AccessSecurityTest y 247 aserciones aprobadas en la revisión funcional del 17 de noviembre. Navegador: guardado administrativo, conservación de contraseña vacía, aislamiento de errores del modal, login provincial y denegación de acceso nacional. No equivale a prueba de carga ni auditoría externa. Credenciales y cuentas locales de prueba no se publican en GitHub. Subir el código no despliega ni migra la base de datos operativa.

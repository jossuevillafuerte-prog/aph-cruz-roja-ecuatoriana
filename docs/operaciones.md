# Operaciones, respaldo y continuidad

Esta es una guía operativa propuesta, no evidencia de que existan copias externas, monitoreo o recuperación probada. Los responsables nominales, RPO/RTO, retención, alojamiento y contactos deben aprobarse antes del servicio institucional.

## Responsabilidades

| Función | Responsabilidad | Designación |
| --- | --- | --- |
| Dueño funcional APH | Aceptar flujos, catálogos y calidad del registro | Pendiente institucional |
| Superadministrador | Cuentas, ámbito nacional y suspensiones | Cuenta nacional existente; designar custodios |
| Operador de infraestructura | TLS, servicios, despliegue, respaldos y alertas | Pendiente |
| Responsable de datos personales | Accesos, conservación, proveedores e incidentes | Pendiente |
| Mantenedor | Cambios, pruebas, documentación y releases | Designar por repositorio |

La persona que aprueba acceso a datos no debe quedar sustituida por una bandera técnica. Mantener directorio de contactos fuera del repositorio si contiene información personal.

## Rutina operativa

- Diario: disponibilidad HTTPS, errores 5xx, espacio libre, último respaldo exitoso, fallos de login anómalos y conectividad a dependencias.
- Semanal: revisar cuentas activas, incidentes abiertos, rotación de logs y cambios pendientes. No inspeccionar fichas sin propósito autorizado.
- Antes de cada actualización: respaldo, prueba de restauración reciente, commit/artefacto identificado y prueba en preproducción.
- Periodicidad de restauración y revisión de permisos: aprobar institucionalmente. Propuesta inicial para ensayo: restauración mensual y después de cambios de esquema; no es una obligación ya adoptada.

## Qué respaldar

Base MySQL (incluye fichas, historial, recursos, avatares y cuentas), APP_KEY y .env en almacén cifrado separado, configuración de servidor/TLS, catálogos y versión de código. Los hashes de contraseñas y los logs también requieren protección. GitHub conserva código, no sustituye respaldo de base ni secretos.

Definir RPO (pérdida máxima tolerable) y RTO (tiempo máximo de recuperación) antes de fijar frecuencia. Una copia nocturna sola no garantiza un RPO menor de 24 horas. Si se requiere recuperación a punto en el tiempo, configurar y probar binlogs y su retención; no están entregados como servicio activo.

## Copia lógica de ejemplo

Solo para un servidor aprobado y directorio privado cifrado. Configurar `/ruta/privada/mysql-backup.cnf` con usuario de respaldo y permisos 0600; no escribir contraseña en argumentos. El usuario debe tener privilegios suficientes para la operación, separados del usuario web.

```bash
umask 077
mysqldump --defaults-extra-file=/ruta/privada/mysql-backup.cnf --single-transaction --quick --hex-blob --no-tablespaces --set-gtid-purged=OFF --databases aph --result-file=/ruta/privada/aph-fecha.sql
sha256sum /ruta/privada/aph-fecha.sql > /ruta/privada/aph-fecha.sql.sha256
```

Usar nombres nuevos por ejecución. Verificar código de salida, tamaño, checksum y prueba de recuperación; un archivo existente no prueba éxito. No ejecutar DDL durante la copia consistente. Cifrar y transferir a destino aprobado con acceso limitado; automatizar solo después de elegir gestor de claves, almacenamiento y retención. No adjuntar el SQL a issues ni subirlo a GitHub. [Referencia de mysqldump](https://dev.mysql.com/doc/refman/8.4/en/mysqldump.html).

## Restauración y ensayo

1. Abrir ticket de ensayo/incidente, identificar copia, checksum, APP_KEY y commit compatibles.
2. Crear una instancia MySQL aislada sin acceso de usuarios y sin SMTP operativo. El ejemplo dump usa --databases y contiene el nombre aph: restaurar en instancia separada, no conectarlo por error al servidor vivo.
3. Verificar checksum y descifrar en almacenamiento privado; importar con cliente MySQL del entorno de recuperación: `mysql --defaults-extra-file=/ruta/privada/mysql-recuperacion.cnf < /ruta/privada/aph-fecha.sql`. Confirmar antes que el archivo de conexión apunta a la instancia aislada.
4. Configurar copia de aplicación apuntando a esa instancia, APP_ENV apropiado y APH_INVITATIONS_ENABLED=false; no reutilizar dominio/cookie de producción.
5. Verificar tablas/migraciones, conteos e integridad, muestras autorizadas, login, aislamiento provincial, ficha con historial, recursos y exportación. No registrar datos clínicos en el acta.
6. Medir tiempo total y antigüedad de la copia; comparar con objetivos aprobados. Registrar resultado y eliminar material temporal siguiendo política institucional.
7. Para recuperación real, mantener mantenimiento, conciliar escrituras posteriores y cambiar tráfico solo con aprobación del responsable del incidente. Invalidar sesiones restauradas antes de abrir servicio, para no revivir accesos anteriores.

No se ha ejecutado un ensayo institucional de restauración como parte de esta documentación.

## Sesiones, registros y mantenimiento

No hay comandos de poda personalizados. Laravel expira sesiones y caché, pero la retención clínica y de auditoría no está automatizada. No borrar cambios, fichas eliminadas ni security_events para ahorrar disco sin política aprobada. Los eventos no son un archivo inmutable externo: un DBA privilegiado puede modificarlos; evaluar almacenamiento de auditoría protegido si se requiere garantía adicional.

Rotar logs de aplicación, FPM, Nginx y MySQL. Evitar registrar payloads, documentos, correos con códigos, cookies o cabeceras de autorización. Una revisión de seguridad exige conservar evidencia mínima con acceso restringido. Configurar alertas por disco, errores, TLS próximo a vencer, fallos de respaldo y aumento de denegaciones.

## Resolución de problemas

| Síntoma | Comprobación | Acción |
| --- | --- | --- |
| 403 en toda la aplicación productiva | HTTPS observado por Laravel y SESSION_SECURE_COOKIE | Corregir TLS/proxy confiable; no desactivar seguridad para publicar. |
| 419 al guardar | Cookie, dominio, CSRF, sesión vencida, APP_KEY consistente | Volver a autenticar; revisar cookie/configuración; no excluir rutas de CSRF. |
| Login falla para cuenta conocida | active, email_verified_at o legacy_access, hash y límites | Revisar en panel nacional; no inventar verificación ni mostrar si usuario existe públicamente. |
| 429 | Límite de intentos o invitaciones | Esperar ventana y revisar causa; no eliminar protección global. |
| Clave nueva rechazada por verificación | Certificados CA, reloj, DNS y HTTPS a Pwned Passwords | Reparar confianza/conectividad. El servicio falla de forma cerrada. |
| Mapas bloqueados/vacíos | Red del navegador, teselas, CSP, atribución y Referer | Verificar respuesta del proveedor; strict-origin, sin añadir datos clínicos a URLs. |
| Pin sin dirección | PHP curl, geocodificador, límite global de una consulta cada 2 s, caché | Reintentar más tarde; el texto escrito se conserva. Una dirección vacía queda en caché: revisarla manualmente. |
| «Trabajo en pausa» | Sesión vencida con cambios sin guardar | Iniciar sesión con el mismo usuario en otra pestaña y pulsar Reanudar antes de 30 minutos; no recargar ni cerrar la pestaña. |
| Guardado concurrente rechazado | version cambió | Recargar, comparar historial y volver a aplicar cambios conciliados. |
| 500 de MySQL | Servicio, permisos y migraciones | Consultar log privado sin publicar secretos; no ejecutar fresh. |
| Correo no llega | Bandera, transporte/remitente, respuesta SMTP, vencimiento | Resolver SMTP y reenviar; código anterior queda invalidado. |
| Descarga vacía | Estado Cerrada, provincia y no eliminada | Revisar alcance; exportación no incluye borradores. |

## Incidentes y acceso privilegiado

Registrar hora, síntoma, alcance y responsable; preservar evidencia privada. Contener con suspensión de cuenta, revocación de sesiones o mantenimiento según impacto. Corregir causa, validar, comunicar por canales institucionales y revisar retrospectivamente. La necesidad de notificación legal, plazos y destinatarios debe resolverla el responsable institucional conforme al marco aplicable; esta guía no fija plazos legales.

Los cambios directos SQL a fichas/cuentas son excepcionales: requieren copia, identidad del ejecutor, justificación y verificación. Preferir la aplicación para conservar historial. No hay recuperación automática de fichas borradas ni contraseña maestra.

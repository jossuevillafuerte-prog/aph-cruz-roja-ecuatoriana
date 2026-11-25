# Seguridad y privacidad: estado vigente

Revisión técnica documental: 17 de noviembre de 2026. Sustituye las referencias a MFA y mínimo de seis caracteres de documentos históricos. No es auditoría externa, dictamen legal ni certificación.

## Controles implementados

| Control | Implementación y límite |
| --- | --- |
| Autenticación | Guard web de Laravel, provider propio y accounts; usuario/contraseña, sin MFA ni Recordarme. |
| Contraseña nueva | 8–72 caracteres ASCII; mayúscula, minúscula, número y símbolo de `. @ _ - ! # $ %`; sin espacios, tildes ni ñ. La política no se aplica retroactivamente a hashes importados. |
| Hash | Hash::make o password_hash bcrypt en adaptador. Máximo 72 evita truncamiento bcrypt. El provider no rehashea automáticamente al login. |
| Contraseñas filtradas | Prefijo SHA-1 de cinco caracteres a Pwned Passwords; cotejo local de sufijo. Rechazo ante filtración o indisponibilidad; no se envía contraseña completa. |
| Intentos | Cinco fallos por usuario y 50 por IP en cinco minutos. El usuario debe coincidir exactamente, también en tildes. Mensaje de login genérico. |
| Sesión | MySQL, payload cifrado, ID rotado al autenticar; credencial derivada de hash/revisión. |
| Tiempo | Aviso a ocho minutos, expiración a diez de inactividad y ocho horas absolutas. No hay autoguardado. |
| Cookies | HttpOnly, SameSite Strict, Secure en producción, sin contraseña almacenada. HTTP local tiene excepción explícita. |
| Logout | POST+CSRF, invalidación de sesión y renovación de token; comunicación entre pestañas. |
| Autorización | Middleware nacional y consultas limitadas por provincia; edición/eliminación de cualquier ficha del ámbito, con auditoría. |
| Cabeceras | no-store, nosniff, X-Frame-Options DENY, strict-origin, HSTS bajo HTTPS y Permissions-Policy. No CSP propia entregada. |
| Entrada y salida | Validación de campos/catálogos, consultas parametrizadas y escape HTML en vistas. |
| Edición de cuentas | Correo único, dominios exactos, hash solo si nueva clave, revocación de sesiones/invitaciones, no suspenderse a sí mismo ni dejar el sistema sin un superadministrador con acceso; un correo verificado no cambia con las invitaciones deshabilitadas. |
| Auditoría | cambios para fichas; security_events para acceso, salidas y administración. No almacén inmutable independiente. |

Email institucional valida pertenencia a una lista, no existencia del buzón ni concordancia obligatoria con la provincia asignada. Las invitaciones prueban control del correo al activarse; no constituyen un segundo factor en cada login. legacy_access permite acceso temporal sin verificación y no tiene interruptor abierto en la interfaz.

## Clasificación y tratamiento

| Datos | Ubicación | Tratamiento requerido |
| --- | --- | --- |
| Identificación y salud | atenciones.datos, cambios.detalle | Acceso por necesidad funcional, cifrado de almacenamiento/respaldos y retención aprobada. |
| Coordenadas | JSON de ficha, geocodificación y salidas | Referencia aproximada; evitar difusión no autorizada. |
| Identidades de personal | accounts, operadores, perfiles | Gestión de altas/bajas y exactitud de roles. |
| Credenciales | Hashes de accounts/operadores/usuarios | No exportar ni exponer; APP_KEY fuera de repositorio. |
| Sesiones y eventos | sessions, security_events, logs | Acceso de soporte restringido y retención definida. |

El cifrado de sesiones no cifra las fichas MySQL. No existe cifrado de columna clínica implementado. El volumen y las copias externas cifradas dependen de infraestructura pendiente. PDF/XLSX/GeoJSON dejan el control de acceso de la aplicación al descargarse: custodiar destino, impresión y compartición.

## Límites y decisiones pendientes

- Seleccionar proveedor de alojamiento y de georreferenciación; valorar envío de coordenadas a terceros y reglas de uso/capacidad.
- Formalizar propósito, base jurídica, información a titulares, conservación, supresión, atención de solicitudes y contratos con encargados; revisión institucional para datos de salud.
- Definir acceso de soporte/DBA y protección de auditoría, copias, secretos y cuentas privilegiadas.
- Revisar riesgo residual de ausencia de MFA y excepción temporal de correos; no declarar conformidad por aplicar una longitud de contraseña.
- Hacer prueba de carga, revisión de seguridad independiente, accesibilidad y restauración antes del servicio definitivo.

## Referencias normativas

Las normas de documentación y calidad se relacionan en [calidad y trazabilidad](calidad-y-trazabilidad.md). Para seguridad de información y salud, la institución puede evaluar ISO/IEC 27001, ISO/IEC 27002 e ISO 27799 con especialistas y textos oficiales vigentes. Esta entrega no evalúa cláusulas ni declara cumplimiento. No se ha emitido una determinación de cumplimiento de protección de datos ecuatoriana.

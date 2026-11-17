# Calidad, requisitos y trazabilidad

Versión documental 1.0 · 17 de noviembre de 2026. Alcance: documentación de la implementación actual y plan verificable de aceptación. No se inventan resultados de carga, certificación ni aprobación institucional.

## Requisitos y evidencia

| ID | Requisito | Implementación | Evidencia disponible / aceptación |
| --- | --- | --- | --- |
| RF-01 | Capturar pendiente y cerrar con validación | legacy/modelo.php e index.php | Validaciones de campos; ensayo funcional de nueva/cerrada requerido en cada release. |
| RF-02 | Corregir fichas del ámbito y conservar historial | save(), version y cambios | Prueba de edición provincial y control optimista; revisar motivo al corregir cerrada. |
| RF-03 | Eliminación lógica auditada | deleteAttention() | test_deletion_is_scoped_and_audited_for_both_roles. |
| RF-04 | Nacional ve todas las provincias | NationalController, dashboard | test_national_records_ignore_previous_scope_and_can_filter. |
| RF-05 | Técnico limitado a su provincia | LegacyController, consultas SQL | Tests de permisos y edición de ficha de otro operador dentro de su provincia. |
| RF-06 | Usuarios activos/inactivos, edición consistente | UsersController | Tests de edición, contraseña vacía, revocación, propia cuenta y dominio. |
| RF-07 | Recursos por provincia | Formulario recursos y FK | Validación de recurso al guardar atención; ensayo funcional específico en aceptación. |
| RF-08 | Mapas y estadísticas cruzadas | dashboard-vista.php y dashboard-mapas.js | Pruebas de navegador registradas; reconciliar métricas con muestra sintética. |
| RF-09 | PDF, Excel y GeoJSON | Impresión y exportar.php | Revisión visual/funcional; archivos solo cerrados para Excel/GeoJSON. |
| RF-10 | Navegación móvil y modal de usuario | CSS y user-editor.js | Navegador: 390 px, guardado, errores aislados, accesos por rol. No auditoría WCAG completa. |
| RS-01 | Login/logout, límites y caducidad | AuthController, SessionLimits | Tests de login, throttle, timeout, polling y logout. |
| RS-02 | Contraseñas coherentes | PasswordPolicy | Test de composición y filtración; verificación real de CA en piloto. |
| RS-03 | HTTPS y cabeceras | SecureResponse | test_https_and_headers; falta validación del alojamiento definitivo. |
| RO-01 | Restauración y continuidad | Guía operaciones | Procedimiento documentado; ensayo institucional pendiente. |

## Pruebas reproducibles

El archivo phpunit.xml apunta a la base exclusiva `aph_test`. AccessSecurityTest usa RefreshDatabase y comprueba ese nombre. Crear esa base y credenciales de ensayo; nunca apuntar pruebas a aph ni ejecutar mientras otro proceso usa aph_test. Con dependencias de desarrollo instaladas, desde aph-laravel/:

```bash
php vendor/phpunit/phpunit/phpunit tests/Feature/AccessSecurityTest.php
```

Para el runtime portátil local, usar el ejecutable PHP y su ini completos si el PHP del PATH no tiene extensiones. No se distribuyen las herramientas privadas .tools/ con Git.

Última evidencia funcional registrada antes de esta entrega documental: **23 pruebas, 247 aserciones, aprobadas el 17 de noviembre de 2026**. Navegador: persistencia del editor, contraseña vacía conservada, edición propia sin perder sesión, errores aislados entre usuarios, login Azuay, bloqueo de /users y /national para técnico y logout. Estas pruebas no se repitieron por cambiar únicamente documentación. Los scripts privados del piloto no equivalen a una suite de navegador versionada en CI; debe incorporarse esa automatización si se desea reproducibilidad completa del recorrido UI.

## Plan de aceptación de release

Ejecutar con datos sintéticos en preproducción y registrar resultado real, fecha, commit, entorno y responsable:

1. Instalar desde cero o restaurar una copia autorizada; verificar migraciones y versión PHP/MySQL.
2. Entrar como técnico y nacional; confirmar menú, perfiles y denegación de ámbito ajeno.
3. Crear recurso, atención pendiente, cerrar, corregir con motivo y probar dos ediciones simultáneas.
4. Seleccionar evento/destino en mapa, guardar, recargar y comprobar coordenadas y referencia.
5. Comparar conteos del listado y dashboard con conjunto conocido; probar filtros y quitar selección.
6. Editar usuario, conservar clave vacía, validar errores por usuario, suspender y comprobar revocación.
7. Imprimir/PDF y exportar cerradas a Excel/GeoJSON; revisar datos y confidencialidad.
8. Probar idle/absoluto, CSRF, logout, HTTPS/cookies y fallo de servicios externos.
9. Restaurar una copia en entorno aislado y medir recuperación.
10. Registrar aprobación funcional/operativa; no marcar “aprobado” por existir esta lista.

## Atributos de calidad y medición

ISO/IEC 25010:2023 define un modelo de calidad de producto; se usa aquí como orientación, no como certificación.

| Atributo a evaluar | Medición propuesta | Estado / criterio |
| --- | --- | --- |
| Adecuación funcional | Casos de aceptación con responsables APH | Casos definidos; aprobación institucional pendiente. |
| Seguridad | Pruebas de autorización, sesión y revisión independiente | Evidencia funcional disponible; auditoría externa pendiente. |
| Rendimiento | p50/p95 de login/listado/dashboard, tamaño de respuesta y memoria con volumen objetivo | No medido; fijar usuarios concurrentes y umbral con institución. |
| Fiabilidad | Fallos, disponibilidad observada y recuperación | Sin SLA validado; RPO/RTO pendientes. |
| Interacción/accesibilidad | Teclado, lector, contraste, móvil y comprensión de formularios | Adaptación móvil parcial verificada; evaluación completa pendiente. |
| Mantenibilidad | Pruebas, separación de responsabilidades y cambios con documentación | Arquitectura híbrida documentada; deuda legacy conocida. |
| Compatibilidad/flexibilidad | Navegadores y despliegue limpio reproducible | Edge/local comprobado; matriz productiva pendiente. |
| Seguridad operacional | Impacto de dato incorrecto y recuperación de error de usuario | Validación técnica no reemplaza revisión asistencial. |

Esta tabla es una selección de aspectos a evaluar, no transcripción completa del modelo ISO ni declaración de que todos sus requisitos se cumplen.

## Ciclo de vida y documentos

Aplicación orientativa de ISO/IEC/IEEE 12207:2026:

| Etapa | Entradas/salidas del proyecto | Responsable propuesto |
| --- | --- | --- |
| Necesidad y alcance | Problema Excel, roles, requisitos y decisiones | Dueño funcional |
| Diseño | Arquitectura, esquema físico/JSON y permisos | Desarrollo y revisión técnica |
| Implementación | Código, migraciones, CHANGELOG y revisión de cambios | Mantenedor |
| Verificación | Pruebas automatizadas y acta de aceptación | QA / responsables APH |
| Transición | Instalación, migración, validación y formación | Infraestructura + APH |
| Operación | Copias, monitorización, incidentes y soporte | Operador designado |
| Mantenimiento | Releases, riesgos, revisión de catálogos y dependencias | Mantenedor + institución |
| Retiro | Exportación/conservación autorizada, revocación y eliminación controlada | Institución y responsable de datos |

ISO/IEC/IEEE 26514:2022 orienta la información para usuarios: audiencia definida, tareas por rol, terminología, errores y soporte en manual-usuario.md. Falta evaluación del manual con usuarios reales y aprobación de edición; no se presenta esa evaluación como realizada.

## Registro de riesgos y decisiones

| Riesgo/decisión | Tratamiento actual | Cierre requerido |
| --- | --- | --- |
| JSON clínico y lógica legacy | Se preserva estructura institucional; diccionario explícito | Revisar normalización/índices si volumen lo exige. |
| Claves antiguas y ausencia MFA | Compatibilidad por decisión del piloto | Aceptación institucional de riesgo y plan de revisión. |
| Servicios externos | Límites, caché y errores explícitos | Proveedor, privacidad y capacidad aprobados. |
| Borrado lógico | Historial conservado; sin restauración UI | Procedimiento de rectificación/restitución y retención. |
| Credenciales privilegiadas | Cuenta nacional y revocación | Custodia, recuperación de administrador y responsable suplente. |
| Infraestructura aún local | Guías de despliegue/operación preparadas | Ensayo real, dominio, SMTP, backups y aprobación. |
| Caducidad sin autoguardado | Aviso previo | Formación y evaluación de impacto operativo. |
| Datos de prueba | Identificados como ensayo | Retiro o segregación antes de incorporar datos clínicos reales. |

## Fuentes oficiales y alcance

- [ISO/IEC/IEEE 26514:2022](https://www.iso.org/obp/ui#iso:std:iso-iec-ieee:26514:ed-1:v1:en): información para usuarios.
- [ISO/IEC/IEEE 12207:2026](https://standards.ieee.org/ieee/12207/11416/): ciclo de vida; reemplaza 2017.
- [ISO/IEC 25010:2023](https://www.iso.org/standard/78176.html): modelo de calidad de producto.

Se verificaron alcances públicos oficiales. Para evaluar conformidad, adquirir/consultar las normas completas, delimitar alcance y ejecutar una revisión formal. Los documentos no sustituyen esa evaluación.

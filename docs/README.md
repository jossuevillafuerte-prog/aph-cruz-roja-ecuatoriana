# Documentación APH · Cruz Roja Ecuatoriana

**Edición documental 1.1 — 25 de noviembre de 2026.** Implementación revisada: commit 6fa336e y correcciones posteriores. Documentación del sistema Laravel/MySQL existente, sin alterar lógica ni registros. Los documentos describen el piloto y un procedimiento de despliegue propuesto; no acreditan un despliegue productivo ni certificación.

## Lectura por audiencia

| Audiencia | Documentos |
| --- | --- |
| Personal APH | [Manual de usuario](manual-usuario.md) |
| Desarrollo | [Arquitectura](arquitectura.md), [rutas y permisos](rutas-y-permisos.md), [dependencias](dependencias.md) |
| Datos | [Diccionario MySQL](base-de-datos/esquema.md), [ERD y relaciones](base-de-datos/relaciones.md), [JSON de ficha](base-de-datos/ficha-json.md) |
| Servidores | [Instalación VPS](instalacion-vps.md), [configuración](configuracion.md), [operaciones y recuperación](operaciones.md) |
| Seguridad y revisión | [Seguridad vigente](seguridad.md), [calidad y trazabilidad](calidad-y-trazabilidad.md) |
| Mantenimiento/GitHub | [Repositorio](repositorio-y-mantenimiento.md), [CHANGELOG](../CHANGELOG.md), [estado de entrega](estado-documentacion.md) |

## Alcance de la entrega

Incluye 18 tablas, 113 campos físicos, claves e índices, ocho relaciones FK y 30 campos institucionales del JSON con sus extensiones. Diferencia constraints SQL de reglas PHP y vínculos lógicos. Documenta tanto funciones activas como componentes heredados que no deben confundirse con ellas.

Las guías contienen pasos operativos, responsabilidades por función y criterios de aceptación. **Quedan por completar con decisiones reales**: dominio/alojamiento, SMTP, custodios, canal de soporte, retención clínica, RPO/RTO y aprobación institucional. No son datos que pueda inventar la documentación. También quedan por ejecutar en el destino definitivo: despliegue, carga, auditoría y restauración; las pruebas históricas disponibles se identifican con su fecha.

## Convenciones

- “Implementado”: constatado en código o metadatos; no implica eficacia certificada.
- “Verificado”: se indica prueba y alcance concreto.
- “Propuesto/pendiente”: acción futura o decisión institucional; no se presenta como completada.
- No se publican secretos, registros de pacientes ni credenciales de pruebas.
- Las normas son referencias de organización; no se ha realizado evaluación de conformidad cláusula por cláusula.

La documentación antigua dentro de aph-laravel/docs contiene antecedentes de MFA y política previa; las instrucciones vigentes son las de este índice y Roles-y-activacion.md actualizado. El informe previo de brechas se conserva en [revisión inicial](historico/revision-documental-inicial.md).

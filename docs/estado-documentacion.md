# Estado de entrega documental

Actualizado: 25 de noviembre de 2026, tras las correcciones de la auditoría. Se completó el paquete documental del sistema actual; la aceptación operativa institucional continúa pendiente.

| Requisito solicitado | Entrega |
| --- | --- |
| Arquitectura y flujo MVC/adaptador | arquitectura.md con componentes, secuencia y estados |
| Lenguajes, frameworks y plugins | dependencias.md y arquitectura.md |
| MySQL: tablas, campos, tipos, nulos y defaults | base-de-datos/esquema.md: 20 tablas, 132 campos (incluye `solicitud` y `fecha` de atenciones y las tablas `drafts` y `registration_requests`) |
| PK, FK, índices y acciones referenciales | Diccionario y base-de-datos/relaciones.md; 8 FK físicas |
| ERD y mapeo Eloquent | Mermaid y explicación del Query Builder/PDO real, sin relaciones Eloquent ficticias |
| JSON/catálogos institucionales | base-de-datos/ficha-json.md: A–AE y extensiones |
| Rutas, middleware y permisos | rutas-y-permisos.md, inventario obtenido de Artisan |
| Eventos, colas y tareas | Arquitectura e instalación indican ausencia de Jobs de negocio y correo síncrono |
| Seguridad vigente | seguridad.md, roles y límites documentados |
| Instalación, configuración y servidor | instalacion-vps.md y configuracion.md; ejemplo Nginx y tres escenarios de datos |
| Copias, recuperación y mantenimiento | operaciones.md con procedimiento de ensayo y responsables por función |
| Manual por rol | manual-usuario.md |
| Calidad, pruebas y normas | calidad-y-trazabilidad.md, evidencia y plan de aceptación |
| GitHub/README/.gitignore/.env.example/CHANGELOG | Archivos existentes y repositorio-y-mantenimiento.md |
| Índice y control de documentación | docs/README.md y esta matriz |

## Condiciones aún abiertas

No faltan secciones documentales de la lista, pero **no se declara terminado el despliegue ni la gestión institucional**. Deben asignarse alojamiento/dominio/SMTP, responsables y canal de soporte, retención, RPO/RTO y aprobación de riesgos. Los procedimientos Linux/Nginx y restauración son guías, no ensayos realizados en un servidor inexistente. Falta ejecutar aceptación productiva, carga y evaluación independiente. No hay certificación ISO.

Se verificaron inventario de rutas y metadatos SQL de solo estructura; los enlaces y diagramas se revisan en la entrega. No se cambiaron datos clínicos ni código de negocio para documentar. La revisión previa de brechas está conservada como antecedente histórico.

# Registro de cambios

Formato inspirado en Keep a Changelog. El proyecto aún no tiene versiones etiquetadas; se registran entregas por fecha.

## [Sin publicar como versión] — 2026-11-25

Requiere ejecutar `php artisan migrate` (columnas `atenciones.solicitud` y `atenciones.fecha`, índices y tabla `drafts`).

### Añadido
- Borrador de ficha en el servidor: se guarda cifrado a los 3 segundos de dejar de escribir, se ofrece restaurar o descartar al volver y se borra al guardar la ficha o a los 7 días. No prolonga una sesión inactiva.
- Atajos de teclado: Ctrl+S (Cmd+S) guarda la ficha y «/» enfoca la búsqueda de Usuarios Nacionales.

### Cambiado
- Los filtros Todas, Cerradas y Pendientes del listado son pestañas con `aria-current`; ya no se desactivan al pulsarlos de nuevo.
- El panel muestra los últimos 12 meses por defecto, con periodo configurable y un máximo de 10.000 atenciones. El listado provincial se pagina.

### Corregido
- Pérdida de fichas al vencer la sesión o fallar la red, y de direcciones al marcar el mapa.
- Mensajes de error que no se mostraban, límite de intentos evadible y bloqueo del superadministrador.
- Historial con motivos reales, conservación de datos adicionales y responsable con usuario.
- Auditoría que distingue escrituras, rechazos y lecturas; fichas duplicadas por doble envío.
- Pruebas que se detienen antes de usar una base distinta de `aph_test`.
- Buscador CIE-10, creación y exportación para el superadministrador.
- Interfaz: foco conservado al filtrar el panel con teclado, ayudas visibles en botones de solo icono, estados vacíos con acción, filtros de atenciones compactos, pesos tipográficos hasta 600 y textos de al menos 12 px, movimiento reducido respetado (también en los mapas) y aviso al abandonar una ficha sin guardar.
- Usuarios nacionales: etiquetas en los filtros, confirmación al desactivar una cuenta y editor como panel lateral.
- Formularios: errores junto a cada campo, resumen enlazado y enfocable, validación al salir del campo, usuario conservado en el login y mensajes de validación en español.
- Plantilla `.env.example`: `APP_LOCALE=es`, `LOG_STACK=daily` y `LOG_LEVEL=warning`.
- Migración de cuentas reintentable y reversiones que no borran datos.
- Abrir una ficha con datos incompletos ya no produce un error 500.
- Marcar, quitar o aplicar una ubicación del mapa ahora guarda el borrador y activa el aviso de cambios sin guardar.

## [Sin publicar como versión] — 2026-11-17

### Cambiado
- Dashboard con accesos a módulos según rol; retirados búsqueda por hoja y gráficos de recurso individual y CIE-10.
- Política unificada de nuevas contraseñas: 8–72 caracteres, mayúscula, minúscula, número y símbolo permitido.
- Separación uniforme entre títulos y contenido.

### Corregido
- Edición administrativa con persistencia, retorno explícito a Usuarios Nacionales y conservación de contraseña vacía.
- Errores del modal limitados al usuario editado y limpiados al cerrarlo.
- Gestión de sesión propia tras edición y envío de formularios con un campo action.
- Mensajes diferenciados para requisitos, contraseña filtrada e indisponibilidad del verificador externo.

### Documentación
- Paquete documental 1.0: arquitectura, rutas/permisos, diccionario físico (18 tablas, 113 campos, 8 FK), ERD y JSON institucional.
- Guías de instalación, configuración, operaciones/recuperación, seguridad, manual por rol y calidad/trazabilidad.
- Índice central y separación explícita de antecedentes históricos y política vigente.
- Revisión de cobertura técnica, operaciones, repositorio y normas en docs/estado-documentacion.md.
- Política vigente actualizada; documentos anteriores identificados como históricos.

## Entrega previa — 2026-11-13

- Navegación, gestión de usuarios e indicadores de atenciones (commit 563c064).
- Publicación inicial de la aplicación Laravel/MySQL (commit bfd25d1).

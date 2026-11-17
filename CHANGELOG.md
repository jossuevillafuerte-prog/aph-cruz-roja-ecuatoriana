# Registro de cambios

Formato inspirado en Keep a Changelog. El proyecto aún no tiene versiones etiquetadas; se registran entregas por fecha.

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
- Revisión de cobertura técnica, operaciones, repositorio y normas en docs/estado-documentacion.md.
- Política vigente actualizada; documentos anteriores identificados como históricos.

## Entrega previa — 2026-11-13

- Navegación, gestión de usuarios e indicadores de atenciones (commit 563c064).
- Publicación inicial de la aplicación Laravel/MySQL (commit bfd25d1).

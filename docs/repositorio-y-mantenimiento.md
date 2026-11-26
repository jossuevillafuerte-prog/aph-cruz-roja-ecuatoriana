# Repositorio y mantenimiento documental

Repositorio privado: [aph-cruz-roja-ecuatoriana](https://github.com/jossuevillafuerte-prog/aph-cruz-roja-ecuatoriana). El código Laravel vive en aph-laravel/; docs/ organiza documentación vigente. GitHub guarda código y documentos, no despliega el servicio ni respalda las fichas.

## Archivos fundamentales

- README.md: propósito, funcionalidades y acceso a documentación.
- CHANGELOG.md: cambios conocidos; sin inventar etiquetas de versión.
- .gitignore y aph-laravel/.gitignore: excluyen secretos, vendor, node_modules, Vite compilado, bases, logs y herramientas locales.
- aph-laravel/.env.example: plantilla real de la aplicación sin secretos; no duplicarla en raíz porque Laravel no lee allí su .env.
- composer.lock: versiones PHP fijadas; no modificar mediante update inadvertido al desplegar. No hay package-lock.json versionado: si se adopta el build Vite, incorporar y revisar ese lock antes de usar npm ci.
- docs/: arquitectura, datos, rutas, configuración, despliegue, operaciones, seguridad, manual y calidad.

Los scripts, hojas, bases, respaldos y credenciales del piloto permanecen fuera del repositorio. Los assets estáticos propios de public/ sí se versionan: son necesarios para el frontend actual y no son el directorio compilado public/build.

## Flujo de cambios propuesto

1. Crear issue sin datos personales con problema, alcance, criterios de aceptación y riesgos.
2. Trabajar en rama `codex/descripcion` o la convención aprobada por el equipo; mantener main estable.
3. Añadir migración para cambios de esquema, pruebas para comportamiento y documentación en el mismo PR.
4. Revisar diff, secretos, permisos, compatibilidad, catálogos y CHANGELOG. Revisor distinto cuando el equipo lo permita.
5. Ejecutar pruebas en aph_test y revisión de aceptación proporcional al cambio.
6. Integrar y desplegar un commit identificable; si se adopta versionado semántico, crear etiquetas solo para releases aprobadas.
7. Registrar despliegue, resultado y plan de reversión en acta privada cuando contenga datos de infraestructura.

Este flujo es una guía: no se afirma que existan branch protection, revisión obligatoria ni CI configurados. Implementar esas opciones requiere administración del repositorio y acordar responsables.

## Cómo mantener los documentos

| Cambio | Documentos a actualizar |
| --- | --- |
| Nueva tabla/campo/FK/índice | Migración, esquema.md, relaciones.md, ficha-json.md si aplica |
| Ruta/permiso/rol | rutas-y-permisos.md, arquitectura.md, manual-usuario.md |
| Validación o sesión | seguridad.md, manual, configuración y pruebas |
| Dependencia/proveedor | arquitectura, instalación, configuración y riesgos |
| UI/flujo | Manual, aceptación y CHANGELOG |
| Despliegue/operación | Guías, responsables, objetivos y evidencia real |

Revisar enlaces relativos y diagramas Mermaid en cada cambio. Actualizar fecha y commit de referencia cuando cambie implementación; una fecha nueva sin revisión no significa vigencia técnica. Los documentos históricos se conservan con rótulo y enlace al vigente para evitar instrucciones contradictorias.

## Herramientas

El esquema se documentó con migraciones y metadatos MySQL (20 tablas, 132 campos, 10 FK). Mermaid representa diagramas en GitHub sin instalar una dependencia en la aplicación. MySQL Workbench/DBeaver pueden generar gráficos desde una conexión autorizada; exportar estructura, no registros. Un generador basado solo en Eloquent no cubrirá este proyecto híbrido. MkDocs/Docusaurus son opciones futuras de publicación, no requisitos para leer Markdown.

## Actualizar dependencias

Ejecutar `composer audit` y, si se usa el frontend NPM, `npm audit` en entorno de desarrollo/CI. Los resultados son instantáneas, no garantías. Separar actualizaciones de código y migraciones cuando facilite revisión, probar con lock nuevo y conservar rollback. La dependencia google2fa se conserva aunque MFA esté retirado; evaluar su eliminación en un cambio técnico separado, no dentro de una tarea documental.

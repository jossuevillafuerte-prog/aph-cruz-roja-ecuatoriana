# APH: aplicación Laravel y MySQL

Esta carpeta es la raíz de la aplicación. La documentación vigente se encuentra en [docs/README.md](../docs/README.md), en la raíz del repositorio.

- [Arquitectura y componentes](../docs/arquitectura.md)
- [Diccionario físico MySQL](../docs/base-de-datos/esquema.md) y [relaciones](../docs/base-de-datos/relaciones.md)
- [Instalación y aprovisionamiento](../docs/instalacion-vps.md)
- [Entorno y desarrollo local](../docs/configuracion.md)
- [Seguridad](../docs/seguridad.md), [operaciones](../docs/operaciones.md) y [manual](../docs/manual-usuario.md)

Se utiliza Laravel 13.40, PHP 8.4 y MySQL 8.4 en el piloto. Autenticación por accounts con usuario y contraseña; sin MFA activo. Los formularios legacy están adaptados detrás de Laravel, no expuestos directamente. No hay reescritura completa a Eloquent.

Para desarrollo: instalar dependencias con Composer, crear .env desde .env.example, configurar MySQL exclusivo, generar APP_KEY una sola vez y aplicar migraciones. Consultar la guía para anclas provinciales y cuenta inicial antes de intentar ingresar. Después ejecutar `php artisan serve --host=127.0.0.1 --port=8090`. No se distribuyen usuarios/contraseñas de prueba en Git.

No ejecutar DatabaseSeeder genérico para cuentas institucionales. No regenerar APP_KEY al actualizar. La importación histórica SQLite tiene orden específico documentado; el piloto actual ya usa MySQL. No usar simultáneamente el antiguo piloto como segunda base operativa.

Los documentos históricos de docs/ dentro de esta carpeta se conservan como antecedentes; los apartados de MFA y contraseñas anteriores no son instrucciones vigentes.

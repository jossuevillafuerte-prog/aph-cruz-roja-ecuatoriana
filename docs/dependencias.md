# Dependencias y herramientas

Versiones del lock revisado el 17 de noviembre de 2026. No todas las herramientas instaladas se usan en el flujo de producción.

| Paquete PHP directo | Versión fijada | Ámbito |
| --- | --- | --- |
| laravel/framework | v13.40.0 | Producción |
| laravel/tinker | v3.0.2 | Producción |
| pragmarx/google2fa | v9.1.0 | Producción |
| fakerphp/faker | v1.24.1 | Desarrollo |
| laravel/boost | v2.10.4 | Desarrollo |
| laravel/pail | v1.2.7 | Desarrollo |
| laravel/pao | v1.1.5 | Desarrollo |
| laravel/pint | v1.32.1 | Desarrollo |
| mockery/mockery | 1.6.15 | Desarrollo |
| nunomaduro/collision | v8.9.5 | Desarrollo |
| phpunit/phpunit | 12.5.43 | Desarrollo |

Laravel sirve sesión, routing, DB, Blade, validación y correo. Tinker es consola operativa, no interfaz pública. Google2FA permanece como dependencia heredada sin rutas MFA. PHPUnit/Pint/Boost son herramientas de desarrollo; no usar su existencia como evidencia de un control de producción.

## Frontend

No existe package-lock.json versionado. Los siguientes son rangos declarados, no versiones exactas instaladas.

| Paquete | Rango |
| --- | --- |
| @tailwindcss/vite | `^4.0.0` |
| concurrently | `^10.0.3` |
| laravel-vite-plugin | `^3.1` |
| tailwindcss | `^4.0.0` |
| vite | `^8.0.0` |


El frontend de módulos usa JavaScript/CSS de public/, Leaflet local y SVG generados directamente para gráficos. No depende de React/Vue ni de Chart.js. Vite/Tailwind pertenecen a la plantilla disponible. Leaflet local es 1.9.4, verificado en la cabecera de public/leaflet/leaflet.js; conservar LICENSE y atribución al redistribuir. @laravel/multiplex (^0.4.1) figura como dependencia NPM opcional de desarrollo.

MySQL probado: 8.4.13. PHP del piloto: 8.4; extensiones y requisito efectivo se validan con composer check-platform-reqs, no solo con el rango raíz de composer.json. No se cambió ninguna dependencia para esta entrega documental.

# Instalación y despliegue

Audiencia: administrador de servidores. Referencia: Linux con Nginx, PHP-FPM y MySQL 8.4. Los dominios, rutas, usuarios de servicio y certificados son ejemplos que deben adaptarse. No se ha desplegado ni certificado este perfil en un VPS real. El servidor PHP integrado es solo para desarrollo.

## Requisitos

| Componente | Base de referencia y comprobación |
| --- | --- |
| PHP | 8.4.x; el lock actual tiene requisitos más estrictos que el php declarado en composer.json. Ejecutar composer check-platform-reqs. |
| Extensiones | PDO/pdo_mysql, mbstring, openssl, curl, fileinfo, zip, dom, xml, xmlwriter, ctype, filter, hash, session, tokenizer; pdo_sqlite solo para importación histórica. Revisar php -m en CLI y FPM. |
| MySQL | 8.4, InnoDB y utf8mb4. MariaDB no validado. |
| Composer | 2.x, composer.lock obligatorio. |
| Node/NPM | Solo para construir herramientas Vite; seleccionar una versión compatible con Vite 8, por ejemplo Node 24. No hay package-lock.json versionado en esta entrega. El piloto usa JS/CSS estáticos en public/. |
| Sistema | Git, certificados CA actualizados, Nginx y PHP-FPM; acceso a MySQL, HTTPS externo y SMTP si se habilita. |
| Capacidad | No hay dimensionamiento validado ni prueba de carga nacional. Medir en preproducción antes de fijar CPU, RAM, disco y concurrencia. |

PHP CLI y FPM deben usar el mismo conjunto de extensiones y certificados de confianza. El error cURL 60 impide validar nuevas contraseñas. Instalar CA del sistema o configurar curl.cainfo/openssl.cafile en ambos SAPIs; no desactivar verificación TLS. La configuración privada local de Windows no viaja en Git.

## Preparación de una instalación nueva

Ejecutar con usuario de despliegue, no con root para Composer/NPM. Los ejemplos suponen checkout en `/srv/aph` y aplicación en `/srv/aph/aph-laravel`.

```bash
git clone https://github.com/jossuevillafuerte-prog/aph-cruz-roja-ecuatoriana.git /srv/aph
cd /srv/aph/aph-laravel
composer install --no-dev --optimize-autoloader
composer check-platform-reqs
cp .env.example .env
```

Configurar `.env` según [entorno](configuracion.md). Crear previamente base `aph` y usuarios MySQL separados: migración con DDL y aplicación con SELECT/INSERT/UPDATE/DELETE sobre esa base. No exponer puerto MySQL a internet. Si MySQL es remoto, configurar TLS y verificar la conexión. Mantener secretos fuera del checkout compartido y nunca dentro de public/.

Solo para una instalación nueva, después de disponer de .env:

```bash
php artisan key:generate --force
php artisan migrate --force
```

Respaldar APP_KEY de forma privada. No volver a generarla en actualizaciones ni restauraciones: protege sesiones y códigos HMAC. No ejecutar `migrate:fresh`, `db:wipe`, ni `db:seed` genérico en producción. DatabaseSeeder usa el User de plantilla y no representa el modelo institucional.

### Catálogo provincial y cuenta nacional en una base vacía

Las migraciones crean tablas, pero no las 24 anclas de usuarios cuando no hay datos heredados. Deben cargarse antes de crear técnicos. En consola Laravel (`php artisan tinker`, disponible con laravel/tinker), sobre una base nueva revisada por el operador:

```php
DB::transaction(function () {
    $provinces = json_decode(file_get_contents(base_path('legacy/provincias.json')), true, 512, JSON_THROW_ON_ERROR);
    foreach (array_keys($provinces) as $province) {
        if (! DB::table('usuarios')->where('provincia', $province)->exists()) {
            DB::table('usuarios')->insert([
                'provincia' => $province,
                'clave' => Illuminate\Support\Facades\Hash::make(bin2hex(random_bytes(24))),
            ]);
        }
    }
});
```

Esto crea anclas territoriales con hashes aleatorios no utilizables como cuentas de acceso; no modifica cuentas existentes. Luego crear un directorio privado fuera de la raíz web, propiedad del operador y permisos 0700, y ejecutar:

```bash
umask 077
php artisan aph:provision-national /ruta/privada/nueva-credencial.txt
```

El comando no reemplaza una cuenta nacional existente. Entregar la credencial por canal privado y cambiarla desde Mi perfil. Los técnicos nuevos se crean en Usuarios Nacionales y requieren activación; con correo deshabilitado quedan pendientes. La excepción legacy_access del piloto no debe extenderse mediante un seeder genérico a todos los usuarios de producción. No copiar la cuenta de prueba local como cuenta institucional.

### Migrar el piloto actual

El camino recomendado para este proyecto ya migrado es restaurar una copia consistente de **MySQL**, junto con APP_KEY y configuración privada, en preproducción; después ejecutar migraciones pendientes. Probar antes de cambiar tráfico. No volver a ejecutar el importador SQLite sobre la base operativa.

Para un origen histórico SQLite y un destino completamente vacío, el orden importa: aplicar únicamente la primera migración de tablas, importar y luego ejecutar las restantes. La migración unified_accounts convierte identidades que ya existan en ese momento:

```bash
php artisan migrate --path=database/migrations/0001_01_01_000000_create_users_table.php --force
php artisan aph:import /ruta/privada/piloto.sqlite
php artisan migrate --force
```

No usar esta secuencia si ya se aplicaron todas las migraciones al destino: importar después no crea automáticamente accounts. Ante una importación fallida, detener el proceso, revisar el destino y restaurar una base de ensayo; no modificar a mano migrations para “forzar” el orden. Comparar conteos y contenido en entorno privado, sin publicar registros médicos.

## Frontend y permisos

Las vistas vigentes sirven estilos, scripts, logos, Leaflet y cartografía desde public/; deben viajar con el código. Si se utiliza el pipeline Vite o se modifican resources/js y resources/css:

```bash
npm install
npm run build
```

Al no existir lock NPM versionado, npm install resuelve los rangos de package.json y puede variar versiones. Si se adopta este pipeline, generar y revisar package-lock.json y luego usar npm ci en despliegues reproducibles. Esto es una tarea técnica futura, no un archivo que se afirme existente. Construir en CI/preproducción con Node compatible y copiar public/build cuando corresponda; no confundir assets propios versionados con compilados ignorados. No se requiere `storage:link` para avatars: se almacenan en MySQL y se sirven autenticados. No crear un enlace público al almacenamiento privado.

El usuario de FPM debe leer código y .env, y escribir únicamente storage/ y bootstrap/cache/. Ejemplo con grupo www-data, desde la aplicación:

```bash
sudo chgrp -R www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
sudo chgrp www-data .env
sudo chmod 640 .env
```

El propietario del código sigue siendo el usuario de despliegue. No usar chmod 777 ni hacer todo el repositorio escribible por la web. Ajustar ACL/propietarios a la instalación real.

## Nginx y TLS

Ejemplo de VirtualHost; sustituir dominio, certificados y socket por los reales. Certificado y DNS deben existir antes de habilitarlo. El único PHP ejecutable es el front controller; no servir el directorio superior como webroot.

```nginx
server {
    listen 80;
    server_name aph.example.org;
    return 301 https://$host$request_uri;
}
server {
    listen 443 ssl;
    server_name aph.example.org;
    root /srv/aph/aph-laravel/public;
    index index.php;
    ssl_certificate /ruta/certificados/fullchain.pem;
    ssl_certificate_key /ruta/certificados/privkey.pem;
    client_max_body_size 2m;

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location = /index.php {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
        fastcgi_param HTTPS on;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    }
    location ~ \.php$ { return 404; }
    location ~ /\.(?!well-known).* { deny all; }
}
```

Configurar upload_max_filesize/post_max_size coherentes con el avatar de 1 MB más el formulario. Probar `nginx -t` antes de recargar. Apache es alternativa: DocumentRoot exclusivamente public/, mod_rewrite y .htaccess existentes, TLS y permisos equivalentes; no se entrega una configuración Apache validada.

Si hay balanceador/terminador TLS, la aplicación necesita proxies confiables explícitos; no están configurados por defecto. Si Laravel interpreta HTTP, SecureResponse devuelve 403 en producción. Ajustar bootstrap/app.php según la topología antes de desplegar detrás de proxy, sin confiar en cabeceras arbitrarias de internet.

## Cachés y arranque

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate:status
```

Revisar cada comando en preproducción con el commit seleccionado. El parámetro opcional APH_REVERSE_URL se lee con getenv en el adaptador: para sobrescribirlo con configuración cacheada debe existir en el entorno real de FPM; añadirlo solo a .env puede no funcionar tras config:cache. El endpoint predeterminado funciona sin esa variable. Ver configuración para esta limitación.

No hay tareas schedule de negocio ni Jobs propios. No instalar Supervisor/cron como si fueran necesarios para guardar fichas. Si posteriormente se implementan tareas, el cron convencional es `* * * * * cd /srv/aph/aph-laravel && php artisan schedule:run` y el trabajador `php artisan queue:work`; deberán agregarse usuario, registros, reinicio y prueba del job real. Actualmente las invitaciones son síncronas.

## Actualización y reversión

1. Elegir commit y revisar diff, dependencias, migraciones y CHANGELOG.
2. Verificar respaldo recuperable y registrar ventana de mantenimiento.
3. `php artisan down`; desplegar código/dependencias del commit, preservando .env y APP_KEY.
4. Ejecutar `php artisan migrate --force`, reconstruir cachés y recargar PHP-FPM según plataforma.
5. Probar login, ámbito provincial, ficha, recursos, mapas, PDF y salida con una cuenta de ensayo autorizada. Después `php artisan up` y prueba por HTTPS externo.
6. Si falla, mantener mantenimiento y decidir retorno de código solo si el esquema sigue compatible. No usar migrate:rollback: las migraciones de cuentas, fichas y auditoría lo rechazan sin cambiar datos, y en producción está prohibido. Para revertir, restaurar respaldo y APP_KEY cuando el esquema cambie de manera incompatible. Conciliar escrituras posteriores al respaldo; no descartarlas silenciosamente.

No se ha ejecutado este procedimiento en un servidor definitivo. Registrar resultados y aprobación conforme a [calidad](calidad-y-trazabilidad.md).

Referencias de configuración general: [Laravel 13, despliegue](https://laravel.com/docs/13.x/deployment) y [colas](https://laravel.com/docs/13.x/queues). Las decisiones y límites específicos anteriores proceden del código APH.

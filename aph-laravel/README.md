> Documento histórico de la entrega anterior. La política vigente de roles y acceso está en docs/Roles-y-activacion.md. Se retiró MFA y las cuentas provinciales ahora son técnicos.

# APH: Laravel y MySQL

La nueva versión conserva los formularios, dashboard, recursos, perfiles, mapas y PDF. Laravel administra autenticación, segundo factor, cookies, CSRF y sesiones. MySQL almacena fichas y estado de seguridad. Las vistas del piloto se conservan mediante un adaptador interno en legacy/, fuera del directorio público. No es una reescritura completa a Eloquent: las fichas conservan su JSON.

## Entorno local

Aplicación: http://127.0.0.1:8090/login
MySQL: 127.0.0.1:3308, base aph.
Pruebas: base aph_test y servidor separado 8091.

Las cuentas importadas conservan usuario, provincia y contraseña. En el primer ingreso se requiere vincular una aplicación autenticadora y guardar los códigos de recuperación. Las pruebas de MFA se realizaron exclusivamente en aph_test: ninguna cuenta operativa fue vinculada por el asistente.

El piloto aph-web permanece intacto. No debe utilizarse en paralelo como otra aplicación operativa, porque las bases divergirían. Las herramientas locales y credenciales están en .tools/ y .env, excluidas de Git.

Para reiniciar la aplicación local, iniciar primero la instancia MySQL existente y luego ejecutar PHP con las extensiones configuradas en .tools/php84.ini. Desde aph-laravel/public se puede iniciar el servidor integrado de PHP en 127.0.0.1:8090 usando como router vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php (relativo al directorio del proyecto). El servidor integrado es exclusivamente para desarrollo.

## Despliegue institucional

Requisitos: PHP >=8.4.1 para el composer.lock actual, extensiones pdo_mysql, openssl, mbstring, fileinfo, curl y zip; MySQL 8.4 y Composer. pdo_sqlite se requiere solamente para importar el piloto.

1. Instalar dependencias con composer install --no-dev --optimize-autoloader.
2. Preparar .env desde .env.example. Configurar dominio HTTPS, APP_ENV=production, APP_DEBUG=false y SESSION_SECURE_COOKIE=true.
3. Generar APP_KEY una sola vez con php artisan key:generate. Respaldarla de forma privada: se necesita para descifrar sesiones y secretos MFA.
4. Crear la base y ejecutar php artisan migrate --force con credenciales de migración.
5. Sobre un destino vacío, ejecutar php artisan aph:import /ruta/aph.sqlite. El origen se abre solo para consultas. Se conservan identificadores e historial y no se copian sesiones anteriores.
6. Usar una cuenta de aplicación con SELECT, INSERT, UPDATE y DELETE sobre su base. Separar las credenciales de migraciones.
7. Configurar la raíz web exclusivamente en public/, TLS y proxies confiables explícitos. No exponer MySQL a internet.
8. Configurar copias protegidas, restauración probada, limpieza periódica de sesiones/caché y retención de registros de seguridad.

No se ha configurado dominio, certificado público, cifrado del volumen ni copias externas. En localhost la cookie Secure se desactiva expresamente para HTTP. La configuración de producción rechaza acceso HTTP o cookies inseguras.

## Recuperación de segundo factor

Usar primero un código de recuperación. Si también se perdió, el administrador técnico debe verificar la identidad y registrar el caso institucional. El comando php artisan aph:mfa-reset permite restablecer el factor para una identidad o:ID o p:provincia, exige --motivo con la referencia del caso, revoca las sesiones y deja registro. No revela contraseñas ni elimina la exigencia de autenticación.

## Pruebas y mantenimiento

phpunit.xml utiliza exclusivamente aph_test. No cambiarlo al nombre de la base operativa. La batería verifica vencimiento, revocación, MFA, reutilización de OTP, política de contraseña, aislamiento provincial, HTTPS y encabezados. Las pruebas en navegador verifican acceso, módulos, guardado, impresión, cookies, CSRF y revocación de la cookie anterior.

Conservar composer.lock. Ejecutar composer audit y probar las actualizaciones antes de publicarlas.

## Alcance y límites

Se conservan 101 fichas, incluidas las 100 de demostración, 24 cuentas provinciales, 1 operador, 6 recursos e historial. El mínimo de seis caracteres es la excepción solicitada y no constituye conformidad NIST. MFA es obligatorio. No hay autoguardado de formularios: los cambios sin guardar pueden perderse al vencer la sesión, con aviso previo. El cifrado de sesiones no equivale al cifrado de los datos clínicos de MySQL.

Consultar docs/Seguridad-y-privacidad.md para las referencias ISO, obligaciones sobre datos de salud y decisiones institucionales pendientes.

# Diccionario físico MySQL

Verificado el 17 de noviembre de 2026 y actualizado el 25 de noviembre de 2026 (atenciones: `solicitud`, `fecha` e índices) contra migraciones y metadatos de MySQL 8.4.13. Incluye las **18 tablas** actuales, incluida migrations. Se consultó information_schema sin leer fichas, credenciales ni filas operativas.

## Convenciones

- PK: clave primaria; UNIQUE: unicidad; FK: integridad referencial física. BIGINT UNSIGNED identifica IDs Laravel. BOOLEAN se materializa como TINYINT(1).
- `—` en predeterminado: no hay valor explícito para columna NOT NULL; se debe suministrar o lo genera AUTO_INCREMENT. `NULL` en columna nullable representa ausencia.
- Las acciones FK se indican tal como las informa MySQL. NO ACTION equivale a RESTRICT en InnoDB; no hay borrados en cascada en este esquema.
- Los nombres y tipos se reproducen del motor, no de un diseño idealizado. Las reglas de negocio no son automáticamente constraints SQL.
- Consultar [ERD](relaciones.md) y [contenido JSON](ficha-json.md).

## `account_invitations`

Una invitación vigente como máximo por cuenta; hashes de ticket y código de activación.

Motor: `InnoDB`. Intercalación: `utf8mb4_unicode_ci`.

| Campo | Tipo MySQL | NULL | Predeterminado | Extra | Significado |
| --- | --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | NO | `—` | auto_increment | Identificador; revisar PK y AUTO_INCREMENT en esta tabla. |
| `account_id` | `bigint unsigned` | NO | `—` | — | Cuenta destinataria de invitación. |
| `ticket_hash` | `varchar(64)` | NO | `—` | — | SHA-256 del ticket aleatorio; no se conserva ticket en claro. |
| `code_hash` | `varchar(64)` | NO | `—` | — | HMAC-SHA256 del código con APP_KEY. |
| `attempts` | `tinyint unsigned` | NO | `0` | — | Intentos de validación; activación rechaza desde cinco. |
| `expires_at` | `timestamp` | NO | `—` | — | Vencimiento de invitación (10 minutos). |
| `sent_at` | `timestamp` | NO | `—` | — | Fecha de creación/envío de invitación. |

### Claves e índices

- `account_invitations_account_id_unique`: UNIQUE (`account_id`).
- `account_invitations_ticket_hash_unique`: UNIQUE (`ticket_hash`).
- `PRIMARY`: PK (`id`).

### Integridad referencial

- `account_invitations_account_id_foreign`: `account_id` → `accounts.id`; ON DELETE **NO ACTION**, ON UPDATE **NO ACTION**.

## `accounts`

Identidades de acceso vigentes y rol nacional/provincial.

Motor: `InnoDB`. Intercalación: `utf8mb4_unicode_ci`.

| Campo | Tipo MySQL | NULL | Predeterminado | Extra | Significado |
| --- | --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | NO | `—` | auto_increment | Identificador; revisar PK y AUTO_INCREMENT en esta tabla. |
| `username` | `varchar(40)` | NO | `—` | — | Usuario de login, único globalmente. |
| `name` | `varchar(200)` | NO | `—` | — | Nombre visible; accounts combina first_name y last_name cuando se edita. |
| `email` | `varchar(255)` | YES | `NULL` | — | Correo de respaldo; dominio institucional autorizado. |
| `province` | `varchar(60)` | YES | `NULL` | — | Clave provincial del catálogo; NULL para nacional. No tiene FK. |
| `role` | `varchar(30)` | NO | `technician` | — | Valores aplicados: technician y superadmin; VARCHAR, no ENUM ni CHECK. |
| `operator_id` | `bigint unsigned` | YES | `NULL` | — | Referencia al operador; NULL admitido en cuenta nacional. |
| `password` | `varchar(255)` | NO | `—` | — | Hash de contraseña, nunca texto claro. |
| `active` | `tinyint(1)` | NO | `0` | — | Bandera de cuenta habilitada. |
| `legacy_access` | `tinyint(1)` | NO | `0` | — | Excepción temporal: permite acceso sin email verificado. |
| `email_verified_at` | `timestamp` | YES | `NULL` | — | Fecha de verificación real, no inferida del dominio. |
| `revision` | `int unsigned` | NO | `0` | — | Versión de credenciales para revocar sesiones o de identidad operativa. |
| `last_login_at` | `timestamp` | YES | `NULL` | — | Último inicio de sesión correcto. |
| `created_at` | `timestamp` | YES | `NULL` | — | Fecha de creación; en jobs/job_batches es Unix, en cuentas/auditoría timestamp. |
| `updated_at` | `timestamp` | YES | `NULL` | — | Última actualización de cuenta. |
| `disabled_at` | `timestamp` | YES | `NULL` | — | Suspensión administrativa; evita reactivación por invitación. |
| `first_name` | `varchar(100)` | YES | `NULL` | — | Nombres personales; nullable para cuentas migradas. |
| `last_name` | `varchar(100)` | YES | `NULL` | — | Apellidos personales; nullable para cuentas migradas. |

### Claves e índices

- `accounts_email_unique`: UNIQUE (`email`).
- `accounts_operator_id_foreign`: INDEX (`operator_id`).
- `accounts_username_unique`: UNIQUE (`username`).
- `PRIMARY`: PK (`id`).

### Integridad referencial

- `accounts_operator_id_foreign`: `operator_id` → `operadores.id`; ON DELETE **NO ACTION**, ON UPDATE **NO ACTION**.

## `atenciones`

Ficha versionada con datos clínicos en JSON y eliminación lógica.

Motor: `InnoDB`. Intercalación: `utf8mb4_unicode_ci`.

| Campo | Tipo MySQL | NULL | Predeterminado | Extra | Significado |
| --- | --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | NO | `—` | auto_increment | Identificador; revisar PK y AUTO_INCREMENT en esta tabla. |
| `version` | `int` | NO | `—` | — | Versión optimista de ficha; empieza en 1 en la aplicación. |
| `estado` | `varchar(20)` | NO | `—` | — | Pendiente o Cerrada por aplicación; sin ENUM/CHECK físico. |
| `datos` | `json` | NO | `—` | — | Objeto JSON: A–AD, coordenadas y recurso_tipo; ver diccionario JSON. |
| `provincia` | `varchar(60)` | NO | `—` | — | Clave provincial; ver FK o vínculo lógico de esta tabla. |
| `recurso_id` | `bigint unsigned` | YES | `NULL` | — | Unidad asignada; puede ser NULL en datos históricos. |
| `responsable_id` | `bigint unsigned` | YES | `NULL` | — | Operador responsable vinculado; puede ser NULL históricamente. |
| `eliminado` | `varchar(255)` | YES | `NULL` | — | Fecha ISO 8601 textual de borrado lógico; NULL indica visible. |
| `solicitud` | `char(36)` | YES | `NULL` | — | Identificador del formulario de creación; evita fichas duplicadas por doble envío. |
| `fecha` | `varchar(10)` | YES | — | VIRTUAL GENERATED | Fecha de atención (clave B) para filtrar por periodo. |

### Claves e índices

- `atenciones_solicitud_unique`: UNIQUE (`solicitud`).
- Índices (`provincia`,`eliminado`,`fecha`), (`provincia`,`eliminado`,`estado`), (`eliminado`,`fecha`) y (`eliminado`,`estado`).
- `atenciones_provincia_id_index`: INDEX (`provincia`, `id`).
- `atenciones_recurso_id_foreign`: INDEX (`recurso_id`).
- `atenciones_responsable_id_foreign`: INDEX (`responsable_id`).
- `PRIMARY`: PK (`id`).

### Integridad referencial

- `atenciones_provincia_foreign`: `provincia` → `usuarios.provincia`; ON DELETE **NO ACTION**, ON UPDATE **NO ACTION**.
- `atenciones_recurso_id_foreign`: `recurso_id` → `recursos.id`; ON DELETE **NO ACTION**, ON UPDATE **NO ACTION**.
- `atenciones_responsable_id_foreign`: `responsable_id` → `operadores.id`; ON DELETE **NO ACTION**, ON UPDATE **NO ACTION**.

## `auth_security`

Tabla heredada de MFA, retirada del flujo activo y vaciada al unificar cuentas.

Motor: `InnoDB`. Intercalación: `utf8mb4_unicode_ci`.

| Campo | Tipo MySQL | NULL | Predeterminado | Extra | Significado |
| --- | --- | --- | --- | --- | --- |
| `identity` | `varchar(255)` | NO | `—` | — | Identidad textual de auditoría o MFA heredado, sin FK. |
| `secret` | `text` | NO | `—` | — | Secreto cifrado del MFA retirado; sin uso actual. |
| `last_step` | `bigint` | NO | `0` | — | Control TOTP heredado; sin uso actual. |
| `recovery` | `text` | NO | `—` | — | Códigos de recuperación heredados; sin uso actual. |

### Claves e índices

- `PRIMARY`: PK (`identity`).

### Integridad referencial

No tiene claves foráneas físicas salientes.

## `cache`

Caché de Laravel, incluidos contadores temporales de límites.

Motor: `InnoDB`. Intercalación: `utf8mb4_unicode_ci`.

| Campo | Tipo MySQL | NULL | Predeterminado | Extra | Significado |
| --- | --- | --- | --- | --- | --- |
| `key` | `varchar(255)` | NO | `—` | — | Clave de caché. |
| `value` | `mediumtext` | NO | `—` | — | Valor serializado de caché. |
| `expiration` | `bigint` | NO | `—` | — | Vencimiento Unix. |

### Claves e índices

- `cache_expiration_index`: INDEX (`expiration`).
- `PRIMARY`: PK (`key`).

### Integridad referencial

No tiene claves foráneas físicas salientes.

## `cache_locks`

Bloqueos atómicos del almacén de caché.

Motor: `InnoDB`. Intercalación: `utf8mb4_unicode_ci`.

| Campo | Tipo MySQL | NULL | Predeterminado | Extra | Significado |
| --- | --- | --- | --- | --- | --- |
| `key` | `varchar(255)` | NO | `—` | — | Clave de caché. |
| `owner` | `varchar(255)` | NO | `—` | — | Propietario del bloqueo. |
| `expiration` | `bigint` | NO | `—` | — | Vencimiento Unix. |

### Claves e índices

- `cache_locks_expiration_index`: INDEX (`expiration`).
- `PRIMARY`: PK (`key`).

### Integridad referencial

No tiene claves foráneas físicas salientes.

## `cambios`

Historial de creación, corrección y eliminación de fichas.

Motor: `InnoDB`. Intercalación: `utf8mb4_unicode_ci`.

| Campo | Tipo MySQL | NULL | Predeterminado | Extra | Significado |
| --- | --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | NO | `—` | auto_increment | Identificador; revisar PK y AUTO_INCREMENT en esta tabla. |
| `atencion` | `bigint unsigned` | NO | `—` | — | Ficha a la que pertenece el cambio. |
| `fecha` | `varchar(255)` | NO | `—` | — | Fecha ISO 8601 almacenada como texto. |
| `autor` | `varchar(255)` | NO | `—` | — | Nombre y usuario en texto; no FK, conserva identificación histórica. |
| `motivo` | `text` | NO | `—` | — | Justificación de corrección o acción registrada. |
| `detalle` | `longtext` | NO | `—` | — | JSON serializado como LONGTEXT con pares anterior/nuevo; no tipo JSON nativo. |

### Claves e índices

- `cambios_atencion_foreign`: INDEX (`atencion`).
- `PRIMARY`: PK (`id`).

### Integridad referencial

- `cambios_atencion_foreign`: `atencion` → `atenciones.id`; ON DELETE **NO ACTION**, ON UPDATE **NO ACTION**.

## `failed_jobs`

Fallos de trabajos en cola; infraestructura disponible.

Motor: `InnoDB`. Intercalación: `utf8mb4_unicode_ci`.

| Campo | Tipo MySQL | NULL | Predeterminado | Extra | Significado |
| --- | --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | NO | `—` | auto_increment | Identificador; revisar PK y AUTO_INCREMENT en esta tabla. |
| `uuid` | `varchar(255)` | NO | `—` | — | Identificador único de fallo. |
| `connection` | `varchar(255)` | NO | `—` | — | Conexión de cola. |
| `queue` | `varchar(255)` | NO | `—` | — | Cola asociada. |
| `payload` | `longtext` | NO | `—` | — | Contenido serializado de sesión o trabajo; no publicar. |
| `exception` | `longtext` | NO | `—` | — | Detalle de excepción; puede contener información sensible. |
| `failed_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` | DEFAULT_GENERATED | Fecha del fallo. |

### Claves e índices

- `failed_jobs_connection_queue_failed_at_index`: INDEX (`connection`, `queue`, `failed_at`).
- `failed_jobs_uuid_unique`: UNIQUE (`uuid`).
- `PRIMARY`: PK (`id`).

### Integridad referencial

No tiene claves foráneas físicas salientes.

## `geocodificacion`

Caché de dirección inversa por hash de consulta.

Motor: `InnoDB`. Intercalación: `utf8mb4_unicode_ci`.

| Campo | Tipo MySQL | NULL | Predeterminado | Extra | Significado |
| --- | --- | --- | --- | --- | --- |
| `clave` | `varchar(64)` | NO | `—` | — | SHA-256 del endpoint y consulta redondeada, no contraseña. |
| `direccion` | `text` | NO | `—` | — | Dirección textual de geocodificación. |

### Claves e índices

- `PRIMARY`: PK (`clave`).

### Integridad referencial

No tiene claves foráneas físicas salientes.

## `job_batches`

Metadatos de lotes de colas; infraestructura disponible, sin uso de negocio actual.

Motor: `InnoDB`. Intercalación: `utf8mb4_unicode_ci`.

| Campo | Tipo MySQL | NULL | Predeterminado | Extra | Significado |
| --- | --- | --- | --- | --- | --- |
| `id` | `varchar(255)` | NO | `—` | — | Identificador; revisar PK y AUTO_INCREMENT en esta tabla. |
| `name` | `varchar(255)` | NO | `—` | — | Nombre visible; accounts combina first_name y last_name cuando se edita. |
| `total_jobs` | `int` | NO | `—` | — | Total de trabajos del lote. |
| `pending_jobs` | `int` | NO | `—` | — | Trabajos aún pendientes. |
| `failed_jobs` | `int` | NO | `—` | — | Cantidad fallida en lote. |
| `failed_job_ids` | `longtext` | NO | `—` | — | IDs fallidos serializados. |
| `options` | `mediumtext` | YES | `NULL` | — | Opciones serializadas del lote. |
| `cancelled_at` | `int` | YES | `NULL` | — | Cancelación Unix. |
| `created_at` | `int` | NO | `—` | — | Fecha de creación; en jobs/job_batches es Unix, en cuentas/auditoría timestamp. |
| `finished_at` | `int` | YES | `NULL` | — | Finalización Unix. |

### Claves e índices

- `PRIMARY`: PK (`id`).

### Integridad referencial

No tiene claves foráneas físicas salientes.

## `jobs`

Infraestructura de colas Laravel, sin Jobs de negocio actuales.

Motor: `InnoDB`. Intercalación: `utf8mb4_unicode_ci`.

| Campo | Tipo MySQL | NULL | Predeterminado | Extra | Significado |
| --- | --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | NO | `—` | auto_increment | Identificador; revisar PK y AUTO_INCREMENT en esta tabla. |
| `queue` | `varchar(255)` | NO | `—` | — | Cola asociada. |
| `payload` | `longtext` | NO | `—` | — | Contenido serializado de sesión o trabajo; no publicar. |
| `attempts` | `smallint unsigned` | NO | `—` | — | Intentos de ejecución del trabajador. |
| `reserved_at` | `int unsigned` | YES | `NULL` | — | Reserva Unix del trabajador. |
| `available_at` | `int unsigned` | NO | `—` | — | Momento Unix disponible. |
| `created_at` | `int unsigned` | NO | `—` | — | Fecha de creación; en jobs/job_batches es Unix, en cuentas/auditoría timestamp. |

### Claves e índices

- `jobs_queue_index`: INDEX (`queue`).
- `PRIMARY`: PK (`id`).

### Integridad referencial

No tiene claves foráneas físicas salientes.

## `migrations`

Control de migraciones ejecutadas por Laravel.

Motor: `InnoDB`. Intercalación: `utf8mb4_unicode_ci`.

| Campo | Tipo MySQL | NULL | Predeterminado | Extra | Significado |
| --- | --- | --- | --- | --- | --- |
| `id` | `int unsigned` | NO | `—` | auto_increment | Identificador; revisar PK y AUTO_INCREMENT en esta tabla. |
| `migration` | `varchar(255)` | NO | `—` | — | Nombre de archivo de migración aplicada. |
| `batch` | `int` | NO | `—` | — | Lote de ejecución de migraciones. |

### Claves e índices

- `PRIMARY`: PK (`id`).

### Integridad referencial

No tiene claves foráneas físicas salientes.

## `operadores`

Identidad operativa provincial vinculada a fichas y cuentas.

Motor: `InnoDB`. Intercalación: `utf8mb4_unicode_ci`.

| Campo | Tipo MySQL | NULL | Predeterminado | Extra | Significado |
| --- | --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | NO | `—` | auto_increment | Identificador; revisar PK y AUTO_INCREMENT en esta tabla. |
| `provincia` | `varchar(60)` | NO | `—` | — | Clave provincial; ver FK o vínculo lógico de esta tabla. |
| `usuario` | `varchar(40)` | NO | `—` | — | Identificador operativo/perfil, no FK global a accounts. |
| `nombre` | `varchar(255)` | NO | `—` | — | Nombre visible histórico u operativo. |
| `perfil` | `varchar(255)` | NO | `—` | — | Etiqueta operativa; no determina el rol de autenticación. |
| `clave` | `varchar(255)` | NO | `—` | — | Hash de contraseña heredada u operativa; login actual consulta accounts. |
| `activo` | `tinyint(1)` | NO | `1` | — | Operador habilitado. |
| `revision` | `int` | NO | `0` | — | Versión de credenciales para revocar sesiones o de identidad operativa. |

### Claves e índices

- `operadores_provincia_usuario_unique`: UNIQUE (`provincia`, `usuario`).
- `PRIMARY`: PK (`id`).

### Integridad referencial

- `operadores_provincia_foreign`: `provincia` → `usuarios.provincia`; ON DELETE **NO ACTION**, ON UPDATE **NO ACTION**.

## `perfiles`

Nombre e imagen del perfil por provincia y usuario; sin FK física.

Motor: `InnoDB`. Intercalación: `utf8mb4_unicode_ci`.

| Campo | Tipo MySQL | NULL | Predeterminado | Extra | Significado |
| --- | --- | --- | --- | --- | --- |
| `provincia` | `varchar(60)` | NO | `—` | — | Clave provincial; ver FK o vínculo lógico de esta tabla. |
| `usuario` | `varchar(40)` | NO | `—` | — | Identificador operativo/perfil, no FK global a accounts. |
| `nombre` | `varchar(255)` | NO | `—` | — | Nombre visible histórico u operativo. |
| `imagen` | `mediumblob` | YES | `NULL` | — | Contenido binario de avatar; validación: JPG/PNG/WebP, 1 MB, máximo 2048 px. |
| `imagen_tipo` | `varchar(255)` | YES | `NULL` | — | MIME de avatar. |

### Claves e índices

- `PRIMARY`: PK (`provincia`, `usuario`).

### Integridad referencial

No tiene claves foráneas físicas salientes.

## `recursos`

Unidades despachables de cada provincia.

Motor: `InnoDB`. Intercalación: `utf8mb4_unicode_ci`.

| Campo | Tipo MySQL | NULL | Predeterminado | Extra | Significado |
| --- | --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | NO | `—` | auto_increment | Identificador; revisar PK y AUTO_INCREMENT en esta tabla. |
| `provincia` | `varchar(60)` | NO | `—` | — | Clave provincial; ver FK o vínculo lógico de esta tabla. |
| `numero` | `varchar(300)` | NO | `—` | — | Identificador de unidad; único por provincia. |
| `ubicacion` | `varchar(300)` | NO | `—` | — | Referencia textual del recurso. |
| `capacidad` | `varchar(300)` | NO | `—` | — | Capacidad de respuesta textual. |
| `combustible` | `varchar(300)` | NO | `—` | — | Tipo de combustible del recurso. |
| `tipo` | `varchar(300)` | NO | `Ambulancia` | — | Tipo de unidad; valor predeterminado Ambulancia. |

### Claves e índices

- `PRIMARY`: PK (`id`).
- `recursos_provincia_numero_unique`: UNIQUE (`provincia`, `numero`).

### Integridad referencial

- `recursos_provincia_foreign`: `provincia` → `usuarios.provincia`; ON DELETE **NO ACTION**, ON UPDATE **NO ACTION**.

## `security_events`

Auditoría de autenticación, acceso, salidas y cambios administrativos.

Motor: `InnoDB`. Intercalación: `utf8mb4_unicode_ci`.

| Campo | Tipo MySQL | NULL | Predeterminado | Extra | Significado |
| --- | --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | NO | `—` | auto_increment | Identificador; revisar PK y AUTO_INCREMENT en esta tabla. |
| `identity` | `varchar(255)` | YES | `NULL` | — | Identidad textual de auditoría o MFA heredado, sin FK. |
| `event` | `varchar(60)` | NO | `—` | — | Tipo de evento de auditoría. |
| `ip` | `varchar(45)` | YES | `NULL` | — | IP observada por Laravel; depende de proxy confiable. |
| `created_at` | `timestamp` | NO | `—` | — | Fecha de creación; en jobs/job_batches es Unix, en cuentas/auditoría timestamp. |
| `reference` | `varchar(300)` | YES | `NULL` | — | Detalle opcional; en profile_name_changed, nombre anterior y nuevo. |
| `target` | `varchar(80)` | YES | `NULL` | — | Objetivo textual de auditoría, ej. account:ID; sin FK. |

### Claves e índices

- `PRIMARY`: PK (`id`).
- `security_events_created_at_index`: INDEX (`created_at`).
- `security_events_identity_index`: INDEX (`identity`).

### Integridad referencial

No tiene claves foráneas físicas salientes.

## `sessions`

Sesiones de Laravel almacenadas en MySQL; payload cifrado por configuración.

Motor: `InnoDB`. Intercalación: `utf8mb4_unicode_ci`.

| Campo | Tipo MySQL | NULL | Predeterminado | Extra | Significado |
| --- | --- | --- | --- | --- | --- |
| `id` | `varchar(255)` | NO | `—` | — | Identificador aleatorio de sesión, no ID numérico. |
| `user_id` | `varchar(255)` | YES | `NULL` | — | ID de accounts serializado; índice sin FK, no eliminar cuentas físicamente. |
| `ip_address` | `varchar(45)` | YES | `NULL` | — | Dirección IP de sesión. |
| `user_agent` | `text` | YES | `NULL` | — | Agente de navegador. |
| `payload` | `longtext` | NO | `—` | — | Contenido serializado de sesión o trabajo; no publicar. |
| `last_activity` | `int` | NO | `—` | — | Última actividad técnica del almacén; límite funcional adicional en payload. |

### Claves e índices

- `PRIMARY`: PK (`id`).
- `sessions_last_activity_index`: INDEX (`last_activity`).
- `sessions_user_id_index`: INDEX (`user_id`).

### Integridad referencial

No tiene claves foráneas físicas salientes.

## `usuarios`

Ancla provincial y credenciales heredadas. No es la fuente actual de login.

Motor: `InnoDB`. Intercalación: `utf8mb4_unicode_ci`.

| Campo | Tipo MySQL | NULL | Predeterminado | Extra | Significado |
| --- | --- | --- | --- | --- | --- |
| `provincia` | `varchar(60)` | NO | `—` | — | Clave provincial; ver FK o vínculo lógico de esta tabla. |
| `clave` | `varchar(255)` | NO | `—` | — | Hash de contraseña heredada u operativa; login actual consulta accounts. |

### Claves e índices

- `PRIMARY`: PK (`provincia`).

### Integridad referencial

No tiene claves foráneas físicas salientes.

## Actualización del diccionario

Las migraciones en `aph-laravel/database/migrations/` son la fuente de cambios. Tras modificar el esquema: aplicar primero en base desechable, revisar `SHOW CREATE TABLE`, `information_schema.COLUMNS`, `STATISTICS`, `KEY_COLUMN_USAGE` y `REFERENTIAL_CONSTRAINTS`; actualizar este archivo y el ERD en el mismo cambio. No exportar filas operativas para generar documentación. No usar `migrate:fresh` sobre la base operativa.

## Consulta de verificación de estructura (solo lectura)

Ejecutar con base seleccionada y permiso de metadatos; no se necesitan datos de pacientes:

```sql
SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY TABLE_NAME, ORDINAL_POSITION;

SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

SELECT k.TABLE_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME,
       k.REFERENCED_COLUMN_NAME, r.DELETE_RULE, r.UPDATE_RULE
FROM information_schema.KEY_COLUMN_USAGE k
JOIN information_schema.REFERENTIAL_CONSTRAINTS r
  ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
 AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
WHERE k.TABLE_SCHEMA = DATABASE();
```

Los índices UNIQUE permiten varios NULL en campos nullable como email; el código define cuándo se exige correo. Los tipos VARCHAR y LONGTEXT usan la intercalación de cada tabla; no se debe suponer comparación sensible a mayúsculas. Los índices actuales no incluyen expresiones JSON de datos.K ni datos.B; evaluar índices/normalización con medidas antes de ampliar volumen.

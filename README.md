# APH · Cruz Roja Ecuatoriana

Sistema web para el registro, seguimiento y análisis de atenciones prehospitalarias, con gestión provincial y supervisión nacional. Desarrollado en **PHP con Laravel y MySQL**.

## Origen y problemática

El proyecto nació de matrices institucionales de **Excel utilizadas a nivel provincial** para registrar las atenciones prehospitalarias de la Cruz Roja Ecuatoriana. Según la problemática identificada durante el desarrollo, los registros llegaban incompletos, presentaban errores de llenado y requerían correcciones cuando se actualizaba la información. La gestión mediante archivos dificultaba mantener una versión consistente de los datos, conocer quién había realizado un cambio y consolidar la información para su revisión nacional.

El propósito del sistema es mejorar ese proceso conservando la estructura y los campos de las matrices institucionales. El piloto se planteó inicialmente para **Azuay**, con alcance posterior a las **24 provincias**. Se evaluó AppSheet y se construyó un piloto web en PHP; la versión actual utiliza Laravel y MySQL para organizar el acceso, las sesiones y la gestión de la información.

La aplicación incorpora validaciones, catálogos, permisos provinciales e historial para reducir errores y facilitar correcciones. Estas funciones apoyan la calidad del registro, pero no sustituyen la revisión de la información por el personal responsable. No se han medido todavía porcentajes de reducción de errores o mejoras operativas.

## Funcionalidades

### Registro y seguimiento de atenciones

- Identificación de cada ficha por su **número de hoja de atención**.
- Registro de datos del evento, paciente, diagnóstico, recurso despachado y destino.
- Catálogo CIE-10 y catálogo de eventos adversos incorporado a partir de la documentación institucional proporcionada.
- Cantones vinculados a la provincia y validaciones de los campos de identificación.
- Estados **Pendiente** y **Cerrada**, con indicadores de total, cerradas y pendientes por provincia.
- Filtros por provincia y estado; las filas pendientes se resaltan en amarillo.
- Edición con retorno contextual al listado, conservando provincia, estado y página.
- Eliminación lógica mediante confirmación en un modal, conservando la ficha y el historial de quién la eliminó.
- Historial de cambios y control de versión para detectar modificaciones concurrentes.

### Recursos y operación provincial

- Registro de ambulancias y otros recursos: número, ubicación, capacidad de respuesta, tipo y combustible.
- Vinculación del recurso despachado con la ficha de atención.
- Registro de kilometraje de salida y llegada, y cálculo de la distancia recorrida.
- Registro de combustible de salida y llegada.

### Usuarios y seguridad

- Dos roles: **superadministrador nacional** y **técnico provincial de atención prehospitalaria**.
- El superadministrador consulta y gestiona información nacional; cada técnico gestiona las fichas y recursos de su provincia.
- Panel de usuarios activos e inactivos, con edición en modal de nombre, apellido, correo, contraseña, rol y provincia.
- Validación del correo contra los dominios institucionales autorizados.
- Reasignación provincial sin trasladar las fichas históricas, y revocación de sesiones ante cambios administrativos.
- Inicio de sesión con usuario y contraseña, sin doble factor, conforme a la decisión adoptada para el piloto.
- Protección CSRF, límite de intentos, contraseñas almacenadas mediante hash y comprobación de contraseñas filtradas al establecer nuevas claves.
- Aviso a los ocho minutos de inactividad, vencimiento a los diez minutos y duración máxima de sesión de ocho horas.
- Cookies HttpOnly y SameSite; uso de Secure y HTTPS exigido en producción.
- Cierre de sesión disponible en todos los encabezados; acceso a Seguridad desde Mi perfil.

Las nuevas contraseñas requieren de 8 a 72 caracteres ASCII, con mayúscula, minúscula, número y uno de los símbolos permitidos (`. @ _ - ! # $ %`). Las contraseñas existentes no se cambian automáticamente. Esta política **no constituye una certificación ni una declaración de cumplimiento de estándares de seguridad**. Las cuentas migradas mantienen temporalmente acceso sin verificación de correo. Los detalles y límites están en [Roles y activación](aph-laravel/docs/Roles-y-activacion.md) y [Seguridad y privacidad](aph-laravel/docs/Seguridad-y-privacidad.md).

### Dashboard y georreferenciación

- Gráficos por evento o causa, sexo, edad, nacionalidad, discapacidad, identificación, tipos de recurso y kilómetros recorridos por fecha.
- Filtros cruzados al seleccionar categorías, provincias o puntos de los mapas.
- Mapas de ubicación del evento y localidad de destino.
- Mapa coroplético de Ecuador con las 24 provincias, recuadro de Galápagos, escala de colores y cantidades de atenciones.
- Botones provinciales en orden alfabético fijo, con estado seleccionado visible.
- Selección de coordenadas mediante un pin y consulta de dirección de referencia, sin exigir al operador introducir coordenadas manualmente.
- Uso de Leaflet y OpenStreetMap. La configuración transmite solo el origen del sitio como Referer, sin rutas ni identificadores de fichas. La cartografía provincial de referencia procede de geoBoundaries y representa límites de 2011.

### Interfaz y salidas

- Diseño adaptable a escritorio y teléfono celular, con colores, logos y recursos gráficos institucionales proporcionados para el proyecto.
- Menú lateral, navegación contextual, íconos y énfasis visual en acciones.
- Perfil personal, cambio de contraseña e imagen.
- Vista de impresión con línea gráfica institucional y mapas de referencia, que puede guardarse como PDF desde el navegador.
- Exportación de atenciones cerradas a Excel y de ubicaciones en GeoJSON.

## Estructura técnica

El código vigente está en [`aph-laravel/`](aph-laravel/):

| Directorio | Contenido |
| --- | --- |
| `app/` | Autenticación, controladores, permisos y servicios |
| `database/migrations/` | Evolución del esquema MySQL |
| `resources/views/` | Vistas Blade de acceso, administración y panel nacional |
| `legacy/` | Formularios y lógica del piloto integrados con Laravel; catálogos institucionales |
| `public/` | JavaScript, CSS, Leaflet, cartografía y recursos gráficos |
| `tests/` | Pruebas automatizadas |
| `docs/` | Decisiones técnicas, seguridad y antecedentes |

La aplicación conserva parte de los formularios originales adaptados a Laravel; no es una reescritura completa de toda la lógica a modelos Eloquent. La conexión a MySQL se administra desde Laravel y la configuración privada `.env`. Las fichas mantienen campos estructurados junto con datos de formulario en JSON.

## Estado y puesta en marcha

El sistema funciona como **piloto local**. Subirlo a GitHub guarda y versiona el código; no lo publica como servicio accesible en internet.

Para un despliegue institucional deben prepararse PHP y sus extensiones, Composer, MySQL, la configuración `.env`, la clave de aplicación, las migraciones y el aprovisionamiento autorizado de cuentas. La raíz del servidor web debe ser exclusivamente `aph-laravel/public/`. Consultar el [README técnico](aph-laravel/README.md), teniendo en cuenta que sus apartados históricos quedan sustituidos por la [política vigente](aph-laravel/docs/Roles-y-activacion.md).

Permanecen pendientes la URL pública HTTPS, el servicio institucional de correo y la validación del despliegue definitivo, incluyendo respaldos y restauración, protección del almacenamiento y procedimientos de tratamiento de datos médicos. Las referencias a normas ISO en la documentación son orientativas y no acreditan certificación.

## Datos y contenido del repositorio

Este repositorio contiene código, migraciones, pruebas, catálogos y recursos de la aplicación. **No incluye contraseñas, claves de configuración, bases de datos operativas, fichas de pacientes, matrices Excel originales, archivos de cuentas ni registros de ejecución.** `.env.example` es una plantilla sin secretos.

Las pruebas utilizan una base independiente denominada `aph_test`. Los ejemplos y datos de demostración del piloto no deben confundirse con información asistencial validada.

## Estado de la documentación

La documentación aún es parcial. Consultar la [revisión por requisito](docs/estado-documentacion.md) y el [registro de cambios](CHANGELOG.md). Los documentos de AppSheet y el diccionario de la matriz Excel son antecedentes, no describen el esquema físico MySQL vigente.

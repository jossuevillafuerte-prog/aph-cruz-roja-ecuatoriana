# Diccionario del JSON de la ficha

`atenciones.datos` es JSON nativo; los campos A–AE se guardan normalmente como cadenas, incluidos números y fechas. Ausencia habitual: cadena vacía, no NULL. El formulario limita entradas no derivadas a 4000 bytes. No se ha convertido el Excel a una tabla SQL por columna.

| Clave | Encabezado institucional | Regla y significado |
| --- | --- | --- |
| `A` | Columna1 | Derivado: prefijo de C. |
| `B` | Fecha_de_atencion | Fecha ISO YYYY-MM-DD válida. Obligatoria incluso en pendiente. |
| `C` | Codigo_APH_Salud | Código APH; obligatorio al cerrar. |
| `D` | Tipo_Codigo_APH_Salud | Derivado del catálogo APH. |
| `E` | Categoria_Codigo_APH_Salud | Derivado del catálogo APH. |
| `F` | Evento_Causa | Nombre de evento/subtipo del catálogo institucional. |
| `G` | Canton | Cantón de la provincia; obligatorio al cerrar. |
| `H` | Direccion_evento | Dirección textual, normalmente obtenida al marcar mapa. |
| `I` | Hora_llegada | Hora HH:MM de 24 horas si se informa. |
| `J` | Recurso_Despacho | Número de unidad copiado por el servidor desde recurso_id; conserva excepciones históricas. |
| `K` | Hoja_atencion | Número de hoja: texto, conserva ceros; no es PK ni UNIQUE. |
| `L` | Nombres_y_apellidos_PACIENTE | Nombre de paciente, dato sensible. |
| `M` | Tipo_identificacion | Tipo de identificación del catálogo. |
| `N` | Nro_identificacion | Cédula: exactamente 10 dígitos, sin validación de dígito verificador. Otros documentos: 1–80 caracteres alfanuméricos, espacios, puntos, barras o guiones. |
| `O` | Sexo | Sexo del catálogo. |
| `P` | Edad | Entero textual entre 0 y 120. |
| `Q` | Nacionalidad | Nacionalidad del catálogo. |
| `R` | Condicion | Condición del catálogo. |
| `S` | Discapacidad | Discapacidad del catálogo. |
| `T` | Diagnostico_descrito_territorio | Diagnóstico descrito; dato clínico sensible. |
| `U` | CIE_10 | Código del catálogo CIE-10 OMS 2019 incorporado; normaliza mayúsculas y retira punto. |
| `V` | Descripcion_Diagnostico_Codigo_CIE_10 | Descripción derivada del código U. |
| `W` | Localidad_destino | Destino textual, normalmente completado por mapa. |
| `X` | Nombres_medico_recepcion_paciente | Nombre del médico receptor. |
| `Y` | Responsable | Responsable asignado por servidor al crear; se conserva al corregir. Desde el 25/11/2026 incluye el usuario, «Nombre (usuario)». No campo editable libre. |
| `Z` | km_salida | Kilometraje de salida no negativo. |
| `AA` | km_llegada | Kilometraje de llegada no negativo; si cambia, no menor que Z. |
| `AB` | combustible_salida | Nivel de combustible de salida del catálogo. |
| `AC` | combustible_llegada | Nivel de combustible de llegada del catálogo. |
| `AD` | Observaciones | Observaciones de atención. |
| `AE` | Zona_afectada | Zona del mapa corporal (desde el 26/11/2026): una de las 12 zonas de `BODY_ZONES` en `legacy/modelo.php`, o vacía. Las fichas anteriores no la tienen. Sale en la hoja de impresión y como última columna del Excel. |

## Extensiones del formulario

| Clave | Tipo JSON | Uso |
| --- | --- | --- |
| evento_lat / destino_lat | número o null | Latitud de referencia, −90 a 90; redondeada a seis decimales. |
| evento_lon / destino_lon | número o null | Longitud de referencia, −180 a 180; redondeada a seis decimales. |
| recurso_tipo | cadena | Copia del tipo de recurso para conservar contexto histórico. |
| demo_lote | cadena | Opcional. Marca una ficha ficticia; la impresión muestra el aviso. |

Las claves no listadas se conservan al guardar y sus cambios quedan en el historial.

Los pares de coordenadas se validan juntos. Si no llegan al actualizar, se conservan los anteriores; ambos campos vacíos los limpian. GeoJSON exporta `[longitud, latitud]`; Leaflet usa `[latitud, longitud]`. No intercambiar el orden.

## Reglas de captura y cierre

Guardar pendiente requiere B. Cerrar requiere B, C, G y Y, además de las validaciones de valores suministrados y el número de documento cuando el tipo seleccionado lo exige. No se exige que absolutamente todos los campos estén llenos. F/G antiguos pueden conservarse aunque ya no estén en el catálogo; documentos y kilometrajes históricos tienen excepciones de conservación. La justificación es exigida al corregir una ficha cerrada, no para crear cualquier borrador.

Los derivados A/D/E/V no se aceptan como verdad desde el navegador. Se recalculan desde los catálogos. El responsable queda vinculado mediante responsable_id y texto Y; los cambios mantienen su autor en cambios. No existe integridad SQL de las claves interiores del JSON.

## Catálogos y mantenimiento

- `legacy/catalogos.json`: códigos APH y listas del formulario.
- `legacy/provincias.json`: 24 claves territoriales y cantones; los dominios de correo se gestionan aparte en config/institution.php.
- `legacy/eventos-sngr.tsv`: catálogo incorporado de eventos adversos. Validar nuevas revisiones institucionales antes de reemplazarlo.
- `legacy/cie10-oms-2019.json`: edición incorporada identificada por el archivo, no una actualización automática al catálogo más reciente.

Toda actualización de catálogo debe preservar lectura de fichas históricas, probar búsqueda/selección/exportación y registrar fuente, edición y fecha. No se debe reinterpretar silenciosamente un diagnóstico previo.

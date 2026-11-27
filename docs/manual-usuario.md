# Manual de usuario APH

Audiencia: técnicos provinciales de atención prehospitalaria y superadministrador nacional. Versión 1.1, 25 de noviembre de 2026. La URL definitiva será comunicada por la institución; el piloto local usa http://127.0.0.1:8090/login. Las imágenes de marca no implican aprobación institucional del sistema.

## Ingresar y salir

1. Abra la URL autorizada. Ingrese su **usuario**, no necesariamente el correo, y su contraseña.
2. El sistema muestra el panel provincial o nacional según su cuenta. No debe compartir usuarios ni credenciales.
3. Use el botón Cerrar sesión del encabezado o del menú lateral al terminar, especialmente en equipos compartidos. Cerrar una pestaña no garantiza el cierre inmediato de la sesión.
4. Tras varios errores, espere cinco minutos. Si olvidó la clave, contacte al superadministrador: no hay recuperación autónoma de contraseña implementada.

Si aún no tiene cuenta, pulse «Solicitar cuenta» en el inicio de sesión y complete nombres, apellidos, provincia, correo institucional (de su provincia o @cruzroja.org.ec) y el usuario que desea. El superadministrador revisa la solicitud; si la aprueba, recibirá en ese correo el enlace y el código para activar la cuenta y crear su contraseña. Si la institución publicó un tablero, el botón «Ver tablero Power BI» lo abre en otra pestaña.

No se pide segundo factor para el login diario. Una cuenta nueva puede requerir activación de correo. Las cuentas migradas tienen excepción temporal autorizada; no se debe asumir que cualquier correo escrito ya esté verificado.

## Menú y perfil

El botón hamburguesa abre la navegación. Mi perfil permite cambiar nombre, avatar y contraseña. Para cambiar la clave, escriba actual, nueva y confirmación. La nueva debe tener 8–72 caracteres, una mayúscula, una minúscula, un número y un símbolo permitido `. @ _ - ! # $ %`; sin tildes, ñ ni espacios. No se aceptan claves comunes o filtradas. Si falla la verificación externa, reintente o avise a soporte.

Avatar: JPG, PNG o WebP de hasta 1 MB y 2048 píxeles por lado. El acceso Seguridad aparece en el menú de Mi perfil y permite cerrar todas las sesiones propias, confirmando la contraseña. El cierre incluye la sesión actual.

## Crear una atención

La ficha tiene cuatro pasos. La barra superior permite ir a cualquiera sin perder lo escrito, y cada botón cambia de color: **verde** con todos los datos obligatorios del paso, **amarillo** con parte de ellos y **rojo** sin ninguno o con datos por revisar. Los campos con asterisco rojo (*) son obligatorios para cerrar. La barra inferior indica qué falta y tiene Anterior, Guardar pendiente y Siguiente; Cerrar atención se resalta en el último paso.

1. Entre en Atenciones y seleccione Nueva atención. El superadministrador debe elegir la provincia cuando el sistema lo solicite.
2. **Evento y despacho.** El primer campo es **Número de hoja de atención**; conserve ceros y letras cuando correspondan. Puede haber números repetidos: compruebe fecha y ficha antes de editar. Complete fecha, hora de llegada, código APH, evento o causa, cantón y recurso despachado. Si falta el recurso, agréguelo primero en Recursos de la misma provincia.
3. La dirección del evento se marca solo con «Marcar en el mapa»: coloque el pin y confirme. Debajo del botón se muestra la dirección de referencia obtenida; «Quitar punto» la borra. Verifique que corresponda al lugar; los mapas no son evidencia de precisión ni reemplazan referencias operativas. Sin conexión el mapa no carga: guarde como pendiente y marque la ubicación después.
4. **Paciente.** Nombres, tipo y número de documento, sexo, edad, nacionalidad, condición y discapacidad. Las opciones cortas se eligen con un toque; tocar de nuevo la opción marcada la desmarca.
5. **Valoración y diagnóstico.** Toque la zona afectada en la silueta (o elíjala en la lista «Zona afectada»; hay botones para «Varias regiones» y «Sin zona (clínico)»). Aparecen códigos CIE-10 frecuentes de esa región; «Buscar en …» abre el listado completo filtrado por la zona. Si escribe primero el código, la zona se marca sola; si la zona marcada no coincide con el código, se muestra un aviso con «Cambiar zona». No escriba un código inventado. Describa el diagnóstico: es obligatorio para cerrar.
6. **Traslado y cierre.** Marque la localidad de destino en el mapa e ingrese el médico de recepción, kilometrajes de salida y llegada (la distancia es llegada menos salida; no confundir con la distancia del mapa), combustibles y observaciones. El responsable se registra automáticamente desde la cuenta.
7. Use Guardar pendiente (atajo Ctrl+S, o Cmd+S en Mac) para dejar la ficha Pendiente, o Cerrar atención cuando la barra indique «Lista para cerrar». Si algún dato tiene error, el formulario abre ese paso. Corregir un campo inválido no sustituye la revisión clínica/operativa del registro.

Mientras escribe, el sistema guarda un borrador a los pocos segundos y lo indica bajo el formulario. Si cierra la pestaña o se corta la conexión, al volver a la misma ficha verá «Restaurar borrador» o «Descartar borrador»; el borrador nunca se aplica solo. Es personal, se borra al guardar la ficha y caduca a los 7 días. No reemplaza guardar: un borrador no es una atención registrada.

La fecha es obligatoria al guardar como pendiente. Al cerrar se requieren todos los datos de Evento y despacho y de Paciente, el diagnóstico descrito, la localidad de destino y el médico de recepción, además del número de documento salvo «No identificado» o «Rehusa identificarse». Al corregir una ficha ya cerrada se mantiene la regla anterior: fecha, código APH, cantón y responsable. La cédula requiere diez dígitos; esto verifica formato, no identidad ni dígito verificador.

## Revisar, corregir y eliminar

En Atenciones verá las pestañas Todas, Cerradas y Pendientes con su cantidad. El listado muestra 30 fichas por página. La pestaña marcada indica el filtro activo; use Todas para quitarlo; las filas pendientes se resaltan en amarillo suave. El superadministrador dispone de filtro provincial. El técnico solo ve su provincia, aunque conozca el ID de otra ficha.

- **Editar:** abre la hoja. Volver a atenciones regresa al listado conservando el contexto disponible. Una ficha cerrada requiere motivo de corrección; los cambios quedan en historial.
- **Conflicto de edición:** si otra persona guardó primero, recargue, revise y concilie. No repita el envío esperando sobrescribir.
- **Eliminar:** abre confirmación sobre la tabla. Compruebe hoja y confirme solo si corresponde. La ficha se oculta y se registra quién la eliminó; no se borra físicamente. No hay botón de deshacer.
- **Imprimir:** abre la hoja imprimible. Espere que carguen los mapas, abra impresión del navegador y elija Guardar como PDF o impresora. Revise vista previa antes de compartir.

Cada técnico puede corregir y eliminar todas las fichas de su provincia, aunque otro técnico las haya creado. El superadministrador puede hacerlo nacionalmente. El historial registra la acción; no convierte una eliminación equivocada en inocua.

## Recursos

Entre a Recursos. Registre número de unidad, ubicación, capacidad de respuesta, tipo y combustible; guarde y compruebe en la tabla. El número de recurso no puede repetirse dentro de una provincia. Use Editar para actualizar. No existe eliminación de recurso desde la interfaz actual. Los cambios de tipo no deben reinterpretar despachos históricos que ya tienen copia del tipo en la ficha.

## Dashboard

Por defecto el panel muestra los últimos 12 meses. Cambie las fechas con «Desde» y «Hasta» o elija «Todo el historial»; si hay más de 10.000 atenciones, un aviso indica que los totales no son exactos.

Los gráficos muestran fichas registradas y no eliminadas, incluidas pendientes. Seleccionar una categoría, provincia o pin filtra el resto; seleccionar de nuevo la categoría o quitar su filtro permite ampliar resultados. El color provincial muestra cantidad de fichas, no incidencia por población. Las estadísticas se recalculan sobre los datos cargados: recargue la página para incluir guardados nuevos de otros usuarios.

“Despachos” cuenta fichas con recurso, no viajes únicos. “Kilómetros” suma diferencias válidas de odómetro. Un registro incompleto puede aparecer como Sin registrar; no sustituirlo por valores ficticios para mejorar el gráfico. Los puntos sin coordenadas no se muestran en mapas.

## Exportar

Desde Atenciones puede descargar cerradas a Excel y ubicaciones GeoJSON. El superadministrador filtra primero por provincia. Estas salidas excluyen pendientes y eliminadas. El dashboard incluye pendientes: por eso sus totales pueden diferir de la exportación. Guarde archivos solo en ubicaciones institucionales autorizadas; un archivo descargado ya no está protegido por la sesión del sistema.

## Usuarios Nacionales: solo superadministrador

1. Filtre por usuario/nombre/correo, provincia o estado. La tecla «/» lleva a la búsqueda. Activo indica habilitación; revise también si el correo está pendiente de activación.
2. Crear técnico solicita nombre, usuario único, correo institucional y provincia. La cuenta nueva queda pendiente; no es accesible hasta completar activación.
3. Editar datos abre un panel lateral con nombre, apellido, correo, contraseña, rol y provincia. Deje contraseña vacía para conservarla. Guardar vuelve al mismo módulo y muestra confirmación; los errores corresponden solo a ese usuario.
4. Cambiar provincia/rol no mueve fichas históricas. Verifique que la asignación sea autorizada. Se revocan otras sesiones de la cuenta.
5. Desactivar pide confirmación con el nombre de la cuenta; impide el ingreso y revoca el acceso. No puede desactivar su propia cuenta nacional. No hay eliminación física de usuarios en el panel.
6. Las solicitudes de registro aparecen arriba, en «Solicitudes de registro». Verifique la identidad de la persona antes de pulsar Aprobar: se crea la cuenta como técnico provincial y se envía la activación. Rechazar no crea nada. Si ya existe una cuenta con ese usuario o correo, la solicitud se marca como rechazada.
7. Reenviar activación requiere correo y servicio habilitado. Mientras SMTP/URL estén pendientes, verá aviso y no se envía código. Cuando esté habilitado, la invitación vence en diez minutos; reenviar invalida la anterior.

El código de activación valida el primer acceso/correo, no se pide en cada sesión. La cuenta suspendida no debe reactivarse a través de una invitación. No cambiar correos o roles para evadir verificaciones.

## Errores y soporte

A los ocho minutos de inactividad aparece aviso; a los diez vence la sesión. A las ocho horas se requiere nuevo ingreso. Si la sesión vence con cambios sin guardar, aparece «Trabajo en pausa»: inicie sesión con el mismo usuario en otra pestaña y pulse Reanudar antes de 30 minutos. Si no hay conexión al guardar, el formulario lo indica y conserva los datos. Guarde avances oportunamente.

Si intenta salir de una ficha con cambios sin guardar, el navegador pide confirmación. Si faltan datos o alguno no es válido, un resumen arriba enlaza a cada campo y el mensaje aparece también junto a él; al corregirlo, el aviso desaparece. Si un mapa falla, revise conexión y comunique el mensaje; no cargue capturas con pacientes en canales públicos.

Al reportar, indique módulo, hora, acción y mensaje, junto con una referencia mínima autorizada. Nunca incluya contraseña, cookies, código de activación ni una ficha clínica completa en GitHub. Canal y responsable de soporte: pendientes de designación institucional; durante el piloto coordinar con el responsable del proyecto.

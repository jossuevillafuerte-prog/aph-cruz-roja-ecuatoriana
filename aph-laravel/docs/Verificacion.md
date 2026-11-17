> **ARCHIVO HISTÓRICO.** Describe la entrega anterior, incluidas funciones MFA ya retiradas. Consultar [documentación vigente](../../docs/README.md), [seguridad actual](../../docs/seguridad.md) y [evidencia de pruebas](../../docs/calidad-y-trazabilidad.md).

> Documento histórico de la entrega anterior. La política vigente de roles y acceso está en Roles-y-activacion.md. Se retiró MFA y las cuentas provinciales ahora son técnicos.

# Verificación de la migración y seguridad

Fecha: 10 de noviembre de 2026.

- PHPUnit sobre MySQL aph_test: 16 pruebas y 82 aserciones aprobadas.
- Navegador Edge automatizado: ingreso, enrolamiento TOTP, dashboard, listado, recursos, perfil, nueva ficha, guardado e impresión aprobados.
- Cookie comprobada como HttpOnly, SameSite=Strict y sin persistencia. La bandera Secure se verificó en prueba de configuración HTTPS productiva; el acceso local HTTP mantiene la excepción documentada.
- POST sin CSRF rechazado con 419. Reutilización de la cookie anterior después del cierre rechazada con 401.
- Controles de servidor comprobados: inactividad, duración absoluta, revocación de credenciales, MFA obligatorio, rechazo de OTP reutilizado, límite de intentos, política de contraseña, rechazo de claves filtradas e indisponibilidad del servicio de verificación.
- Aislamiento provincial y restricción de creación de fichas a cuentas personales comprobados.
- Composer audit sin avisos de vulnerabilidades conocidas en la revisión realizada. Esto no sustituye una auditoría de seguridad.
- Comparación completa de registros importados con SQLite: 24 cuentas provinciales, 1 operador, 0 perfiles personalizados, 6 recursos, 101 atenciones, 101 cambios y 4 direcciones coincidentes. Los JSON se compararon por contenido.
- La base operativa aph no tiene secretos MFA creados por las pruebas. Cada usuario vinculará su propia aplicación autenticadora.

No se realizaron pruebas de carga, una auditoría externa, certificación ISO ni despliegue público. No se configuró cifrado del volumen ni copias externas. La matriz de controles y límites está en Seguridad-y-privacidad.md.

# RESMAP - Copilot Instructions

## Contexto del proyecto
- Sistema de gestión de repuestos para maquinaria pesada.
- PHP, Laravel y arquitectura existente.
- Módulos: inventario, POS, cotizaciones y vitrina web.
- Roles: Administrador y Vendedor.
- Respetar las versiones y convenciones existentes.

## Eficiencia de tokens y contexto
- Analizar únicamente los archivos necesarios para la tarea.
- Ampliar el contexto solo cuando esté justificado.
- Evitar leer repetidamente archivos sin modificaciones.
- Evitar explorar todo el proyecto salvo solicitud expresa.
- Omitir vendor, node_modules, archivos compilados y logs
  completos, salvo necesidad justificada.
- Reutilizar funciones y componentes existentes.
- No repetir código que ya fue modificado.
- Dar respuestas concisas, técnicas y en español.
- Evitar explicaciones redundantes.

## Buenas prácticas
- Aplicar los cambios mínimos necesarios.
- Respetar la arquitectura y reglas de negocio existentes.
- Evitar duplicación, sobreingeniería y refactorizaciones
  innecesarias.
- No instalar dependencias sin autorización.
- Revisar consultas N+1, índices y paginación cuando corresponda.
- No modificar módulos ajenos a la solicitud.

## Seguridad e integridad
- Validar entradas y permisos en el servidor.
- No confiar en precios, descuentos, totales ni stock del cliente.
- Garantizar la consistencia de ventas e inventario.
- Utilizar transacciones y control de concurrencia cuando proceda.
- No leer ni exponer secretos del archivo .env.
- Proteger credenciales e información sensible.
- No ejecutar operaciones destructivas sin autorización.
- No utilizar bases de datos reales para pruebas.

## Verificación
- Ejecutar primero las pruebas relacionadas con los cambios.
- No afirmar que una prueba pasó si no fue ejecutada.
- Informar riesgos y errores pendientes.
- Solicitar autorización para cambios de alto impacto.

## Formato de respuesta
- Resultado.
- Archivos modificados.
- Pruebas realizadas.
- Problemas pendientes.
- Omitir secciones que no correspondan.

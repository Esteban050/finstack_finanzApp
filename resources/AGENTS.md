1. Fuente de verdad: docs/ (RF v1.1, RNF v1.1; para TRM, RF-017 del doc v1.0). Si algo contradice los docs, detente y repórtalo. No agregues funcionalidades, campos, pantallas ni textos que no estén en los requisitos.
2. Terminología: "Gasto" (nunca "Costo"), "Ingreso", "Fuente de ingreso", "Medio de pago", "Naturaleza", "Necesidad", "Relevancia tributaria".
3. Dinero: prohibida aritmética con float/Number sobre montos; usa strings decimales y una librería decimal. COP se muestra "$ 1.234.567" (miles ".", decimales "," solo si aplica). USD se muestra "US$ 1.234,56". Rango de monto: >0 y ≤ 999.999.999.999,99. Monedas soportadas: COP y USD. Con USD se guarda y muestra: monto original, TRM usada y monto convertido a COP; este último es el que usan todos los cálculos. El front NO calcula la conversión autoritativa: la TRM viene del backend (consulta por fecha); el front puede mostrar una vista previa claramente rotulada "estimado".
4. Fechas: mostrar DD/MM/AAAA; zona America/Bogota; evita corrimientos de día por UTC.
5. Validación: el servidor es la autoridad; el cliente replica las reglas para UX. Error junto al campo nombrando la regla; obligatorios con "*". Mapea errores del servidor a campos. Nunca muestres trazas, SQL ni nombres de servidor (RNF-012).
6. Toda vista tiene estados: carga (skeleton), vacío, error y éxito. Eliminar siempre con ConfirmDialog (RNG-07).
7. Listados: paginación en servidor, 20 por defecto (10/20/50/100), orden por fecha desc; los totales del conjunto filtrado completo vienen del servidor; filtros en query string.
8. Seguridad: nada de tokens en localStorage/sessionStorage; sesión por cookie HttpOnly; nada de innerHTML con datos del usuario; 401 → login con "Tu sesión expiró por inactividad"; 403 → acceso denegado; 404 → "Recurso no encontrado".
9. Responsive 360–1920 px sin scroll horizontal (tablas colapsan a tarjetas en móvil).
10. WCAG 2.1 AA: contraste ≥4.5:1 en texto y ≥3:1 en UI; fuente base ≥14 px y nunca <12 px; foco visible; todo operable con teclado; labels asociados; la información no depende solo del color; gráficos con alternativa tabular; errores con aria-live.
11. UI en español de Colombia.
12. Íconos: solo <Icon name="..."/> de src/ui/icons. Prohibido crear SVG ad hoc, usar imágenes raster como íconos o logos de terceros.
13. Dependencias nuevas: solo una librería decimal y charts si no existen. Justifica cualquier otra.
14. Cada tarea termina con: archivos tocados, TODO(contrato) pendientes, requisitos cubiertos (RN-xxx.x) y comandos de verificación ejecutados.

## Convenciones del repositorio

La raíz del front es `resources/`; `docs/` está en la raíz del repositorio.
Blade utiliza `<x-icon name="..." />`, equivalente local de `<Icon>`; el registro existente vive en `resources/js/ui/icons`. No modificar el kit.
Los componentes del sistema de diseño se usan como `<x-ui.*>`; los componentes Blade existentes se reutilizan donde corresponda.

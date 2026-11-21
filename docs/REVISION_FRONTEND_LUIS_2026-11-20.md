> **Estado posterior a la corrección:** este documento conserva la revisión inicial. La implementación, segunda revisión, resultados y dependencias pendientes están en [PLAN_CORRECCION_FRONTEND_LUIS.md](PLAN_CORRECCION_FRONTEND_LUIS.md).

# Revisión del frontend de FinanzApp — trabajo de Luis

Fecha: 20/11/2026. Alcance: diagnóstico y planificación de correcciones; sin cambios de implementación.

## 1. Resultado y límites de la revisión

Hay fallos funcionales que conviene resolver antes del pulido visual: renta rechaza montos válidos, mantiene resultados desactualizados y borra importes de sus tablas; la desactivación de 2FA tiene HTML que deja controles sin formulario; una simulación de rentista pierde parámetros al enviarlos; las máscaras de movimientos de inversión recortan precisión y transforman entradas inválidas en otros números. Además hay problemas reproducidos de alineación, contraste, modales y accesibilidad.

Esta revisión comprende el inventario de **206 archivos** de presentación y cliente: **134 plantillas Blade, 60 archivos JavaScript —incluidas pruebas— y 12 hojas CSS**. Se inventariaron **278 declaraciones de controles**. El inventario incluye componentes, pantallas auxiliares, documentos imprimibles y vistas que pueden no estar activas. No son 134 pantallas distintas.

Se combinaron búsquedas sistemáticas en ese inventario, revisión manual de los flujos y componentes relevantes, comprobación de rutas y contratos, pruebas existentes y revisión en navegador de **16 vistas renderizadas con datos ficticios**. Los diez primeros casos se comprobaron a 360, 768 y 1920 px, además de una pasada a 1280 px con una medición descartada por cambio de tamaño aún en curso. Los seis casos adicionales se comprobaron a 360 y 1280 px. Se probaron el modal de renta en oscuro y estados de formularios concretos.

**No es una certificación de todos los recorridos con datos reales.** La aplicación local abrió, pero el intento con la cuenta de demostración de MOD-09 devolvió credenciales incorrectas. No se crearon usuarios, no se ejecutaron semillas, no se alteraron perfiles ni registros financieros. Las vistas ficticias se renderizaron fuera de las rutas autenticadas, con consultas a BD bloqueadas; conservan componentes, CSS y JavaScript reales, pero no certifican respuestas de la API, permisos, persistencia o todos los estados del controlador. Las pestañas de módulo derivadas de la ruta no se pueden juzgar visualmente en esas fixtures; se revisó su código.

Los datos de las capturas son ficticios. Los umbrales y reglas tributarias citados aquí describen el contrato y el código del repositorio; este informe no verifica legislación vigente ni propone cambiar fórmulas legales.

### Cómo leer los hallazgos

- **V:** reproducido en navegador con las vistas ficticias o en la pantalla pública real.
- **C:** comprobado por código, contrato o reproducción de una función aislada. No equivale a una operación completa contra el servidor.
- **D:** decisión de presentación recomendada a partir de la composición actual; no se presenta como una excepción técnica.
- **P1:** bloquea una tarea, cambia datos/resultados o puede inducir una decisión equivocada.
- **P2:** perjudica uso, accesibilidad, consistencia o comprensión.
- **P3:** mantenimiento o pulido de menor impacto inmediato.

El catálogo contiene **45 hallazgos**, agrupados por causa y responsabilidad. Las repeticiones de una misma causa en varias pantallas se enumeran como alcance, no como fallos distintos.

## 2. Qué le corresponde a Luis

| Encargo del equipo | Trabajo concreto de Luis | Dependencia de otra persona |
|---|---|---|
| Quitar opciones avanzadas de la calculadora | Retirar bloque, controles, estado, ayuda y dependencias de presentación; ajustar el flujo y sus pruebas | Garay confirma el contrato y comportamiento del cálculo simplificado |
| Puntos en TODOS los campos de valor | Unificar entradas monetarias, conservación del cursor, pegado, visualización y normalización del envío en todos los módulos | Acordar excepciones de precisión para tasas, unidades y precios; no modificar importes recibidos |
| Corregir ingreso de valores en renta | Formato, rango, decimales, errores junto al campo, recuperación de datos y resultados actualizados | Teban replica y aplica reglas en servidor |
| Revisar todo el frontend | Corregir distribución, alineación, acciones, modales, teclado, estados, fechas y coherencia de componentes | Necesita una sesión válida y respuestas representativas para cerrar la verificación integrada |
| Seguridad social supera el tope y queda igual | Revisar que se presenten los valores/avisos devueltos y que los datos se envíen completos | Esteban resuelve la regla de cálculo; Luis no debe compensarla con otra fórmula local |
| Pago con tarjeta de crédito | Revisar selección, etiqueta, validación y envío del medio | Teban resuelve el comportamiento de negocio acordado |
| Endeudamiento no funciona | Separar errores del indicador y del listado; comprobar contrato y estados | Garay investiga cálculo/datos/respuesta. La ruta sí existe |

## 3. Formatos, componentes y navegación compartida

### F01 — El formateador compartido introduce apóstrofos [P2 · C]

**Ubicación:** `resources/js/utils/money.js`, `formatMoneyDraft`; `resources/js/tests/money.test.js:47`.

`1234567,89` se presenta como `1'234.567,89`. La normalización conserva `1234567.89`, de modo que el fallo comprobado es de presentación. Las pruebas actuales esperan los apóstrofos y por eso pasan. Afecta los MoneyInput compartidos y otros módulos que reutilizan la agrupación, incluidos presupuestos y seguridad social.

**Corrección y cierre:** presentar `1.234.567,89`, actualizar los casos existentes y comprobar inserción/borrado en medio del número, selección, inicio/final, pegado y centavos. No basta con reemplazar el carácter en una pantalla.

### F02 — La cobertura del formato monetario es incompleta [P2 · C/V]

**Ubicaciones:** `renta/calculadora.blade.php:58`, `renta/complementaria.blade.php:85`, `inversiones/index.blade.php:73`, `inversiones/show.blade.php:735`, `components/ui/pila-fields.blade.php:70`, `admin/parametros/create.blade.php:44` y `edit.blade.php:45`.

Conviven MoneyInput, `type=number` para dinero y máscaras privadas. Falta cubrir patrimonio, ingresos, tarjetas, compras, consignaciones, ajustes, capital de inversión, valoraciones, ingresos estimados, IBC y parámetros monetarios. El CSV de controles permite recorrer también los casos menos visibles.

**Corrección y cierre:** una entrada común para dinero con texto localizado y valor canónico independiente. Separar dinero, tasas, UVT, cantidades, plazos, documentos y años: poner puntos indiscriminadamente en un porcentaje o un documento sería otro error. Para unidades/precios conservar la precisión contractual, que puede ser de ocho decimales.

### F03 — La acción primaria pierde su jerarquía al pasar el puntero [P2 · C/D]

**Ubicación:** `resources/css/design-system.css`, reglas `.ui-button:enabled:hover` y `a.ui-button:hover`.

Las variantes primaria y de peligro comparten un hover que las lleva a superficie elevada y texto normal. La secundaria tiene una excepción propia; la primaria no. Esto reduce la diferenciación justo al interactuar.

**Corrección y cierre:** hover propio por variante, manteniendo la acción principal claramente identificable en claro/oscuro y también en foco, carga y deshabilitado. Los colores base existentes pasan las pruebas de contraste: no hace falta reemplazar la paleta completa.

### F04 — Volver/avanzar en el historial redirige al login [P2 · C]

**Ubicación:** `resources/views/layouts/app.blade.php:8`.

La plantilla oculta el documento y redirige a login ante `back_forward` o páginas restauradas, sin consultar si la sesión sigue vigente. Afecta la navegación entre listados y formularios. No se afirma que todos los cambios internos de filtros disparen este caso: la condición comprobada es navegación/restauración del documento.

**Corrección y cierre:** conservar la protección de páginas privadas y validar la sesión mediante el contrato existente; permitir volver con sesión válida. Una sesión realmente vencida debe mantener su mensaje específico. Coordinar cualquier mecanismo de revalidación que necesite backend.

## 4. Declaración de renta y calculadora

### F05 — Diecinueve marcadores monetarios vacíos borran importes [P1 · C/V]

**Ubicaciones:** `renta/complementaria.blade.php:132` y `152`; `renta/consolidado.blade.php`; `renta/evaluacion.blade.php`; consumidor en `resources/js/ui/foundation.js`, bucle `[data-money]`.

Hay **19 apariciones en el código** de `data-money` sin valor en estas vistas; no necesariamente se renderizan todas a la vez. El inicializador intenta formatear `dataset.money === ''`, falla y reemplaza el contenido ya generado por `—`. Se reprodujo en los dos consolidados de información complementaria.

**Corrección y cierre:** entregar el decimal canónico en el atributo o quitar el marcador cuando Blade ya entrega la presentación final. Comprobar ingresos, patrimonio, ajustes y criterios de evaluación, no solo las dos celdas reproducidas.

### F06 — Los saltos de mil impiden introducir pesos válidos [P1 · V]

**Ubicaciones:** `renta/calculadora.blade.php:58–208`; `renta/complementaria.blade.php:85–155`.

`step="1000"` convierte `1234567` en inválido; el navegador pide `1234000` o `1235000`. También impide los centavos. Esto ocurre antes de la validación de negocio.

**Corrección y cierre:** retirar ese paso monetario y usar entrada localizada con hasta dos decimales y reglas por campo. Probar `1.234.567`, `1.234.567,89`, cero cuando corresponda y los ajustes negativos permitidos. No “solucionarlo” redondeando el valor del usuario al millar.

### F07 — La calculadora no replica los límites ni distingue correctamente vacío e inválido [P1 · C]

**Ubicación:** `renta/calculadora.blade.php`, `calcular()` y controles numéricos.

Los controles no tienen el límite superior global y el cálculo usa `Math.max(0, parseFloat(valor) || 0)`. Los campos vacíos terminan como cero y la lógica local transforma entradas no válidas si llega a ejecutarse con ellas. La validación nativa bloquea parte de esos casos, pero no sustituye la regla monetaria compartida. La notación exponencial tampoco se excluye explícitamente.

**Corrección y cierre:** validar formato, rango y precisión antes del cálculo; definir si vacío significa cero o dato faltante; mostrar la regla junto al campo. No mezclar el permiso de cero de una simulación con la regla de montos positivos de un registro.

### F08 — Hay una segunda calculadora tributaria completa dentro del frontend [P1 · C]

**Ubicación:** `renta/calculadora.blade.php`, función `calculadoraRentaApp` desde aproximadamente la línea 480; `routes/web.php`, ruta `renta.calculadora.calcular`; `DeclaracionRentaController::calcularSimulacion`.

El formulario ejecuta fórmulas locales con Number, `parseFloat`, constantes de rangos y redondeos; no usa la ruta de simulación que ya existe. También información complementaria suma ajustes con flotantes. Esto incumple la exactitud exigida por el repositorio y permite que la pantalla diverja del servidor. La existencia de un servicio backend no significa que su contrato ya esté adaptado a la simplificación solicitada.

**Corrección y cierre:** acordar con Garay/Teban el contrato final, consumir el resultado autorizado y conservar strings decimales. Para previsualizaciones de ajustes usar la librería decimal existente. No copiar otra vez las tablas ni actualizar exclusiones del lint para silenciar el problema.

### F09 — Las opciones avanzadas siguen presentes y participan del cálculo [P1 · C/V]

**Ubicación:** `renta/calculadora.blade.php:118` en adelante.

Persisten el desplegable, seis campos, estado, descripciones y operaciones de depuración. Ocultar el bloque con CSS dejaría sus valores y dependencias activos.

**Corrección y cierre:** retirar el recorrido avanzado completo, limpiar controles/estado/textos y adaptar resultados según el contrato simplificado. Decidir con Garay cómo se obtiene la información que antes introducía el usuario; no presumir que todos esos datos deben pasar a cero.

### F10 — La calculadora presenta cinco criterios y el consolidado seis [P1 · C/V · contrato]

**Ubicaciones:** `renta/calculadora.blade.php`, `renta/consolidado.blade.php`, `renta/evaluacion.blade.php`, `profile/show.blade.php`.

La calculadora anuncia una conclusión general con cinco topes; el resto del módulo también considera la condición de responsable de IVA. La calculadora no incorpora ese criterio de perfil. Esto puede producir mensajes aparentemente definitivos que no significan lo mismo entre pantallas.

**Corrección y cierre:** confirmar si el simulador debe evaluar toda la obligación o solamente esos cinco montos y hacer que alcance, título y conclusión coincidan. Si debe ser integral, consumir también el criterio autorizado. No añadir reglas tributarias nuevas por cuenta del frontend.

### F11 — Los resultados permanecen vigentes después de modificar los datos [P1 · V]

**Ubicación:** `renta/calculadora.blade.php:228` y estado `calculado`.

Se calculó con ingresos de `70.000.000`, se cambió el campo a cero y el resultado siguió indicando obligación y los 70 millones anteriores. No existe invalidación por edición.

**Corrección y cierre:** ocultar o marcar explícitamente el resultado como pendiente de recalcular ante cualquier cambio relevante; impedir que se interprete como resultado de los valores actuales. Cubrir este recorrido con una prueba de interacción.

### F12 — Un error de servidor hace perder la información complementaria introducida [P1 · C]

**Ubicación:** `renta/complementaria.blade.php:6` y `175`.

El estado inicial toma el modelo guardado y los consolidados, sin `old()` para los montos; el textarea de justificación se renderiza vacío. Tras una respuesta con errores y retorno del formulario, puede reaparecer el dato persistido en lugar del intento del usuario.

**Corrección y cierre:** recuperar los valores enviados y la nota, conservar su presentación y distinguir los consolidados de solo lectura. Probar un envío rechazado sin guardar nada y comprobar que los cinco campos y la explicación sobreviven.

### F13 — La nota se anuncia obligatoria pero no se exige en el cliente [P1 · C]

**Ubicación:** `renta/complementaria.blade.php:173`.

El bloque aparece si hay ajustes y muestra asterisco; el textarea tiene longitudes pero no `required` condicional. Vacío pasa la obligatoriedad nativa y no hay validación cliente equivalente.

**Corrección y cierre:** requerirla únicamente cuando la regla lo exija, validar 10–500 caracteres según el contrato y mostrar el error asociado. Cuando deja de aplicar, no debe quedar un control oculto bloqueando el envío.

### F14 — Los errores de renta no siguen el patrón de campo accesible [P2 · C/V]

**Ubicación:** `renta/complementaria.blade.php`, controles y listado `$errors->all()`.

Los errores del servidor se agrupan arriba; los controles monetarios no tienen los contenedores, referencias y mensajes de los componentes compartidos. Los dos ajustes carecen de etiqueta asociada. El mensaje de total negativo es una fila aparte y no sustituye la identificación del campo.

**Corrección y cierre:** errores por nombre, `aria-invalid`, ayuda/error asociados y foco en el primer campo afectado; mantener un resumen navegable si aporta valor. Usar el tratamiento seguro de mensajes ya existente.

### F15 — Tres columnas dentro de una columna estrecha desalinean los controles [P2 · V/D]

**Ubicaciones:** `renta/complementaria.blade.php`, `.income-editor`, `.mk-fields.three`; `resources/css/income.css:3`, `resources/css/renta.css:29`.

A 1280 px la columna de entrada comparte espacio con el resumen y todavía intenta mostrar tres campos. Las etiquetas de consignaciones, tarjetas y compras ocupan distintas alturas: sus inputs quedan a diferentes niveles y poco anchos. Hay captura.

**Corrección y cierre:** adaptar el número de columnas al ancho disponible del contenedor, no solamente al de la ventana. Una columna o dos grupos legibles es preferible en ese ancho. Comprobar máximos monetarios completos, ayuda y errores visibles sin saltos de alineación.

### F16 — En móvil el resumen y la auditoría desplazan el formulario hacia abajo [P2 · V/D]

**Ubicación:** `resources/css/income.css`, `.income-summary { grid-row: 1; }` dentro del media query móvil; reutilización en renta.

A 360 px aparece antes de los campos la tarjeta con cinco resultados y el historial de ajustes. El usuario debe pasar un resumen y una auditoría para introducir la información que los produce.

**Corrección y cierre:** definir un orden específico para renta: captura primero y resumen al final, o resumen compacto si se necesita. Evaluar por separado ingresos/gastos: no cambiar el orden de todos los módulos a ciegas.

### F17 — Las filas de resultados creadas después de cargar no reciben etiquetas móviles [P2 · V]

**Ubicaciones:** `renta/calculadora.blade.php`, tabla con `x-for`; `ui/foundation.js`, adaptación inicial de tablas.

Tras calcular, las celdas dinámicas no tienen `data-label`. En móvil pasan a `display:grid`, pero faltan los nombres que permiten relacionarlas con sus encabezados ocultos. Se verificó en la primera fila de resultados.

**Corrección y cierre:** generar etiquetas y semántica junto con cada fila dinámica, o usar un adaptador que también cubra su inserción. No basta con que las tablas del HTML inicial se vean bien.

### F18 — El selector de año no tiene etiqueta asociada y contiene años fijos [P2 · C/V]

**Ubicaciones:** `renta/calculadora.blade.php:24`, `renta/consolidado.blade.php:13`, `layouts/module-tabs.blade.php`.

“Año gravable” es un span; el select no queda nombrado por él. Las opciones son 2025/2026 y existe un valor por defecto 2025 en navegación. Hoy esos años son visibles, pero la lista no procede de las vigencias disponibles.

**Corrección y cierre:** asociar label/select, conservar el año al navegar y recibir las vigencias del contrato cuando corresponda. El cambio visual lo hace Luis; la disponibilidad de años requiere datos del backend.

### F19 — Evaluación invoca un servicio sin su nombre de clase completo [P1 · C]

**Ubicación:** `renta/evaluacion.blade.php:30` y `81`.

Se llama `ConsolidadoRentaService::aUvt` sin importación en la vista. En cambio, consolidado usa `App\Services\Renta\ConsolidadoRentaService`. La importación del controlador no se hereda en el archivo de la vista. Los caminos preliminar e incompleto pueden fallar al renderizar. La compilación Blade por sí sola no ejecuta esas ramas.

**Corrección y cierre:** resolver la referencia correctamente o recibir ese dato preparado. Renderizar explícitamente ambas ramas con fixtures. No modificar la regla del servicio.

### F20 — Se inventa una fecha límite cuando falta el dato [P1 · C]

**Ubicaciones:** `renta/consolidado.blade.php:61`, `renta/evaluacion.blade.php:59`.

Si `fecha_limite_presentacion` es nula, se presenta `15/09/` del año siguiente. El fallback parece una fecha real personalizada aunque no provenga del calendario.

**Corrección y cierre:** mostrar que la fecha está pendiente/no disponible y enlazar al recorrido de consulta que corresponda. Solicitar al backend la fecha autorizada; no sustituirla por otra constante.

### F21 — El modal de presentación de renta aparece en la esquina superior izquierda [P2 · V]

**Ubicación:** `renta/vencimiento.blade.php:104`.

Tras abrirlo, el contenedor tiene `display:block`: el `display:flex` inline no se conserva al mostrar con `x-show`. `align-items` y `justify-content` quedan sin efecto. Se midió el diálogo en `(0, 0)` a 1280 px.

**Corrección y cierre:** usar el diálogo compartido o una clase de layout que se mantenga al abrir/cerrar. Comprobar apertura repetida, móvil, scroll y teclado virtual.

### F22 — Ese modal es ilegible en modo oscuro [P1 · V]

**Ubicación:** `renta/vencimiento.blade.php:109`.

El fondo usa `var(--color-bg-surface, #fff)`, pero el token no está definido en el sistema actual. Hereda texto claro: se comprobó fondo `rgb(255,255,255)` con texto `rgb(243,245,247)`. Hay captura.

**Corrección y cierre:** usar `--ui-surface`, `--ui-text` y los componentes vigentes. Verificar título, etiquetas, ayuda, botones y campos en ambos temas, no solo el fondo.

### F23 — Los modales particulares no implementan el comportamiento accesible del diálogo compartido [P2 · C/V]

**Ubicaciones:** `renta/vencimiento.blade.php`; `admin/usuarios/index.blade.php`, `modalCrearAdmin` y `modalInactivar`.

Al abrir el modal de renta el foco permanece en el botón del fondo. No se declara `aria-modal` ni se vuelve inerte el resto; el administrativo ni siquiera declara `role=dialog`. Mostrar/ocultar un div no crea confinamiento de foco. El modal administrativo se vio a 360 px; no se constató corte de contenido a 640 px de alto, así que ese defecto no se da por probado.

**Corrección y cierre:** `<dialog>`/componente compartido con nombre, foco inicial, Escape, restauración del foco, navegación dentro del modal y scroll interior cuando haga falta.

## 5. Inversiones, unidades y movimientos

### F24 — La máscara de movimientos recorta los ocho decimales ofrecidos [P1 · C]

**Ubicaciones:** `inversiones/show.blade.php:957`, `979`, `990`, `1001`; campo `trx_unidades` en `781`.

El placeholder permite `10,00000000`, pero ambas funciones de máscara recortan la fracción a cuatro caracteres. Reproducción aislada: `desformatearMiles('1,12345678')` devuelve `1.1234`.

**Corrección y cierre:** precisión explícita por control, ocho donde lo requiera el contrato y hasta dos para dinero común. Nunca truncar silenciosamente. Verificar también precio unitario, comisión y TRM según sus reglas específicas.

### F25 — La máscara transforma texto inválido en un importe distinto [P1 · C]

**Ubicación:** las mismas funciones de `inversiones/show.blade.php`.

Al quitar caracteres no numéricos indiscriminadamente, `formatearMiles('1e3')` devuelve `13`; `formatearMiles('-1000')` devuelve `1.000`. Eso no es una validación: modifica el significado del dato.

**Corrección y cierre:** rechazar formatos fuera del contrato con error explícito y conservar el texto para corregirlo. Separar formato colombiano, decimal canónico y cantidad/precio. Probar signos, exponentes, varios separadores y pegado con símbolos.

### F26 — Movimientos y valoraciones siguen calculando con flotantes [P1 · C]

**Ubicaciones:** `inversiones/show.blade.php:923`, `1114`, `1312` y fórmulas de previsualización.

Se multiplican unidades y precios con `parseFloat`, se pasa por Number y algunos valores vuelven a `toFixed(2)`. Esto convive con utilidades exactas ya instaladas. Las conversiones exclusivas para dibujar coordenadas de un gráfico no se consideran equivalentes a calcular importes de una operación.

**Corrección y cierre:** conservar strings/Decimal para totales, ganancias y payloads; usar números solo en el límite gráfico. Verificar redondeo contractual y que abrir/editar una valoración no altere sus decimales.

### F27 — El formulario actual de inversión no utiliza el validador común previsto para él [P2 · C]

**Ubicaciones:** `inversiones/index.blade.php:42`, formulario `investment-form`; `finance/index.js:67`, selector `[data-finance-form]`; `finance/rules.js`, rama `investment`.

El formulario actual tiene manejadores propios y no la marca que activa las reglas comunes. Por tanto, la existencia de `validateFinance(..., 'investment')` y sus pruebas no garantiza que se ejecute en esta pantalla. Nombre y algunos campos tienen restricciones nativas, pero no todo el conjunto ni todas sus relaciones.

**Corrección y cierre:** elegir una integración consistente con el contrato actual y conectar validación/errores/carga. No añadir solamente el atributo: el adaptador genérico tiene una lista de campos que debe contrastarse con los campos actuales de este formulario antes de migrarlo.

### F28 — Varias etiquetas no identifican el control que muestran [P2 · V/C]

**Ubicaciones:** `inversiones/show.blade.php:763`, `775`; `components/ui/date-input.blade.php`.

La vista usa campos como `name=tipo` con input `id=trx_tipo`; el componente field etiqueta `tipo`. Se confirmaron sin etiqueta `trx_tipo`, `trx_monto` y `trx_fecha`. DateInput calcula un ID personalizado para el input, pero no lo pasa al field que genera su label. El adaptador global no corrige este desfase cuando el input ya tiene ID.

**Corrección y cierre:** transmitir el ID a todos los elementos asociados y mantener `for`, ayudas y errores consistentes. Auditar todos los usos de DateInput con ID personalizado, no solo inversiones.

### F29 — Eliminar un movimiento puede pedir dos confirmaciones diferentes [P2 · C]

**Ubicación:** `inversiones/show.blade.php:903`; `ui/foundation.js`, `installConfirmation`.

El formulario usa `onsubmit="return confirm(...)"` y el inicializador compartido intercepta métodos DELETE con ConfirmDialog. Conviven dos mecanismos que pueden encadenarse al reenviar el formulario aprobado.

**Corrección y cierre:** dejar una única confirmación accesible y contextual. Cancelar no debe enviar; confirmar debe producir una sola petición y una sola notificación de resultado.

### F30 — El registro de una nueva inversión no destaca como acción de entrada [P2 · V/D]

**Ubicación:** `inversiones/index.blade.php:13–35`.

La cabecera contiene cuatro accesos secundarios y “Registrar Nueva Inversión” queda como summary dentro de una tarjeta. Con la navegación de módulo añadida por el layout, también se duplican accesos a portafolio/reportes/simulador.

**Corrección y cierre:** hacer dominante el inicio del registro, agrupar la navegación y reservar los botones para acciones. Conservar el panel expandible si es útil, pero con una activación inequívoca y sin repetir enlaces equivalentes en cada nivel.

## 6. Ingresos, gastos, TRM y seguridad social

### F31 — La TRM de ingresos/gastos no tiene la validación cliente que su marcado sugiere [P2 · C]

**Ubicaciones:** `components/income/form.blade.php:30`, `components/expense/form.blade.php`; `income/rules.js`, `expenses/rules.js`, `finance/index.js`, `ui/currency.js`.

El campo se marca `data-decimal-input` y muestra ejemplo `4150.00`, pero los formularios de ingresos/gastos no pasan por el normalizador de los formularios financieros que interpreta esa marca. Sus validadores de módulo tampoco comprueban moneda/TRM. El usuario no recibe la regla equivalente antes del envío.

**Corrección y cierre:** formato y precisión explícitos, error local asociado y decimal canónico en el envío, acordados con el backend. Unificar el ejemplo con el separador esperado. El alcance de USD debe resolverse con la documentación reciente: `resources/AGENTS.md` conserva COP único mientras el código ya habilita COP/USD; no quitar ni ampliar USD sin resolver esa discrepancia.

### F32 — Una consulta tardía de TRM puede mostrar la referencia de otra fecha [P2 · C]

**Ubicación:** `ui/currency.js`, `fetchTrm`.

No hay cancelación ni comprobación de que la fecha consultada siga siendo la seleccionada al recibir la respuesta. Si se cambia rápido entre fechas, una respuesta antigua puede actualizar la ayuda después de la nueva.

**Corrección y cierre:** vincular respuesta a fecha/moneda vigentes o cancelar solicitudes anteriores. Probar respuestas invertidas y cambio a COP durante una consulta. No afirmar que el backend guarda una TRM equivocada: el fallo comprobado aquí está en la referencia mostrada.

### F33 — Los recurrentes no tienen fecha enlazada a la sugerencia de TRM [P2 · C]

**Ubicación:** `ui/currency.js`, selector de `dateInput`; formularios recurrentes y `components/ui/recurrence-form.blade.php`.

La selección de fecha contempla fecha de ingreso/gasto o apertura de inversión, pero no `fecha_inicio` de recurrencia. Los formularios comparten el aviso de TRM, aunque no se consulta de la misma manera. La fecha de referencia aplicable a una plantilla también debe estar acordada en el contrato.

**Corrección y cierre:** explicar y conectar la referencia de recurrencia autorizada; no presentar una sugerencia como consultada cuando no lo está. Verificar alta y edición de plantillas COP/USD.

### F34 — La simulación de rentista pierde `es_rentista` y `gastos` en el frontend [P1 · C]

**Ubicaciones:** `seguridad-social/liquidaciones/index.blade.php:16`; `resources/js/api/index.js`, `contractPayload` para seguridad social; `SeguridadSocialController::simular`.

La pantalla ofrece la casilla y los gastos, y el controlador los recibe para elegir el cálculo. El filtro de payload solo conserva periodo, ingreso_base y es_voluntario en esa ruta. Reproducción: para ingreso de 5 millones y gastos de 1 millón, el payload resultante conserva el ingreso y elimina los dos parámetros de rentista.

**Corrección y cierre:** ajustar la lista permitida de esa ruta al contrato real y probar los parámetros enviados. Es corrección de Luis; la fórmula sigue a cargo del backend.

### F35 — Autocompletar PILA puede aplicar una respuesta a un periodo ya cambiado [P2 · C]

**Ubicación:** `resources/js/finance/pila.js`, manejador `[data-pila-autofill-btn]`.

La petición captura periodo, modalidad y gastos, pero al recibirla escribe IBC y porcentajes sin verificar que esos datos sigan iguales. Solo se deshabilita el botón; los controles de contexto continúan editables.

**Corrección y cierre:** invalidar/cancelar al cambiar contexto o comparar una firma antes de aplicar la respuesta. Diferenciar “datos consultados” de valores editados manualmente. Este riesgo se establece por el manejador; falta reproducir el recorrido integrado con respuestas demoradas.

## 7. Mi perfil y administración

### F36 — Desactivar 2FA tiene campos sin formulario por anidación inválida [P1 · V]

**Ubicación:** `profile/show.blade.php:208–224`.

Un formulario para solicitar código está anidado dentro del de desactivación. El navegador repara el HTML y deja los inputs de contraseña/código fuera de cualquier formulario. En el DOM se comprobó `input.form === null` para ambos; no se envió ninguna operación de seguridad.

**Corrección y cierre:** formularios hermanos o asociaciones `form=` válidas, cada botón con su acción correcta. Comprobar estructura y payload con 2FA activo/inactivo, sin desactivar una cuenta real para hacer la prueba.

### F37 — Campos y botones de seguridad del perfil no tienen el acabado accesible del resto [P2 · V/C]

**Ubicación:** `profile/show.blade.php`, sección MFA y exportación/supresión.

Contraseña/código usan placeholder sin label asociado. Se midieron acciones de aproximadamente 33 px para enviar código, 37 px para desactivar y solicitar supresión, y 32 px para exportar: por debajo de las áreas de 44/48 px indicadas en el repositorio. El campo de código no declara el patrón numérico del componente MFA de autenticación.

**Corrección y cierre:** labels persistentes, ayuda/error asociados, entrada de código apropiada, área táctil y estados compartidos. Mantener diferenciadas acciones de seguridad, descarga y eliminación.

### F38 — El perfil replica solo una parte pequeña de sus reglas [P2 · C]

**Ubicaciones:** `auth/rules.js`, `validateProfilePresentation`; `profile/show.blade.php:97–150`.

La validación cliente de perfil solo cubre nombres/apellidos y teléfono. Fecha de nacimiento se anuncia para mayores de 18 sin límite cliente equivalente; la selección obligatoria de ocupaciones no se valida como grupo. La etiqueta del grupo termina asociada al primer checkbox en lugar de representar el conjunto.

**Corrección y cierre:** replicar reglas autorizadas de fecha/edad y selección de grupo, usando fieldset/legend para ocupaciones y errores por campo. El backend conserva la decisión final. No añadir reglas de perfil nuevas.

### F39 — Perfil y varias vistas administrativas no tienen H1 [P2 · V/C]

**Ubicaciones:** `profile/show.blade.php`, `admin/dashboard.blade.php`, `admin/parametros/create.blade.php`, `admin/usuarios/index.blade.php`, `admin/categorias/index.blade.php` y vistas administrativas hermanas con el mismo patrón.

El título principal se coloca como H2 en el slot de cabecera. Se confirmó ausencia de H1 en cinco vistas renderizadas. Sus páginas también heredan el título genérico de documento si no lo declaran.

**Corrección y cierre:** un H1 y título de documento descriptivo por página, subsecciones jerárquicas, sin duplicar el nombre visualmente. La barra superior puede seguir siendo contexto secundario.

### F40 — Administración usa peticiones y errores distintos a la capa común [P2 · C]

**Ubicaciones:** `admin/calendario/index.blade.php:111` y `admin/tarifas/index.blade.php:122` en adelante.

Los fetch directos no verifican `response.ok` y muestran `alert(...error.message)`. El simulador de tarifas interpola respuesta en `innerHTML`, mientras el resto dispone de petición común y tratamiento seguro. Una respuesta de validación o sesión vencida puede acabar como resultado incompleto o error técnico. Esto no demuestra por sí solo una explotación de seguridad.

**Corrección y cierre:** tratamiento coherente de 401/403/404/419/422/429/500, texto seguro, error junto al campo y estado de carga/reintento. Construir el resultado con nodos/texto en vez de HTML interpolado.

### F41 — La navegación administrativa no tiene recorrido propio en el shell [P2 · C/D]

**Ubicaciones:** `layouts/navigation.blade.php`, `admin/dashboard.blade.php`.

El menú común enumera módulos personales y no accesos administrativos ni retorno al panel. El panel reúne cuatro tarjetas, pero al entrar a una sección falta un contexto administrativo uniforme; algunas vistas tienen retorno puntual y otras no.

**Corrección y cierre:** navegación por rol y ubicación consistentes, conservando el control de permisos en backend. No exponer funciones administrativas a usuarios normales ni considerar ocultar enlaces una medida de autorización.

## 8. Créditos, reportes y cierre transversal

### F42 — El fallo de endeudamiento bloquea también el listado de créditos [P1 · C]

**Ubicación:** `resources/js/credits/index.js:51`; `api/credits.js`.

`loadList()` espera `Promise.all([list, debt])` antes de pintar cualquiera de los dos. Si falla el indicador, se pierde la presentación del listado aunque su respuesta sea correcta. **La ruta GET `/creditos/endeudamiento` existe**, acepta HTML/JSON en el controlador y está conectada en el frontend.

**Corrección y cierre:** estados independientes para indicador y lista, conservar el listado si el indicador falla y permitir reintentar solo lo necesario. Garay debe comprobar el servicio y la respuesta real; no se puede concluir la causa original del cálculo sin esa respuesta.

### F43 — Fechas, porcentajes y códigos internos siguen sin unificarse [P2 · C/V]

**Ubicaciones:** `admin/calendario/index.blade.php`, `admin/parametros/index.blade.php`, `admin/usuarios/index.blade.php`, `profile/show.blade.php`, `renta/calculadora.blade.php`, `renta/complementaria.blade.php`, `inversiones/reportes/retenciones.blade.php:12`, `utilidades.blade.php:12`, `ui/financial-view.js`, `finance/pila.js`.

Se muestran fechas `Y-m-d` en administración; porcentajes con punto (`0.522%`, UVT `1054.24`); títulos con `RF-REP-*`; ayudas e historial con `RN-*`/`RF-*`. En el detalle CDT hay `number_format($puntoFinal['total_neto'], 2)` sin separadores explícitos: produce el formato predeterminado con coma de miles y punto decimal, distinto del colombiano usado en otras tarjetas. Los tooltips de gráficos compartidos concatenan valores crudos sin formatear. PILA concatena `item.fecha` sin pasar por el formato de fecha común. Son inconsistencias de presentación, no motivo para tocar las reglas de negocio.

**Corrección y cierre:** DD/MM/AAAA para fechas visibles, coma decimal y unidad explícita por medida; códigos solo en documentación/comentarios. Los tooltips y tablas deben concordar. No exigir a una unidad/UVT el formato de pesos por reutilizar un helper monetario.

### F44 — Quedan elementos que incumplen el sistema visual aunque las comprobaciones pasen [P2 · C/V/D]

**Ubicaciones:** `layouts/navigation.blade.php`, vistas de renta, `admin/dashboard.blade.php`, `inversiones/index.blade.php`, `resources/css/renta.css`, páginas de error.

Persisten SVG directos y emojis fuera del kit; texto de 12 px en “Opcional” y 12,8 px en el indicador de paso de inversiones; botones/enlaces administrativos pequeños; un bloque completo de reglas `.mk-table-wrap`/`.mk-compare`/`.mk-pdf` repetido en renta.css. Las vistas 403/404/500 usan EmptyState con H2 y no ofrecen una acción de recuperación propia. Las reglas de botones e iconos existentes son una base, pero sus verificaciones no detectan todos estos incumplimientos.

**Corrección y cierre:** reutilizar kit y tokens; jerarquía de una acción principal por tarea, secundaria para retorno y peligro para eliminación; fuente mínima y área táctil; quitar duplicados sin alterar la presentación esperada. Para errores, título principal y salida segura al módulo/inicio o reintento cuando aplique. En el consolidado incompleto, “Completar información” debe destacar antes que una exportación PDF. Estas decisiones de jerarquía son recomendaciones de diseño, no fallos de autorización.

### F45 — Las verificaciones actuales no cubren los defectos encontrados [P1 · C]

**Ubicaciones:** `resources/js/tests`, `scripts/lint-frontend.mjs`, `scripts/legacy-financial-baseline.mjs`, `scripts/check-icons.mjs`, `resources/views/reportes/pdf/flujo-caja.blade.php`.

Las **84 pruebas pasan**, pero el lint falla primero en `inversiones/show.blade.php`. Al evaluar todos los archivos sin detenerse en el primer fallo aparecen también `renta/calculadora.blade.php` y `reportes/pdf/flujo-caja.blade.php` como errores de la regla financiera. Hay cinco vistas con deuda heredada aceptada por hash. El lint imprime una etapa inicial “OK” antes de fallar después; no significa que el comando completo haya pasado. El chequeo de iconos valida referencias pero no prohíbe todo SVG directo. Las pruebas de ocho vistas financieras usan un caso de detalle CDT y no certifican todos los productos o recorridos. Hay pruebas que consolidan el comportamiento de apóstrofos que ahora se quiere quitar.

**Corrección y cierre:** mantener las comprobaciones y ampliar casos dirigidos a fallos reales: renta con monto no múltiplo de mil, edición tras cálculo, errores y recuperación; perfil con 2FA activo; precisión de unidades; payload de rentista; indicador de deuda fallido con lista correcta; etiquetas/filas dinámicas; modales en oscuro. Corregir la deuda financiera de las vistas que se modifiquen, sin renovar hashes para permitir infracciones nuevas. El PDF de flujo de caja requiere además revisión de presentación/exportación; no se generó ni verificó visualmente un PDF final en este diagnóstico.

## 9. Cobertura por módulo y trabajo que falta comprobar con sesión válida

| Área | Qué se revisó | Pendiente de cierre integrado |
|---|---|---|
| Acceso/registro/recuperación/MFA | Login real en local, componentes, reglas y suite de autenticación | Registro completo con correo, expiración, límites y flujo MFA real; no enviar correos de prueba a terceros |
| Shell y navegación | Layout, menú, pestañas, tema, tablas y controles compartidos; shell real renderizado en fixtures | Volver/avanzar con sesión válida y vencida, menús por rol, estados activos derivados de ruta |
| Resumen | Vista activa `resumen/index`, configuración del workspace, `reports/index.js` y helpers | Datos reales, filtros, enlaces a movimientos, respuestas vacías/error y carga lenta |
| Ingresos | Alta/edición, fuentes, recurrentes, tablas, filtros, reglas, formularios compartidos, dinero, TRM | Guardar/editar COP y USD; fuentes inactivas; neto/retenciones; respuesta 422 sin pérdida; 0/1/muchas filas y paginación |
| Gastos | Alta/edición, categorías, filtros, selección masiva, etiquetas, pendientes y recurrencias | Los seis medios de pago con respuesta real; edición masiva, límite 100, categorías/subcategorías inactivas, borrado confirmado, errores de red |
| Seguridad social | Cuatro vistas en navegador: unificada, liquidaciones, asalariado y pagos; adaptador y autofill | Tres modalidades con parámetros válidos; tope inferior/superior del backend; periodos sin ingresos; pago y soporte; diferencias y correcciones |
| Inversiones | Cuatro vistas en navegador: lista/registro, detalle CDT, portafolio y simulaciones; reportes y fórmulas por código | Los otros cinco tipos de producto; alta/edición; valoración manual; movimientos permitidos; paginación, CSV y precisión real de API |
| Renta | Calculadora, complementaria y vencimiento en navegador; consolidado, evaluación, estimación y constancia por código | Estados preliminar/incompleto/obligado/no obligado/presentada, año, perfil IVA, persistencia y conciliación con resultado del servidor |
| Créditos | Vista, reglas, rutas, adaptadores y pruebas existentes | Reproducir el fallo de endeudamiento original con respuesta real; alta/edición, simulación, pagos, abonos, reverso y lectura del plan |
| Presupuestos | Workspace, reglas, editor, categorías, porcentajes, límites, gráficos y navegación por código/pruebas | Crear/editar futuro, porcentajes sin ingreso base, copiar/promedio, 404 sin presupuesto, detalle, concurrencia y errores reales |
| Alertas/notificaciones | Rutas, preferencias, estados, contador, paginación y navegación por código/pruebas | Contador/lectura/archivo y restauración de preferencias con datos representativos |
| Reportes | Cuatro recorridos, helpers de gráficos/tablas, exportación y plantillas por código/pruebas | Datos de porcentajes/moneda, N/A y cero, filtros, contraste de series, PDF/XLSX final y teclado en gráficos |
| Perfil | Vista con 2FA activo en navegador; componentes y validación de perfil | Ambos estados MFA, actualización, errores del servidor, historial, descarga y supresión en un entorno de prueba expresamente destinado a ello |
| Administración | Panel, crear vigencia, usuarios y categorías en navegador; otras plantillas y peticiones por código | Usuario bloqueado/inactivo/activo, validación real de vigencias/tarifas/calendario, permisos y errores; sin crear administradores reales |
| Política, errores y desarrollo | Plantillas, componentes, rutas y exclusión de styleguide de producción | Error real y recuperación; compatibilidad de navegador; lector de pantalla; PDFs finales |

Las vistas auxiliares antiguas (`dashboard.blade.php`, `profile/edit` y parciales de Breeze, algunas vistas de autenticación) deben cruzarse con las rutas antes de invertir en ellas como si todas fueran pantallas activas. Su presencia en el inventario no autoriza borrarlas. El comportamiento de flechas de `ui/movements.js`, por ejemplo, solo debe priorizarse si el recorrido heredado que monta ese componente sigue activo.

### Matriz mínima de comprobación visual

Para cerrar cada pantalla activa, usar **360, 390, 768, 1024, 1280, 1440 y 1920 px**, claro/oscuro y zoom de texto/navegador. Los anchos adicionales son trabajo pendiente, no tamaños ya certificados por esta auditoría. Probar:

1. Un título principal; acción dominante coherente; retorno secundario; eliminación claramente diferenciada.
2. Campos vacíos, completos, con etiquetas largas y con error; ayuda debajo; filas alineadas por grupo.
3. Montos cortos, millones, máximo contractual, cero permitido, negativos solo donde apliquen y decimales.
4. Escritura/pegado/selección/borrado con cursor estable; enviar el mismo importe que se ve.
5. Listados con 0, 1, 20 y muchas filas; filtros persistentes; totales del conjunto completo; páginas válidas.
6. Tablas móviles con etiqueta por celda, también cuando las filas se crean después del arranque.
7. Carga, vacío, éxito, 422, acceso denegado, recurso ausente, sesión vencida y desconexión; reintento sin duplicar operaciones.
8. Modales abiertos/cerrados/reabiertos, Tab/Shift+Tab/Escape, foco restaurado, altura baja y teclado virtual.
9. Gráficos con lectura equivalente en tabla y presentación correcta de moneda/porcentaje; no depender solo del color.
10. Chrome/Edge y al menos un navegador móvil real; las fixtures de esta sesión no equivalen a compatibilidad cruzada.

## 10. Orden recomendado para corregir

### Bloque A — Evitar bloqueos y datos engañosos

F05, F06, F11, F12, F13, F19, F20, F22, F24–F26, F34, F36 y F42. Son recorridos que ya pueden mostrar datos equivocados, perder información o impedir acciones. Añadir comprobaciones focalizadas de regresión.

### Bloque B — Unificar la captura de valores

F01, F02, F07, F08, F27 y F31–F33. Establecer dinero canónico, presentación localizada y precisiones por campo; revisar el inventario de controles completo. Coordinar en este bloque F09/F10 para evitar rehacer dos veces la calculadora.

### Bloque C — Distribución y acciones

F03, F15, F16, F21, F23, F30, F37, F39, F41 y F44. Empezar por componentes compartidos y luego excepciones de pantalla; no arreglar una alineación acumulando más estilos inline o más `!important`.

### Bloque D — Accesibilidad, estados y contratos

F04, F14, F17, F18, F28, F29, F35, F38, F40 y F43. Ejecutar la matriz visual y los recorridos de error con una sesión de prueba válida.

### Bloque E — Cierre verificable

F45, compilación, pruebas y lint completos; revisar exportaciones y estados pendientes. Que build pase no basta para cerrar un flujo; que un control se vea bien no prueba que envíe el contrato correcto.

## 11. TODO(contrato) y coordinación

- **Garay / calculadora:** contrato simplificado sin opciones avanzadas, alcance de la conclusión y datos que antes se introducían manualmente. Luis retira la UI y la conecta; no modifica fórmulas del backend.
- **Teban / renta:** obligatoriedad, cero, máximos, precisión, ajustes negativos permitidos, justificación y nombres de errores; acordar los mismos campos entre cliente y servidor.
- **Esteban / seguridad social:** respuesta correcta al superar topes, avisos y valores finales. Frontend debe mostrarlos, sin enmascarar el fallo con cálculos paralelos.
- **Garay / endeudamiento:** revisar respuesta y datos reales, promedio, periodos y caso sin ingresos. La ruta no está ausente en esta copia. Luis desacopla indicador/listado.
- **Teban / tarjetas:** precisar el recorrido de pago con tarjeta y su efecto; frontend conserva enum/etiqueta correctos. No se atribuye al selector un fallo de negocio no reproducido.
- **Equipo / moneda:** reconciliar la instrucción histórica COP único con el soporte COP/USD actual. Mientras se resuelve, no retirar campos de un flujo existente ni anunciar conversiones que no se verifican.
- **Backend / vigencias y calendario:** entregar años disponibles y fecha oficial o ausencia explícita; quitar fechas ficticias de la vista.
- **Equipo / acceso QA:** una sesión y datos de prueba representativos, incluidos los seis productos de inversión, estados de renta y usuarios/perfiles de ambos roles, para cerrar la columna de pendientes sin modificar datos reales.

## 12. Verificación ejecutada y evidencias

| Comprobación | Resultado |
|---|---|
| `npm test` | 84 pruebas, 84 aprobadas, 0 fallidas; aproximadamente 45,8 s |
| `npm run build` | Aprobado; compilación de producción completada |
| `npm run lint` | Falló en `resources/views/inversiones/show.blade.php` por la regla de flotantes en vistas |
| Evaluación de `financialLintStatus` sobre todas las vistas | 3 errores: detalle de inversiones, calculadora de renta y PDF de flujo de caja; 5 archivos con deuda heredada |
| `npm run check-icons` | Aprobado, 87 claves; no certifica ausencia de SVG directos |
| `npm run check-styleguide` | Aprobado; guía/fixtures/estilos de guía excluidos de producción |
| `php artisan view:cache` | Aprobado; compila plantillas, sin demostrar que todas sus ramas ejecutan correctamente |
| `php artisan route:list --except-vendor --path=creditos/endeudamiento` | Ruta GET/HEAD presente |
| `php artisan route:list --except-vendor --path=renta` | Rutas de renta y endpoint de simulación presentes |
| Reproducciones aisladas de formato y payload | Apóstrofos, recorte a cuatro decimales, transformación de texto inválido y pérdida de parámetros de rentista confirmados |
| Navegador sobre 16 vistas ficticias | Casos descritos y medidas guardados; login real sin acceso con la cuenta de demo probada |

En las mediciones guardadas **no se observó scroll horizontal del documento** en las combinaciones revisadas. Algunas medidas de celdas TH aparentan salirse del ancho móvil porque su encabezado está oculto/recortado; no se contaron como un desbordamiento visible. Tampoco se declara correcto todo contenido de las tablas por esa sola medida: F17 y los controles comprimidos siguen siendo problemas reales.

Evidencias locales en `docs/qa-evidence/frontend-2026-11-20/`:

- `inventario-frontend.csv`: 206 archivos y recuentos de estructura.
- `inventario-controles.csv`: 278 declaraciones con archivo/línea/tipo/paso/componente; incluye controles no monetarios para no perder alcance.
- `deuda-financiera.json`: resultado de la regla en todos los archivos afectados.
- `reproducciones-logica.json`: salidas exactas de las cuatro causas reproducidas.
- `viewport-results.json`: mediciones de las primeras diez vistas; se excluyó la medición de 1280 que seguía a 768.
- `additional-views.json`: seis vistas adicionales en 360/1280; H1, controles sin etiqueta y alturas de acciones.
- `perfil-mfa-formularios.json`: asociación real de campos a formularios; sin contraseñas ni datos personales reales.
- `renta-complementaria-1280.jpg` y `renta-complementaria-360.jpg`.
- `renta-modal-oscuro-1280.jpg`.
- `admin-modal-movil-360.jpg`.

La carpeta `docs/qa-evidence/` está excluida por el `.gitignore` existente: las evidencias permanecen en esta máquina, pero no se incluirán automáticamente en un commit. Este informe está fuera de esa exclusión. No se cambiaron los criterios de ignorado ni se añadieron archivos por la fuerza.

## 13. Archivos tocados, requisitos y conclusión de alcance

**Implementación:** ningún archivo de frontend ni backend modificado. No se corrigieron todavía los hallazgos, porque el encargo era primero revisar. Se generó este informe y evidencias; build y compilación actualizaron artefactos/cachés ignorados. No se instalaron dependencias ni se hicieron migraciones/semillas.

**Requisitos utilizados:** RF v1.1, RNF v1.1, documentación de renta/inversiones y decisión explícita del usuario. Trazabilidad principal: RNG-07 (confirmación de eliminación), RNG-09 (rangos); RN-044.3 (ajustes/justificación), RN-045 (evaluación), RN-047.2 (fecha de presentación), RN-053.1/RN-053.3 (endeudamiento); RNF-011 (exactitud), RNF-012 (errores), RNF-013 (uso), RNF-014 (adaptación), RNF-015 (accesibilidad) y RNF-016 (formatos locales). Son requisitos revisados y criterios de corrección; **no se declaran implementados ni cumplidos de forma integral**.

La revisión deja un backlog accionable para Luis y una lista explícita de dependencias. El cierre completo exige corregir los hallazgos y ejecutar los recorridos integrados pendientes con sesión de prueba válida; no debe confundirse la cobertura de código y fixtures de este diagnóstico con haber aprobado todos los flujos reales.

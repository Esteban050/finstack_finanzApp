# FinanzApp — Hallazgos de la prueba end-to-end (para resolver)

Documento de traspaso para un agente de IA que corrija la aplicación. Resume lo encontrado al ejecutar manualmente el caso de prueba "Teban" (Independiente, inversiones en CDT y acciones) sobre todos los módulos, comparando lo que muestra la app contra el cálculo hecho a mano.

## 0. Contexto y reglas de lectura

- App: Laravel + PostgreSQL (`bd_finanzApp`), local en `http://127.0.0.1:8000`. Interfaz en español (miles con ".", decimales con ",").
- Fecha de la prueba: 24/11/2026 (zona America/Bogota). Cuenta usada: `estebansalazar1407@metabiblioteca.com`.
- Parámetros 2026 cargados: UVT $52.374; SMMLV $1.750.905; topes Renta 4.500 UVT (patrimonio) y 1.400 UVT (ingresos, tarjeta, compras, consignaciones); renta exenta 25 % con límite 790 UVT; límite global 40 % / 1.340 UVT; tabla Art. 241 ET; PILA independiente: salud 12,5 %, pensión 16 %, IBC 40 %, ARL clase I 0,522 %, FSP por tramos.
- Cada hallazgo trae: **Observado**, **Esperado** (con la cuenta a mano), **Cómo reproducir**, **Causa probable** (hipótesis, no verificada en código salvo que se diga) y **Criterio de aceptación**.
- Severidad: **Alta** = dato o cálculo incorrecto que llega a otro módulo o a la declaración; **Media** = inconsistencia visible o regla de negocio dudosa; **Baja** = UX, redondeo o detalle.
- Tipo: **Bug** (la cuenta o la conexión está mal), **Decisión** (hay que definir la regla de negocio antes de cambiar código) o **Verificar** (no se pudo confirmar cuál es el comportamiento correcto).
- Todo lo demás se verificó a mano y **coincide** (ver sección 5); no hay que tocarlo.
- Evidencia: las capturas `c01`–`c54` están en `evidencias_capturas.zip` y el informe completo en `Caso_de_prueba_FinanzApp_Teban.docx/.pdf`. La columna "Captura" de cada hallazgo indica cuál.

## 1. Resumen priorizado

| ID | Módulo | Resumen | Sev. | Tipo |
|---|---|---|---|---|
| H01 | Renta ← PILA | Aportes de salud y pensión pagados llegan a Renta como $0 | Alta | Bug |
| H02 | Renta ← Inversiones | Utilidad de venta de acciones usa costo de la 1.ª compra (75.000) y no el costo promedio (60.066,67) | Alta | Bug |
| H03 | Inversiones → Ingresos | Rendimientos de CDT llegan netos y con retención en $0 | Alta | Bug |
| H04 | Inversiones | Redención anticipada de CDT paga el 100 % del rendimiento pactado | Alta | Decisión/Bug |
| H05 | Resumen ← Inversiones | "Portafolio de inversiones" muestra $0 (hay $7.720.000) | Alta | Bug |
| H06 | Notificaciones ← Créditos | Tarjeta: ninguna alerta al 90 % de uso ni al superar el cupo | Alta | Bug |
| H07 | Créditos (tarjeta) | Compra posterior en la misma tarjeta paga 20 días de interés en vez de los reales | Alta | Bug |
| H08 | Créditos / Reportes | Endeudamiento: denominador distorsionado (478 %) | Media | Decisión |
| H09 | Presupuestos/Alertas/Reportes | Tres valores distintos de "ingreso promedio" | Media | Bug |
| H10 | Notificaciones | No salta "Vas a pasarte" cuando la proyección supera el umbral | Media | Bug/Verificar |
| H11 | Gastos / Créditos | Doble conteo: compra con tarjeta + cuota pagada de esa misma compra | Media | Decisión |
| H12 | Reportes | Retenciones: la base incluye el capital devuelto del CDT | Media | Bug |
| H13 | Reportes | "Valores deducibles generados" suma la retención en la fuente | Media | Bug |
| H14 | Resumen | "Próximos 15 días" muestra $0 para un recurrente de $110.000 | Media | Bug |
| H15 | Ingresos/Gastos | Recurrentes: 0 ocurrencias generadas al guardar y tarjeta con título genérico | Media | Verificar |
| H16 | Créditos | Pago adelantado cobra el interés del mes completo | Media | Decisión |
| H17 | Créditos (tarjeta) | Superar el cupo solo avisa; no bloquea | Baja | Decisión |
| H18 | Configuración | No hay tasa de usura cargada | Baja | Config |
| H19 | Reportes | Gasto diario promedio divide entre 30 en vez de entre los días transcurridos | Baja | Bug |
| H20 | Renta | Dividendos no entran en C2 (ingresos brutos) | Baja | Verificar |
| H21 | Presupuestos | Proyección de línea fija con gasto $0 inconsistente entre líneas | Baja | Bug |
| H22 | Varios | Redondeos y presentación (ROI, ejecución presupuesto, antigüedad, concentración) | Baja | UX |
| H23 | Formularios | Campos con "0" precargado se concatenan; tasa solo acepta coma; simulación vacía | Baja | UX |
| H24 | Seguridad social | Reglas del selector de periodo confusas | Baja | Verificar |
| H25 | Autenticación | Correo de verificación falla (Resend 550) y términos sin publicar | Baja | Config |
| H26 | Inversiones | GMF nunca se aplica; ROI/XIRR del portafolio con bases distintas | Baja | Verificar |
| H27 | Renta | Año por defecto 2025; calculadora no refleja la simulación en la "Estimación" | Baja | UX |
| R01 | Migraciones | Dos migraciones pendientes causaban errores 500 (ya resueltas) | — | Resuelto |

## 2. Hallazgos de severidad alta

### H01 — Aportes obligatorios de salud y pensión no llegan a Renta
- **Observado:** en `/renta/2026/consolidado` y `/renta/2026/estimacion`, "Salud y pensión obligatorias (INCRNGO)" muestra **$0** con el texto "certificados en tus pagos". Persiste después de aplicar migraciones.
- **Esperado:** el módulo Seguridad social tiene el periodo 2026-10 **Pagado** (salud 450.000 + pensión 576.000 = **1.026.000**) y un acumulado anual de salud 668.900 + pensión 856.200 = **1.525.100** (incluye PILA de noviembre 499.100: salud 218.900 + pensión 280.200). Debe entrar como mínimo 1.026.000 (periodos pagados) o 1.525.100 si el periodo de noviembre se cuenta como pagado.
- **Reproducir:** registrar y pagar PILA de octubre 2026 (honorarios 9.000.000, ARL I, planilla 8839201948, 24/11/2026) y abrir Renta 2026 → Estimación, paso 2.
- **Causa probable:** la estimación lee un campo/tabla que el pago PILA no alimenta (o exige un paso de "certificación" que no existe en la UI). Revisar de dónde sale el valor y por qué el pago PILA (que sí crea el gasto "Aporte PILA independiente") no actualiza esa fuente.
- **Efecto:** renta líquida ordinaria sobreestimada en 1.026.000–1.525.100. Con los datos de prueba el impuesto sigue en $0, pero con otros datos cambia el resultado.
- **Criterio de aceptación:** tras pagar la PILA, el INCRNGO de Renta es igual a salud + pensión de los periodos pagados del año gravable, y la depuración se recalcula.
- **Captura:** c37, c39.

### H02 — Utilidad de venta de acciones inconsistente entre Ingresos y Renta
- **Observado:** Ingresos registra "Utilidad por venta de acciones / ETF" **$60.066,67**. Renta usa **$75.000** (en "ingresos no laborales" de C2 y en "utilidad exenta Art. 36-1" del INCRNGO). Diferencia $14.933,33.
- **Esperado:** venta 22/11: 20 acciones × 44.000 = 880.000, comisión 5.000. Costo promedio ponderado 40.746,6667 (= (4.000.000 + 2.100.000 + 12.000) / 150). Utilidad = 880.000 − 20 × 40.746,6667 − 5.000 = **60.066,67**.
- **Hipótesis fuerte:** 75.000 = 880.000 − 5.000 − 20 × **40.000**, es decir Renta calcula el costo con el precio de la primera compra (¿FIFO o lote inicial?) y Ingresos con costo promedio. Hay dos métodos de costo en el mismo sistema.
- **Reproducir:** acción PFBCOLOM, compra 24/09 100 × 40.000; compra 15/10 50 × 42.000 + comisión 12.000; venta 22/11 20 × 44.000 + comisión 5.000. Comparar `/ingresos` con `/renta/2026/estimacion`.
- **Criterio de aceptación:** un único método de costo (definir cuál exige la norma fiscal aplicable y usarlo en ambos módulos); ambos módulos muestran la misma utilidad.
- **Captura:** c36, c39.

### H03 — Rendimientos de CDT llegan a Ingresos netos y sin retención
- **Observado:** en Ingresos, "Rendimientos de inversiones" (24/10/2026) aparece con monto **43.137,98** y retención **$0**. La redención del 24/11 aparece como **221.575,37** y retención $0.
- **Esperado:** abono de intereses 24/10: bruto **44.935,40** (= 5.000.000 × (1,115^(30/365) − 1)), retención 4 % = **1.797,42**, neto 43.137,98. Redención: bruto **230.807,68**, retención **9.232,31**, neto 221.575,37.
- **Efecto:** Ingresos subestima el bruto y las retenciones en 11.029,73 en total, lo que contradice Reportes › Utilidades (que sí muestra 44.935,40 y retención 1.797,42) y los totales de Ingresos (bruto 14.197.912,98; retención 1.140.000 en vez de 1.151.029,73).
- **Criterio de aceptación:** el ingreso generado por inversiones guarda bruto, retención y neto por separado y coincide con el módulo Inversiones y con Reportes.
- **Captura:** c06 (lista de ingresos).

### H04 — Redención anticipada de CDT paga el rendimiento completo
- **Observado:** CDT Davivienda de 180 días, 5.000.000 al 11,5 % EA, apertura 24/09. El formulario de redención sugiere la fecha de vencimiento (23/03/2027); al poner 24/11/2026 (día 61) la app la acepta sin advertencia y liquida bruto **5.230.807,68** (= capital + 275.743,08 total pactado − 44.935,40 ya abonado). Estado → Liquidada.
- **Esperado (cuenta a mano):** rendimiento causado al día 61 = 5.000.000 × (1,115^(61/365) − 1) = **91.792,95**; menos 44.935,40 abonado = **46.857,55** adicionales, no 230.807,68. La diferencia es 183.950,13.
- **Decisión de negocio:** definir qué ocurre al redimir antes del vencimiento (rendimiento causado a la fecha, penalidad, o bloqueo/advertencia). Hoy se paga como si hubiera vencido.
- **Criterio de aceptación:** la redención antes del vencimiento muestra advertencia y calcula según la regla definida; el neto, la retención y los reportes se actualizan con ese valor.

### H05 — "Portafolio de inversiones" en Resumen muestra $0
- **Observado:** `/resumen` (noviembre 2026) → "Portafolio de inversiones: $0".
- **Esperado:** Inversiones muestra valor total **7.720.000** (acción 130 × 44.000 = 5.720.000 + CDT Bancolombia 2.000.000; el CDT Davivienda liquidado no suma) y capital aportado neto 7.237.000.
- **Criterio de aceptación:** Resumen usa el mismo valor consolidado que Inversiones (misma fecha de corte).
- **Captura:** c35.

### H06 — Sin notificación por uso del cupo de la tarjeta
- **Observado:** en Notificaciones → Preferencias existe el tipo "Consumos con tarjeta de crédito" con umbral por defecto **80 %**. La tarjeta Bancolombia (cupo 3.000.000) llegó a **90 %** (2.700.000) y luego a **103,33 %** (3.100.000, cupo disponible −100.000). El contador de notificaciones no cambió (17 sin leer) en ninguno de los dos casos.
- **Esperado:** al menos una notificación al cruzar 80 % y otra (crítica) al superar el cupo.
- **Lo que sí ocurre:** toast "La tarjeta Bancolombia supera su cupo en $100.000" al guardar el gasto, y aviso en el detalle del crédito.
- **Reproducir:** registrar gasto con tarjeta nueva (cupo 3.000.000), luego otros 1.500.000 y 400.000 con la misma tarjeta; abrir `/notificaciones`.
- **Causa probable:** el generador de alertas no evalúa ese tipo para créditos tipo tarjeta, o no se dispara al crear gastos vinculados.
- **Criterio de aceptación:** se genera notificación al cruzar el umbral configurado y otra al superar el cupo; respeta las preferencias del usuario.
- **Captura:** c48, c49, c50.

### H07 — Interés de compras posteriores en la misma tarjeta
- **Observado:** al agregar compras a la tarjeta existente, el plan se recalcula como si todo el saldo se hubiera causado desde la primera compra (10/11/2026). La cuota 1 (vence 30/11) incluye interés de **20 días** sobre el saldo total: con saldo 3.100.000 → **42.217**.
- **Esperado:** cada compra causa interés desde su propia fecha. Avianca (1.500.000) y Hotel (400.000) se compraron el 24/11, o sea 6 días hasta el 30/11: Platzi 1.200.000 × 20 días = 16.342,17; Avianca 1.500.000 × 6 días = 6.099,33; Hotel 400.000 × 6 días = 1.626,49. Total **24.067,99**.
- **Diferencia:** la app cobra **18.149,29** de más en la cuota 1 (todo con 28 % EA, base 365).
- **Reproducir:** gasto TC 10/11 Platzi 1.200.000 (tarjeta nueva, corte 15/11, pago 30/11, 28 % EA, cupo 3.000.000, 6 cuotas); luego 24/11 Avianca 1.500.000 y Hotel 400.000 con la misma tarjeta.
- **Criterio de aceptación:** el interés de la primera cuota se calcula por compra y por días reales; el valor sale igual a la suma por compra.
- **Captura:** c48, c50.

## 3. Hallazgos de severidad media

### H08 — Endeudamiento con denominador distorsionado
- **Observado:** Créditos y Reportes muestran endeudamiento **478,00 %** (rojo) = cuotas del mes 206.199 / ingreso neto promedio de 3 meses cerrados **43.138**. Antes de la tarjeta mostraba **0,00 %** porque la cuota del crédito libre inversión vence el 24/12 (cuotas del mes = 0).
- **La cuenta coincide con la fórmula** (206.199 / 43.137,98 = 477,999 %), pero el resultado no es útil: el único ingreso de un mes cerrado fue un abono de CDT de octubre. Con el ingreso real de noviembre (13.536.417,04) el indicador sería **1,52 %** (o 2,62 % con la cuota del crédito libre inversión).
- **Decisión:** definir qué hacer cuando los meses cerrados tienen ingresos atípicos o insuficientes (usar el mes en curso, exigir mínimo de meses con datos, mostrar "No calculable").
- **Captura:** c14, c47.

### H09 — Tres valores de "ingreso promedio"
- **Observado:** Presupuestos: "No calculable (ingreso promedio $0)"; Notificaciones (alerta "Gasto proyectado alto"): **$14.379** (= 43.137,98 / 3); Reportes y Créditos: **$43.138** (= 43.137,98 / 1).
- **Esperado:** una sola definición (y una sola función) de ingreso promedio usada por los cuatro módulos.
- **Criterio de aceptación:** el mismo dato base produce el mismo valor en todos los módulos.

### H10 — Alerta "Vas a pasarte" no se genera
- **Observado:** con umbral 110 % por defecto, la proyección de la línea PILA (1.929.875 sobre 450.000 = 429 %) y la del total (201,52 %) superan el umbral, pero no hay alerta de ese tipo en la lista.
- **Esperado:** una alerta "Vas a pasarte" (o aclarar qué mide exactamente ese tipo).
- **Verificar:** confirmar con el diseño si aplica a líneas o solo al total y en qué condiciones se dispara.

### H11 — Doble conteo de las compras con tarjeta
- **Observado:** cada compra con tarjeta crea un gasto el día de la compra (3.100.000 en total) y cada cuota pagada de esa tarjeta crea otro gasto "Obligaciones financieras › Cuota de crédito / hipoteca" (532.681). Total del listado: **8.106.449** = 4.473.768 + 3.100.000 + 532.681.
- **Efecto:** los gastos del mes, la ejecución del presupuesto, los indicadores de Reportes y el criterio C4 de Renta ("compras y consumos") cuentan la misma plata dos veces.
- **Decisión:** definir si el gasto se reconoce al comprar (y la cuota solo amortiza deuda) o al pagar.
- **Captura:** c54, c53.

### H12 — Reporte de retenciones incluye el capital
- **Observado:** Reportes › Retenciones 2026: "Total bruto sujeto a descuento" **5.275.743,08** (= redención 5.230.807,68, que incluye capital, + abono 44.935,40).
- **Esperado:** base = rendimientos **275.743,08**. Retención 11.029,73 y neto sí coinciden.

### H13 — Reporte de utilidades: "valores deducibles" incluye la retención
- **Observado:** "Total valores deducibles generados" = **71.096,40** = 60.066,67 (utilidad Art. 36-1) + 11.029,73 (retención). La etiqueta dice "Aportes FPV + Cesantías exentas + Art. 36-1".
- **Esperado:** la retención en la fuente no es un valor deducible; debería quedar 60.066,67. (Ver H02: el valor correcto de la utilidad también depende del método de costo.)

### H14 — Resumen: "Próximos 15 días" con monto $0
- **Observado:** `/resumen` muestra "Claro Hogar Internet 24/11/2026 **$0**" para el recurrente de **110.000** mensual.
- **Esperado:** $110.000.

### H15 — Recurrentes no generan ocurrencias y título genérico
- **Observado:** al guardar un ingreso recurrente (Soporte mensual, 2.000.000, retención 11 %, inicio 24/11, automático) y un gasto recurrente (Claro Hogar, 110.000, inicio 24/11, automático), el resultado es "0 ocurrencias generadas" con próxima fecha 24/11. En la pestaña Recurrentes de Gastos, la tarjeta se titula "Gasto recurrente" y no el comercio.
- **Verificar:** si la generación depende de un job programado (`php artisan schedule:run` / cola) que no corría en el entorno de prueba, o si debería generarse la ocurrencia del día de inicio al guardar.
- **Criterio de aceptación:** con el scheduler activo se genera la ocurrencia de la fecha de inicio (o el diseño lo documenta) y el título muestra el comercio.

### H16 — Pago adelantado cobra interés de un mes completo
- **Observado:** crédito de libre inversión (10.000.000, 18 % EA, 36 cuotas, primera cuota 24/12/2026). Se pagó el 24/11 (30 días antes de la cuota 1) con "Anticipar cuotas": capital 215.984 + **interés corriente 138.884** (mes completo, 10.000.000 × 1,388843 %).
- **Decisión:** definir si el interés corriente se causa por días reales cuando se paga antes de la fecha de la cuota. La amortización en sí es correcta (cuota 354.868 = 10.000.000 × i / (1 − (1+i)^−36)).
- **Nota:** el sistema trató el pago como "excedente" porque aún no había cuota exigible y obligó a elegir "Anticipar cuotas" o "Abonar a capital". Esto es coherente pero conviene documentarlo.

## 4. Hallazgos de severidad baja

- **H17 — Cupo superado no bloquea:** el gasto se registra igual y solo hay aviso (el texto del formulario dice "Si los consumos lo superan, se avisa"). Es coherente con el diseño; confirmar si se quiere un bloqueo opcional.
- **H18 — Tasa de usura sin cargar:** aviso "No hay tasa de usura cargada para el 10/11/2026: no se pudo validar la tasa" (y para 24/11). Cargar la tasa en Administración › Vigencias y tarifas para poder validar el 28 % EA.
- **H19 — Gasto diario promedio:** Reportes muestra **137.296,67** (= 4.118.900 / 30, días del mes). La definición en pantalla dice "días transcurridos" (24 al momento de la prueba) → **171.620,83**. Alinear fórmula y etiqueta.
- **H20 — Dividendos y C2:** el consolidado de Renta dice que no considera dividendos (240.000) en los ingresos brutos de C2. Verificar con la norma si deben sumarse al criterio de ingresos brutos totales.
- **H21 — Proyección de líneas fijas:** en Presupuestos, "Internet Hogar" (gastado 0) proyecta 110.000 (su asignado) mientras "Cuota de crédito" (gastado 0) proyectaba 0. Unificar la regla de proyección para líneas fijas con gasto cero.
- **H22 — Redondeos y presentación:**
  - ROI de una inversión: +6,75 % arriba y +6,76 % abajo en la misma pantalla (real 413.000 / 6.112.000 = 6,757 %).
  - Ejecución del presupuesto 130,30 % vs 130,34 % real (4.118.900 / 3.160.000), y concentración por categoría 37,50 % / 16,30 % en Reportes frente a 37,48 % / 16,27 % en Resumen: se redondean valores ya redondeados.
  - Regla 50/30/20 suma 100,1 %.
  - "Antigüedad 0,16953355192234 años" sin redondear.
  - Diferencias de 3 pesos (saldo cuota 12) y 12 pesos (cuota 36) por redondeo de cuota en el plan del crédito: son un ajuste normal, solo documentarlo.
- **H23 — Formularios:**
  - Campos con "0" precargado (retención %, montos) concatenan lo que se escribe ("11" queda "110"); el simulador de Art. 241 acumuló 2000 como 20.002.000. Seleccionar todo al enfocar.
  - El campo de tasa muestra el placeholder "Ej: 11.90" pero descarta el punto; solo acepta coma.
  - La simulación del formulario de CDT muestra "—" y no calcula.
  - El resumen de un ingreso en USD muestra "$ 500" sin indicar la moneda.
  - Aviso "Valoración desactualizada (más de 35 días)" en inversiones recién creadas con fecha de apertura antigua.
- **H24 — Selector de periodo de PILA:** para cotizar el mes M toma ingresos de M−1 y dice "no hay ingresos confirmados de rentas de trabajo" aunque hay ingresos en noviembre; permite diciembre 2026 (usa octubre) pero rechaza enero 2027 con "El periodo no puede ser posterior al mes actual". Definir la regla y unificar mensajes.
- **H25 — Autenticación y textos:** el correo de verificación falla con Resend 550 ("dominio finstack.online no verificado"), por lo que no se pudo activar la cuenta `teban.prueba@finanzapp.test`. Además, en el registro: "Los términos de uso aún no tienen una página publicada."
- **H26 — Inversiones:** (a) el GMF (0,4 %) no se aplicó en ningún movimiento (columna "—"); verificar si es correcto por la exención de 350 UVT. (b) ROI del portafolio 8,91 % (= 723.000 / 8.112.000 aportes brutos) frente a 11,83 % por inversión (= 723.000 / 6.112.000); XIRR de 115,68 % sobre un periodo muy corto puede inducir a error.
- **H27 — Renta:** la pantalla abre por defecto en 2025 con todo en cero (el año en curso es 2026). En la calculadora de límites la "Estimación del sistema" no cambia con los valores simulados porque usa los registros del usuario.

## 5. Resuelto durante la prueba

**R01 — Migraciones pendientes.** En la primera ejecución dos pruebas fallaban con `SQLSTATE[42703]`: columna `creditos.cupo` (gasto con tarjeta, ref. 272880e7-a24b-4a62-affe-32a04283e4fa) y `declaraciones_renta.c1_inmuebles_valor_fiscal_cop` (Información complementaria, ref. add30d43-d745-45df-8f21-1d32c0956ebd). Tras `php artisan migrate` ambas funcionan. Recomendación: validar migraciones en el despliegue/CI para que no se repita.

## 6. Lo que coincide y no hay que tocar

- Neto de ingresos (retención en % y en pesos, USD con TRM 3.309,55 → 1.654.775).
- Totales de gastos y clasificación; PILA (IBC 40 %, mínimo 1 SMMLV, FSP 1 % para IBC > 4 SMMLV, ARL clase I, redondeo a 100), con 4 casos verificados, y pago PILA → Gastos.
- CDT: vencimiento, rendimiento, retención 4 %, abono de intereses; acciones: costo promedio 40.746,6667, valorización, ROI, dividendos, venta.
- Crédito libre inversión (cuota 354.868, intereses 2.775.248, saldo tras cuota) y todo el plan de la tarjeta (cuotas, intereses, total, cupo disponible, "Para cancelar a la fecha"); pago de cuota → Gastos, saldo, Presupuestos y Notificaciones.
- Presupuestos: gastado, disponible, porcentaje, proyección (30/24); alertas por umbral de categoría, presupuesto total y límite mensual.
- Reportes › Indicadores (tasa de ahorro, balance, concentración, ticket promedio, regla 50/30/20 salvo redondeos) y Resumen (ingresos, gastos, balance, variaciones).
- Renta: topes en pesos (4.500 y 1.400 UVT, 2025 y 2026), C1, C4, C5, retenciones, renta exenta 25 %, límite 40 %/1.340 UVT, renta líquida gravable 10.767.740 (205,59 UVT), impuesto Art. 241 = 0, saldo a favor 1.151.000, calculadora de límites y simulador de la tabla Art. 241 (1.500 / 2.000 / 5.000 UVT).

## 7. Datos para reproducir el escenario

- **Perfil:** Independiente por cuenta propia, ARL clase I (0,522 %), afiliación obligatoria; CC, Bogotá D.C.
- **Ingresos (honorarios, retención):** 24/11 3.500.000 (ret. 150.000); 05/11 6.000.000 ret. 11 %; 12/11 3.000.000 ret. 330.000; 18/11 USD 500 a TRM 3.309,55; recurrente mensual 2.000.000 ret. 11 % desde 24/11.
- **Gastos:** 02/11 arriendo 1.800.000; 08/11 supermercado 420.000 (más 250.000 previo); 14/11 cine 60.000; 24/11 Netflix 45.000; recurrente Claro Hogar 110.000 desde 24/11; PILA noviembre 499.100 previa; PILA octubre pagada 1.044.800 (planilla 8839201948).
- **Tarjeta:** Bancolombia, 28 % EA, cupo 3.000.000, 6 cuotas, corte 15/11, pago 30/11: Platzi 10/11 1.200.000; Avianca 24/11 1.500.000; Hotel Cartagena 24/11 400.000. Pago cuota 1 el 24/11: 532.681 (Anticipar cuotas).
- **Crédito previo:** libre inversión Bancolombia, 10.000.000, 18 % EA, 36 cuotas, primera cuota 24/12/2026 (pago de cuota 1 el 24/11: 354.868).
- **Inversiones:** CDT Bancolombia 360 días 2.000.000 (previo); CDT Davivienda 180 días 5.000.000, 11,5 % EA, apertura 24/09/2026 (abono 24/10, redención 24/11); acciones PFBCOLOM: compra 24/09 100 × 40.000, compra 15/10 50 × 42.000 + 12.000 comisión, dividendo 10/11 1.600 por acción, valoración 20/11 a 43.500, venta 22/11 20 × 44.000 + 5.000 comisión.
- **Presupuesto noviembre 2026:** PILA 450.000; Arriendo 1.200.000; Cuota de crédito 500.000; Supermercado 800.000; Streaming 100.000; Internet 110.000 (total 3.160.000). Alertas: categoría 50/80/100 %, límite mensual 3.500.000.
- **Renta (información complementaria):** consignaciones 26.126.775; compras 4.473.768; tarjeta 0; inmuebles 0.

## 8. Sugerencia de orden de trabajo

1. **Datos que llegan mal a otros módulos:** H03, H05, H14, H01, H02 (corrigen cifras visibles y la declaración).
2. **Tarjeta de crédito:** H07 y H06; luego H11 y H17 una vez definida la regla de negocio.
3. **Reglas de negocio por definir:** H04, H08, H16 (pedir la decisión antes de programar).
4. **Consistencia de datos entre módulos:** H09, H10, H12, H13, H19.
5. **UX, configuración y verificaciones:** H15, H18, H20–H27.

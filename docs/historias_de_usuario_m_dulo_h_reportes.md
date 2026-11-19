# Historias de Usuario · Módulo H: Reportes y Dashboards Dinámicos (MOD-11)

**Proyecto:** FinanzApp (SCFP)  
**Alcance:** Pantallas 06 a 10 (`RF-065`, `RF-066`, `RF-067`, `RF-070` y propuestas nuevas basadas en AN §6.6)  
**Moneda base:** COP  

---

## HU-H01 · Visualizar el Dashboard Principal (Resumen) y sus KPIs

* **Referencia:** `RF-065` (`RN-065.1`, `RN-065.2`, `RN-065.4`, `RN-065.5`, `CA-065.1`, `CA-065.2`)
* **Ruta:** `/resumen`
* **Pantalla:** 06

> **Como** usuario de FinanzApp,  
> **quiero** consultar un tablero consolidado con mis indicadores clave, alertas de déficit y próximos pagos según el periodo seleccionado,  
> **para** conocer mi estado financiero general de un vistazo y navegar rápido al detalle de cada cifra.

### Criterios de aceptación

#### Escenario 1: Carga inicial y consistencia de cifras (`RN-065.1`, `RN-065.2`, `CA-065.1`)
* **Dado** que el usuario ingresa a la sección *Resumen*,
* **Cuando** la pantalla termina de cargar,
* **Entonces** el sistema selecciona por defecto el periodo *Mes actual* y muestra:
  1. El bloque *Próximos 15 días* con cuotas de créditos y gastos recurrentes próximos a vencer (indicando si el monto es estimado y está por confirmar).
  2. Las 7 tarjetas KPI: *Ingresos netos*, *Gastos*, *Balance*, *Tasa de ahorro*, *Ejecución del presupuesto*, *Endeudamiento* y *Portafolio de inversiones* (sin incluir *Patrimonio neto*).
  3. El valor de la tarjeta *Gastos* coincide exactamente con la suma del listado de gastos del periodo.

#### Escenario 2: Cambio dinámico de periodo (`RN-065.1`, `CA-065.2`)
* **Dado** que el usuario se encuentra en `/resumen`,
* **Cuando** cambia el selector de periodo a *Mes anterior*, *Trimestre*, *Año* o *Rango*,
* **Entonces** el sistema recalcula y actualiza automáticamente la alerta superior, las 7 tarjetas KPI y todos los gráficos del tablero.

#### Escenario 3: Variación accesible y navegación al detalle (`RN-065.4`, `RN-065.5`)
* **Dado** que existen datos en el periodo actual y en el periodo inmediatamente anterior,
* **Cuando** el usuario visualiza las tarjetas KPI,
* **Entonces** cada tarjeta muestra la variación frente al periodo anterior con flecha y signo (sin depender solo del color) y, al hacer clic sobre cualquiera de ellas, redirige al módulo o reporte detallado correspondiente.

#### Escenario 4: Alerta de balance negativo
* **Dado** que los gastos del periodo seleccionado superan a los ingresos netos,
* **Cuando** se renderiza el tablero,
* **Entonces** se muestra un banner de alerta indicando la diferencia exacta, las principales categorías que causaron el aumento y un enlace directo a `/reportes/comparativo`.

---

## HU-H02 · Analizar gráficos de comportamiento financiero en el Resumen

* **Referencia:** `RF-065` (`RN-065.3`, `RN-065.6`)
* **Ruta:** `/resumen`
* **Pantalla:** 06

> **Como** usuario de FinanzApp,  
> **quiero** visualizar gráficos comparativos de mis ingresos, gastos por categoría y distribución del gasto dentro del Resumen,  
> **para** identificar patrones de consumo, meses en déficit y oportunidades de recorte.

### Criterios de aceptación

#### Escenario 1: Gráfico de evolución de los últimos 12 meses (`RN-065.3`)
* **Dado** que el usuario tiene movimientos registrados en los últimos 12 meses,
* **Cuando** consulta la sección de gráficos del Resumen,
* **Entonces** se muestra un gráfico de barras agrupadas de *Ingresos netos* vs. *Gastos* mes a mes, resaltando en rojo los meses donde los gastos superaron a los ingresos.

#### Escenario 2: Distribución de gastos por categoría y naturaleza (`RN-065.3`)
* **Dado** que el usuario tiene gastos en el periodo seleccionado,
* **Cuando** visualiza la fila inferior de gráficos,
* **Entonces** el sistema despliega:
  1. Un gráfico de barras horizontales ordenado de mayor a menor con máximo 8 categorías principales (agrupando el resto en *Otras*), mostrando monto, porcentaje y enlace a `/reportes/gastos`.
  2. Dos gráficos circulares (tipo dona) con la proporción de *Fijo vs. Variable* y *Esencial vs. Discrecional*.

#### Escenario 3: Estado vacío sin registros (`RN-065.6`)
* **Dado** que un bloque del Resumen no cuenta con movimientos registrados en el periodo consultado,
* **Cuando** se carga la vista,
* **Entonces** el bloque muestra un mensaje de estado vacío claro acompañado de un botón o enlace de acción para registrar datos.

---

## HU-H03 · Consultar y exportar Indicadores de Salud Financiera

* **Referencia:** `RF-065` (Extensión / Propuesta nueva · AN §6.6)
* **Ruta:** `/reportes/indicadores`
* **Pantalla:** 07

> **Como** usuario de FinanzApp,  
> **quiero** evaluar mi salud financiera mediante indicadores estandarizados con sus fórmulas y referencias,  
> **para** entender qué tan equilibradas están mis finanzas y descargar el diagnóstico en PDF.

### Criterios de aceptación

#### Escenario 1: Visualización de los 7 indicadores
* **Dado** que el usuario accede a la pestaña *Indicadores* y selecciona un mes,
* **Cuando** se calculan las métricas del periodo,
* **Entonces** el sistema muestra 7 tarjetas con nombre, valor, gráfica visual y etiqueta de estado:
  1. **Tasa de ahorro:** $\frac{\text{ingresos netos} - \text{gastos}}{\text{ingresos netos}} \times 100$ (tendencia de 6 meses y línea de meta configurable, $10\%$ por defecto).
  2. **Ejecución del presupuesto:** $\frac{\text{gastado en categorías con presupuesto}}{\text{asignado}} \times 100$ (barra con marca en $100\%$ y estado *En control* $< 80\%$, *En riesgo* de $80\%$ a $< 100\%$, *Excedido* $\ge 100\%$).
  3. **Endeudamiento:** $\frac{\text{cuotas de deuda del mes}}{\text{ingreso neto promedio de 3 meses}} \times 100$ (medidor: *Verde* $\le 30\%$, *Amarillo* $\le 40\%$, *Rojo* $> 40\%$).
  4. **Gasto diario promedio:** $\frac{\text{gastos del periodo}}{\text{días del periodo}}$ (tendencia de 6 meses y referencia del mes anterior).
  5. **Concentración del gasto:** $\frac{\text{gasto de la categoría más grande}}{\text{gastos totales}} \times 100$ (barra apilada de las 3 categorías principales frente al resto).
  6. **Ticket promedio:** $\frac{\text{gastos totales}}{\text{número de gastos}}$ (comparativo frente al mes anterior y conteo total de transacciones).
  7. **Regla 50/30/20:** distribución porcentual de gastos *Esenciales*, *Discrecionales* (según la dimensión *Necesidad* de `RF-024`) y *Ahorro* sobre ingresos netos, frente a la referencia configurable en *Mi perfil*.

#### Escenario 2: Transparencia de cálculo ("Ver detalle")
* **Dado** que el usuario visualiza cualquier tarjeta de indicador,
* **Cuando** hace clic en *Ver detalle*,
* **Entonces** se despliega la fórmula matemática, el desglose con sus valores reales del mes y la guía de interpretación del rango de referencia.

#### Escenario 3: Exportación a PDF y aviso legal
* **Dado** que el usuario está en `/reportes/indicadores`,
* **Cuando** presiona *Exportar PDF*,
* **Entonces** se descarga un archivo PDF con los indicadores del periodo seleccionado y, tanto en pantalla como en el documento, figura el aviso legal: *"Valores estimados con fines informativos. No constituyen asesoría tributaria, contable ni financiera."*

---

## HU-H04 · Generar y exportar el Reporte de Flujo de Caja

* **Referencia:** `RF-066` (`RN-066.1`, `RN-066.4`, `CA-066.1`)
* **Ruta:** `/reportes/flujo-de-caja`
* **Pantalla:** 08

> **Como** usuario de FinanzApp,  
> **quiero** consultar el flujo de caja histórico agrupado por periodo con sus entradas, salidas y saldo acumulado,  
> **para** verificar cuánto dinero me sobró o faltó en cada periodo y exportarlo a Excel o PDF.

### Criterios de aceptación

#### Escenario 1: Consulta y agrupación de flujo de caja (`RN-066.1`, `RN-066.4`)
* **Dado** que el usuario accede a la pestaña *Flujo de caja*,
* **Cuando** selecciona un rango (*Desde* y *Hasta*) y una agrupación (*Semanal*, *Mensual*, *Trimestral* o *Anual*),
* **Entonces** el sistema genera:
  1. Un gráfico combinado con barras para el *Flujo neto* (resaltando meses negativos en rojo) y una línea superpuesta para el *Flujo acumulado*.
  2. Una tabla desglosada con *Ingresos netos* por fuente, *Gastos* por categoría, *Total ingresos*, *Total gastos*, *Flujo neto* y *Flujo acumulado*, acompañada de una nota explicativa al pie.

#### Escenario 2: Consistencia con el Dashboard (`CA-066.1`)
* **Dado** que el usuario consulta el flujo de caja mensual para un mes específico,
* **Cuando** revisa la fila *Flujo neto* de ese mes,
* **Entonces** el valor calculado ($\text{ingresos netos} - \text{gastos}$) coincide exactamente con el *Balance* mostrado en el Dashboard principal para el mismo mes.

#### Escenario 3: Exportación del reporte
* **Dado** que el reporte de flujo de caja tiene datos visibles,
* **Cuando** el usuario hace clic en *Exportar XLSX* o *Exportar PDF*,
* **Entonces** el sistema descarga el archivo en el formato elegido conservando el rango de fechas y la agrupación aplicada.

---

## HU-H05 · Analizar Gastos por Dimensión y Top de Comercios

* **Referencia:** `RF-067` (`RN-067.1`, `RN-067.2`, `RN-067.3`, `CA-067.1`, `CA-067.2`)
* **Ruta:** `/reportes/gastos`
* **Pantalla:** 09

> **Como** usuario de FinanzApp,  
> **quiero** cruzar mis gastos por dos dimensiones distintas y ver los comercios donde más gasto,  
> **para** descubrir en qué tipos de consumo y establecimientos se concentra mi dinero.

### Criterios de aceptación

#### Escenario 1: Cruce multidimensional de gastos (`RN-067.1`, `RN-067.2`, `CA-067.1`)
* **Dado** que el usuario ingresa a *Gastos por dimensión* y selecciona un periodo,
* **Cuando** elige una dimensión principal (*Agrupar filas por*) y una secundaria (*Columnas*) entre *Categoría*, *Subcategoría*, *Naturaleza*, *Necesidad*, *Medio de pago*, *Relevancia tributaria*, *Etiqueta* o *Mes*,
* **Entonces** el sistema muestra una tabla cruzada con las columnas de la dimensión secundaria más *Total*, *% del total*, *Gastos* (conteo) y *Promedio por gasto*, cuya fila *Total* suma exactamente el total de gastos del periodo.

#### Escenario 2: Advertencia al agrupar por etiquetas (`CA-067.2`)
* **Dado** que un mismo gasto puede tener múltiples etiquetas,
* **Cuando** el usuario selecciona *Etiqueta* como dimensión de agrupación,
* **Entonces** el sistema muestra una nota informativa indicando que los totales por etiqueta no son sumables entre sí.

#### Escenario 3: Ranking de comercios y exportación (`RN-067.3`)
* **Dado** que existen gastos con comercio o beneficiario en el periodo,
* **Cuando** se carga el reporte,
* **Entonces** se despliega el bloque *Comercios donde más gastaste* con hasta el Top 10 ordenado de mayor a menor (con barra proporcional, monto y porcentaje) y el botón *Exportar XLSX* descarga los datos de la consulta actual.

---

## HU-H06 · Comparar resultados financieros entre dos periodos

* **Referencia:** `RF-070` (`RN-070.1`, ajuste `RN-070.2`, `RN-070.3`, ajuste `CA-070.2`)
* **Ruta:** `/reportes/comparativo`
* **Pantalla:** 10

> **Como** usuario de FinanzApp,  
> **quiero** comparar mis ingresos, gastos, balance y ejecución presupuestal entre dos periodos equivalentes,  
> **para** identificar qué categorías subieron o bajaron y explicar los cambios de un periodo a otro.

### Criterios de aceptación

#### Escenario 1: Validación de duración equivalente (`RN-070.3`)
* **Dado** que el usuario accede a la pestaña *Comparativo*,
* **Cuando** selecciona un *Periodo A* y un *Periodo B* cuya diferencia de duración supera $\pm 3$ días,
* **Entonces** el sistema impide la comparación y muestra un mensaje de validación indicando que ambos periodos deben tener una duración equivalente (tolerancia de $\pm 3$ días).

#### Escenario 2: Cálculo de variaciones y ordenamiento (`RN-070.1`, ajuste `RN-070.2`)
* **Dado** que el usuario selecciona dos periodos válidos (ej. septiembre vs. octubre),
* **Cuando** se genera el comparativo,
* **Entonces** el sistema muestra:
  1. Un resumen con la variación absoluta y porcentual de *Ingresos netos*, *Gastos*, *Balance* y *Ejecución del presupuesto* (en puntos porcentuales).
  2. Una tabla por categoría con *Periodo A*, *Periodo B*, *Variación* ($B - A$) y *Variación %* ($\frac{B - A}{A} \times 100$), ordenada por la variación en pesos (de mayor aumento a mayor disminución), sin etiquetas textuales como *"Nueva"* o *"Mayor aumento"*.
  3. En los gastos, los aumentos se muestran en rojo con signo $+$ y las reducciones en verde con signo $-$, asegurando lectura accesible sin depender del color.

#### Escenario 3: Manejo de división por cero (`CA-070.2`) y exportación
* **Dado** que una categoría registra $\$0$ en el *Periodo A* y un valor mayor a $\$0$ en el *Periodo B*,
* **Cuando** se renderiza la tabla comparativa,
* **Entonces** la columna *Variación %* muestra `"N/A"` en lugar de un error matemático, y el usuario puede descargar la tabla completa mediante el botón *Exportar XLSX*.

---

## Notas de control de cambios (ajustes frente al ERS v2.0)

| Cambio detectado | Detalle | Impacto |
| :--- | :--- | :--- |
| **Exclusión de Patrimonio Neto** | Se retiran la tarjeta de *Patrimonio neto* (`RN-065.2`) y el gráfico de *Evolución del patrimonio* (`RN-065.3`). | Dependen de MOD-10 (`RF-061` a `RF-063`), fuera del alcance actual. Requiere solicitud de cambio. |
| **Indicadores de Salud Financiera** | Se agregan *Regla 50/30/20*, *Gasto diario promedio*, *Concentración del gasto* y *Ticket promedio*. Se descartan *Rentabilidad del portafolio*, *Rigidez del gasto*, *Fondo de emergencia* y *Deuda/Patrimonio*. | Propuesta nueva basada en AN §6.6. |
| **Eliminación de Flujo Proyectado** | Se retira el modo proyectado del reporte de Flujo de Caja (`RN-066.2`, `RN-066.3`, `CA-066.2`) a solicitud del cliente. | Requiere solicitud de cambio. |
| **Eliminación de etiquetas en Comparativo** | Se eliminan etiquetas como *"Nueva"* o *"Mayor aumento"* (`RN-070.2`, `CA-070.2`); se reemplazan por el ordenamiento por variación en pesos. | Requiere solicitud de cambio. |
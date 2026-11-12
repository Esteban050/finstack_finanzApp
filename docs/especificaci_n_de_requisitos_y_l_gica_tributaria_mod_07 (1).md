# Especificación de Requisitos de Software (ERS) y Lógica de Negocio
## Módulo E (MOD-07): Cálculo de la Obligación y Estimación de Declaración de Renta

**Proyecto:** FinanzApp (SCFP - Sistema de Control Financiero Personal)  
**Versión:** 2.0 (Alcance integrado: Módulos de Ingresos, Retiros e Inversiones)  
**Marco Normativo:** Estatuto Tributario Colombiano (Art. 36-1, 38-41, 126-1, 206, 241, 335-336, 592-594-3)

---

## 1. Alcance y Arquitectura de Integración

El módulo **MOD-07 (Declaración de Renta)** opera como un motor consolidador y liquidador tributario para personas naturales residentes en Colombia. Se alimenta exclusivamente de los registros fechados entre el `01/01/YYYY` y el `31/12/YYYY` provenientes de:

1. **Módulo de Ingresos:** Salarios, honorarios, rentas no laborales, aportes obligatorios a salud y pensión, y retenciones en la fuente laborales.
2. **Módulo de Inversiones (y Retiros asociados):** Portafolio compuesto por 6 productos financieros (**CDTs**, **Cuentas de Alto Rendimiento**, **FICs**, **ETFs/Acciones**, **FPV** y **Cesantías**).
3. **Información Complementaria del Usuario:** Consignaciones bancarias externas, consumos opcionales con tarjeta/compras totales y ajustes manuales auditados.

---

## 2. Matriz de Reglas Tributarias por Producto de Inversión

Cada producto del módulo de **Inversiones** impacta la declaración de renta en cuatro dimensiones simultáneas:

| Producto | 1. Patrimonio Bruto al 31/12 (Tope C1) | 2. Ingresos Brutos Cédula General (Tope C2 y Paso 1) | 3. Beneficio Tributario: INCRNGO (Paso 2) o Renta Exenta (Paso 3) | 4. Retenciones (Paso 8) y Aperturas (Tope C5) |
| :--- | :--- | :--- | :--- | :--- |
| **1. CDTs** | Capital vigente al 31/12 + intereses causados no pagados. | **Renta de Capital:** $100\%$ de los intereses brutos pagados o abonados en el año. | **INCRNGO (Paso 2):** Se descuenta el $\% \text{ Componente Inflacionario}$ anual sobre los intereses brutos recibidos. | • Retención practicada ($4\%$).<br>• Aperturas/renovaciones del año suman al piso del Tope C5. |
| **2. Cuentas de Alto Rendimiento** | Saldo disponible al corte del 31/12. | **Renta de Capital:** $100\%$ de los rendimientos abonados en cuenta durante el año. | **INCRNGO (Paso 2):** Se descuenta el $\% \text{ Componente Inflacionario}$ anual sobre los rendimientos brutos. | • Retención practicada ($7\%$).<br>• Depósitos del año suman al piso del Tope C5. |
| **3. FICs (Fondos de Inversión Colectiva)** | Valor de mercado de las unidades al 31/12. | **Renta de Capital:** Rendimientos efectivamente pagados o abonados al inversionista en el año. | **INCRNGO (Paso 2):** Aplica $\% \text{ Componente Inflacionario}$ sobre la porción de rendimientos de renta fija. | • Retención practicada en rescate/pago.<br>• Aportes del año suman al piso del Tope C5. |
| **4. ETFs y Acciones** | Costo fiscal de adquisición de los títulos en portafolio al 31/12. | **Renta No Laboral:** Utilidad realizada en ventas ($\text{Precio Venta} - \text{Costo Fiscal}$) con tenencia $< 730 \text{ días}$ ($2 \text{ años}$). *(Nota: Ventas $\ge 2 \text{ años}$ y dividendos se excluyen del modelo simplificado de Cédula General).* | **INCRNGO (Paso 2 - Art. 36-1 ET):** Si el activo tiene el flag `cotiza_en_bvc_o_mgc = true` (Bolsa de Colombia o Mercado Global Colombiano), el **$100\%$ de la utilidad en venta** es INCRNGO (paga $\$0$). | • Retención local si aplica.<br>• Compras del año suman al piso del Tope C5. |
| **5. FPV (Pensión Voluntaria)** | Saldo acumulado en el fondo al 31/12. | **No suma como ingreso** (salvo retiro anticipado en el módulo de Retiros sin cumplir requisitos de vivienda, pensión o $10 \text{ años}$). | **Renta Exenta (Paso 3 - Art. 126-1 ET):** Los aportes realizados en el año son Renta Exenta hasta el $30\%$ del ingreso bruto total, sujetos al límite global del Paso 5 ($40\%$ o $1.340 \text{ UVT}$). | • Sin retención si cumple permanencia.<br>• Aportes del año suman al piso del Tope C5. |
| **6. Cesantías e Intereses** | Saldo acumulado en el fondo de cesantías al 31/12. | **Renta de Trabajo:** Cesantías e intereses consignados o pagados por el empleador en el año. | **Renta Exenta (Paso 3 - Art. 206 num. 4 ET):** $100\%$ Renta Exenta si el ingreso mensual promedio de los últimos $6 \text{ meses} \le 350 \text{ UVT}$ (tabla regresiva si supera $350 \text{ UVT}$). Sujeta al límite global del Paso 5. | • Sin retención en la fuente.<br>• No suma a consignaciones bancarias (C5) si va directo al fondo. |

---

## 3. Parámetros Normativos Requeridos (Entidad de Configuración)

Para que el motor tributario funcione sin valores quemados en código (*hardcoding*), la tabla de parámetros por vigencia (`parametros_tributarios_anuales`) debe almacenar por cada `anio_gravable`:

* `valor_uvt`: Valor oficial de la UVT en COP (ej. $49.799$ para $2025$; $52.374$ para $2026$).
* `pct_componente_inflacionario`: Decimal entre $0$ y $1$ decretado anualmente para rendimientos financieros (ej. $0.6540$ para $65.40\%$).
* `tope_patrimonio_uvt`: $4500$.
* `tope_ingresos_uvt`: $1400$.
* `tope_tarjetas_uvt`: $1400$.
* `tope_compras_uvt`: $1400$.
* `tope_consignaciones_uvt`: $1400$.
* `limite_global_exentas_pct`: $0.40$ ($40\%$).
* `limite_global_exentas_uvt`: $1340$.
* `limite_exenta_laboral_25_uvt`: $790$.
* `limite_fpv_ingreso_bruto_pct`: $0.30$ ($30\%$).

---

## 4. Requisitos Funcionales, Reglas de Negocio y Criterios de Aceptación

### RF-043 · Consolidación anual de Ingresos e Inversiones
**Descripción:** El sistema debe consolidar automáticamente los registros del usuario entre el $1$ de enero y el $31$ de diciembre del año gravable seleccionado, presentando cada concepto en pesos colombianos (COP) y su equivalente en UVT (redondeado a $2$ decimales), permitiendo inspeccionar los registros origen (*drill-down*).

#### Reglas de Negocio (RF-043)
* **RN-043.1 (Conceptos Consolidados):** El sistema consolidará los siguientes conceptos:
  1. **Ingresos brutos totales de la Cédula General:** Desglosados en *Rentas de trabajo* (módulo Ingresos + Cesantías consignadas), *Rentas de capital* (rendimientos de CDTs, Cuentas de alto rendimiento y FICs) y *Rentas no laborales* (utilidad en venta de ETFs/Acciones con tenencia $< 2 \text{ años}$ más otros ingresos no laborales).
  2. **Ingresos no constitutivos de renta (INCRNGO):** Desglosados en *Aportes obligatorios a salud y pensión*, *Componente inflacionario de rendimientos financieros* y *Utilidad exenta en venta de acciones/ETFs en BVC/MGC (Art. 36-1 ET)*.
  3. **Rentas exentas por inversiones:** Desglosadas en *Aportes voluntarios a FPV* y *Cesantías e intereses exentos*.
  4. **Retenciones en la fuente practicadas:** Suma de retenciones del módulo de Ingresos y retenciones del módulo de Inversiones (CDTs, cuentas de alto rendimiento y FICs).
  5. **Patrimonio bruto de inversiones al 31/12:** Suma de los saldos vigentes o costo fiscal al $31$ de diciembre de los $6$ tipos de productos de inversión.
  6. **Aperturas, depósitos y compras de inversión del año:** Suma de los montos desembolsados en nuevas inversiones durante el año gravable (base para el criterio C5).
* **RN-043.2 (Conversión a UVT):** Para todo monto en COP, su equivalente en UVT se calcula como:
  $$\text{Valor UVT} = \text{round}\left(\frac{\text{Valor COP}}{\text{UVT}_{\text{año}}}, 2\right)$$
* **RN-043.3 (Inspección de Registros / Drill-down):** Cada fila del consolidado debe permitir desplegar la lista paginada de los registros específicos de Ingresos o del Portafolio de Inversiones que conforman ese subtotal.

#### Criterios de Aceptación (RF-043)
* **CA-043.1:** Dado un usuario con ingresos laborales de $\$128.000.000$, rendimientos de CDTs/Cuentas/FICs de $\$12.000.000$ y utilidad en venta de ETFs ($< 2 \text{ años}$) de $\$2.500.000$ en $2025$, el consolidado de *Ingresos brutos totales* debe mostrar exactamente $\$142.500.000$ ($2.861,50 \text{ UVT}$).
* **CA-043.2:** Al desplegar el detalle de *Patrimonio bruto de inversiones al 31/12*, el sistema debe listar únicamente los productos activos al cierre del año gravable con su tipo de producto, tratamiento tributario, rendimiento anual, retención anual y saldo patrimonial.

---

### RF-044 · Registro de Información Tributaria Complementaria y Ajustes
**Descripción:** El sistema debe pre-cargar el piso de inversiones del año para el tope de consignaciones (C5), permitir el registro de topes bancarios externos (C3, C4, C5) y habilitar ajustes manuales positivos o negativos sobre los valores consolidados de Ingresos Brutos y Patrimonio Bruto.

#### Reglas de Negocio (RF-044)
* **RN-044.1 (Autocompletado de Base de Inversiones para C5):** El sistema mostrará en una caja informativa la suma de todas las aperturas de CDTs, depósitos en cuentas de alto rendimiento, compras de ETFs/Acciones y aportes a FIC/FPV realizados en el año ($\text{Piso Inversiones}$). El campo *Total consignaciones, depósitos e inversiones (C5)* es obligatorio para declarar una evaluación como definitiva cuando ningún otro tope se ha superado.
* **RN-044.2 (Cálculo de Valor Final y No Negatividad):** Para los criterios de Ingresos Brutos (C2) y Patrimonio Bruto (C1):
  $$\text{Valor Final} = \text{Valor Consolidado} + \text{Ajuste Manual}$$
  En ningún caso el $\text{Valor Final}$ puede ser menor que $\$0$. Si $\text{Ajuste Manual} < -\text{Valor Consolidado}$, el sistema bloqueará el guardado e indicará el monto máximo que es posible restar.
* **RN-044.3 (Justificación y Auditoría de Ajustes):** Todo ajuste manual diferente de $\$0$ requiere obligatoriamente una nota explicativa de entre $10$ y $500$ caracteres y quedará registrado en el historial de ajustes del año gravable con su fecha y hora.

#### Criterios de Aceptación (RF-044)
* **CA-044.1:** Si el consolidado de Ingresos Brutos es $\$142.500.000$ y el usuario registra un ajuste de $+\$5.000.000$ con nota válida, el valor evaluado en el criterio C2 será $\$147.500.000$.
* **CA-044.2:** Si el Patrimonio Bruto consolidado es $\$215.000.000$ y el usuario ingresa un ajuste de $-\$220.000.000$, el sistema debe marcar el campo como inválido y mostrar el mensaje: *"El valor final del patrimonio bruto no puede ser negativo. Revisa el ajuste: el máximo que puedes restar es $ 215.000.000."*

---

### RF-045 · Evaluación de la Obligación de Declarar Renta
**Descripción:** El sistema debe evaluar los $6$ criterios normativos del Estatuto Tributario para determinar si el usuario está obligado a declarar renta por el año gravable seleccionado y permitir la descarga de la constancia en PDF.

#### Reglas de Negocio (RF-045)
* **RN-045.1 (Matriz de Evaluación de los 6 Criterios):** Se evalúan los valores finales (consolidado + ajuste) frente a los topes en UVT del año gravable:
  * **C1 (Patrimonio bruto al 31/12):** Cumple si $\text{Patrimonio Final} > 4.500 \times \text{UVT}$.
  * **C2 (Ingresos brutos del año):** Cumple si $\text{Ingresos Brutos Finales} \ge 1.400 \times \text{UVT}$. *(Único criterio con operador mayor o igual).*
  * **C3 (Consumos con tarjeta de crédito):** Cumple si $\text{Consumos Tarjeta} > 1.400 \times \text{UVT}$.
  * **C4 (Compras y consumos totales):** Cumple si $\text{Compras Totales} > 1.400 \times \text{UVT}$.
  * **C5 (Consignaciones, depósitos o inversiones):** Cumple si $\max(\text{Piso Inversiones}, \text{Consignaciones Declaradas}) > 1.400 \times \text{UVT}$.
  * **C6 (Responsable de IVA):** Cumple si el atributo `responsable_iva` en el perfil del usuario es `true`.
* **RN-045.2 (Veredicto Global):**
  * Si al menos un criterio ($C_1 \dots C_6$) se cumple $\rightarrow$ **`OBLIGADO A DECLARAR`**.
  * Si ningún criterio se cumple y el campo C5 aún no ha sido confirmado por el usuario $\rightarrow$ **`INCOMPLETO`**.
  * Si ningún criterio se cumple y el campo C5 ya fue confirmado $\rightarrow$ **`NO OBLIGADO A DECLARAR`** (mostrando aviso informativo sobre declaración voluntaria para devolución de retenciones de salarios o inversiones).
* **RN-045.3 (Detalle y Margen por Criterio):** Para cada criterio $C_1 \dots C_5$, si no se cumple, se mostrará el margen disponible en COP ($\text{Tope COP} - \text{Valor Final COP}$). Si el valor alcanza el $90\%$ o más del tope sin superarlo, se mostrará el badge preventivo `"No cumple · cerca"`.
* **RN-045.4 (Proyección para Año en Curso):** Si el usuario consulta el año gravable actual antes del $31$ de diciembre, la evaluación se etiquetará como **`PRELIMINAR · AÑO EN CURSO`** y proyectará los ingresos y rendimientos al cierre del año mediante:
  $$\text{Valor Proyectado} = \frac{\text{Acumulado Meses Transcurridos}}{M_{\text{transcurridos}}} \times 12$$
* **RN-045.6 (Constancia en PDF):** Permitirá exportar un documento PDF con el veredicto, la tabla de los $6$ criterios, los parámetros empleados (UVT y componente inflacionario), el resumen de la estimación del impuesto y la cláusula legal informativa.

#### Criterios de Aceptación (RF-045)
* **CA-045.1:** Con $\text{UVT} = \$49.799$, un usuario con ingresos brutos exactos de $\$69.718.600$ ($1.400,00 \text{ UVT}$) **sí cumple** el criterio C2 y queda `OBLIGADO A DECLARAR`.
* **CA-045.2:** Con el mismo monto exacto de $\$69.718.600$ ($1.400,00 \text{ UVT}$) en consumos con tarjeta de crédito (C3) o en inversiones/consignaciones (C5), **no se cumple** el criterio (requiere $\ge \$69.718.601$).
* **CA-045.3:** Un usuario con Patrimonio Bruto en inversiones de $\$224.095.501$ supera las $4.500 \text{ UVT}$ ($\$224.095.500$) y cumple C1.
* **CA-045.4:** Si el usuario tiene `responsable_iva = true` en su perfil, el resultado es `OBLIGADO A DECLARAR` aunque todos sus montos financieros sean $\$0$.

---

### RF-046 · Estimación Simplificada del Impuesto de Renta (Cédula General)
**Descripción:** El sistema debe ejecutar y mostrar la depuración paso a paso (en $8$ niveles) de la Cédula General (Art. 336 y 241 del ET), integrando las rentas de trabajo, rentas de capital de inversiones, rentas no laborales, el componente inflacionario, la exención de BVC/MGC, los aportes a FPV, las cesantías exentas y las retenciones en la fuente.

#### Reglas de Negocio y Fórmulas Exactas (RF-046)

##### Paso 1: Ingresos Brutos de la Cédula General ($IB$)
$$IB = I_{\text{trabajo}} + I_{\text{cesantías}} + I_{\text{capital\_inv}} + I_{\text{no\_laborales}} + U_{\text{venta\_acciones\_<2a}}$$
*(Si el usuario registró un retiro anticipado de FPV en el módulo de Retiros marcado como `sin_cumplir_requisitos = true`, dicho monto también se suma a $IB$).*

##### Paso 2: Ingresos No Constitutivos de Renta ni Ganancia Ocasional ($INCRNGO$)
Se compone de tres bolsas:
1. **Aportes obligatorios a salud y pensión ($INCRNGO_{\text{salud\_pensión}}$):** Consolidados del módulo de Ingresos.
2. **Componente inflacionario de rendimientos financieros ($INCRNGO_{\text{inflación}}$):** Aplicado sobre los intereses brutos de CDTs, Cuentas de alto rendimiento y FICs de renta fija:
   $$INCRNGO_{\text{inflación}} = \text{round}\left(I_{\text{capital\_inv}} \times \text{pct\_componente\_inflacionario}\right)$$
3. **Utilidad en venta de acciones/ETFs listados en BVC o MGC ($INCRNGO_{\text{bvc}}$ - Art. 36-1 ET):**
   $$INCRNGO_{\text{bvc}} = \sum U_{\text{venta\_acciones\_<2a}} \quad \text{donde } \text{cotiza\_en\_bvc\_o\_mgc} = \text{true}$$

$$\text{Total Paso 2 } (INCRNGO) = INCRNGO_{\text{salud\_pensión}} + INCRNGO_{\text{inflación}} + INCRNGO_{\text{bvc}}$$

##### Subtotal: Renta Líquida ($RL$)
$$RL = \max(0,\; IB - INCRNGO)$$
Adicionalmente, se define la **Renta Líquida de Trabajo ($RL_{\text{trabajo}}$)** para el cálculo posterior del $25\%$ exento:
$$RL_{\text{trabajo}} = \max\left(0,\; (I_{\text{trabajo}} + I_{\text{cesantías}}) - INCRNGO_{\text{salud\_pensión}}\right)$$

##### Paso 3: Rentas Exentas Específicas por Inversiones ($RE_{\text{inversiones}}$)
1. **Aportes voluntarios a FPV ($RE_{\text{fpv}}$ - Art. 126-1 ET):** Los aportes realizados en el año al FPV, limitados individualmente a que la suma de aportes obligatorios a pensión + aportes voluntarios a FPV no supere el $30\%$ del ingreso bruto ($IB$):
   $$RE_{\text{fpv}} = \min\left(\text{Aportes FPV}_{\text{año}},\; \max(0,\; 0.30 \times IB - \text{Aporte Pensión Obligatoria})\right)$$
2. **Cesantías e intereses a las cesantías exentas ($RE_{\text{cesantías}}$ - Art. 206 num. 4 ET):**
   * Si el ingreso mensual promedio de los últimos $6$ meses $\le 350 \text{ UVT} \rightarrow 100\%$ exentas ($RE_{\text{cesantías}} = I_{\text{cesantías}}$).
   * Si supera $350 \text{ UVT}$, se aplica el porcentaje legal del Art. 206 num. 4 ET ($90\%, 80\%, 60\%, 40\%, 20\%$ o $0\%$).

$$\text{Total Paso 3 } (RE_{\text{inversiones}}) = RE_{\text{fpv}} + RE_{\text{cesantías}}$$

##### Paso 4: Renta Exenta Laboral del 25% ($RE_{25\%}$ - Art. 206 num. 10 ET)
Se calcula el $25\%$ sobre las rentas de trabajo depuradas, con tope legal de $790 \text{ UVT}$ anuales:
$$\text{Base}_{25\%} = \max\left(0,\; RL_{\text{trabajo}} - RE_{\text{inversiones}}\right)$$
$$RE_{25\%} = \min\left(\text{round}(\text{Base}_{25\%} \times 0.25),\; 790 \times \text{UVT}\right)$$

##### Paso 5: Límite Global de Rentas Exentas y Deducciones ($RE_{\text{aceptadas}}$ - Art. 336 ET)
La suma de los Pasos 3 y 4 no puede exceder el $40\%$ de la Renta Líquida ($RL$) ni tampoco $1.340 \text{ UVT}$:
$$RE_{\text{brutas}} = RE_{\text{inversiones}} + RE_{25\%}$$
$$L_{40\%} = \text{round}(RL \times 0.40)$$
$$L_{1340} = 1340 \times \text{UVT}$$
$$RE_{\text{aceptadas}} = \min\left(RE_{\text{brutas}},\; L_{40\%},\; L_{1340}\right)$$
* **Regla de Alerta Visual:**
  * Si $RE_{\text{brutas}} > RE_{\text{aceptadas}}$, se muestra el badge de advertencia con el monto no aprovechado: $\text{Exceso} = RE_{\text{brutas}} - RE_{\text{aceptadas}}$.
  * Si $RE_{\text{brutas}} \le RE_{\text{aceptadas}}$, se muestra el badge de éxito indicando el margen aún disponible bajo el tope del $40\%$: $\text{Cupo Disponible} = \min(L_{40\%}, L_{1340}) - RE_{\text{brutas}}$.

##### Paso 6: Renta Líquida Gravable ($RLG$)
$$RLG_{\text{COP}} = \max(0,\; RL - RE_{\text{aceptadas}})$$
$$RLG_{\text{UVT}} = \frac{RLG_{\text{COP}}}{\text{UVT}}$$

##### Paso 7: Impuesto según Tabla Progresiva del Art. 241 del E.T. ($Imp$)
Se evalúa $U = RLG_{\text{UVT}}$ en la tabla marginal del artículo 241 del Estatuto Tributario:

| Rango en UVT ($U$) | Tarifa Marginal | Fórmula del Impuesto en UVT ($\text{Imp}_{\text{UVT}}$) |
| :--- | :---: | :--- |
| $0 \text{ a } 1.090$ | $0\%$ | $0$ |
| $> 1.090 \text{ a } 1.700$ | $19\%$ | $(U - 1.090) \times 0.19$ |
| $> 1.700 \text{ a } 4.100$ | $28\%$ | $(U - 1.700) \times 0.28 + 116$ |
| $> 4.100 \text{ a } 8.670$ | $33\%$ | $(U - 4.100) \times 0.33 + 788$ |
| $> 8.670 \text{ a } 18.970$ | $35\%$ | $(U - 8.670) \times 0.35 + 2.296$ |
| $> 18.970 \text{ a } 31.000$ | $37\%$ | $(U - 18.970) \times 0.37 + 5.901$ |
| $> 31.000$ | $39\%$ | $(U - 31.000) \times 0.39 + 10.352$ |

El valor resultante se convierte a pesos y se redondea al múltiplo de $\$1.000$ más cercano (`RN-046.2`):
$$\text{Imp}_{\text{COP}} = \text{round}\left(\frac{\text{Imp}_{\text{UVT}} \times \text{UVT}}{1000}\right) \times 1000$$

##### Paso 8: Imputación de Retenciones y Saldo Estimado ($\text{Saldo}$)
Se suman las retenciones del módulo de Ingresos y del módulo de Inversiones (redondeadas al múltiplo de $\$1.000$):
$$\text{Ret}_{\text{totales}} = \text{round}\left(\frac{\text{Ret}_{\text{ingresos}} + \text{Ret}_{\text{inversiones}}}{1000}\right) \times 1000$$
$$\text{Saldo Neto} = \text{Imp}_{\text{COP}} - \text{Ret}_{\text{totales}}$$
* Si $\text{Saldo Neto} > 0 \rightarrow$ **Saldo neto a pagar** por valor de $\text{Saldo Neto}$.
* Si $\text{Saldo Neto} < 0 \rightarrow$ **Saldo a favor** por valor de $|\text{Saldo Neto}|$.
* Si $\text{Saldo Neto} = 0 \rightarrow$ **Saldo en ceros ($\$0$)**.

##### Cálculo de la Tarjeta "Impacto Tributario de tus Inversiones"
Para cuantificar el ahorro logrado por el portafolio de inversiones del usuario, el motor calcula un escenario contrafactual $\text{Imp}_{\text{sin\_beneficios\_inv}}$ (donde $INCRNGO_{\text{inflación}} = 0$, $INCRNGO_{\text{bvc}} = 0$ y $RE_{\text{inversiones}} = 0$) y presenta:
$$\text{Ahorro Total por Inversiones} = \left(\text{Imp}_{\text{sin\_beneficios\_inv}} - \text{Imp}_{\text{COP}}\right) + \text{Ret}_{\text{inversiones}}$$

#### Criterios de Aceptación (RF-046)
* **CA-046.1:** Con $\text{UVT} = \$49.799$ y una Renta Líquida Gravable de $1.509,61 \text{ UVT}$ ($\$75.177.000$), el impuesto calculado es $(1.509,61 - 1.090) \times 0.19 \times 49.799 = \$3.970.275$, que redondeado al múltiplo de $\$1.000$ arroja exactamente $\$3.970.000$.
* **CA-046.2:** Si la suma de rentas exentas por inversiones (Paso 3) más el $25\%$ exento laboral (Paso 4) supera el $40\%$ de la Renta Líquida o las $1.340 \text{ UVT}$, el Paso 5 debe truncar la resta al tope legal y mostrar en pantalla la diferencia no aprovechada.
* **CA-046.3:** Con un impuesto estimado de $\$3.970.000$ y retenciones consolidadas de $\$3.800.000$ ($\$3.120.000$ laborales + $\$680.000$ de inversiones), el sistema muestra `Saldo neto a pagar: $ 170.000`. Si las retenciones fueran $\$5.200.000$, muestra `Saldo a favor: $ 1.230.000`.

---

### RF-047 · Calendario de Vencimiento y Recordatorios
**Descripción:** El sistema debe determinar la fecha límite de presentación de la declaración de renta según los dos últimos dígitos del documento de identidad del usuario y programar notificaciones preventivas.

#### Reglas de Negocio (RF-047)
* **RN-047.1 (Programación de Recordatorios):** Para todo usuario cuyo último resultado de evaluación sea `OBLIGADO` o `INCOMPLETO`, el sistema programará $4$ recordatorios automáticos: $30$, $15$, $5$ y $1$ día calendario antes de su fecha de vencimiento.
* **RN-047.2 (Suspensión por Declaración Presentada):** Cuando el usuario registre la acción *"Marcar declaración como presentada"* indicando la fecha de presentación (la cual no puede ser futura), el estado del año gravable pasará a `Presentada` y se cancelarán los recordatorios pendientes de ese periodo.

#### Criterios de Aceptación (RF-047)
* **CA-047.1:** Un usuario con documento terminado en `"37"` y estado `OBLIGADO` visualiza su fecha límite correspondiente en el calendario tributario y el estado de sus $4$ recordatorios.
* **CA-047.2:** Un usuario cuyo resultado es `NO OBLIGADO` no tiene recordatorios activos para ese año gravable.

---

## 5. Implementación de Referencia del Motor de Liquidación (TypeScript)

A continuación se presenta la función pura de dominio lista para incorporar en tu servicio de backend (NestJS / Node.js) o adaptar a Java/Python:

```typescript
export interface ParametrosTributariosAnuales {
  anioGravable: number;
  uvt: number; // Ej: 49799 para 2025
  pctComponenteInflacionario: number; // Ej: 0.6540 (65.40%)
}

export interface ConsolidadoIngresosInversiones {
  // Del Módulo de Ingresos
  ingresosTrabajo: number;
  ingresosNoLaborales: number;
  aportesSaludPensionObligatoria: number;
  retencionesIngresos: number;

  // Del Módulo de Inversiones (6 productos)
  rendimientosCdtCuentasFic: number; // CDTs + Cuentas Alto Rend. + FICs Renta Fija
  utilidadVentaAccionesMenor2Anios: number;
  utilidadVentaAccionesEsBvcOMgc: boolean; // True si cotiza en BVC o MGC (Art. 36-1 ET)
  aportesVoluntariosFpvAnio: number;
  retirosFpvSinRequisitosAnio: number;
  cesantiasConsignadasAnio: number;
  cesantiasPorcentajeExento: number; // 1.0 (100%) si salario prom <= 350 UVT
  retencionesInversiones: number; // 4% CDTs + 7% Cuentas + FICs
}

export interface ResultadoEstimacionRenta {
  paso1IngresosBrutos: number;
  paso2IncrngoTotal: number;
  detalleIncrngo: {
    saludPension: number;
    componenteInflacionario: number;
    utilidadExentaBvc: number;
  };
  rentaLiquida: number;
  paso3RentasExentasInversiones: number;
  paso4RentaExenta25Laboral: number;
  paso5TotalExentasConLimite: number;
  limiteExentasAplicado: boolean;
  montoNoAprovechadoOMargen: number;
  paso6RentaLiquidaGravableCop: number;
  paso6RentaLiquidaGravableUvt: number;
  paso7ImpuestoRedondeado: number;
  paso8RetencionesRedondeadas: number;
  saldoNeto: number; // Positivo = A pagar, Negativo = A favor
  tipoResultado: 'SALDO_A_PAGAR' | 'SALDO_A_FAVOR' | 'CERO';
  ahorroTributarioInversiones: number;
}

export function calcularImpuestoArt241(rlgUvt: number, uvt: number): number {
  let impUvt = 0;
  if (rlgUvt <= 1090) {
    impUvt = 0;
  } else if (rlgUvt <= 1700) {
    impUvt = (rlgUvt - 1090) * 0.19;
  } else if (rlgUvt <= 4100) {
    impUvt = (rlgUvt - 1700) * 0.28 + 116;
  } else if (rlgUvt <= 8670) {
    impUvt = (rlgUvt - 4100) * 0.33 + 788;
  } else if (rlgUvt <= 18970) {
    impUvt = (rlgUvt - 8670) * 0.35 + 2296;
  } else if (rlgUvt <= 31000) {
    impUvt = (rlgUvt - 18970) * 0.37 + 5901;
  } else {
    impUvt = (rlgUvt - 31000) * 0.39 + 10352;
  }
  const impCop = impUvt * uvt;
  return Math.round(impCop / 1000) * 1000;
}

export function estimarImpuestoCedulaGeneral(
  datos: ConsolidadoIngresosInversiones,
  params: ParametrosTributariosAnuales
): ResultadoEstimacionRenta {
  // Paso 1: Ingresos brutos
  const paso1IngresosBrutos =
    datos.ingresosTrabajo +
    datos.cesantiasConsignadasAnio +
    datos.rendimientosCdtCuentasFic +
    datos.ingresosNoLaborales +
    datos.utilidadVentaAccionesMenor2Anios +
    datos.retirosFpvSinRequisitosAnio;

  // Paso 2: INCRNGO (Salud/Pensión + Componente Inflacionario + Art. 36-1 BVC/MGC)
  const incrngoSaludPension = datos.aportesSaludPensionObligatoria;
  const incrngoInflacion = Math.round(
    datos.rendimientosCdtCuentasFic * params.pctComponenteInflacionario
  );
  const incrngoBvc = datos.utilidadVentaAccionesEsBvcOMgc
    ? datos.utilidadVentaAccionesMenor2Anios
    : 0;

  const paso2IncrngoTotal = incrngoSaludPension + incrngoInflacion + incrngoBvc;
  const rentaLiquida = Math.max(0, paso1IngresosBrutos - paso2IncrngoTotal);

  // Paso 3: Rentas exentas por inversiones (FPV máx 30% ingreso bruto + Cesantías)
  const topeFpv30Pct = Math.round(paso1IngresosBrutos * 0.30);
  const fpvAceptado = Math.min(datos.aportesVoluntariosFpvAnio, topeFpv30Pct);
  const cesantiasExentas = Math.round(
    datos.cesantiasConsignadasAnio * datos.cesantiasPorcentajeExento
  );
  const paso3RentasExentasInversiones = fpvAceptado + cesantiasExentas;

  // Paso 4: Renta exenta laboral del 25% (tope 790 UVT)
  const rentaLiquidaTrabajo = Math.max(
    0,
    datos.ingresosTrabajo + datos.cesantiasConsignadasAnio - incrngoSaludPension
  );
  const base25 = Math.max(0, rentaLiquidaTrabajo - paso3RentasExentasInversiones);
  const tope790Uvt = 790 * params.uvt;
  const paso4RentaExenta25Laboral = Math.min(Math.round(base25 * 0.25), tope790Uvt);

  // Paso 5: Límite global 40% de la Renta Líquida o 1.340 UVT
  const sumaExentasBruta = paso3RentasExentasInversiones + paso4RentaExenta25Laboral;
  const limite40Pct = Math.round(rentaLiquida * 0.40);
  const limite1340Uvt = 1340 * params.uvt;
  const topeMaximoGlobal = Math.min(limite40Pct, limite1340Uvt);

  const paso5TotalExentasConLimite = Math.min(sumaExentasBruta, topeMaximoGlobal);
  const limiteExentasAplicado = sumaExentasBruta > topeMaximoGlobal;
  const montoNoAprovechadoOMargen = Math.abs(topeMaximoGlobal - sumaExentasBruta);

  // Paso 6: Renta Líquida Gravable
  const paso6RentaLiquidaGravableCop = Math.max(
    0,
    rentaLiquida - paso5TotalExentasConLimite
  );
  const paso6RentaLiquidaGravableUvt = Number(
    (paso6RentaLiquidaGravableCop / params.uvt).toFixed(2)
  );

  // Paso 7: Impuesto Art. 241 ET
  const paso7ImpuestoRedondeado = calcularImpuestoArt241(
    paso6RentaLiquidaGravableUvt,
    params.uvt
  );

  // Paso 8: Retenciones y Saldo Neto
  const retencionesTotales = datos.retencionesIngresos + datos.retencionesInversiones;
  const paso8RetencionesRedondeadas = Math.round(retencionesTotales / 1000) * 1000;
  const saldoNeto = paso7ImpuestoRedondeado - paso8RetencionesRedondeadas;

  // Cálculo de impacto tributario de las inversiones (Escenario contrafactual sin beneficios de inversión)
  const rentaLiquidaSinBeneficiosInv = Math.max(
    0,
    paso1IngresosBrutos - incrngoSaludPension
  );
  const exenta25SinInv = Math.min(
    Math.round(rentaLiquidaTrabajo * 0.25),
    tope790Uvt
  );
  const limiteGlobalSinInv = Math.min(
    Math.round(rentaLiquidaSinBeneficiosInv * 0.40),
    limite1340Uvt
  );
  const rlgSinBeneficiosInvCop = Math.max(
    0,
    rentaLiquidaSinBeneficiosInv - Math.min(exenta25SinInv, limiteGlobalSinInv)
  );
  const impSinBeneficiosInv = calcularImpuestoArt241(
    rlgSinBeneficiosInvCop / params.uvt,
    params.uvt
  );
  const ahorroTributarioInversiones =
    Math.max(0, impSinBeneficiosInv - paso7ImpuestoRedondeado) +
    Math.round(datos.retencionesInversiones / 1000) * 1000;

  return {
    paso1IngresosBrutos,
    paso2IncrngoTotal,
    detalleIncrngo: {
      saludPension: incrngoSaludPension,
      componenteInflacionario: incrngoInflacion,
      utilidadExentaBvc: incrngoBvc,
    },
    rentaLiquida,
    paso3RentasExentasInversiones,
    paso4RentaExenta25Laboral,
    paso5TotalExentasConLimite,
    limiteExentasAplicado,
    montoNoAprovechadoOMargen,
    paso6RentaLiquidaGravableCop,
    paso6RentaLiquidaGravableUvt,
    paso7ImpuestoRedondeado,
    paso8RetencionesRedondeadas,
    saldoNeto,
    tipoResultado:
      saldoNeto > 0 ? 'SALDO_A_PAGAR' : saldoNeto < 0 ? 'SALDO_A_FAVOR' : 'CERO',
    ahorroTributarioInversiones,
  };
}
```
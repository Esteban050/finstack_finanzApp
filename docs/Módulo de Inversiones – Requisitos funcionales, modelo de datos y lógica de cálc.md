# Módulo de Inversiones – Requisitos funcionales, modelo de datos y lógica de cálculo

Versión 1.1 · Fecha de corte de la investigación: 8 de noviembre de 2026 · Jurisdicción: Colombia · Moneda base: COP

> Aviso: este documento es un insumo técnico, no asesoría tributaria. Las reglas marcadas como «Validar» no pudieron confirmarse en fuente primaria durante la investigación y deben revisarse con un contador antes de salir a producción.

Las decisiones de negocio acordadas el 8 de noviembre de 2026 están en la sección 9 y prevalecen sobre las secciones 2 a 7 donde haya diferencias.

## 1. Alcance y supuestos

1. El módulo permite a un usuario persona natural, residente fiscal en Colombia, registrar y simular seis tipos de inversión: CDT, acciones/ETF, FIC, cuentas de alto rendimiento, FPV y cesantías.
2. El módulo descuenta en la misma simulación los valores que la entidad descuenta en el momento (retención en la fuente y GMF/4x1000). El impuesto de renta final se muestra como estimación informativa y no se resta del neto por defecto.
3. Ninguna tarifa, tope ni porcentaje va fijo en el código. Todo vive en una tabla de parámetros con vigencia (ver RF-PAR).
4. Valores de referencia 2026: UVT = $52.374 (Resolución DIAN 000238 de 2025). El GMF (4x1000) está exento hasta 350 UVT mensuales (≈ $18.330.900) en una cuenta marcada como exenta.
5. Está en trámite el Proyecto de Ley 477 de 2026 (Ley de Rescate Económico), radicado el 29 de octubre de 2026. Propone derogar la exclusión del componente inflacionario de los rendimientos financieros de personas naturales. No es ley; el diseño debe soportar el cambio por parámetros.

## 2. Reglas tributarias por producto

| Producto | Se descuenta en la simulación | Informativo (declaración de renta) | Estado |
| --- | --- | --- | --- |
| CDT / CDAT | Retención en la fuente 4% sobre el rendimiento bruto, practicada al pago de intereses o al vencimiento. Aplica a CDT (renta fija) y, desde el Decreto 572 de 2025, también a CDAT. | Rendimiento gravable = rendimiento bruto × (1 − componente inflacionario del año). La retención se acredita como anticipo. | Verificado. Algunos portales dicen 7%: es la tarifa general de rendimientos financieros, no la de renta fija. |
| Cuenta de alto rendimiento | Retención 7% sobre cada abono de intereses (rendimientos financieros generales). GMF 0,4% sobre retiros que excedan el tope exento. | Igual que CDT: componente inflacionario excluido en la declaración. | Verificado (7%); GMF verificado. |
| Acciones / ETF | Sin retención en la fuente en venta en la BVC. Para acciones/ETF del exterior no hay retención local en venta. Dividendos: retención parametrizable. | Utilidad en venta: (a) acción inscrita en BVC, venta ≤ 3% de las acciones en circulación en el año: 0% (art. 36-1 ET); (b) tenencia ≥ 2 años: ganancia ocasional 15%; (c) tenencia < 2 años: renta ordinaria a tarifa marginal (hasta 39%). Acciones y ETF del exterior no tienen el beneficio de la BVC. | Verificado (reglas de utilidad). Dividendos: Validar. |
| FIC | Retención al momento de la redención sobre la utilidad efectivamente pagada, con la tarifa de cada ingreso subyacente del fondo (renta fija 4%, rendimientos financieros 7%, etc., ponderadas por composición). Al redimir, el pago se imputa primero a la utilidad. | Parte de renta fija: componente inflacionario aplica. Dividendos y utilidad en venta de acciones dentro del fondo conservan su naturaleza (principio de transparencia). | Verificado el mecanismo; la tarifa ponderada por fondo se toma del certificado del administrador. Tarifa por defecto: Validar. |
| FPV | Aportes: no hacen parte de la base de retención laboral y son renta exenta hasta 30% del ingreso del año y 3.800 UVT por año (sumados AFC y sujetos al límite general del art. 336). Retiro: sin retención si hay permanencia ≥ 10 años (aportes desde 2013) o destino permitido (vivienda, pensión); si no, el aporte pierde el beneficio y hay retención. Los retiros de aportes del FPV no pagan GMF. | Si el retiro cumple condiciones, los rendimientos no se incluyen en la renta. | Verificado el esquema. Tarifa de retención por retiro anticipado (referencia 7%): Validar. |
| Cesantías (fondo) | Retención en la fuente 0% en pago de cesantías e intereses de cesantías. | Renta exenta por art. 206 num. 4 ET: 100% si el ingreso mensual promedio de los últimos 6 meses ≤ 350 UVT; si es mayor, tabla gradual (350–410 UVT: 90%; 410–470: 80%; 470–530: 60%; 530–590: 40%; 590–650: 20%; más de 650: 0%). Los rendimientos del fondo son un concepto distinto a los intereses de cesantías que paga el empleador. | Verificado (exención y tramos hasta 530 UVT). Tramos superiores y tratamiento de rendimientos del fondo: Validar. |

## 3. Requisitos funcionales

### 3.1 Gestión del perfil tributario (RF-PER)

- **RF-PER-01** El sistema debe permitir al usuario registrar su perfil tributario: declarante de renta (sí/no), residente fiscal (sí/no), obligado a llevar contabilidad (sí/no), ingreso anual estimado, ingreso laboral mensual promedio de los últimos 6 meses y tarifa marginal estimada.
- **RF-PER-02** Si el usuario no diligencia el perfil, el sistema debe calcular únicamente los descuentos en el momento (retención y GMF) y ocultar la estimación de renta.
- **RF-PER-03** El perfil debe tener historial por año gravable.

### 3.2 Registro de inversiones (RF-INV)

- **RF-INV-01** El sistema debe permitir crear una inversión de tipo CDT, ACCION\_ETF, FIC, CUENTA\_ALTO\_RENDIMIENTO, FPV o CESANTIAS, con nombre, entidad, moneda y fecha de inicio.
- **RF-INV-02** Cada tipo debe exponer solo los campos que le aplican (sección 4.2).
- **RF-INV-03** El sistema debe permitir editar, cerrar y eliminar lógicamente una inversión sin perder el historial de transacciones.
- **RF-INV-04** El sistema debe registrar transacciones por inversión: compra, venta, aporte, retiro, abono de intereses, dividendo, comisión y redención.
- **RF-INV-05** El sistema debe validar que no se venda o retire más de lo disponible (unidades o saldo).
- **RF-INV-06** Para ACCION\_ETF, FIC y FPV el usuario debe poder ingresar dos de los tres valores (monto, valor por unidad, cantidad de unidades) y el sistema calcula el tercero. Si ingresa los tres y no son consistentes (tolerancia configurable), debe mostrar error.

### 3.3 Valoración y actualización de precios (RF-VAL)

- **RF-VAL-01** Para productos por unidades (ACCION\_ETF, FIC, FPV, CESANTIAS en fondo) el usuario debe poder registrar una nueva valoración (fecha, valor por unidad) en un campo aparte; cada registro se guarda en un historial y no sobrescribe el anterior.
- **RF-VAL-02** El sistema debe mostrar valorización no realizada = (valor actual − costo) × unidades, en valor y en porcentaje, por lote y total.
- **RF-VAL-03** El sistema debe mostrar la serie histórica de la valoración en gráfica.
- **RF-VAL-04** Para instrumentos en moneda extranjera, el sistema debe registrar la TRM de compra y de venta y convertir a COP; la diferencia cambiaria forma parte de la utilidad.
- **RF-VAL-05** Para CDT y cuentas, el sistema debe proyectar el rendimiento sin intervención del usuario a partir de tasa, plazo y fecha.

### 3.4 Simulación de ganancia o pérdida (RF-SIM)

- **RF-SIM-01** El sistema debe simular, para cada inversión, el rendimiento bruto, la retención en la fuente, el GMF, las comisiones y el rendimiento neto, según las fórmulas de la sección 5.
- **RF-SIM-02** El resultado debe presentarse como: bruto, cada descuento por separado (con su tarifa y su norma de referencia), neto y rentabilidad neta efectiva anual.
- **RF-SIM-03** En CDT debe permitir simular al vencimiento y con pago periódico de intereses.
- **RF-SIM-04** En acciones/ETF debe permitir simular una venta hipotética (unidades y precio) antes de ejecutarla y mostrar utilidad, clasificación fiscal por lote e impuesto estimado.
- **RF-SIM-05** En FIC y FPV debe permitir simular una redención o retiro parcial o total, mostrando la imputación a utilidad y la retención.
- **RF-SIM-06** En cesantías debe permitir simular el retiro y mostrar la parte exenta según el ingreso promedio.
- **RF-SIM-07** El sistema debe poder guardar una simulación como instantánea (parámetros, versión de tarifas usada y resultado).
- **RF-SIM-08** Una pérdida debe mostrarse como valor negativo; los descuentos tributarios solo se calculan sobre utilidad positiva.

### 3.5 Cálculo de impuestos (RF-IMP)

- **RF-IMP-01** El sistema debe aplicar la retención en la fuente sobre el valor bruto del rendimiento, nunca sobre el capital.
- **RF-IMP-02** CDT y CDAT: retención = parámetro RETEFUENTE\_RENTA\_FIJA (inicial 4%). Cuenta de alto rendimiento: parámetro RETEFUENTE\_REND\_FINANCIEROS (inicial 7%).
- **RF-IMP-03** GMF: el sistema debe aplicar 0,4% sobre retiros de cuentas marcadas como gravadas y excluir los retiros hasta el tope mensual en UVT en la cuenta marcada como exenta. Los retiros de aportes de FPV no causan GMF.
- **RF-IMP-04** FIC: en cada redención el sistema debe imputar el pago primero a la utilidad acumulada pendiente de retiro y aplicar la tarifa ponderada del fondo solo a esa parte.
- **RF-IMP-05** Acciones/ETF: el sistema debe clasificar cada lote vendido por tiempo de tenencia (años cumplidos desde la fecha de compra) y por tipo de instrumento, y aplicar: 0% si cumple art. 36-1; 15% ganancia ocasional si la tenencia es de 2 años o más; tarifa marginal del usuario si es menos.
- **RF-IMP-06** Si una venta involucra lotes de distinta antigüedad, el sistema debe dividir la utilidad por lote y mostrarla clasificada.
- **RF-IMP-07** FPV: el sistema debe validar la permanencia de cada aporte con beneficio tributario y aplicar retención contingente solo si el retiro no cumple permanencia ni destino permitido.
- **RF-IMP-08** Cesantías: retención 0% y cálculo de la parte exenta por la tabla del art. 206 num. 4 usando el ingreso promedio del perfil.
- **RF-IMP-09** Estimación de renta (opcional, informativa): aplicar el componente inflacionario del año gravable a los rendimientos de CDT, cuentas y parte de renta fija de FIC cuando el usuario no está obligado a llevar contabilidad.
- **RF-IMP-10** Dividendos: el sistema debe registrar el valor bruto, el impuesto retenido en origen (si es del exterior) y la retención local parametrizada, y mostrar el neto.
- **RF-IMP-11** Cada línea de impuesto del resultado debe indicar la tarifa usada, su base y la vigencia del parámetro aplicado.

### 3.6 Parámetros tributarios (RF-PAR)

- **RF-PAR-01** Todas las tarifas, topes y porcentajes deben guardarse en una tabla de parámetros con valor, unidad, vigencia desde/hasta, norma de referencia y estado de verificación.
- **RF-PAR-02** El sistema debe aplicar el parámetro vigente en la fecha del hecho económico (fecha de pago, venta o retiro), no en la fecha en que se consulta.
- **RF-PAR-03** Solo un rol administrador puede crear o modificar parámetros, con auditoría de quién y cuándo.
- **RF-PAR-04** El sistema debe soportar el valor de UVT por año, el componente inflacionario por año gravable y la tabla de exención de cesantías como parámetros.
- **RF-PAR-05** Cada resultado de simulación debe guardar la versión de parámetros usada para poder reproducirlo.

### 3.7 Reportes y consulta (RF-REP)

- **RF-REP-01** Portafolio consolidado: valor invertido, valor actual, ganancia o pérdida bruta y neta, por producto y total.
- **RF-REP-02** Reporte anual de retenciones practicadas por producto, para cruzar con los certificados de las entidades.
- **RF-REP-03** Reporte de utilidad realizada del año por producto, con clasificación fiscal (renta ordinaria, ganancia ocasional, no gravada).
- **RF-REP-04** Exportación a CSV y PDF.

### 3.8 Requisitos no funcionales relevantes

- **RNF-01** Los importes se almacenan con precisión decimal (no punto flotante) y se redondean al peso solo en presentación.
- **RNF-02** Los cálculos deben ser deterministas y reproducibles con la misma versión de parámetros.
- **RNF-03** El sistema debe mostrar un aviso visible de que los valores son estimaciones y que la certificación de la entidad prevalece.

## 4. Modelo de datos

### 4.1 Entidades comunes

**usuario\_perfil\_tributario**: id, usuario\_id, anio\_gravable, es\_declarante, es\_residente, obligado\_contabilidad, ingreso\_anual\_estimado, ingreso\_laboral\_promedio\_6m, tarifa\_marginal\_estimada.

**entidad\_financiera**: id, nombre, tipo (BANCO, FIDUCIARIA, COMISIONISTA, AFP, FONDO\_CESANTIAS, OTRA).

**inversion**: id, usuario\_id, tipo (CDT, ACCION\_ETF, FIC, CUENTA\_ALTO\_RENDIMIENTO, FPV, CESANTIAS), nombre, entidad\_id, moneda, fecha\_inicio, estado (ACTIVA, CERRADA), creado\_en, actualizado\_en.

**transaccion**: id, inversion\_id, lote\_id (nullable), tipo (COMPRA, VENTA, APORTE, RETIRO, ABONO\_INTERES, DIVIDENDO, COMISION, REDENCION), fecha, monto\_bruto, unidades (nullable), valor\_unidad (nullable), trm (nullable), comision, retencion\_aplicada, gmf\_aplicado, impuesto\_origen (nullable), notas.

**valoracion**: id, inversion\_id, fecha, valor\_unidad, fuente (MANUAL, ENTIDAD).

**tasa\_cambio**: fecha, moneda, trm.

### 4.2 Detalle por tipo

**detalle\_cdt** (1:1 con inversion): capital, tasa\_valor, tipo\_tasa (EA, NOMINAL), periodicidad\_nominal (si aplica), plazo\_dias, fecha\_vencimiento, periodicidad\_pago (VENCIMIENTO, MENSUAL, TRIMESTRAL, SEMESTRAL, ANUAL), es\_cdat, base\_dias (365 o 360).

**detalle\_cuenta** (1:1): saldo\_inicial, es\_cuenta\_gmf\_exenta, base\_dias, periodicidad\_abono (MENSUAL, DIARIA). **cuenta\_tramo\_tasa**: id, detalle\_cuenta\_id, saldo\_desde, saldo\_hasta, tasa\_ea, vigente\_desde, vigente\_hasta (las cuentas suelen pagar tasas escalonadas por saldo).

**detalle\_renta\_variable** (1:1 para ACCION\_ETF): simbolo, nombre\_instrumento, tipo (ACCION, ETF), mercado (BVC, EXTERIOR), moneda, esta\_inscrito\_bvc, porcentaje\_acciones\_circulacion\_vendido\_anio (nullable). **lote\_compra**: id, inversion\_id, fecha\_compra, unidades, valor\_unidad, comision\_compra, trm\_compra (nullable), costo\_total\_cop, unidades\_disponibles.

**detalle\_fic** (1:1): codigo\_fondo, tipo\_fondo (LIQUIDEZ, RENTA\_FIJA, RENTA\_VARIABLE, MIXTO), tarifa\_retencion\_ponderada, pacto\_permanencia\_dias (nullable), comision\_administracion\_ea (informativa). **fic\_composicion\_tributaria**: id, detalle\_fic\_id, concepto (RENTA\_FIJA, REND\_FINANCIEROS, DIVIDENDOS, OTRO), porcentaje, tarifa\_retencion, vigente\_desde. **fic\_lote**: igual a lote\_compra (unidades y valor de unidad por aporte).

**detalle\_fpv** (1:1): administradora, tipo\_vinculacion (DIRECTO, VIA\_EMPLEADOR). **fpv\_aporte**: id, inversion\_id, fecha, monto, unidades, valor\_unidad, tomo\_beneficio\_tributario (bool), origen (TRABAJADOR, EMPLEADOR, INDEPENDIENTE).

**detalle\_cesantias** (1:1): fondo, regimen (ANUALIZADO, TRADICIONAL), salario\_promedio\_6m\_override (nullable). **cesantias\_consignacion**: id, inversion\_id, fecha, monto, anio\_causacion.

### 4.3 Parámetros y resultados

**parametro\_tributario**: id, clave, valor\_numerico, unidad (PORCENTAJE, UVT, COP), vigente\_desde, vigente\_hasta, norma\_referencia, estado\_verificacion (VERIFICADO, VALIDAR), creado\_por, creado\_en. Claves iniciales: UVT; RETEFUENTE\_RENTA\_FIJA (4%); RETEFUENTE\_REND\_FINANCIEROS (7%); GMF\_TARIFA (0,4%); GMF\_TOPE\_EXENTO\_UVT (350); GO\_TARIFA (15%); GO\_ANIOS\_TENENCIA (2); ART\_36\_1\_TOPE\_PORC\_CIRCULACION (3%); FPV\_TOPE\_PORC\_INGRESO (30%); FPV\_TOPE\_UVT (3.800); FPV\_PERMANENCIA\_ANIOS (10); FPV\_RETENCION\_RETIRO\_ANTICIPADO (7%, Validar); CESANTIAS\_UMBRAL\_UVT (350); COMPONENTE\_INFLACIONARIO\_\[AÑO\]; DIVIDENDOS\_RETENCION\_\* (Validar).

**tabla\_exencion\_cesantias**: id, uvt\_desde, uvt\_hasta, porcentaje\_exento, vigente\_desde.

**simulacion**: id, inversion\_id, usuario\_id, fecha\_simulacion, parametros\_entrada (JSON), version\_parametros, bruto, retencion\_total, gmf\_total, comisiones, neto, rentabilidad\_neta\_ea, detalle\_lineas (JSON: concepto, base, tarifa, valor, norma), impuesto\_renta\_estimado (nullable).

## 5. Lógica de cálculo

Notación: C = capital, d = días, EA = tasa efectiva anual, B = base de días (365 por defecto, parametrizable).

### 5.1 Conversión de tasa

Si la tasa es nominal anual con m periodos al año: EA = (1 + nominal/m)^m − 1.

### 5.2 CDT / CDAT

1. Rendimiento bruto del plazo: bruto = C × ((1 + EA)^(d/B) − 1).
2. Retención: ret = bruto × RETEFUENTE\_RENTA\_FIJA, sobre el valor bruto de cada pago de intereses.
3. Con pago periódico, se calcula el bruto de cada periodo sobre el capital y se retiene en cada pago.
4. Neto = bruto − ret.
5. Rentabilidad neta EA = ((C + neto)/C)^(B/d) − 1.
6. Informativo: renta gravable estimada = bruto × (1 − componente\_inflacionario\_anio); impuesto estimado = renta gravable × tarifa marginal − retención ya practicada.

### 5.3 Cuenta de alto rendimiento

1. Para cada día: interes\_dia = saldo\_dia × ((1 + EA\_tramo)^(1/B) − 1), con EA\_tramo según el saldo y su tramo vigente.
2. En cada abono (mensual): bruto\_abono = suma de interes\_dia del periodo; ret = bruto\_abono × RETEFUENTE\_REND\_FINANCIEROS; se acredita bruto − ret.
3. GMF por retiro: si la cuenta es exenta, base = max(0, retiros\_del\_mes\_acumulados − GMF\_TOPE\_EXENTO\_UVT × UVT); gmf = base × GMF\_TARIFA. Si no es exenta, gmf = retiro × GMF\_TARIFA.
4. Neto del periodo = suma(bruto − ret) − suma(gmf).

### 5.4 Acciones y ETF

1. Costo del lote: costo = unidades × valor\_unidad + comision\_compra (en COP: × TRM\_compra si es moneda extranjera).
2. Valorización no realizada: (valor\_actual\_unidad − costo\_unitario) × unidades\_disponibles, en COP al TRM actual si aplica. No genera impuesto.
3. Venta de n unidades: se consumen lotes en orden PEPS (FIFO, parametrizable). Para cada lote consumido k: ingreso\_k = unidades\_k × precio\_venta × TRM\_venta; costo\_k = unidades\_k × costo\_unitario\_k; comision\_venta prorrateada; utilidad\_k = ingreso\_k − costo\_k − comision\_prorrateada.
4. Clasificación por lote:
   - Si tipo\_instrumento = acción BVC y venta cumple tope del 3%: clase = NO\_GRAVADA, impuesto = 0.
   - Si no, años\_tenencia = años completos entre fecha\_compra y fecha\_venta. Si años\_tenencia ≥ 2: clase = GANANCIA\_OCASIONAL, impuesto = max(0, utilidad\_k) × GO\_TARIFA. Si < 2: clase = RENTA\_ORDINARIA, impuesto = max(0, utilidad\_k) × tarifa\_marginal\_usuario.
5. Resultado: utilidad total, utilidad por clase, impuesto estimado (informativo), neto antes y después de impuesto estimado.
6. Dividendo: neto = bruto − impuesto\_origen − retencion\_local (parámetro).

### 5.5 FIC

1. Valor actual = suma(unidades\_lote × valor\_unidad\_actual). Rendimiento acumulado = valor actual − suma(aportes).
2. Utilidad pendiente de retiro U = rendimiento acumulado − utilidad ya redimida en redenciones anteriores.
3. Redención por monto M: parte\_utilidad = min(M, max(0, U)); parte\_capital = M − parte\_utilidad.
4. Retención = parte\_utilidad × tarifa\_ponderada, con tarifa\_ponderada = suma(porcentaje\_concepto × tarifa\_concepto) de fic\_composicion\_tributaria vigente (o el valor del certificado del administrador si el usuario lo ingresa).
5. Neto recibido = M − retención − comisiones de salida (si existen).
6. Se descuentan unidades en orden PEPS: unidades\_redimidas = M / valor\_unidad\_actual.

### 5.6 FPV

1. Valor actual y rendimiento: igual que FIC (unidades × valor de unidad).
2. Beneficio por aporte (informativo): exento\_anual = min(aportes\_FPV\_y\_AFC\_del\_año, FPV\_TOPE\_PORC\_INGRESO × ingreso\_anual, FPV\_TOPE\_UVT × UVT). Ahorro tributario estimado = exento\_anual × tarifa\_marginal.
3. Retiro: para cada aporte con tomo\_beneficio\_tributario = verdadero, cumple\_permanencia = (años desde el aporte ≥ FPV\_PERMANENCIA\_ANIOS) o el usuario declara destino permitido (vivienda, pensión).
4. Si cumple: retención = 0. Si no cumple: retención = (aporte retirado + rendimientos proporcionales) × FPV\_RETENCION\_RETIRO\_ANTICIPADO. Para aportes sin beneficio: la retención se aplica solo sobre los rendimientos retirados.
5. GMF = 0 en retiros de aportes del FPV.

### 5.7 Cesantías

1. Saldo del fondo = consignaciones + rendimientos − retiros. Rendimiento = (valor\_unidad\_actual − costo) × unidades, o rentabilidad informada por el fondo.
2. Retiro: retención = 0.
3. Parte exenta: ingreso\_UVT = ingreso\_laboral\_promedio\_6m / UVT. Si ingreso\_UVT ≤ 350: % exento = 100%. Si no, se busca el tramo en tabla\_exencion\_cesantias. Exento = monto\_retirado × % exento. Gravada = monto\_retirado − exento (informativo).
4. El módulo debe mostrar un aviso cuando la exención esté sujeta al límite general del art. 336 (cesantías causadas desde 2017).

### 5.8 Consolidación

- Valor neto del portafolio = suma de valores actuales netos de descuentos ya practicados.
- Rentabilidad neta total ponderada = (valor actual neto − aportes netos) / aportes netos, calculada por producto y total.

## 6. Reglas de negocio y validaciones

1. Fecha de venta, retiro o redención ≥ fecha de compra o aporte del lote.
2. Tasas y porcentajes entre 0 y 100; unidades y montos > 0.
3. Los lotes no se editan después de tener ventas asociadas; se corrigen con transacciones de ajuste.
4. El tiempo de tenencia se cuenta en años cumplidos, comparando fechas calendario.
5. Un CDT cuyo vencimiento ya pasó pasa a estado CERRADA y genera la transacción de pago de intereses con su retención.
6. Todo valor monetario del resultado debe poder rastrearse a una línea de detalle con tarifa, base y norma.
7. Si falta un parámetro vigente para la fecha del hecho, el cálculo debe fallar con mensaje claro y no usar un valor por defecto silencioso.

## 7. Puntos abiertos a validar con un contador

1. Tarifa ponderada de retención de FIC por tipo de fondo y si el módulo debe pedir siempre el dato del certificado del administrador.
2. Retención sobre dividendos de personas naturales residentes y descuento tributario asociado, incluido el tratamiento de dividendos del exterior y el descuento por impuesto pagado en el exterior.
3. Tarifa de retención en retiros anticipados de FPV según la fecha de origen del aporte (anteriores a 2013, 2013–2016, desde 2017).
4. Aplicación del beneficio del art. 36-1 a ETF listados en la BVC (el texto hace referencia a acciones).
5. Tratamiento de pérdidas en venta de acciones (no se modeló compensación entre lotes ni entre años).
6. Aportes a seguridad social sobre ganancias de renta ordinaria.
7. Obligación de reportar activos en el exterior cuando el valor supere el umbral anual, que puede aplicar a quien tenga acciones o ETF extranjeros.
8. Tramos de la tabla de cesantías por encima de 530 UVT y tratamiento de los rendimientos del fondo.
9. Porcentaje de componente inflacionario del año gravable 2025 y 2026: se fija por decreto cada año; el del 2024 fue 50,88% (Decreto 771 de 2025) y el Decreto 898 de 29 de julio de 2026 fija el del siguiente año.
10. Evolución del Proyecto de Ley 477 de 2026 y del Decreto 572 de 2025 (el Consejo de Estado suspendió parcialmente sus artículos 2 a 8 el 7 de mayo de 2026 y levantó la suspensión el 2 de junio de 2026; el artículo 1, sobre CDAT, nunca estuvo suspendido).

## 8. Referencias normativas usadas

- Estatuto Tributario: arts. 36-1, 38 a 41 (componente inflacionario), 126-1 (FPV), 206 num. 4 y 207-1 (cesantías), 300 y 314 (ganancia ocasional), 336, 368-1 y 395 (retención).
- Decreto 1625 de 2016: arts. 1.2.4.1.33 (retiro de aportes voluntarios), 1.2.4.2.78 (retención de FIC), 1.2.4.2.83 (CDT y CDAT).
- Decreto 572 de 2025 (retención en la fuente, CDT/CDAT al 4%) y Decreto 1848 de 2013 (retención en FIC).
- Resolución DIAN 000238 de 2025 (UVT 2026 = $52.374).
- Proyecto de Ley 477 de 2026 (Cámara de Representantes), en trámite.

## 9. Decisiones de negocio acordadas (v1.1)

El módulo no calcula el impuesto de renta final: entrega ingresos y valor deducible al módulo de Ingresos de la aplicación, que calcula lo que debe pagar el usuario; los topes de FPV se validan en este módulo (9.8).

### 9.1 Alcance

1. Es un ejercicio académico y el usuario es siempre persona natural. No se considera el Proyecto de Ley 477 de 2026: los puntos 1.5 y 7.10 quedan fuera.
2. El formulario es dinámico: los campos de perfil y los específicos de cada producto (por ejemplo ingreso anual para FPV o ingreso promedio para cesantías) se piden solo si el usuario elige ese producto.
3. Todas las tarifas viven en una tabla de la base de datos que el administrador puede modificar (RF-PAR).
4. El cliente elige el plazo de cada inversión.
5. Las pérdidas en acciones y ETF solo se informan; no se compensan.

### 9.2 Reglas por producto

| Producto | Decisión |
| --- | --- |
| CDT | Plazo elegido por el cliente. Pago al vencimiento o periódico. Retención 4% sobre cada pago de intereses. |
| Cuenta de alto rendimiento | Tasa E.A. fija. El usuario registra abonos y retiros, por lo que el saldo cambia. Retención 7% sobre cada abono de intereses. En el formulario marca si la cuenta está exenta del 4x1000: si no lo está, se descuenta 0,4% en cada retiro; si lo está, no se descuenta hasta el tope mensual de 350 UVT y lo que exceda se descuenta. |
| Acciones / ETF | Una sola posición por inversión, con costo promedio. La fecha de la primera compra define el tiempo de tenencia. El usuario digita el valor actual de la unidad y la TRM usa el campo que ya existe en la base de datos. Sin retención en la venta. Impuesto estimado de la venta según 5.4 (0% BVC dentro del tope, 15% con 2 años o más, tarifa marginal con menos). |
| Dividendos | Bruto = unidades × valor por acción digitado por el usuario. Retención: ETF distributivo 30%, ETF acumulativo 15%, acciones individuales 30%. El bruto suma como ingreso y la retención suma además al valor deducible. |
| FIC | Catálogo predefinido (9.4). Sin comisiones. Retención solo al redimir, sobre la utilidad, con la tarifa ponderada del fondo. |
| FPV | Cada aporte suma como ingreso deducible dentro del tope, validado en el formulario. La retención solo aplica en retiro anticipado: 7% sobre los aportes retirados y 7% sobre los rendimientos retirados. |
| Cesantías | El usuario ingresa saldo, porcentaje de interés esperado y plazo. Retención 0%. La parte exenta (tabla por UVT) suma al valor deducible. |

### 9.3 Valor deducible

El módulo registra un valor deducible por cada uno de estos conceptos y lo envía al módulo de Ingresos.

| Concepto | Origen |
| --- | --- |
| Aportes a FPV | Cada aporte registrado, hasta el tope; se revierte en un retiro anticipado |
| Cesantías exentas | Parte exenta del retiro según el ingreso promedio |
| Utilidad no gravada de acciones BVC | Ventas dentro del tope del art. 36-1 |
| Retención sobre dividendos | 30% o 15% retenido en acciones y ETF |

### 9.4 Catálogo de FIC (ilustrativo)

Los nombres son genéricos y los porcentajes son ejemplos para el ejercicio; el administrador puede editarlos. Tarifa ponderada = suma de (porcentaje × tarifa de cada componente).

| Fondo | Renta fija (4%) | Rend. financieros (7%) | Renta variable (0%, a validar) | Tarifa ponderada |
| --- | --- | --- | --- | --- |
| FIC Liquidez | 30% | 70% | 0% | 6,1% |
| FIC Renta Fija Corto Plazo | 80% | 20% | 0% | 4,6% |
| FIC Renta Fija Largo Plazo | 100% | 0% | 0% | 4,0% |
| FIC Balanceado | 50% | 20% | 30% | 3,4% |

### 9.5 Cambios al modelo de datos

1. **detalle\_renta\_variable** incluye ahora: unidades\_total, costo\_promedio\_unitario, fecha\_primera\_compra, tipo\_reparto (DISTRIBUTIVO, ACUMULATIVO, NO\_APLICA), valor\_actual\_unidad, valor\_dividendo\_por\_unidad (ambos digitados por el usuario) y trm\_compra.
2. **lote\_compra** y **fic\_lote** dejan de usarse.
3. **detalle\_cuenta** guarda una tasa E.A. fija y es\_cuenta\_gmf\_exenta; **cuenta\_tramo\_tasa** deja de usarse. Los abonos y retiros se registran en **transaccion**.
4. **fic\_catalogo**: id, nombre, perfil, activo. **fic\_catalogo\_composicion**: id, fic\_id, concepto, porcentaje, tarifa\_retencion. **detalle\_fic** referencia fic\_catalogo\_id y guarda unidades, valor\_unidad\_compra y valor\_unidad\_actual.
5. **detalle\_cesantias** guarda saldo, tasa\_interes\_esperada y plazo\_dias.
6. **valor\_deducible\_registro**: id, usuario\_id, inversion\_id, transaccion\_id, anio, concepto (APORTE\_FPV, CESANTIAS\_EXENTA, UTILIDAD\_NO\_GRAVADA\_BVC, RETENCION\_DIVIDENDOS), valor.
7. **ingreso\_inversion** (salida hacia el módulo de Ingresos): id, usuario\_id, inversion\_id, anio, tipo (RENDIMIENTO, UTILIDAD\_VENTA, DIVIDENDO), valor\_bruto, retencion.

### 9.6 Fórmulas actualizadas

1. Costo promedio tras una compra adicional: (unidades\_previas × costo\_previo + unidades\_nuevas × precio\_nuevo + comisión) / (unidades\_previas + unidades\_nuevas).
2. Venta: utilidad = unidades\_vendidas × (precio\_venta − costo\_promedio) − comisiones. El costo promedio no cambia al vender. En moneda extranjera, cada valor se convierte con la TRM de su fecha.
3. Cuenta de alto rendimiento: el interés se calcula por periodos de saldo constante, interes = saldo × ((1 + EA)^(días/365) − 1); retención = 7% × interés abonado; GMF = 0,4% × retiro si la cuenta no es exenta.
4. Dividendo: bruto = unidades × valor\_dividendo\_por\_unidad; retención = bruto × tarifa según tipo\_reparto; neto = bruto − retención.
5. FPV en retiro anticipado: retención = 7% × aportes retirados + 7% × rendimientos retirados.

### 9.7 Partes de las secciones 2 a 7 que quedan sustituidas

- RF-IMP-06 (división por lote), 4.2 lote\_compra, fic\_lote y cuenta\_tramo\_tasa.
- 5.3 (tramos de tasa por saldo) y 5.4 pasos 3 y 4 en lo que se refiere a PEPS y lotes.
- 5.6 punto 4 (retención sobre aportes retirados): se reemplaza por 9.6 punto 5.
- 7.5 y 7.10 de los puntos abiertos.

### 9.8 Decisiones sobre los pendientes

1. Dividendos de acciones individuales: retención 30%. Si existieran acciones acumulativas se usaría 15%; si no, queda 30% fijo. La tarifa es un parámetro editable.
2. Los topes de FPV se validan en este módulo. El formulario pide ingreso anual, aportes a AFC del año y aportes a FPV ya registrados en el año, y comprueba que el monto a consignar quepa dentro del menor valor entre 30% del ingreso anual y 3.800 UVT, menos lo ya aportado. Si el monto excede el tope, el sistema avisa y solo la parte dentro del tope suma como deducible.
3. En un retiro anticipado de FPV, los aportes retirados dejan de contar como deducible: se registra un ajuste negativo en valor\_deducible\_registro por ese monto.
4. Las cuentas marcadas como exentas conservan el tope mensual de 350 UVT: los retiros del mes por encima del tope pagan 0,4%.
5. Los FIC con componente de renta variable quedan en 0% de retención, porque es solo una simulación.

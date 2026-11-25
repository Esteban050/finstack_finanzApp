# Hallazgos FinanzApp — 2.ª ejecución (25/11/2026)

Persona de prueba: Teban (independiente, CDT y acciones). BD limpia al inicio. UVT 2026 = 52.374, SMMLV = 1.750.905.
Regla de la prueba: no se corrigió nada para que cuadre; cada diferencia se anota con su cálculo a mano.

## 1. Diferencias abiertas (para corregir)

### N01 — Créditos: interés corriente y "Para cancelar a la fecha" usan 15 días sobre todo el saldo
- **Dónde:** Créditos › tarjeta Bancolombia (detalle) › "Para cancelar a la fecha" y simulación del pago de cuota.
- **Datos:** tarjeta 28 % EA, cupo 3.000.000. Compras: Platzi 1.200.000 (10/11), Avianca 1.500.000 (25/11), Hotel 300.000 (25/11). Saldo 3.000.000. Fecha de consulta: 25/11.
- **Esperado:** el interés corriente se cuenta por compra y por días desde cada compra. Solo Platzi lleva días: 1.200.000 × (1,28^(15/365) − 1) = 12.236. Las compras del 25/11 aún no causan interés. Cancelar a la fecha = 3.000.000 + 12.236 = **3.012.236**.
- **Obtenido:** interés **30.590** = 3.000.000 × (1,28^(15/365) − 1), o sea 15 días sobre todo el saldo. Cancelar a la fecha = **3.030.590** (+18.354).
- **Efecto en cadena:** en el pago de 497.082 del 25/11 el abono a capital queda en 466.492 (esperado 484.846), el saldo en 2.533.508 (esperado 2.515.154) y el gasto generado en 30.590 (esperado 12.236).
- **Nota:** el plan de cuotas sí calcula bien el interés por compra (cuota 1 = 497.082). La inconsistencia es solo en el cálculo "a la fecha".

### N02 — Inversiones: el interés cobrado del CDT se cuenta dos veces
- **Dónde:** Inversiones › detalle del CDT; Portafolio consolidado; Resumen › "Portafolio de inversiones".
- **Datos:** CDT Davivienda 5.000.000, 11,5 % EA, 180 días, apertura 24/09/2026. Abono de intereses 24/10/2026: bruto 44.935,40, retención 1.797,42, neto 43.137,98.
- **Esperado:** el interés cobrado sale de la posición. Valor actual ≈ 5.000.000 (+ causado pendiente del 24/10 al 25/11 = 47.945,42 bruto). Ganancia ≈ 43.137,98 cobrado neto (+ causado pendiente neto).
- **Obtenido:** valor actual **5.043.137,98** (capital + neto cobrado) y ganancia **86.275,96** (= 2 × 43.137,98), ROI 1,73 %. La acción en la misma pantalla sí se comporta bien: ganancia 723.000 = valorización 483.000 + dividendos 240.000.
- **Efecto en cadena:** Portafolio consolidado: valor 10.763.137,98 y ganancia 809.275,96. Resumen › portafolio: 10.763.137,98. Con la corrección el valor del CDT bajaría cerca de 43.138.

### N03 — Renta › Calculadora: "Estimación del sistema" no coincide con Estimación
- **Dónde:** Renta › Calculadora.
- **Esperado:** saldo a favor estimado 1.212.000 (retenciones 1.211.797 redondeadas a miles; impuesto $0), igual que en Renta › Estimación.
- **Obtenido:** Impuesto estimado $0 y Saldo estimado **$0** ("Saldo en ceros"). La calculadora no considera retenciones.

### H10 — Notificaciones: no llega "Vas a pasarte"
- **Dónde:** Notificaciones (regla "Vas a pasarte", umbral 110 %).
- **Esperado:** alerta, porque la proyección del presupuesto total (4.158.468 sobre 3.160.000 = 131,60 %) y de varias líneas supera 110 %: PILA 1.253.760/450.000 = 278,6 %, Arriendo 2.160.000/1.200.000 = 180 %, Internet 132.000/110.000 = 120 %.
- **Obtenido:** no hay ninguna alerta de ese tipo. Las demás reglas de presupuesto y cupo sí llegaron correctas.

### H22 — Redondeos e inconsistencias menores entre pantallas
| Dónde | Esperado a mano | Obtenido |
|---|---|---|
| Resumen › Ejecución del presupuesto | 3.465.390 / 3.160.000 = 109,66 % | Resumen 109,70 % (Presupuestos y Reportes muestran 109,66 %) |
| Inversiones › acción tras valoración | 413.000 / 6.112.000 = 6,757 % | "+6,75 %" arriba y "6,76 %" en el formulario |
| Reportes › Regla 50/30/20 | Esenciales 3.405.390 / 11.744.841,67 = 28,99 % | 29,00 % (se fuerza para que sume 100) |
| Renta › Impacto de inversiones | Retención 1.797,42 | 2.000 en "ahorro por inversiones" |

### H09 — Presupuestos: etiqueta "Ingreso promedio"
- **Obtenido:** $11.744.842, que es el **ingreso neto de noviembre** (5.340.000 + 2.670.000 + 1.654.775 + 1.780.000 + 240.000 + 60.066,67), no un promedio.
- "Frente a tus ingresos 35,41 %" usa la **proyección** (4.158.468 / 11.744.842); el gastado real da 29,51 %. La etiqueta no lo aclara.

### Sin cambios respecto a la 1.ª ejecución
- **H18:** no hay tasa de usura cargada.
- **H23:** la simulación del formulario de registro de inversión muestra "—".
- **H24:** el selector de periodo de PILA indica que no hay ingresos confirmados.
- **H16 (parcial):** el pago anticipado ya no cobra el mes completo (cobra 15 días), pero con la base equivocada (ver N01).
- **GMF:** sigue en "—" en todos los movimientos.
- **No se re-probaron:** H25 (correo/términos) y H26 (ROI/XIRR; la XIRR sigue anualizando periodos cortos, con aviso en pantalla).

## 2. Hallazgos de la 1.ª ejecución ya corregidos (verificados)

| ID | Qué pasaba | Evidencia ahora |
|---|---|---|
| H01 | PILA pagada no llegaba a Renta (INCRNGO $0) | INCRNGO salud+pensión 1.026.000 |
| H02 | Utilidad de acciones 75.000 vs 60.066,67 | 60.067 en Renta |
| H03 | Rendimiento del CDT entraba neto y con retención $0 | Ingresos: bruto 44.935,40 / ret. 1.797,42 / neto 43.137,98 |
| H04 | Redención anticipada pagaba 100 % del rendimiento | Se bloquea: "El CDT no se puede redimir antes de su vencimiento (23/03/2027)" |
| H05 | Portafolio $0 en Resumen | Muestra valor (ver N02 por el doble conteo) |
| H06 | Sin alerta de cupo de tarjeta | Alerta "Uso del cupo" al 80 % |
| H07 | Compras posteriores no sumaban interés en el plan | Interés c1 = 16.342 + 5.081 = 21.423 |
| H08 | Endeudamiento sobre base equivocada | 206.199 / 11.444.775 = 1,80 %; 497.082 / 11.744.841,67 = 4,23 % |
| H11 | Pago de tarjeta duplicaba capital en Gastos | Gasto solo con intereses |
| H12 | Reporte de retenciones con base que incluía capital | Base 44.935,40 |
| H13 | Valores deducibles incluían la retención | 60.066,67 (solo Art. 36-1) |
| H14 | "Próximos 15 días" en $0 | Cuota #1 30/11 por 497.082 |
| H15 | Recurrentes sin ocurrencia ni título | Genera ocurrencia 25/11, próxima 25/12 |
| H17 | El cupo no bloqueaba consumos | "El gasto supera el cupo disponible ($ 300.000) en $ 100.000" |
| H19 | Gasto diario dividía por 30 | 6.465.390 / 25 = 258.615,60 |
| H20 | Dividendos fuera de ingresos de Renta | C2 = 12.999.777 incluye 240.000 |
| H21 | Proyección con líneas fijas distinta | Todas por ritmo: gastado × 30/25 |
| H27 | Renta abría en 2025 | Abre en 2026 |

## 3. Verificaciones que cuadran (referencia)

- **Ingresos:** 6.000.000 y 3.000.000 con retención 11 %; USD 500 × 3.309,55 = 1.654.775; totales 12.654.775 / 1.210.000 / 11.444.775.
- **PILA:** 9.000.000 → IBC 3.600.000, salud 450.000, pensión 576.000, ARL 18.800, total 1.044.800. Mínimo SMMLV: total 508.300. FSP con 20.000.000: total 2.401.800.
- **CDT:** bruto al vencimiento 275.743,08; retención 11.029,72; neto 264.713,36. Abono: 44.935,40 / 1.797,42 / 43.137,98.
- **Acciones:** costo promedio 40.746,6667; utilidad de venta 880.000 − 814.933,33 − 5.000 = 60.066,67; dividendo 240.000.
- **Tarjeta:** cuota fija de 1.200.000 = 214.798,66; cuota 1 con tres compras 497.082; cuotas 2–6 = 536.997.
- **Presupuesto:** total 3.160.000; gastado 3.465.390 (109,66 %); proyección 4.158.468 (131,60 %).
- **Resumen:** ingresos 11.744.841,67; gastos 6.465.390; balance 5.279.451,67; ahorro 44,95 %.
- **Renta:** renta líquida ordinaria 11.893.489; exenta 25 % = 2.907.194; gravable 8.986.295 = 171,58 UVT; impuesto $0; saldo a favor 1.212.000. Calculadora de topes y simulador Art. 241 (1.500 / 2.000 / 5.000 UVT → 4.079.934,60 / 10.474.800 / 56.825.790) correctos.

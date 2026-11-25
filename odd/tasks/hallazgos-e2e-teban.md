# Fix findings from the "Teban" end-to-end test

## Objective
Fix the findings listed in `docs/Hallazgos_FinanzApp_para_agente.md` (H01–H27) so every module shows the same, correct figures as the hand calculation.

## Problem / why
Several values computed in one module reach other modules wrong (Renta, Resumen, Ingresos, Reportes, Notificaciones), which corrupts the tax declaration estimate and dashboard figures.

## Scope
- Bugs with a clear expected value: fix directly with tests.
- Findings typed **Decision** (H04, H08, H11, H16, H17) and **Verificar** (H10, H15, H20, H24, H26): ask the user one decision at a time before coding.
- Config items (H18, H25): data/config, not code; report to the user.

## Constraints
- Do not touch what section 6 of the findings document marks as correct.
- One work-unit commit per task on branch `fix/hallazgos-e2e-teban`; push/PR is the user's decision.
- Delivery strategy: ask-on-risk (forecast > 400 lines; ask chain strategy before the PR).

## TDD
Mode: enabled (global CLAUDE.md "Strict TDD Mode: enabled"). Runner: `php artisan test` (PHPUnit, local pgsql `bd_finanzApp` via `Tests\Concerns\UsaBaseLocal`).

## Tasks
Group 1 — data reaching other modules wrong (route: delegated writer, writer trigger: 2+ non-trivial files)
- [x] T1 — H03: investment-generated income stores gross, retention and net separately.
- [x] T2 — H05: Resumen "Portafolio de inversiones" uses the Inversiones consolidated value.
- [x] T3 — H14: Resumen "Próximos 15 días" shows the recurring amount.
- [x] T4 — H01: paid PILA health + pension reach Renta INCRNGO.
- [x] T5 — H02: single cost method (weighted average) for share-sale profit in Ingresos and Renta.

Group 2 — credit card
- [x] T6 — H07: first-installment interest computed per purchase and real days.
- [x] T6b — hardening of T5b..T17 review findings (interest per purchase, card limit input, SUPERA_CUPO alert test, card payment guards, edit/delete recompute).
- [x] T7 — H06 (decided: NEW ALERT TYPE USO_CUPO_TARJETA, threshold default 80 %): notification when a card reaches the configured usage of its limit (the "limit exceeded" case is blocked by T19).

Group 3 — cross-module consistency
- [x] T8 — H09: one "ingreso promedio" function used by Presupuestos, Alertas, Reportes, Créditos.
- [x] T9 — H10 (decided ADD TOTAL ALERT): "Vas a pasarte" also fires for the budget total when its projection exceeds the threshold.
- [x] T10 — H12 + H13: retention report base = yields only; deductible values exclude withholding.
- [x] T11 — H19: daily average divides by elapsed days.
- [x] T12 — H21: same projection rule for fixed lines with zero spend.
- [x] T12b — hardening of the 0a5b3e7..aeaece1 review (retention fallbacks, deducible rows, FIJO projection alerts, percentage base, daily average to today, tests via real evaluator/mail path).

Group 4 — UX / verify
- [x] T13 — H15: recurring items generate the start-date occurrence on save; card title shows the merchant.
- [ ] T14 — H22, H23, H27: rounding, form inputs, Renta default year.

- [x] T15 — H04 (decided 2026-11-24: BLOCK): CDT redemption before maturity is rejected (server-side validation + form min date = maturity date).

- [x] T16 — H08 (decided 2026-11-24: USE CURRENT MONTH): when closed months lack enough income, the debt ratio uses the current month income as denominator.

- [x] T17 — H11 (decided 2026-11-24: RECOGNIZE AT PURCHASE): card installment payments no longer create a duplicate expense for capital; only the interest portion is an expense (financial obligations).

- [x] T18 — H16 (decided 2026-11-24: REAL DAYS, like ABONO_CAPITAL): payment before the installment due date charges current interest accrued by real days; the rest goes to capital and the plan is regenerated as in ABONO_CAPITAL.

- [x] T19 — H17 (decided 2026-11-24: BLOCK): an expense that exceeds the card's available limit is rejected with a validation error (create and edit). Consequence for T7/H06: the "limit exceeded" notification no longer applies; keep the threshold notification.

- [x] T20 — H20 (decided 2026-11-24: INCLUDE): dividends received in the year are added to C2 gross income.
- [ ] T21 — H24 + H26 (no user decision needed, defaults): unify PILA period selector rule/messages (period M uses income of M−1; allow up to current month+1 consistently); portfolio ROI uses the same capital base as per-investment ROI; GMF behaviour documented in UI (exemption not tracked).

Pending decisions (ask user, one at a time): none. H16, H17, H20, H24, H26.
Config only (report): H18 usury rate, H25 Resend domain / terms page.

## Acceptance criteria
Each finding's "Criterio de aceptación" in the findings document; `php artisan test` green except known pre-existing failures.

## Progress
- Created 2026-11-24. Branch `fix/hallazgos-e2e-teban` created from `main` (a039e89).
- T1 (H03) done: RED 2 failed -> GREEN 2 passed (IngresoRendimientoInversionBrutoTest); suite subset Inversion|Ingreso|Reportes 92 green. Root cause: InversionService::registrarPuentesTributarios sections 6/6b/7 created Ingreso with net as bruto and retention 0. Commit: see git log (fix(ingresos) H03).
- T2 (H05) done: RED 1 failed -> GREEN 1 passed (ResumenPortafolioInversionesTest); Resumen|Dashboard filter 12 green. Root cause: DashboardResumenService read nonexistent keys totales.valor_actual_cop (real: valor_total). Cut-off clamped to today.
- T3 (H14) done: RED 1 failed -> GREEN 1 passed (ResumenProximos15DiasTest). Root cause: DashboardResumenService::obtenerProximos15Dias read datos_plantilla['monto'] (stored key is monto_cop).
- T4 (H01) done: RED 1 failed -> GREEN 1 passed (RentaIncrngoSeguridadSocialTest); Renta|SeguridadSocial|PagoSeguridad filter 100 green. Root cause: ConsolidadoRentaService::consolidar summed only ingresos.aportes_descontados_cop, never PagoSeguridadSocial (PILA). Now adds SeguridadSocialService::resumenAnual (by fecha_pago year, paid rows).
- T5 (H02) done: RED 1 failed (75000 vs 60066.67) -> GREEN 1 passed (RentaUtilidadVentaCostoPromedioTest). Root cause: ConsolidadoRentaService used inv.precio_unitario_compra * unidades; now reads IngresoInversion UTILIDAD_VENTA (weighted average, same as Ingresos). Note: legacy sales without a UTILIDAD_VENTA bridge row count as 0.
- T5b (follow-ups, commit a67ae97) done: RED 2 failed (retention 3594.84 vs 1797.42; sale fallback 0 vs 60066.67) -> GREEN 3 passed (RentaConsolidadoSeguimientoTest). (a) Real double count: ConsolidadoRentaService summed ingresos.retencion_fuente_cop of investment-generated incomes AND MovimientoInversion.retencion_aplicada; now skips ingresos linked via movimientos_inversion.ingreso_id (Reportes > Retenciones reads only movimientos: no double count; Ingresos totals read only ingresos: correct 1.151.029,73). (b) PILA vs aportes_descontados are independent sources (RN-034.5 forbids payroll aportes on honorarios): documented with test, no code change. (c) fallback utilidadVentaSinPuente reuses CalculoImpuestoInversionService::calcularVentaAcciones. Added rendimiento_estimado assertion in ResumenPortafolioInversionesTest.
- T6 (H07, commit 0852049) done: RED 42217 vs 24068 -> GREEN (TarjetaInteresPrimeraCuotaPorCompraTest). Root cause: CreditoService::tablaAmortizacion charged interest on the whole balance from fecha_inicio. Now option 'compras' => interesComprasPorDias (sum per purchase, rounded once). Purchases come from PagoCreditoService::comprasTarjeta (gastos with credito_id) or from ConsumoTarjetaService::aplicar (pending gasto not yet saved). Limitation: plans regenerated after payments (ajustarPlanTarjeta) keep the previous logic.
- T19 (H17, commit fb54269) done: RED 3 failed -> GREEN 5 passed (CupoTarjetaTest rewritten). ConsumoTarjetaService::validarCupoDisponible (existing card, create/edit, uses cupo - saldo_capital) and GastoController::datosCredito (new card purchase > cupo_tarjeta); error on monto_original; form help text updated.
- T17 (H11, commit 9d6535b) done: RED 94560 vs 20000 -> GREEN (PagoTarjetaGastoSoloInteresesTest). PagoCreditoService::crearGastos: for TARJETA_CREDITO the expense is valor - capital - abono extraordinario (interest, mora, seguro, cargos); gasto_id null if zero. Other types unchanged. Full suite 345/345 green.
- T7 (H06) BLOCKED, needs decision: the preference 'Consumos con tarjeta de crédito' (default 80%) is TOPE_DIAN_TARJETA = annual card consumption vs the DIAN 1.400 UVT cap (AlertaFinancieraService::topeDian), not card-limit usage. It works as designed. A card-limit-usage notification would need a new alert type/preference (or re-purposing).
- T18 (H16) BLOCKED, needs decision: disbursement date of the libre-inversion credit is not in the scenario (first cuota 24/12 monthly implies fecha_inicio 24/11 = payment day -> 0 days of interest). Also ANTICIPO semantics (cuota stays partial vs plan regeneration) undefined.
- T6b done: RED 1 failed (capital fechado 3393 pesos over; others were characterization of already-correct behavior: factorDias already clamps days<=0, cupo_tarjeta already validated by regex) -> GREEN 12 passed (TarjetaEndurecimientoTest + CupoTarjetaTest). Fixes: CreditoService::interesComprasPorDias caps dated capital at saldo and clamps days explicitly; GastoController::cupoEntero guards cupo (no ValueError); PagoCreditoService::crearGastos null-safe CAPITAL/ABONO_EXTRAORDINARIO keys. Tests added: zero-interest payment (gasto_id null), payment with abono extraordinario (gasto = interest only), edit/delete recompute cuota-1 interest, SUPERA_CUPO detail alert, no Gasto row after rejection.
- T7 (H06) done: RED 4 failed (no type) -> GREEN 4 passed (UsoCupoTarjetaAlertaTest); Alerta|Notificacion|Regla|Presupuesto|Perfil|Resumen 50 green. New type USO_CUPO_TARJETA: config/scfp.php (default 80 %, ADVERTENCIA), ReglaAlertaService (const + umbral 1..100), AlertaFinancieraService::usoCupoTarjeta (per TARJETA_CREDITO with cupo, saldo_capital/cupo, key USO_CUPO_TARJETA:{credito}:{umbral}, evaluated on card expense create/edit and daily), NotificacionService email text, ResumenCorreoService name; TOPE_DIAN_TARJETA relabeled "Tope DIAN: consumos con tarjeta de crédito" (resumen correo + planning rules.js/index.js). No migration needed (types are config-driven). Frontend source changed in resources/js; public/build not rebuilt (run npm run build). Limitation: one notification per card+threshold (re-crossing after paying down does not re-notify).
- T18 (H16) done: RED 2 failed (138884 vs 45449 for 10 days; interest present at 0 days) -> GREEN 2 passed (PagoAdelantadoInteresPorDiasTest); Credito|Pago|Tarjeta 60 green. Root cause: PagoCreditoService::imputar ANTICIPO branch paid the full interest of the next cuota (:492). Now ANTICIPO is mapped to ABONO_CAPITAL with reducir=PLAZO (same code path: interes_causado by real days since start/last cuota or abono, rest to capital, plan regenerated, same cuota shorter term); stored aplicacion_excedente is ABONO_CAPITAL so later causado references the payment date. Blade label clarified. Scenario 10M/18 % EA/36/first cuota 24-12/start 24-11 paid 24-11 -> 0 interest.
- T15 (H04) done: RED 1 failed (no session error, early redemption accepted) -> GREEN 1 passed (InversionesTributariasTest::test_h04_...); Inversion|Ingreso|Reportes|Renta 140 green. MovimientoInversionController (CDT REDENCION branch) throws ValidationException on `fecha` when fecha < detalleCdt/inversion fecha_vencimiento; show.blade.php sets trx_fecha min to maturity for REDENCION (reset to apertura otherwise). No existing test redeemed early (existing ones redeem on maturity). pint also normalized unrelated style in the two touched files.
- T16 (H08) done: RED 478.00 vs 1.52 (+ no `base` key) -> GREEN 5 passed (EndeudamientoBaseIngresoTest); Endeudamiento|Credito|Indicadores|Reportes|Resumen|Alerta|Dashboard 82 green. EndeudamientoService::baseIngresos (used by calcular): if fewer than 2 of the last 3 closed months have income (or closed average 0) and the current month has net income, denominator = current-month net income (`ingresos_netos.base` = MES_EN_CURSO, else MESES_CERRADOS). Shown in Créditos (credits/index.js labels + note) and Reportes (IndicadoresSaludService variables "Base del ingreso"/"Ingreso neto usado"). Scenario 206.199 / 13.536.417 = 1,52 %. Alert ENDEUDAMIENTO uses the same calcular. public/build not rebuilt.
- T20 (H20) done: RED 2 failed (no dividendos_cop key; UI note present) -> GREEN 2 passed (RentaDividendosC2Test); Renta|Informacion 52 green. ConsolidadoRentaService::consolidar now sums Ingreso clasificacion DIVIDENDOS (monto_bruto_cop, year by fecha_recepcion) into ingresos_brutos.dividendos_cop/_uvt and total_cop (+ drill_down.dividendos); estimacion paso1 and consolidado show the row; the "no considera dividendos" bullet was removed. Retention of investment-linked dividend ingresos stays counted once (existing exclusion).
- T20b (hardening of 62eb53a..72b80b3) done: RED (c) consolidado view 500 on missing dividendos_cop, (e) CDT min date not from detalleCdt, (b) ANTICIPO message generic -> GREEN PagoAdelantadoInteresPorDiasTest 5, RentaDividendosInversionTest 2, InversionCdtVencimientoUiTest 1, UsoCupoTarjetaAlertaTest 6. (a) verified: total payoff / null aplicacion still maps to ABONO_CAPITAL (test added); other values rejected by controller validation. (b) UI already hides `reducir` unless ABONO_CAPITAL; ANTICIPO interest-only now gets a specific message. (c) view uses `?? 0`; investment DIVIDENDO (150 x 1.600 = 240.000) counts once. (d) USO_CUPO_TARJETA moved to group "Obligaciones y cuenta" (needs npm run build). (e) server/client share detalleCdt->fecha_vencimiento first. (f) email text + daily job tests.
- T8 (H09) done: RED presupuestos 14379 vs 13536417 / 2000000 vs 3000000 -> GREEN 3 passed (IngresoPromedioUnicoTest); Presupuesto|Alerta|Credito|Endeudamiento|Reporte|Notificacion 74 green. Root cause: Presupuestos/Alertas/AlertaCredito used ingresosNetosPorMes (fixed 3-month window incl. zeros) while Créditos/Reportes used baseIngresos. Now EndeudamientoService::ingresoPromedio (= baseIngresos promedio, H08 fallback) is the single definition; PresupuestoService::ingresoBase and AlertaCreditoService::ingresoMensualPromedio delegate to it.
- T9 (H10) BLOCKED, needs decision: "Vas a pasarte" (PROYECCION_LINEA) is evaluated only per line, from mid-month, and by documented design (RN-057.3, AlertaPresupuestoService `$yaExcedida`) skips a line already at >= 100 % execution because UMBRAL_LINEA 100 % covers it. In the scenario the PILA line (1.543.900 spent on 450.000) and the total (4.118.900 on 3.160.000 = 130 %) were already exceeded, and there is no total-level projection alert at all. Decision needed: (a) also alert on already-exceeded lines, (b) add a total-level projection alert, or (c) keep as designed and document.
- T10 (H12 + H13) done: RED 5275743.08 vs 275743.08; 71096.40 vs 60066.67 -> GREEN 2 passed (ReportesInversionesRetencionesUtilidadesTest). ReporteInversionController::retenciones base = IngresoInversion.valor_bruto of the movement (yield) with fallback monto_bruto, for totals/producto/entidad; utilidades deducibles_total excludes concepts starting RETE (RETENCION_*, RETEFUENTE_*). CSV rows unchanged.
- T11 (H19) done: RED 137296.67 vs 171620.83 -> GREEN 3 passed (ReportesGastoDiarioPromedioTest). IndicadoresSaludService::diasParaPromedio: current month = elapsed days (hoy->day), closed month = days in month, future = 1; used for the value and the 6-month trend; label "Días transcurridos" and formula text aligned. Previous-month reference stays on full days.
- T12 (H21) done: RED 500000 expected vs 0 -> GREEN 1 passed (PresupuestoProyeccionLineaFijaTest). Root cause: projection = gastado + pendientes + habitual; "Internet Hogar" got 110.000 only from a pending recurring template, "Cuota de crédito" (FIJO, no template) got 0. calcularLineas: FIJO line (naturaleza_sugerida) with 0 spent and days remaining projects its assigned amount (habitual = assigned - pending, so pending counts inside it). VARIABLE lines unchanged.
- T12b done: RED (retenciones: fallback net 43137.98 vs 0, CDT redemption 5230807.68 vs 230807.68; utilidades rows included RETE*; daily avg 213287.5 vs 171620.83) -> GREEN ReportesInversionesRetencionesUtilidadesTest 5, ReportesGastoDiarioPromedioTest 4, InversionCdtVencimientoUiTest 2; PresupuestoProyeccionLineaFijaTest 5 (characterization, no code change: FIJO at 100 % never exceeds the >=100 threshold; GASTO_PROYECTADO_ALTO uses the global projection, which has no FIJO rule; pending > assigned does alert; PORC_INGRESO lines are frozen at month start via base_fijada, documented by test); IngresoPromedioUnicoTest now evaluates the real GASTO_PROYECTADO_ALTO and IndicadoresSaludService; UsoCupoTarjetaAlertaTest asserts the sent email (array mailer) with distinct values. Reportes|Presupuesto|IngresoPromedio|UsoCupo|InversionCdt 57 green.
- T9 (H10, decided: ADD TOTAL ALERT) done: RED 1 failed (no total alert, 0 vs 1) -> GREEN 4 passed (PresupuestoProyeccionTotalAlertaTest); Presupuesto|Alerta|Notificacion 23 green. AlertaPresupuestoService::proyeccionTotal reuses type PROYECCION_LINEA (same preference/threshold/timing), key PROYECCION_TOTAL:{mes}, datos.total=true; fires only when total projection > threshold, total execution < 100 % and the budget has 2+ lines (single line: line alert already is the total). Scenario: lines 1.000.000/1.000.000, spent 1.000.000 + 800.000 on day 24 -> 112,50 %. NotificacionService email text has a total variant; planning description updated (needs npm run build).
- T13 (H15) done: RED 4 failed (0 occurrences generated on save; card title generic) -> GREEN 5 passed (RecurrenteGeneraAlGuardarTest); Recurr|Gasto|Ingreso 116 green. Root cause: the first occurrence was only created by the 00:05 job. RecurrenciaService::generarAlGuardar (same isolation as the job) runs from both store() actions when fecha_inicio <= today (any confirmation mode: REQUIERE_CONFIRMACION yields a PENDIENTE expense); future starts untouched. Gastos > Recurrentes card title = comercio_beneficiario, then descripcion, then 'Gasto recurrente'.

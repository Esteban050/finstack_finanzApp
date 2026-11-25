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
- [ ] T8 — H09: one "ingreso promedio" function used by Presupuestos, Alertas, Reportes, Créditos.
- [ ] T9 — H10: "Vas a pasarte" alert fires when the projection exceeds the threshold.
- [ ] T10 — H12 + H13: retention report base = yields only; deductible values exclude withholding.
- [ ] T11 — H19: daily average divides by elapsed days.
- [ ] T12 — H21: same projection rule for fixed lines with zero spend.

Group 4 — UX / verify
- [ ] T13 — H15: recurring items generate the start-date occurrence; card title shows the merchant.
- [ ] T14 — H22, H23, H27: rounding, form inputs, Renta default year.

- [x] T15 — H04 (decided 2026-11-24: BLOCK): CDT redemption before maturity is rejected (server-side validation + form min date = maturity date).

- [x] T16 — H08 (decided 2026-11-24: USE CURRENT MONTH): when closed months lack enough income, the debt ratio uses the current month income as denominator.

- [x] T17 — H11 (decided 2026-11-24: RECOGNIZE AT PURCHASE): card installment payments no longer create a duplicate expense for capital; only the interest portion is an expense (financial obligations).

- [x] T18 — H16 (decided 2026-11-24: REAL DAYS, like ABONO_CAPITAL): payment before the installment due date charges current interest accrued by real days; the rest goes to capital and the plan is regenerated as in ABONO_CAPITAL.

- [x] T19 — H17 (decided 2026-11-24: BLOCK): an expense that exceeds the card's available limit is rejected with a validation error (create and edit). Consequence for T7/H06: the "limit exceeded" notification no longer applies; keep the threshold notification.

- [ ] T20 — H20 (decided 2026-11-24: INCLUDE): dividends received in the year are added to C2 gross income.
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

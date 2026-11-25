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
- [ ] T2 — H05: Resumen "Portafolio de inversiones" uses the Inversiones consolidated value.
- [ ] T3 — H14: Resumen "Próximos 15 días" shows the recurring amount.
- [ ] T4 — H01: paid PILA health + pension reach Renta INCRNGO.
- [ ] T5 — H02: single cost method (weighted average) for share-sale profit in Ingresos and Renta.

Group 2 — credit card
- [ ] T6 — H07: first-installment interest computed per purchase and real days.
- [ ] T7 — H06: notifications at the configured card-usage threshold and when the limit is exceeded.

Group 3 — cross-module consistency
- [ ] T8 — H09: one "ingreso promedio" function used by Presupuestos, Alertas, Reportes, Créditos.
- [ ] T9 — H10: "Vas a pasarte" alert fires when the projection exceeds the threshold.
- [ ] T10 — H12 + H13: retention report base = yields only; deductible values exclude withholding.
- [ ] T11 — H19: daily average divides by elapsed days.
- [ ] T12 — H21: same projection rule for fixed lines with zero spend.

Group 4 — UX / verify
- [ ] T13 — H15: recurring items generate the start-date occurrence; card title shows the merchant.
- [ ] T14 — H22, H23, H27: rounding, form inputs, Renta default year.

- [ ] T15 — H04 (decided 2026-11-24: BLOCK): CDT redemption before maturity is rejected (server-side validation + form min date = maturity date).

Pending decisions (ask user, one at a time): H08, H11, H16, H17, H20, H24, H26.
Config only (report): H18 usury rate, H25 Resend domain / terms page.

## Acceptance criteria
Each finding's "Criterio de aceptación" in the findings document; `php artisan test` green except known pre-existing failures.

## Progress
- Created 2026-11-24. Branch `fix/hallazgos-e2e-teban` created from `main` (a039e89).
- T1 (H03) done: RED 2 failed -> GREEN 2 passed (IngresoRendimientoInversionBrutoTest); suite subset Inversion|Ingreso|Reportes 92 green. Root cause: InversionService::registrarPuentesTributarios sections 6/6b/7 created Ingreso with net as bruto and retention 0. Commit: see git log (fix(ingresos) H03).

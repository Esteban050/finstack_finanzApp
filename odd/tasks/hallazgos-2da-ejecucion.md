# Fix findings from the second "Teban" end-to-end run

## Objective
Fix the open findings in `docs/Hallazgos_FinanzApp_2da_ejecucion.md` (N01, N02, N03, H10, H22, H09) so each screen matches the hand calculation.

## Problem / why
Card payoff interest, CDT value/gain, the Renta calculator, the projection alert, and several labels/roundings disagree with the hand calculation or with other screens.

## Scope (approved by the user 2026-11-25)
- N01: card interest "a la fecha" per purchase and real days (`PagoCreditoService::situacion()` and `regenerar()`), non-card credits unchanged. Expected 12.236 / 3.012.236.
- N02: `ABONO_INTERES` does not increase a CDT valuation (`InversionService` ~297 and ~327); gain counts the payout once; one-off data fix for already-inflated CDT valuations.
- N03: Renta calculator "Estimación del sistema" uses `EstimacionRentaService::estimar()`.
- H10: "Vas a pasarte" fires even when execution is already >= 100 % (keep once-per-month dedup).
- H22: (a) Resumen budget execution 2 decimals; (b) valuation % rounds instead of truncating; (c) keep 50/30/20 largest-remainder, add note "ajustado para sumar 100 %"; (d) Renta "ahorro por inversiones" uses exact retention, thousand-rounding only for the declaration saldo.
- H09: label "Ingreso del mes en curso" when base = MES_EN_CURSO; "Frente a tus ingresos" uses spent; projection shown on a separate line.

Out of scope: H18, H23, H24, GMF, H25, H26 (unchanged / not re-tested).

## Constraints
- Do not touch what section 3 of the findings document marks as correct.
- One work-unit commit per task on branch `fix/hallazgos-2da-ejecucion`; push/PR is the user's decision.
- Conventional commits in Spanish (repo convention), no AI attribution.
- Delivery strategy: ask-on-risk.

## TDD
Mode: enabled (global CLAUDE.md "Strict TDD Mode: enabled"). Runner: `php artisan test` (PHPUnit, local pgsql `bd_finanzApp`).

## Tasks (route: delegated writer — writer trigger, 2+ non-trivial files)
- [x] T1 — N01: card interest per purchase in situacion()/regenerar().
- [x] T2 — N02: CDT interest payout not added to valuation + data fix.
- [ ] T3 — N03: calculator system estimate from estimar().
- [ ] T4 — H10: projection alert also when already exceeded.
- [ ] T5 — H22 (a)(b)(c)(d): rounding consistency.
- [ ] T6 — H09: income label and "frente a tus ingresos".

## Progress / evidence
- T1 done: RED saldo_para_cancelar 3030590 vs 3012236 esperado; GREEN tras interesComprasPorDias en situacion()/regenerar(); `php artisan test`: 421 passed (003021a).
- T2 done: RED valor 5043137.98 vs 5000000.00; GREEN con ABONO_INTERES sin efecto en CDT (solo CUENTA_ALTO_RENDIMIENTO capitaliza) + migracion de correccion unica (omite filas que quedarian negativas; no distingue valoraciones MANUAL tecleadas por el usuario); `php artisan test`: 424 passed.

## Next step
T3.

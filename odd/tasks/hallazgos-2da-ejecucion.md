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
- [x] T3 — N03: calculator system estimate from estimar().
- [x] T4 — H10: projection alert also when already exceeded.
- [x] T5 — H22 (a)(b)(c)(d): rounding consistency.
- [x] T6 — H09: income label and "frente a tus ingresos".

## Progress / evidence
- T1 done: RED saldo_para_cancelar 3030590 vs 3012236 esperado; GREEN tras interesComprasPorDias en situacion()/regenerar(); `php artisan test`: 421 passed (003021a).
- T2 done: RED valor 5043137.98 vs 5000000.00; GREEN con ABONO_INTERES sin efecto en CDT (solo CUENTA_ALTO_RENDIMIENTO capitaliza) + migracion de correccion unica (omite filas que quedarian negativas; no distingue valoraciones MANUAL tecleadas por el usuario); `php artisan test`: 424 passed.
- T3 done: RED `estimacion_sistema` ausente en la respuesta; GREEN con estimar() (saldo 1.212.000); `php artisan test`: 425 passed. `npm test` tiene 9 fallos preexistentes (iguales sin mis cambios).
- T4 done: RED nuevo test (total al 105 % sin alerta, 0 vs 1); GREEN al quitar la supresion por ejecucion >= 100 % (linea y total). Tests existentes ajustados: el de "alerta una sola vez" ahora espera 2 alertas (linea A al 100 % + total) y el de umbral usa 130 % porque la linea A (125 %) ya alerta; el de "no alerta si el total paso 100 %" invertido. `php artisan test`: 425 passed.
- T5 done: RED 3 tests (109.7 vs 109.66; 6.75 vs 6.76; 2000 vs 1797.42); GREEN con round 2 decimales, redondeo de % de valorizacion, retencion exacta en ahorro (saldo declaracion sigue a miles), nota "Ajustado para sumar 100 %" en Reportes 50/30/20 (JS); `php artisan test`: 428 passed; `npm run build` ok; `npm test` 9 fallos preexistentes.
- T6 done: RED `ingreso_base_tipo` ausente (2 tests); GREEN con `ingreso_base_tipo` (MES_EN_CURSO|MESES_CERRADOS) en payload y /base, etiqueta "Ingreso del mes en curso", "Frente a tus ingresos" = gastado/ingreso y linea aparte "Proyeccion frente a tus ingresos"; `php artisan test`: 430 passed (flake preexistente: tests con documento 10203040NN pueden chocar con el usuario real ...037); `npm run build` ok; JS sin tests de este modulo (npm test: 9 fallos preexistentes).

- Native review (review-reliability, medium) on main..29546e2: approved and acknowledged (lineage review-786f6e9b0842fbf0). 8 informational findings.

## Review hardening (approved by the user 2026-11-25; R3-js-unproved explicitly excluded)
- [x] T7a — Migration: sum abonos with bcadd; restrict the fix to system-derived valuations (same date as an abono and value = previous valuation + monto) so user-typed values are untouched; add a migration test (inflated lowered, user-typed kept, negative skipped, date matching, liquidated CDT untouched).
- [x] T7b — `InversionService` ABONO_INTERES no-effect rule explicit for `tipo === 'CDT'`; valuation % rounded with bcmath half-up instead of float round.
- [x] T7c — Calculator: `estimar()` failure must not break the simulation (try/catch → estimacion_sistema null/"No disponible"), with test.
- [x] T7d — Test: card without registered purchases in regenerar() keeps flat interest by days (blindaje).
- [x] T7e — `RentaCalculadoraEstimacionSistemaTest`: own category and guaranteed-unique document.

## Next step
Push/PR at the user decision.

### Review hardening evidence
- T7a done: RED valor digitado por el usuario bajado (5043000 -> 4999862.02); GREEN con identificacion por marcas de tiempo (misma fecha del abono sin reedicion posterior; fechas posteriores con creado_en <= creado_en del abono) y bcsub/bcadd. 5 tests de migracion (inflada baja, digitada se respeta, negativa omitida, liquidado intacto, precision decimal). La migracion sigue Pending en la BD local (no se ejecuto sobre datos reales; los tests la invocan dentro de una transaccion que se revierte). `php artisan test`: 435 passed.
- T7b done: `$sinEfecto` ahora `tipo === 'CDT'`; % de valorizacion con bcmath mitad-arriba (`redondearMitadArriba`). Test de borde (x,xx5 exacto sube, negativo se aleja de cero) paso de inmediato (el round() de PHP ya lo hacia bien): es blindaje, no RED.
- T7c done: RED 500 cuando `estimar()` lanza; GREEN con try/catch + `Log::warning`, `estimacion_sistema` null y la tarjeta muestra "No disponible".
- T7d done: test de blindaje (tarjeta sin compras en `regenerar()` cobra interes plano por dias, 13.618 en el ejemplo); paso de inmediato.
- T7e done: `RentaCalculadoraEstimacionSistemaTest` crea su categoria INGRESO y usa documento unico basado en hrtime.
- `php artisan test`: 437 passed (el flake preexistente de documentos 10203040NN en otros tests aparecio varias veces y pasa al repetir).

# User bank accounts (debit/savings) and credit cards from Profile

## Objective
From the Profile page, the user registers debit/savings accounts (bank, initial balance, GMF/4x1000 exempt yes/no) and credit cards (bank, approved limit). Registered accounts/cards appear first on the Dashboard with available balance (debit) and available limit (credit). Credit purchases list the user's cards (already supported). Incomes may optionally target a debit account. Social-security payment confirmation selects the debit account the money leaves from. Debit balances move automatically.

## Problem / why
No user-owned debit account exists; cards can only be created through an expense ("NUEVA") or /creditos. The dashboard does not show liquidity per account.

## Scope
- New table `cuentas_bancarias` (uuid PK, usuario_id, entidad_financiera_id, nombre/alias optional, saldo_inicial, es_exenta_gmf, creado_en/actualizado_en, eliminado_en). Model with `DelUsuarioAutenticado` scope and SoftDeletes.
- Balance is DERIVED, never stored: `saldo_disponible = saldo_inicial + Σ ingresos.monto_neto_cop (cuenta_id, not deleted) − Σ gastos.monto_cop (cuenta_id, not deleted)`, bcmath. Avoids drift on edit/delete.
- GMF: an outflow (gasto with `cuenta_id`) from a non-exempt account auto-generates a linked GMF gasto (category Impuestos > GMF, tarifa from `parametros_normativos`), kept in sync on edit/delete. Exempt accounts: exemption up to the existing 350 UVT monthly tope, reusing existing GMF parameters/logic where possible.
- Credit cards: reuse `creditos` with `tipo_credito=TARJETA_CREDITO` + `cupo` via `CreditoController::registrar()`. Available limit = `cupo − saldo_capital` (existing `ConsumoTarjetaService` logic). No new card table.
- New nullable FKs: `ingresos.cuenta_id`, `gastos.cuenta_id`.
- Out of scope: investments, renta declaration, credits (already linked), account selection on ordinary debit expenses.

## Constraints
- Spanish identifiers/UI copy (project convention), bcmath money strings, soft delete for financial records.
- Base schema in `database/schema_scfp.sql`; new migrations applied manually with `php artisan migrate --path=...` to local pgsql. Tests use DatabaseTransactions, never RefreshDatabase.
- ~400 changed lines per task is advisory only.

## TDD
Mode: disabled for this feature (explicit user choice 2026-11-25: "si es pequeña sí TDD, sino no"; feature is substantial). Ordinary functional checks still run. Runner: `php artisan test` (PHPUnit 12). Baseline: 5 pre-existing failures.

## Delivery
Branch `feat/cuentas-bancarias-y-tarjetas`, one work-unit commit per task. Strategy: ask-on-risk. Push/PR are the user's decision. RDD: on (default).

## Tasks
- [x] T1 — Migration + `CuentaBancaria` model + `SaldoCuentaService` (derived balance) + feature tests. Route: delegated (writer trigger: 2+ non-trivial files).
- [x] T2 — Profile section "Cuentas y tarjetas": button opens Alpine form, type debit (banco, monto, exenta 4x1000) or credit (banco, cupo); list with delete. Routes/controller. Route: delegated.
- [x] T3 — Dashboard: accounts/cards block first (`DashboardResumenService::obtenerDashboard` + `resources/js/planning/workspace.js`). Route: delegated.
- [x] T4 — Incomes: optional `cuenta_id` on create/edit (+ recurring if straightforward), validation scoped to user's accounts. Route: delegated.
- [x] T5 — Social security payment: account select in confirm modal, `gastos.cuenta_id` via `atributosGastoPila`, GMF gasto generation for non-exempt accounts, sync on update/delete. Route: delegated.
- [x] T6 — Verify cards created from Profile appear in the expense form card selector (test). Route: delegated.
- [x] T7 — Fix review follow-ups R3-001 and R3-002. Route: delegated.
- [x] T8 — Browser QA found the frontend payload whitelist (`resources/js/api/index.js` `contractPayload`) dropped `cuenta_id` for incomes and PILA payments, so the UI never linked the account (PHP tests post directly and missed it). Route: inline (one mechanical file + test). TDD: RED 1 failing in `api.test.js`; GREEN `npm test` 108/108. Recurring incomes keep excluding `cuenta_id`. Browser re-check: income +500.000 to Bancolombia, PILA 570.000 + GMF 2.280 from Bancolombia, card purchase 200.000 lowers cupo to 2.800.000; dashboard and console OK.
- [x] T9 — "Otro banco": option in the profile bank selects revealing `banco_otro`; migration 2026_11_26_000002; `CuentaBancaria::nombre_banco` accessor used everywhere. Route: delegated. Evidence in Progress.
- [x] T10 — Optional `cuenta_id` on ordinary expenses (TARJETA_DEBITO/TRANSFERENCIA) with GMF sync, whitelist + JS test. Route: delegated. Evidence in Progress.
- [ ] T11 — Reject outflows (PILA and ordinary expenses) that exceed the available balance (amount + GMF), row lock, error shown in the forms. Route: delegated.

## Acceptance criteria
- Profile can register debit accounts and credit cards; both listed on Dashboard first with correct available amounts.
- Income with account raises its balance; SS payment from account lowers it (+ GMF when non-exempt).
- Credit purchase selector lists Profile-registered cards.
- `php artisan test` green except known pre-existing failures.

## Progress
- Created 2026-11-25 after exploration.
- T1 commit 5bcd534. - T1 done (delegated writer). Files: migration 2026_11_26_000001_crear_cuentas_bancarias (cuentas_bancarias + ingresos.cuenta_id + gastos.cuenta_id + gastos.gasto_origen_gmf_id, applied locally), CuentaBancaria model, SaldoCuentaService (balance counts only estado CONFIRMADO ingresos/gastos), fillables, CuentaBancariaSaldoTest. Checks: filtered test 4/4 pass; full suite 441/443 pass, 2 errors both PerfilFinancieroTest (unique violation usuarios CC 1020304050, leftover local data, unrelated).
- T2 commit 234df6e. - T2 done (delegated). Files: CuentaBancariaController (storeCuenta/destroyCuenta/storeTarjeta/paraPerfil), routes perfil.cuentas.store|destroy + perfil.tarjetas.store, UserProfileController@show, profile/show.blade.php section "Cuentas y tarjetas" (Alpine toggle). Decision: card registered with creditos monto=0 (no purchase), numero_cuotas=1, MENSUAL, tipo_tasa EA, fecha_inicio=today, banco=entidad nombre, proposito="Tarjeta <banco>"; tasa E.A. is an extra OPTIONAL profile field (default 0, correctable in Creditos); fecha_corte left null. Section is unnumbered (placed after section 1, no renumbering). Checks: PerfilCuentasTarjetasTest 6/6; full suite 448/449, 1 error PerfilFinancieroTest (pre-existing unique-violation on usuarios CC 1020304050, flaky leftover data).
- T3 commit c3f67aa. - T3 done (delegated). Files: DashboardResumenService::obtenerCuentas + `cuentas` {debito[], tarjetas[]} in obtenerDashboard payload, resources/js/reports/index.js (block "Tus cuentas y tarjetas" rendered before the empty state and KPIs), ResumenCuentasTest. Also fixed T2 regression: profile money inputs now carry data-value-input (frontend-regressions coverage test). Checks: ResumenCuentasTest 2/2; `npm test` 107/107 (needs php.exe on PATH); full PHP suite 451/451 pass.
- T4 commit 424ced0. - T4 done (delegated). Files: IngresoController (optional `cuenta_id`, validated own + not deleted via CuentaBancaria scope; update keeps current account when field absent, empty clears), Ingreso fillable, components/income/form.blade.php select "Cuenta que recibe el dinero" (create/edit), IngresoConCuentaTest. Limitation: recurring incomes NOT wired to accounts (templates carry datos_plantilla; out of the "if straightforward" bar). Checks: IngresoConCuentaTest 5/5; full PHP suite 456/456; npm test 107/107.
- T5 commit aa6d04c. - T5 done (delegated). Files: GmfCuentaService (sincronizar: create/update/restore/soft-delete the linked GMF gasto; reuses CalculoImpuestoInversionService::calcularRetiroCuenta), PagoSeguridadSocialController (cuenta_id rule, own-account check, sync on store/update/destroy), GastoController (blocks edit/delete/bulk of gasto_origen_gmf_id gastos), SeguridadSocialController + seguridad-social/index.blade.php (account select in the PILA modal), PagoPilaCuentaGmfTest. Decision: exempt accounts apply the 350 UVT monthly tope via calcularRetiroCuenta, accumulating prior CONFIRMADO non-GMF outflows of the user's exempt accounts that month (ordered by fecha, creado_en, id). Known limitation: editing/deleting an EARLIER outflow does not recompute the GMF of later same-month outflows. Checks: PagoPilaCuentaGmfTest 6/6; full PHP suite 462/462; npm test 107/107.
- T6 done (delegated). Verified (no production change needed): GastoController::tarjetas() lists every TARJETA_CREDITO credito, so Profile cards appear in the expense form selector; consumption lowers available limit; over-cupo purchase rejected. TarjetaDePerfilEnGastoTest 3/3. Final: full PHP suite 465/465, npm test 107/107, `npm run build` OK.
- T7 done (delegated, TDD on, small fix). R3-001: GmfCuentaService::sincronizar now recomputes GMF of every exempt-account outflow of the user in the affected month (plus the previous month when the date moved; PagoSeguridadSocialController::update passes the prior date), ordered fecha, creado_en, id, inside the caller's transaction; docblock limitation removed. R3-002: exempt-over-tope test now uses distinct fechas and asserts the exact GMF (350 UVT and tarifa read via ParametroTributarioService, bcmath); added tests for delete/edit of an earlier outflow, month move, same-day creado_en ordering. RED: 3 of 10 failed before the fix (delete, edit, move; stale GMF). GREEN: PagoPilaCuentaGmfTest 10/10. Full-suite result and commit hash reported in the T7 hand-off.

- T9 done (delegated, no TDD). Files: migration 2026_11_26_000002 (entidad_financiera_id nullable + banco_otro varchar(100), applied locally), CuentaBancaria (fillable + nombre_banco accessor), CuentaBancariaController (normalizarBanco: OTRO or empty id -> banco_otro, a catalog bank discards the free text; required_without both ways), profile/show.blade.php (Otro banco option + input in both selects), DashboardResumenService, income form and PILA modal select use nombre_banco, CuentaOtroBancoTest 4/4. Decision: card banco_otro max 80 (creditos.banco is varchar(80)); debit max 100. Checks: related cuentas tests 17/17.

- T10 done (delegated, no TDD). Files: Gasto::MEDIOS_CON_CUENTA, GastoController (cuenta_id rule own+not deleted via CuentaBancaria scope, excluded unless TARJETA_DEBITO/TRANSFERENCIA; store/update/destroy/confirmar call GmfCuentaService::sincronizar incl. fechaAnterior; update without cuenta_id key keeps the account for debit medios), expense form select + resources/js/expenses/index.js (hide, clear and disable when medio changes), api/index.js whitelist (cuenta_id only for those medios, never recurring) + api.test.js assertion, expenses.test.js scope guard no longer forbids "cuenta", GastoConCuentaTest 7/7. Checks: filter Gasto|Pila|Tarjeta|Frontend 110/110; npm test 109/109. Recurring expenses out of scope.

## Next step
Feature complete; push/PR are the user decision (delivery strategy ask-on-risk).

## Review
- 2026-11-25: native review (medium, 1 lens reliability, base main, committed-only, untracked excluded) approved and acknowledged (lineage review-0f93e47e32d0e2d8). Non-blocking follow-ups: R3-001 stale GMF on later same-month exempt outflows after edit/delete; R3-002 exempt-over-tope test may be order-nondeterministic and asserts a range, not an exact amount; R3-003 PILA modal account select not pre-selected (modal appears create-only, so no live edit path found); R3-004 no JS test for dashboard accounts block.

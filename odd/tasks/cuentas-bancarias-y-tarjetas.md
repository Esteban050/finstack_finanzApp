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
- [ ] T2 — Profile section "Cuentas y tarjetas": button opens Alpine form, type debit (banco, monto, exenta 4x1000) or credit (banco, cupo); list with delete. Routes/controller. Route: delegated.
- [ ] T3 — Dashboard: accounts/cards block first (`DashboardResumenService::obtenerDashboard` + `resources/js/planning/workspace.js`). Route: delegated.
- [ ] T4 — Incomes: optional `cuenta_id` on create/edit (+ recurring if straightforward), validation scoped to user's accounts. Route: delegated.
- [ ] T5 — Social security payment: account select in confirm modal, `gastos.cuenta_id` via `atributosGastoPila`, GMF gasto generation for non-exempt accounts, sync on update/delete. Route: delegated.
- [ ] T6 — Verify cards created from Profile appear in the expense form card selector (test). Route: delegated.

## Acceptance criteria
- Profile can register debit accounts and credit cards; both listed on Dashboard first with correct available amounts.
- Income with account raises its balance; SS payment from account lowers it (+ GMF when non-exempt).
- Credit purchase selector lists Profile-registered cards.
- `php artisan test` green except known pre-existing failures.

## Progress
- Created 2026-11-25 after exploration.
- T1 done (delegated writer). Files: migration 2026_11_26_000001_crear_cuentas_bancarias (cuentas_bancarias + ingresos.cuenta_id + gastos.cuenta_id + gastos.gasto_origen_gmf_id, applied locally), CuentaBancaria model, SaldoCuentaService (balance counts only estado CONFIRMADO ingresos/gastos), fillables, CuentaBancariaSaldoTest. Checks: filtered test 4/4 pass; full suite 441/443 pass, 2 errors both PerfilFinancieroTest (unique violation usuarios CC 1020304050, leftover local data, unrelated).

## Next step
T1.

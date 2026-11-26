# Credit cards: type label, purchase breakdown, statement/payment day, per-purchase installments

## Objective
1. Dashboard tiles show the product type next to the name: "Nu · Crédito" / "Bancolombia · Débito".
2. Credits module (card detail) lists the individual purchases (gastos) linked to the card.
3. Profile card registration asks for statement (corte) day and payment day; the card billing cycle is computed from them.
4. Expense with an existing credit card asks for optional number of installments (default 1) and a "Compra sin intereses" toggle.

## Product decision (user, 2026-11-26: "Por compra")
Each purchase is amortized over its own installments (default 1). A purchase made after the statement day falls into the next statement. Monthly card payment = Σ per purchase (capital / installments + interest at the card rate on that purchase's outstanding balance; zero interest when `sin_intereses`), due on the payment day following the statement.

## Scope
- New columns: `creditos.dia_corte`, `creditos.dia_pago` (smallint 1..31, nullable for legacy cards); `gastos.numero_cuotas` (smallint, default 1), `gastos.sin_intereses` (boolean, default false). New migration(s), applied locally with `php artisan migrate --path=...`.
- Reuse `CreditoService`/`PagoCreditoService` card plan (`reemplazarPlan`, `ajustarPlanTarjeta`, `comprasTarjeta`, interest-by-days helpers); do not duplicate credit logic.
- Frontend payload whitelist `resources/js/api/index.js` must carry the new expense fields.
- Out of scope: recurring expenses, NUEVA flow changes beyond defaults (it already asks cuotas/fecha_corte/fecha_pago).

## Constraints
- Spanish identifiers/UI copy (project convention), bcmath money strings, soft delete.
- Tests use DatabaseTransactions, never RefreshDatabase.
- ~400 changed lines per task is advisory only.

## TDD
Mode: disabled (source: user preference recorded in cuentas-bancarias-y-tarjetas, "si es pequeña sí TDD, sino no"; this feature is substantial). Ordinary functional checks run. Runner: `php artisan test` (PHPUnit 12), `npm test` (needs php.exe on PATH), `npm run build`.

## Delivery
Branch `feat/tarjetas-cuotas-y-corte`, one work-unit commit per task (Conventional Commits, no AI attribution). Strategy: ask-on-risk. Push/PR are the user's decision. RDD: on (default).

## Tasks
- [x] T1 — Dashboard type label (`DashboardResumenService::obtenerCuentas` adds `tipo`, `resources/js/reports/index.js` renders "nombre · Débito/Crédito"). Route: delegated (single writer for the feature).
- [x] T2 — Card purchase breakdown in Credits detail (endpoint/payload + `resources/js/credits/index.js`): fecha, descripción, monto, cuotas, sin intereses. Route: delegated.
- [ ] T3 — Profile card form: `dia_corte`, `dia_pago` (required for new cards), store on creditos, show next statement/payment date in card detail. Route: delegated.
- [ ] T4 — Expense with existing card: optional `numero_cuotas` (default 1) + `sin_intereses` checkbox; card plan computed per purchase using corte/pago days. Route: delegated.

## Acceptance criteria
- Dashboard shows "Banco · Débito" / "Banco · Crédito".
- Card detail lists each linked purchase.
- Card registration requires corte/pago day; plan due dates follow them.
- A 3-installment purchase on a card adds capital/3 per month; "sin intereses" adds no interest for that purchase; empty installments = 1.
- `php artisan test` and `npm test` green.

## Progress
- Created 2026-11-26 after exploration (mapper: dashboard reports/index.js:23, ConsumoTarjetaService::aplicar, PagoCreditoService::comprasTarjeta:969, GastoController::reglas 806-840, profile/show.blade.php 257-286).

- T1 done. Files: DashboardResumenService::obtenerCuentas (tipo DEBITO|CREDITO), resources/js/reports/index.js (nombre · Débito/Crédito), ResumenCuentasTest. Checks: ResumenCuentasTest|CuentaOtroBancoTest|TarjetaDePerfilEnGastoTest 9/9. Commit: 02e744f.

- T2 done. Files: CreditoController::show adds `compras` (gastos vigentes de la tarjeta; numero_cuotas/sin_intereses default 1/false until T4 columns), resources/js/credits/index.js (card "Compras" table), TarjetaComprasDetalleTest. Checks: TarjetaComprasDetalleTest 2/2; Credito*/CupoTarjeta 35/35; npm test 109/109. Commit: T2HASH.

## Next step
T1.
